<?php
// migrations/config.php
require_once __DIR__ . '/../config.php';

define('MIGRATIONS_TABLE', 'migrations');
define('MIGRATIONS_DIR', __DIR__ . '/versions');

// Create migrations table if it doesn't exist
function ensureMigrationsTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS " . MIGRATIONS_TABLE . " (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INTEGER NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($sql);
}

// Get current migration batch
function getCurrentBatch($pdo) {
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) as batch FROM " . MIGRATIONS_TABLE);
    return $stmt->fetch()['batch'];
}

// Record executed migration
function recordMigration($pdo, $migration, $batch) {
    $stmt = $pdo->prepare("INSERT INTO " . MIGRATIONS_TABLE . " (migration, batch) VALUES (?, ?)");
    $stmt->execute([$migration, $batch]);
}

// Remove migration record (for rollback)
function removeMigration($pdo, $migration) {
    $stmt = $pdo->prepare("DELETE FROM " . MIGRATIONS_TABLE . " WHERE migration = ?");
    $stmt->execute([$migration]);
}

// Get executed migrations
function getExecutedMigrations($pdo) {
    $stmt = $pdo->query("SELECT migration FROM " . MIGRATIONS_TABLE . " ORDER BY id");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>
