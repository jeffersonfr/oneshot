<?php
// migrations/versions/2024_01_05_000005_add_password_protection.php

return [
    'up' => function($pdo) {
        // Add password protection columns
        $sql = "ALTER TABLE shared_items 
                ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL,
                ADD COLUMN IF NOT EXISTS password_attempts INTEGER DEFAULT 0,
                ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL";
        $pdo->exec($sql);
        
        // Add index for locked_until for cleanup
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shared_items_locked ON shared_items(locked_until)");
    },
    'down' => function($pdo) {
        $sql = "ALTER TABLE shared_items 
                DROP COLUMN IF EXISTS password_hash,
                DROP COLUMN IF EXISTS password_attempts,
                DROP COLUMN IF EXISTS locked_until";
        $pdo->exec($sql);
        
        $pdo->exec("DROP INDEX IF EXISTS idx_shared_items_locked");
    }
];
