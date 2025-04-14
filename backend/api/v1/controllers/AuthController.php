<?php
// backend/api/v1/controllers/AuthController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Services\AuthService;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Api\V1\Utils\SecurityUtils;

class AuthController
{
    /**
     * @var AuthService
     */
    private $authService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }
    
    /**
     * Login user
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate input
        $errors = [];
        
        if (!isset($data['email']) || empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!SecurityUtils::validateEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = 'Password is required';
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Authenticate user
        $result = $this->authService->authenticate(
            SecurityUtils::sanitizeInput($data['email']),
            $data['password']
        );
        
        if (!$result) {
            return ResponseUtils::errorResponse($response, 'Invalid credentials', 401);
        }
        
        // Return tokens and user info
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'accessToken' => $result['accessToken'],
            'refreshToken' => $result['refreshToken'],
            'expiresIn' => $result['expiresIn'],
            'user' => $result['user']
        ]);
    }
    
    /**
     * Refresh access token
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function refreshToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate input
        if (!isset($data['refreshToken']) || empty($data['refreshToken'])) {
            return ResponseUtils::errorResponse($response, 'Refresh token is required', 400);
        }
        
        // Refresh token
        $result = $this->authService->refreshAccessToken($data['refreshToken']);
        
        if (!$result) {
            return ResponseUtils::errorResponse($response, 'Invalid refresh token', 401);
        }
        
        // Return new tokens
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'accessToken' => $result['accessToken'],
            'refreshToken' => $result['refreshToken'],
            'expiresIn' => $result['expiresIn']
        ]);
    }
    
    /**
     * Logout user
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function logout(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate input
        if (!isset($data['refreshToken']) || empty($data['refreshToken'])) {
            return ResponseUtils::errorResponse($response, 'Refresh token is required', 400);
        }
        
        // Logout user
        $success = $this->authService->logout($data['refreshToken']);
        
        return ResponseUtils::successResponse($response, [
            'success' => $success,
            'message' => $success ? 'Logged out successfully' : 'Logout failed'
        ]);
    }
    
    /**
     * Request password reset
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function requestPasswordReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate input
        if (!isset($data['email']) || empty($data['email'])) {
            return ResponseUtils::errorResponse($response, 'Email is required', 400);
        } elseif (!SecurityUtils::validateEmail($data['email'])) {
            return ResponseUtils::errorResponse($response, 'Invalid email format', 400);
        }
        
        // Request password reset
        $success = $this->authService->requestPasswordReset(
            SecurityUtils::sanitizeInput($data['email'])
        );
        
        // Always return success for security reasons
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'If your email is registered, you will receive a password reset link'
        ]);
    }
    
    /**
     * Reset password
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Validate input
        $errors = [];
        
        if (!isset($data['email']) || empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!SecurityUtils::validateEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (!isset($data['token']) || empty($data['token'])) {
            $errors['token'] = 'Reset token is required';
        }
        
        if (!isset($data['password']) || empty($data['password'])) {
            $errors['password'] = 'New password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        
        if (!isset($data['passwordConfirmation']) || $data['password'] !== $data['passwordConfirmation']) {
            $errors['passwordConfirmation'] = 'Passwords do not match';
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Reset password
        $success = $this->authService->resetPassword(
            SecurityUtils::sanitizeInput($data['email']),
            $data['token'],
            $data['password']
        );
        
        if (!$success) {
            return ResponseUtils::errorResponse($response, 'Invalid or expired reset token', 400);
        }
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }
}