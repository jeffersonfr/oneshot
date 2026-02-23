#!/usr/bin/env php
<?php
// migrations/migrate.php
require_once __DIR__ . '/config.php';

// ANSI color codes for output
define('COLOR_GREEN', "\033[32m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_RED', "\033[31m");
define('COLOR_RESET', "\033[0m");

echo COLOR_YELLOW . "OneShot Migration Tool\n" . COLOR_RESET;
echo "========================\n\n";

// Ensure migrations table exists
ensureMigrationsTable($pdo);

// Get command line argument
$command = $argv[1] ?? 'migrate';

switch ($command) {
    case 'migrate':
        runMigrations($pdo);
        break;
    case 'rollback':
        $steps = $argv[2] ?? 1;
        rollbackMigrations($pdo, $steps);
        break;
    case 'reset':
        resetMigrations($pdo);
        break;
    case 'refresh':
        refreshMigrations($pdo);
        break;
    case 'status':
        showStatus($pdo);
        break;
    case 'create':
        $name = $argv[2] ?? null;
        createMigration($name);
        break;
    default:
        echo "Usage: php migrate.php [command]\n\n";
        echo "Commands:\n";
        echo "  migrate          Run all pending migrations\n";
        echo "  rollback [steps] Rollback the last migration (or specific number of steps)\n";
        echo "  reset            Rollback all migrations\n";
        echo "  refresh          Reset and re-run all migrations\n";
        echo "  status           Show migration status\n";
        echo "  create [name]    Create a new migration file\n";
        break;
}

function runMigrations($pdo) {
    $executed = getExecutedMigrations($pdo);
    $batch = getCurrentBatch($pdo) + 1;
    
    // Get all migration files
    $files = glob(MIGRATIONS_DIR . '/*.php');
    sort($files);
    
    $pending = array_filter($files, function($file) use ($executed) {
        $filename = basename($file, '.php');
        return !in_array($filename, $executed);
    });
    
    if (empty($pending)) {
        echo COLOR_GREEN . "No pending migrations.\n" . COLOR_RESET;
        return;
    }
    
    echo "Running migrations (Batch {$batch})...\n\n";
    
    foreach ($pending as $file) {
        $filename = basename($file, '.php');
        echo "Migrating: {$filename}... ";
        
        try {
            $pdo->beginTransaction();
            
            // Include and run migration
            $migration = require $file;
            $migration['up']($pdo);
            
            // Record migration
            recordMigration($pdo, $filename, $batch);
            
            $pdo->commit();
            echo COLOR_GREEN . "DONE\n" . COLOR_RESET;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo COLOR_RED . "FAILED\n" . COLOR_RESET;
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    echo "\n" . COLOR_GREEN . "Migrations completed successfully.\n" . COLOR_RESET;
}

function rollbackMigrations($pdo, $steps = 1) {
    // Get last batch number
    $currentBatch = getCurrentBatch($pdo);
    
    if ($currentBatch == 0) {
        echo COLOR_YELLOW . "No migrations to rollback.\n" . COLOR_RESET;
        return;
    }
    
    $targetBatch = max($currentBatch - $steps + 1, 1);
    
    echo "Rolling back migrations from batch {$targetBatch} to {$currentBatch}...\n\n";
    
    for ($batch = $currentBatch; $batch >= $targetBatch; $batch--) {
        // Get migrations in this batch
        $stmt = $pdo->prepare("SELECT migration FROM " . MIGRATIONS_TABLE . " WHERE batch = ? ORDER BY id DESC");
        $stmt->execute([$batch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($migrations as $migration) {
            echo "Rolling back: {$migration}... ";
            
            try {
                $pdo->beginTransaction();
                
                $file = MIGRATIONS_DIR . '/' . $migration . '.php';
                if (file_exists($file)) {
                    $migrationData = require $file;
                    $migrationData['down']($pdo);
                }
                
                // Remove migration record
                removeMigration($pdo, $migration);
                
                $pdo->commit();
                echo COLOR_GREEN . "DONE\n" . COLOR_RESET;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo COLOR_RED . "FAILED\n" . COLOR_RESET;
                echo "Error: " . $e->getMessage() . "\n";
                exit(1);
            }
        }
    }
    
    echo "\n" . COLOR_GREEN . "Rollback completed successfully.\n" . COLOR_RESET;
}

function resetMigrations($pdo) {
    $currentBatch = getCurrentBatch($pdo);
    if ($currentBatch > 0) {
        rollbackMigrations($pdo, $currentBatch);
    } else {
        echo COLOR_YELLOW . "No migrations to reset.\n" . COLOR_RESET;
    }
}

function refreshMigrations($pdo) {
    resetMigrations($pdo);
    runMigrations($pdo);
}

function showStatus($pdo) {
    $executed = getExecutedMigrations($pdo);
    $files = glob(MIGRATIONS_DIR . '/*.php');
    sort($files);
    
    echo "Migration Status\n";
    echo "================\n\n";
    
    echo "Executed Migrations:\n";
    if (empty($executed)) {
        echo "  " . COLOR_YELLOW . "None\n" . COLOR_RESET;
    } else {
        foreach ($executed as $migration) {
            echo "  " . COLOR_GREEN . "✓ " . $migration . "\n" . COLOR_RESET;
        }
    }
    
    echo "\nPending Migrations:\n";
    $pending = array_filter($files, function($file) use ($executed) {
        $filename = basename($file, '.php');
        return !in_array($filename, $executed);
    });
    
    if (empty($pending)) {
        echo "  " . COLOR_GREEN . "None\n" . COLOR_RESET;
    } else {
        foreach ($pending as $file) {
            $filename = basename($file, '.php');
            echo "  " . COLOR_YELLOW . "○ " . $filename . "\n" . COLOR_RESET;
        }
    }
    
    echo "\nCurrent Batch: " . getCurrentBatch($pdo) . "\n";
}

function createMigration($name) {
    if (!$name) {
        echo COLOR_RED . "Error: Migration name is required.\n" . COLOR_RESET;
        echo "Usage: php migrate.php create [migration_name]\n";
        exit(1);
    }
    
    // Sanitize name
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    $timestamp = date('Y_m_d_His');
    $filename = "{$timestamp}_{$name}.php";
    $path = MIGRATIONS_DIR . '/' . $filename;
    
    // Create migrations directory if it doesn't exist
    if (!is_dir(MIGRATIONS_DIR)) {
        mkdir(MIGRATIONS_DIR, 0755, true);
    }
    
    // Migration template
    $template = <<<PHP
<?php
// Migration: {$name}
// Created: {$timestamp}

return [
    'up' => function(\$pdo) {
        // Add your migration logic here
        // Example:
        // \$sql = "CREATE TABLE example (
        //     id SERIAL PRIMARY KEY,
        //     name VARCHAR(255) NOT NULL
        // )";
        // \$pdo->exec(\$sql);
    },
    'down' => function(\$pdo) {
        // Add your rollback logic here
        // Example:
        // \$pdo->exec("DROP TABLE IF EXISTS example");
    }
];
?>
PHP;
    
    if (file_put_contents($path, $template)) {
        echo COLOR_GREEN . "Migration created: {$filename}\n" . COLOR_RESET;
    } else {
        echo COLOR_RED . "Failed to create migration.\n" . COLOR_RESET;
    }
}
?>
