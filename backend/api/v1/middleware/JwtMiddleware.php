<?php
// backend/api/v1/middleware/JwtMiddleware.php
namespace Vyper\Api\V1\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Vyper\Api\V1\Utils\ResponseUtils;

class JwtMiddleware
{
    /**
     * JWT authentication middleware
     *
     * @param Request $request PSR-7 request
     * @param RequestHandler $handler PSR-15 request handler
     *
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = new \Slim\Psr7\Response();
        
        // Get authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        
        // Check if authorization header exists and contains Bearer token
        if (!$authHeader || strpos($authHeader, 'Bearer') !== 0) {
            return ResponseUtils::errorResponse($response, 'Authorization header required', 401);
        }
        
        // Extract token from header
        $token = trim(substr($authHeader, 7));
        
        if (!$token) {
            return ResponseUtils::errorResponse($response, 'JWT token required', 401);
        }
        
        try {
            // Decode and validate token
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], $_ENV['JWT_ALGORITHM']));
            
            // Check if token type is access token
            if (!isset($decoded->type) || $decoded->type !== 'access') {
                return ResponseUtils::errorResponse($response, 'Invalid token type', 401);
            }
            
            // Add decoded token to request attributes
            $request = $request->withAttribute('token', $decoded);
            $request = $request->withAttribute('userId', $decoded->userId);
            $request = $request->withAttribute('username', $decoded->username);
            $request = $request->withAttribute('role', $decoded->role);
            
            // Pass request to next middleware
            return $handler->handle($request);
        } catch (ExpiredException $e) {
            return ResponseUtils::errorResponse($response, 'Token expired', 401);
        } catch (\Exception $e) {
            return ResponseUtils::errorResponse($response, 'Invalid token: ' . $e->getMessage(), 401);
        }
    }
}