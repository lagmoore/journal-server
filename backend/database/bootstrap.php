<?php
// backend/database/bootstrap.php
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Set up database connection
$db = new mysqli(
    $_ENV['DB_HOST'],
    $_ENV['DB_USERNAME'],
    $_ENV['DB_PASSWORD'],
    $_ENV['DB_DATABASE'],
    $_ENV['DB_PORT'] ?? 3306
);

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Enable error reporting
$db->set_charset('utf8mb4');

echo "Connected to database successfully.\n";

// Path to migration files
$migrationsPath = __DIR__ . '/migrations/';

// Get list of migration files
$migrationFiles = glob($migrationsPath . '*.sql');
natsort($migrationFiles); // Sort files naturally to ensure proper order

// Create migrations table if it doesn't exist
$db->query("
    CREATE TABLE IF NOT EXISTS `migrations` (
        `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration` varchar(255) NOT NULL,
        `batch` int(11) NOT NULL,
        `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Get already executed migrations
$result = $db->query("SELECT migration FROM migrations");
$executedMigrations = [];
while ($row = $result->fetch_assoc()) {
    $executedMigrations[] = $row['migration'];
}

// Execute migrations
$batch = 1;
if (!empty($executedMigrations)) {
    $result = $db->query("SELECT MAX(batch) as max_batch FROM migrations");
    $row = $result->fetch_assoc();
    $batch = $row['max_batch'] + 1;
}

$migrationsRun = 0;

foreach ($migrationFiles as $migrationFile) {
    $migrationName = basename($migrationFile);
    
    // Skip already executed migrations
    if (in_array($migrationName, $executedMigrations)) {
        echo "Migration {$migrationName} already executed, skipping...\n";
        continue;
    }
    
    echo "Running migration: {$migrationName}\n";
    
    // Read migration file
    $sql = file_get_contents($migrationFile);
    
    // Split into individual queries
    $queries = explode(';', $sql);
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Execute each query
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $db->query($query);
                if ($db->error) {
                    throw new Exception("Error executing query: {$db->error}");
                }
            }
        }
        
        // Record migration
        $stmt = $db->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->bind_param('si', $migrationName, $batch);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $db->commit();
        
        echo "Migration {$migrationName} executed successfully.\n";
        $migrationsRun++;
        
    } catch (Exception $e) {
        // Rollback transaction
        $db->rollback();
        echo "Error running migration {$migrationName}: {$e->getMessage()}\n";
        die("Migration failed. Database has been rolled back.\n");
    }
}

echo "Database bootstrap completed. {$migrationsRun} migrations executed.\n";

// Close connection
$db->close();