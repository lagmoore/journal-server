<?php
// backend/src/ErrorHandler.php
namespace Vyper;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Vyper\Api\V1\Utils\ResponseUtils;

class ErrorHandler
{
    /**
     * @var Logger
     */
    private $logger;
    
    /**
     * @var bool
     */
    private $displayErrorDetails;
    
    /**
     * Constructor
     * 
     * @param bool $displayErrorDetails Whether to display error details
     */
    public function __construct(bool $displayErrorDetails = false)
    {
        $this->displayErrorDetails = $displayErrorDetails;
        
        // Set up logger
        $this->logger = new Logger('vyper-journal');
        
        // Add processors
        $this->logger->pushProcessor(new IntrospectionProcessor());
        $this->logger->pushProcessor(new WebProcessor());
        
        // Add handlers based on environment
        if ($_ENV['APP_ENV'] === 'production') {
            // In production, use rotating file handler for better log management
            $this->logger->pushHandler(new RotatingFileHandler(
                __DIR__ . '/../logs/app.log',
                30, // Keep 30 days of logs
                Logger::WARNING
            ));
            
            // Add separate handler for errors and above
            $this->logger->pushHandler(new RotatingFileHandler(
                __DIR__ . '/../logs/error.log',
                30,
                Logger::ERROR
            ));
        } else {
            // In development, log everything to a single file with more details
            $this->logger->pushHandler(new StreamHandler(
                __DIR__ . '/../logs/app.log',
                Logger::DEBUG
            ));
        }
    }
    
    /**
     * Custom error handler for Slim
     * 
     * @param Request $request The request object
     * @param \Throwable $exception The exception
     * @param bool $displayErrorDetails Display error details
     * @param bool $logErrors Log errors
     * @param bool $logErrorDetails Log error details
     * 
     * @return Response
     */
    public function __invoke(
        Request $request,
        \Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): Response {
        // Log the error
        $this->logException($exception);
        
        // Create response object
        $response = new \Slim\Psr7\Response();
        
        // Get error code and message
        $statusCode = $this->getStatusCode($exception);
        $errorMessage = $this->getErrorMessage($exception, $statusCode);
        
        // Return JSON error response
        return ResponseUtils::errorResponse(
            $response,
            $errorMessage,
            $statusCode,
            $this->displayErrorDetails ? $this->getErrorDetails($exception) : []
        );
    }
    
    /**
     * Log an exception
     * 
     * @param \Throwable $exception The exception to log
     * @return void
     */
    public function logException(\Throwable $exception): void
    {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        // Log based on exception type or HTTP status code
        $statusCode = $this->getStatusCode($exception);
        
        if ($statusCode >= 500) {
            $this->logger->error($exception->getMessage(), $context);
        } elseif ($statusCode >= 400) {
            $this->logger->warning($exception->getMessage(), $context);
        } else {
            $this->logger->info($exception->getMessage(), $context);
        }
    }
    
    /**
     * Get HTTP status code from exception
     * 
     * @param \Throwable $exception The exception
     * @return int HTTP status code
     */
    private function getStatusCode(\Throwable $exception): int
    {
        // Default status code
        $statusCode = 500;
        
        // Check if exception has HTTP status code
        if (method_exists($exception, 'getCode')) {
            $code = $exception->getCode();
            
            // Only use code if it's a valid HTTP status code
            if (is_int($code) && $code >= 100 && $code < 600) {
                $statusCode = $code;
            }
        }
        
        return $statusCode;
    }
    
    /**
     * Get appropriate error message based on status code
     * 
     * @param \Throwable $exception The exception
     * @param int $statusCode HTTP status code
     * @return string Error message
     */
    private function getErrorMessage(\Throwable $exception, int $statusCode): string
    {
        // Use exception message if in development mode
        if ($this->displayErrorDetails) {
            return $exception->getMessage();
        }
        
        // In production, provide generic messages based on status code
        switch ($statusCode) {
            case 400:
                return 'Bad request';
            case 401:
                return 'Unauthorized';
            case 403:
                return 'Forbidden';
            case 404:
                return 'Not found';
            case 405:
                return 'Method not allowed';
            case 422:
                return 'Validation error';
            case 429:
                return 'Too many requests';
            default:
                return $statusCode >= 500 ? 'Server error' : 'Client error';
        }
    }
    
    /**
     * Get detailed error information
     * 
     * @param \Throwable $exception The exception
     * @return array Error details
     */
    private function getErrorDetails(\Throwable $exception): array
    {
        $details = [
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        
        // Only include trace in development environment
        if ($_ENV['APP_ENV'] !== 'production') {
            $details['trace'] = explode("\n", $exception->getTraceAsString());
        }
        
        return $details;
    }
}