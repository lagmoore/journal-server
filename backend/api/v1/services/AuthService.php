<?php
// backend/api/v1/services/AuthService.php
namespace Vyper\Api\V1\Services;

use Vyper\Api\V1\Models\User;
use Vyper\Api\V1\Services\TokenService;
use Vyper\Api\V1\Services\MailService;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class AuthService
{
    /**
     * @var TokenService
     */
    private $tokenService;
    
    /**
     * @var MailService
     */
    private $mailService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->tokenService = new TokenService();
        $this->mailService = new MailService();
    }
    
    /**
     * Authenticate user and generate tokens
     *
     * @param string $email User email
     * @param string $password User password
     * @return array|null Authentication data or null if failed
     */
    public function authenticate(string $email, string $password): ?array
    {
        // Find user by email
        $user = User::where('email', $email)->first();
        
        // Check if user exists and is active
        if (!$user || !$user->is_active) {
            return null;
        }
        
        // Verify password
        if (!SecurityUtils::verifyPassword($password, $user->password)) {
            // Update failed login attempts
            $user->failed_login_attempts += 1;
            
            // Lock account after too many failed attempts
            if ($user->failed_login_attempts >= 5) {
                $user->is_locked = true;
                $user->locked_at = Helpers::now();
            }
            
            $user->save();
            return null;
        }
        
        // Check if account is locked
        if ($user->is_locked) {
            return null;
        }
        
        // Reset failed login attempts on successful login
        if ($user->failed_login_attempts > 0) {
            $user->failed_login_attempts = 0;
            $user->save();
        }
        
        // Rehash password if needed
        if (SecurityUtils::passwordNeedsRehash($user->password)) {
            $user->password = SecurityUtils::hashPassword($password);
            $user->save();
        }
        
        // Update last login timestamp
        $user->last_login_at = Helpers::now();
        $user->save();
        
        // Generate tokens
        $accessToken = $this->tokenService->generateAccessToken($user->id, $user->username, $user->role);
        $refreshToken = $this->tokenService->generateRefreshToken($user->id);
        
        return [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'lastLogin' => $user->last_login_at
            ],
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken['token'],
            'expiresIn' => (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY']
        ];
    }
    
    /**
     * Refresh access token using refresh token
     *
     * @param string $refreshToken Refresh token
     * @return array|null New tokens or null if invalid
     */
    public function refreshAccessToken(string $refreshToken): ?array
    {
        // Verify refresh token
        $tokenData = $this->tokenService->verifyRefreshToken($refreshToken);
        
        if (!$tokenData) {
            return null;
        }
        
        // Get user
        $user = User::find($tokenData['userId']);
        
        if (!$user || !$user->is_active || $user->is_locked) {
            return null;
        }
        
        // Generate new access token
        $accessToken = $this->tokenService->generateAccessToken(
            $user->id,
            $user->username,
            $user->role
        );
        
        // Rotate refresh token
        $newRefreshToken = $this->tokenService->rotateRefreshToken(
            $tokenData['tokenId'],
            $user->id
        );
        
        return [
            'accessToken' => $accessToken,
            'refreshToken' => $newRefreshToken['token'],
            'expiresIn' => (int) $_ENV['JWT_ACCESS_TOKEN_EXPIRY']
        ];
    }
    
    /**
     * Logout user by revoking refresh token
     *
     * @param string $refreshToken Refresh token
     * @return bool Success status
     */
    public function logout(string $refreshToken): bool
    {
        // Verify refresh token
        $tokenData = $this->tokenService->verifyRefreshToken($refreshToken);
        
        if (!$tokenData) {
            return false;
        }
        
        // Revoke the token
        return $this->tokenService->revokeRefreshToken($tokenData['tokenId']);
    }
    
    /**
     * Request password reset
     *
     * @param string $email User email
     * @return bool Success status
     */
    public function requestPasswordReset(string $email): bool
    {
        // Find user by email
        $user = User::where('email', $email)->first();
        
        if (!$user || !$user->is_active) {
            return false;
        }
        
        // Generate reset token
        $resetToken = SecurityUtils::generateRandomString(32);
        $resetExpiry = Helpers::addSeconds(Helpers::now(), 3600); // 1 hour expiry
        
        // Store reset token
        $user->reset_token = SecurityUtils::hashPassword($resetToken);
        $user->reset_token_expires_at = $resetExpiry;
        $user->save();
        
        // Send email with reset token
        $emailSent = $this->mailService->sendPasswordResetEmail(
            $user->email,
            $resetToken,
            $user->username
        );
        
        return $emailSent;
    }
    
    /**
     * Reset password using reset token
     *
     * @param string $email User email
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function resetPassword(string $email, string $token, string $newPassword): bool
    {
        // Find user by email
        $user = User::where('email', $email)
            ->where('reset_token_expires_at', '>', Helpers::now())
            ->first();
        
        if (!$user || !$user->is_active) {
            return false;
        }
        
        // Verify reset token
        if (!SecurityUtils::verifyPassword($token, $user->reset_token)) {
            return false;
        }
        
        // Update password
        $user->password = SecurityUtils::hashPassword($newPassword);
        $user->reset_token = null;
        $user->reset_token_expires_at = null;
        $user->failed_login_attempts = 0;
        $user->is_locked = false;
        $user->locked_at = null;
        $user->password_changed_at = Helpers::now();
        $user->save();
        
        // Revoke all refresh tokens
        $this->tokenService->revokeAllUserTokens($user->id);
        
        return true;
    }
    
    /**
     * Create user with email notification
     *
     * @param array $userData User data
     * @param bool $sendEmail Whether to send welcome email
     * @return array|null New user data or null if failed
     */
    public function createUser(array $userData, bool $sendEmail = true): ?array
    {
        // Check if email exists
        $existingUser = User::where('email', $userData['email'])->first();
        if ($existingUser) {
            return null;
        }
        
        // Check if username exists
        $existingUser = User::where('username', $userData['username'])->first();
        if ($existingUser) {
            return null;
        }
        
        // Create user
        $user = new User();
        $user->username = $userData['username'];
        $user->email = $userData['email'];
        
        // Generate random password if not provided
        $password = $userData['password'] ?? SecurityUtils::generateRandomString(12);
        $user->password = SecurityUtils::hashPassword($password);
        
        $user->first_name = $userData['first_name'] ?? '';
        $user->last_name = $userData['last_name'] ?? '';
        $user->role = $userData['role'] ?? 'staff';
        $user->is_active = $userData['is_active'] ?? true;
        $user->password_changed_at = Helpers::now();
        $user->save();
        
        // Send welcome email with login details
        if ($sendEmail) {
            $this->mailService->sendAccountCreatedEmail(
                $user->email,
                $user->username,
                $password
            );
        }
        
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'fullName' => $user->full_name,
            'role' => $user->role,
            'isActive' => $user->is_active,
            'createdAt' => $user->created_at
        ];
    }
}