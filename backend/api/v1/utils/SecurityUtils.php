<?php
// backend/api/v1/utils/SecurityUtils.php
namespace Vyper\Api\V1\Utils;

class SecurityUtils
{
    /**
     * Generate a secure random string
     *
     * @param int $length Length of the random string
     * @return string
     */
    public static function generateRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash a password using bcrypt
     *
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        $cost = $_ENV['BCRYPT_COST'] ?? 12;
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => (int) $cost]);
    }
    
    /**
     * Verify a password against a hash
     *
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password needs rehash due to cost changes
     *
     * @param string $hash Hashed password
     * @return bool
     */
    public static function passwordNeedsRehash(string $hash): bool
    {
        $cost = $_ENV['BCRYPT_COST'] ?? 12;
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => (int) $cost]);
    }
    
    /**
     * Sanitize input
     *
     * @param string $input Input to sanitize
     * @return string Sanitized input
     */
    public static function sanitizeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Generate a CSRF token
     *
     * @param string $formName Name of the form (used as namespace)
     * @return string
     */
    public static function generateCsrfToken(string $formName): string
    {
        $token = self::generateRandomString();
        $_SESSION['csrf_tokens'][$formName] = $token;
        return $token;
    }
    
    /**
     * Validate a CSRF token
     *
     * @param string $formName Name of the form (used as namespace)
     * @param string $token Token to validate
     * @return bool
     */
    public static function validateCsrfToken(string $formName, string $token): bool
    {
        if (!isset($_SESSION['csrf_tokens'][$formName])) {
            return false;
        }
        
        $valid = hash_equals($_SESSION['csrf_tokens'][$formName], $token);
        
        // Remove token after validation
        unset($_SESSION['csrf_tokens'][$formName]);
        
        return $valid;
    }
}