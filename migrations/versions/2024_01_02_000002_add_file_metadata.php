<?php
// migrations/versions/2024_01_02_000002_add_file_metadata.php

return [
    'up' => function($pdo) {
        // Add new columns for enhanced file metadata
        $sql = "
            ALTER TABLE shared_items 
            ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64),
            ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45),
            ADD COLUMN IF NOT EXISTS user_agent TEXT,
            ADD COLUMN IF NOT EXISTS download_count INTEGER DEFAULT 0,
            ADD COLUMN IF NOT EXISTS last_accessed TIMESTAMP NULL
        ";
        $pdo->exec($sql);
        
        // Create index on file_hash for faster lookups
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_shared_items_hash ON shared_items(file_hash) WHERE file_hash IS NOT NULL");
    },
    'down' => function($pdo) {
        $sql = "
            ALTER TABLE shared_items 
            DROP COLUMN IF EXISTS file_hash,
            DROP COLUMN IF EXISTS ip_address,
            DROP COLUMN IF EXISTS user_agent,
            DROP COLUMN IF EXISTS download_count,
            DROP COLUMN IF EXISTS last_accessed
        ";
        $pdo->exec($sql);
        
        $pdo->exec("DROP INDEX IF EXISTS idx_shared_items_hash");
    }
];
?>