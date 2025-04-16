<?php
// backend/public/index.php
$backendDir = __DIR__ . '/../backend';

// Load Composer's autoloader
require $backendDir . '/vendor/autoload.php';

// Rest of your code, adjusted for the new path
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Vyper\Api\V1\Middleware\JwtMiddleware;
use DI\Container;
use Vyper\Config\Database;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable($backendDir);
$dotenv->load();

// Initialize database connection
Database::init();

// Create DI Container
$container = new Container();

// Create Slim App
$app = AppFactory::createFromContainer($container);

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// Add JSON parsing middleware
$app->addBodyParsingMiddleware();

// Add CORS middleware
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CORS_ALLOW_ORIGIN'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Handle preflight OPTIONS requests
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Define API routes
$app->group('/api', function ($group) {

    // Unprotected routes
    $group->group('/v1', function ($group) {
        // Auth routes
        $group->post('/auth/login', '\Vyper\Api\V1\Controllers\AuthController:login');
        $group->post('/auth/refresh-token', '\Vyper\Api\V1\Controllers\AuthController:refreshToken');
        $group->post('/auth/request-password-reset', '\Vyper\Api\V1\Controllers\AuthController:requestPasswordReset');
        $group->post('/auth/reset-password', '\Vyper\Api\V1\Controllers\AuthController:resetPassword');
        $group->post('/auth/logout', '\Vyper\Api\V1\Controllers\AuthController:logout');

        // Protected routes (require JWT)
        $group->group('', function ($group) {
            // User routes
            $group->get('/users/me', '\Vyper\Api\V1\Controllers\UserController:getCurrentUser');
            $group->put('/users/me', '\Vyper\Api\V1\Controllers\UserController:updateCurrentUser');
            $group->put('/users/me/password', '\Vyper\Api\V1\Controllers\UserController:updatePassword');

            // Patient routes
            $group->get('/patients', '\Vyper\Api\V1\Controllers\PatientController:getAllPatients');
            $group->post('/patients', '\Vyper\Api\V1\Controllers\PatientController:createPatient');
            $group->get('/patients/{id}', '\Vyper\Api\V1\Controllers\PatientController:getPatientById');
            $group->put('/patients/{id}', '\Vyper\Api\V1\Controllers\PatientController:updatePatient');
            $group->delete('/patients/{id}', '\Vyper\Api\V1\Controllers\PatientController:deletePatient');

            // Patient journals
            $group->get('/patients/{id}/journals', '\Vyper\Api\V1\Controllers\JournalController:getPatientJournals');
            $group->post('/patients/{id}/journals', '\Vyper\Api\V1\Controllers\JournalController:createPatientJournal');

            // Patient medications
            $group->get('/patients/{id}/medications', '\Vyper\Api\V1\Controllers\MedicationController:getPatientMedications');
            $group->get('/patients/{id}/medications/active', '\Vyper\Api\V1\Controllers\MedicationController:getPatientActiveMedications');
            $group->post('/patients/{id}/medications', '\Vyper\Api\V1\Controllers\MedicationController:createMedication');

            // Journal routes
            $group->get('/journals', '\Vyper\Api\V1\Controllers\JournalController:getAllJournals');
            $group->post('/journals', '\Vyper\Api\V1\Controllers\JournalController:createJournal');
            $group->get('/journals/{id}', '\Vyper\Api\V1\Controllers\JournalController:getJournalById');
            $group->put('/journals/{id}', '\Vyper\Api\V1\Controllers\JournalController:updateJournal');
            $group->delete('/journals/{id}', '\Vyper\Api\V1\Controllers\JournalController:deleteJournal');

            // Medication routes
            $group->get('/medications/{id}', '\Vyper\Api\V1\Controllers\MedicationController:getMedicationById');
            $group->put('/medications/{id}', '\Vyper\Api\V1\Controllers\MedicationController:updateMedication');
            $group->delete('/medications/{id}', '\Vyper\Api\V1\Controllers\MedicationController:deleteMedication');

            // Economy routes
            $group->get('/economy', '\Vyper\Api\V1\Controllers\EconomyController:getYearlyEconomyData');
            $group->post('/economy', '\Vyper\Api\V1\Controllers\EconomyController:updateEconomyData');

            // Admin routes
            $group->group('/admin', function ($group) {
                $group->get('/users', '\Vyper\Api\V1\Controllers\UserController:getAllUsers');
                $group->post('/users', '\Vyper\Api\V1\Controllers\UserController:createUser');
                $group->get('/users/{id}', '\Vyper\Api\V1\Controllers\UserController:getUserById');
                $group->put('/users/{id}', '\Vyper\Api\V1\Controllers\UserController:updateUser');
                $group->delete('/users/{id}', '\Vyper\Api\V1\Controllers\UserController:deleteUser');
            });
        })->add(new JwtMiddleware());
    });
});

// Run the application
$app->run();