<?php
// backend/config/database.php
namespace Vyper\Config;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    /**
     * Initialize database connection
     */
    public static function init()
    {
        $capsule = new Capsule;
        
        $capsule->addConnection([
            'driver'    => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? 'localhost',
            'port'      => $_ENV['DB_PORT'] ?? '3306',
            'database'  => $_ENV['DB_DATABASE'] ?? 'dev',
            'username'  => $_ENV['DB_USERNAME'] ?? 'root',
            'password'  => $_ENV['DB_PASSWORD'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ]);
        
        // Make this Capsule instance available globally
        $capsule->setAsGlobal();
        
        // Setup the Eloquent ORM
        $capsule->bootEloquent();
    }
}