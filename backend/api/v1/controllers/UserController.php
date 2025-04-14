<?php
// backend/api/v1/controllers/UserController.php
namespace Vyper\Api\V1\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Vyper\Api\V1\Models\User;
use Vyper\Api\V1\Services\TokenService;
use Vyper\Api\V1\Services\AuthService;
use Vyper\Api\V1\Utils\ResponseUtils;
use Vyper\Api\V1\Utils\SecurityUtils;
use Vyper\Helpers;

class UserController
{
    /**
     * @var TokenService
     */
    private $tokenService;
    
    /**
     * @var AuthService
     */
    private $authService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->tokenService = new TokenService();
        $this->authService = new AuthService();
    }
    
    /**
     * Get current user
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function getCurrentUser(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        return ResponseUtils::successResponse($response, [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'fullName' => $user->full_name,
                'role' => $user->role,
                'lastLogin' => $user->last_login_at,
                'createdAt' => $user->created_at
            ]
        ]);
    }
    
    /**
     * Update current user
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function updateCurrentUser(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        // Validate input
        $errors = [];
        
        if (isset($data['username']) && !empty($data['username'])) {
            // Check if username is already taken by another user
            $existingUser = User::where('username', $data['username'])
                ->where('id', '!=', $userId)
                ->first();
                
            if ($existingUser) {
                $errors['username'] = 'Username is already taken';
            } else {
                $user->username = SecurityUtils::sanitizeInput($data['username']);
            }
        }
        
        if (isset($data['email']) && !empty($data['email'])) {
            if (!SecurityUtils::validateEmail($data['email'])) {
                $errors['email'] = 'Invalid email format';
            } else {
                // Check if email is already taken by another user
                $existingUser = User::where('email', $data['email'])
                    ->where('id', '!=', $userId)
                    ->first();
                    
                if ($existingUser) {
                    $errors['email'] = 'Email is already taken';
                } else {
                    $user->email = SecurityUtils::sanitizeInput($data['email']);
                }
            }
        }
        
        if (isset($data['firstName'])) {
            $user->first_name = SecurityUtils::sanitizeInput($data['firstName']);
        }
        
        if (isset($data['lastName'])) {
            $user->last_name = SecurityUtils::sanitizeInput($data['lastName']);
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Save changes
        $user->save();
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'fullName' => $user->full_name,
                'role' => $user->role,
                'lastLogin' => $user->last_login_at,
                'createdAt' => $user->created_at
            ]
        ]);
    }
    
    /**
     * Update current user password
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function updatePassword(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('userId');
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        // Validate input
        $errors = [];
        
        if (!isset($data['currentPassword']) || empty($data['currentPassword'])) {
            $errors['currentPassword'] = 'Current password is required';
        }
        
        if (!isset($data['newPassword']) || empty($data['newPassword'])) {
            $errors['newPassword'] = 'New password is required';
        } elseif (strlen($data['newPassword']) < 8) {
            $errors['newPassword'] = 'Password must be at least 8 characters long';
        }
        
        if (!isset($data['passwordConfirmation']) || $data['newPassword'] !== $data['passwordConfirmation']) {
            $errors['passwordConfirmation'] = 'Passwords do not match';
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Verify current password
        if (!SecurityUtils::verifyPassword($data['currentPassword'], $user->password)) {
            return ResponseUtils::errorResponse($response, 'Current password is incorrect', 400);
        }
        
        // Update password
        $user->password = SecurityUtils::hashPassword($data['newPassword']);
        $user->save();
        
        // Revoke all refresh tokens except the current one
        // $this->tokenService->revokeAllUserTokens($userId);
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }
    
    /**
     * Get all users (admin only)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function getAllUsers(Request $request, Response $response): Response
    {
        // Check if user is admin
        $role = $request->getAttribute('role');
        
        if ($role !== 'admin') {
            return ResponseUtils::errorResponse($response, 'Forbidden', 403);
        }
        
        // Get users
        $users = User::select([
            'id',
            'username',
            'email',
            'first_name',
            'last_name',
            'role',
            'is_active',
            'is_locked',
            'last_login_at',
            'created_at'
        ])->get();
        
        // Transform data
        $userData = [];
        foreach ($users as $user) {
            $userData[] = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'fullName' => $user->full_name,
                'role' => $user->role,
                'isActive' => $user->is_active,
                'isLocked' => $user->is_locked,
                'lastLogin' => $user->last_login_at,
                'createdAt' => $user->created_at
            ];
        }
        
        return ResponseUtils::successResponse($response, [
            'users' => $userData
        ]);
    }
    
    /**
     * Create user (admin only)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @return Response
     */
    public function createUser(Request $request, Response $response): Response
    {
        // Check if user is admin
        $role = $request->getAttribute('role');
        
        if ($role !== 'admin') {
            return ResponseUtils::errorResponse($response, 'Forbidden', 403);
        }
        
        $data = $request->getParsedBody();
        
        // Validate input
        $errors = [];
        
        if (!isset($data['username']) || empty($data['username'])) {
            $errors['username'] = 'Username is required';
        }
        
        if (!isset($data['email']) || empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!SecurityUtils::validateEmail($data['email'])) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (isset($data['password']) && strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }
        
        if (!isset($data['role']) || !in_array($data['role'], ['admin', 'manager', 'staff'])) {
            $errors['role'] = 'Invalid role';
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Whether to send welcome email
        $sendEmail = isset($data['sendEmail']) ? (bool) $data['sendEmail'] : true;
        
        // Prepare user data
        $userData = [
            'username' => SecurityUtils::sanitizeInput($data['username']),
            'email' => SecurityUtils::sanitizeInput($data['email']),
            'password' => $data['password'] ?? null, // Optional, will be generated if not provided
            'first_name' => SecurityUtils::sanitizeInput($data['firstName'] ?? ''),
            'last_name' => SecurityUtils::sanitizeInput($data['lastName'] ?? ''),
            'role' => $data['role'],
            'is_active' => $data['isActive'] ?? true,
        ];
        
        // Create user
        $user = $this->authService->createUser($userData, $sendEmail);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'Failed to create user', 500);
        }
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => $sendEmail 
                ? 'User created successfully and welcome email sent' 
                : 'User created successfully',
            'user' => $user
        ], 201);
    }
    
    /**
     * Get user by ID (admin only)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function getUserById(Request $request, Response $response, array $args): Response
    {
        // Check if user is admin
        $role = $request->getAttribute('role');
        
        if ($role !== 'admin') {
            return ResponseUtils::errorResponse($response, 'Forbidden', 403);
        }
        
        $userId = $args['id'];
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        return ResponseUtils::successResponse($response, [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'fullName' => $user->full_name,
                'role' => $user->role,
                'isActive' => $user->is_active,
                'isLocked' => $user->is_locked,
                'lastLogin' => $user->last_login_at,
                'createdAt' => $user->created_at
            ]
        ]);
    }
    
    /**
     * Update user by ID (admin only)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function updateUser(Request $request, Response $response, array $args): Response
    {
        // Check if user is admin
        $role = $request->getAttribute('role');
        
        if ($role !== 'admin') {
            return ResponseUtils::errorResponse($response, 'Forbidden', 403);
        }
        
        $userId = $args['id'];
        $data = $request->getParsedBody();
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        // Validate input
        $errors = [];
        
        if (isset($data['username']) && !empty($data['username'])) {
            // Check if username is already taken by another user
            $existingUser = User::where('username', $data['username'])
                ->where('id', '!=', $userId)
                ->first();
                
            if ($existingUser) {
                $errors['username'] = 'Username is already taken';
            } else {
                $user->username = SecurityUtils::sanitizeInput($data['username']);
            }
        }
        
        if (isset($data['email']) && !empty($data['email'])) {
            if (!SecurityUtils::validateEmail($data['email'])) {
                $errors['email'] = 'Invalid email format';
            } else {
                // Check if email is already taken by another user
                $existingUser = User::where('email', $data['email'])
                    ->where('id', '!=', $userId)
                    ->first();
                    
                if ($existingUser) {
                    $errors['email'] = 'Email is already taken';
                } else {
                    $user->email = SecurityUtils::sanitizeInput($data['email']);
                }
            }
        }
        
        if (isset($data['firstName'])) {
            $user->first_name = SecurityUtils::sanitizeInput($data['firstName']);
        }
        
        if (isset($data['lastName'])) {
            $user->last_name = SecurityUtils::sanitizeInput($data['lastName']);
        }
        
        if (isset($data['role']) && in_array($data['role'], ['admin', 'manager', 'staff'])) {
            $user->role = $data['role'];
        }
        
        if (isset($data['isActive'])) {
            $user->is_active = (bool) $data['isActive'];
        }
        
        if (isset($data['isLocked'])) {
            $user->is_locked = (bool) $data['isLocked'];
            
            // If unlocking user, reset failed login attempts
            if ($user->is_locked === false) {
                $user->failed_login_attempts = 0;
                $user->locked_at = null;
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters long';
            } else {
                $user->password = SecurityUtils::hashPassword($data['password']);
            }
        }
        
        if (!empty($errors)) {
            return ResponseUtils::validationErrorResponse($response, $errors);
        }
        
        // Save changes
        $user->save();
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'fullName' => $user->full_name,
                'role' => $user->role,
                'isActive' => $user->is_active,
                'isLocked' => $user->is_locked,
                'lastLogin' => $user->last_login_at,
                'createdAt' => $user->created_at
            ]
        ]);
    }
    
    /**
     * Delete user by ID (admin only)
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param array $args Route arguments
     * @return Response
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        // Check if user is admin
        $role = $request->getAttribute('role');
        
        if ($role !== 'admin') {
            return ResponseUtils::errorResponse($response, 'Forbidden', 403);
        }
        
        $userId = $args['id'];
        $currentUserId = $request->getAttribute('userId');
        
        // Prevent deleting yourself
        if ($userId == $currentUserId) {
            return ResponseUtils::errorResponse($response, 'Cannot delete your own account', 400);
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            return ResponseUtils::errorResponse($response, 'User not found', 404);
        }
        
        // Delete user (will also delete associated tokens due to foreign key constraint)
        $user->delete();
        
        return ResponseUtils::successResponse($response, [
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}