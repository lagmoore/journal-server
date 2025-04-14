<?php
// backend/api/v1/services/TokenService.php
namespace Vyper\Api\V1\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Vyper\Api\V1\Models\Token;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class TokenService
{
    /**
     * Generate access token for user
     *
     * @param int $userId User ID
     * @param string $username Username
     * @param string $role User role
     * @return string JWT access token
     */
    public function generateAccessToken(int $userId, string $username, string $role): string
    {
        $issuedAt = time();
        $expiryTime = $issuedAt + (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY'];
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiryTime,
            'userId' => $userId,
            'username' => $username,
            'role' => $role,
            'type' => 'access',
            'jti' => SecurityUtils::generateRandomString(16)
        ];
        
        return JWT::encode($payload, $_ENV['JWT_SECRET'], $_ENV['JWT_ALGORITHM']);
    }
    
    /**
     * Generate refresh token for user
     *
     * @param int $userId User ID
     * @return array Contains the token string and expiry time
     */
    public function generateRefreshToken(int $userId): array
    {
        $tokenString = SecurityUtils::generateRandomString(64);
        $issuedAt = time();
        $expiryTime = $issuedAt + (int) $_ENV['JWT_REFRESH_TOKEN_EXPIRY'];
        
        // Store refresh token in database
        $token = new Token();
        $token->user_id = $userId;
        $token->token = password_hash($tokenString, PASSWORD_BCRYPT);
        $token->expires_at = Helpers::timestampToDateTime($expiryTime);
        $token->created_at = Helpers::now();
        $token->save();
        
        return [
            'tokenId' => $token->id,
            'token' => $tokenString,
            'expiresAt' => $expiryTime
        ];
    }
    
    /**
     * Verify and validate refresh token
     *
     * @param string $refreshToken Refresh token to validate
     * @return array|null User data if token is valid, null otherwise
     */
    public function verifyRefreshToken(string $refreshToken): ?array
    {
        // Find all non-expired tokens
        $tokens = Token::where('expires_at', '>', Helpers::now())
            ->where('is_revoked', false)
            ->get();
        
        foreach ($tokens as $token) {
            // Verify token hash
            if (password_verify($refreshToken, $token->token)) {
                // Get user data
                $user = $token->user;
                
                // Return user data and token ID
                return [
                    'tokenId' => $token->id,
                    'userId' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Rotate refresh token (invalidate old, generate new)
     *
     * @param int $tokenId Old token ID
     * @param int $userId User ID
     * @return array New refresh token data
     */
    public function rotateRefreshToken(int $tokenId, int $userId): array
    {
        // Revoke old token
        $this->revokeRefreshToken($tokenId);
        
        // Generate new token
        return $this->generateRefreshToken($userId);
    }
    
    /**
     * Revoke a refresh token
     *
     * @param int $tokenId Token ID
     * @return bool Success status
     */
    public function revokeRefreshToken(int $tokenId): bool
    {
        $token = Token::find($tokenId);
        
        if (!$token) {
            return false;
        }
        
        $token->is_revoked = true;
        $token->revoked_at = Helpers::now();
        
        return $token->save();
    }
    
    /**
     * Revoke all refresh tokens for a user
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        $tokens = Token::where('user_id', $userId)
            ->where('is_revoked', false)
            ->get();
            
        foreach ($tokens as $token) {
            $token->is_revoked = true;
            $token->revoked_at = Helpers::now();
            $token->save();
        }
        
        return true;
    }
    
    /**
     * Clean up expired tokens
     *
     * @return int Number of deleted tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $tokens = Token::where('expires_at', '<', Helpers::now())->get();
        $count = count($tokens);
        
        foreach ($tokens as $token) {
            $token->delete();
        }
        
        return $count;
    }
}