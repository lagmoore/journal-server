<?php
// backend/api/v1/utils/ResponseUtils.php
namespace Vyper\Api\V1\Utils;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseUtils
{
    /**
     * Generate a JSON success response
     *
     * @param Response $response PSR-7 response
     * @param array|object $data Response data
     * @param int $status HTTP status code
     * @return Response
     */
    public static function successResponse(Response $response, $data = null, int $status = 200): Response
    {
        $payload = [
            'success' => true,
        ];
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $payload = array_merge($payload, (array) $data);
            } else {
                $payload['data'] = $data;
            }
        }
        
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
    /**
     * Generate a JSON error response
     *
     * @param Response $response PSR-7 response
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $errors Validation errors
     * @return Response
     */
    public static function errorResponse(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($json);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
    /**
     * Generate a JSON validation error response
     *
     * @param Response $response PSR-7 response
     * @param array $errors Validation errors
     * @return Response
     */
    public static function validationErrorResponse(Response $response, array $errors): Response
    {
        return self::errorResponse($response, 'Validation failed', 422, $errors);
    }
}