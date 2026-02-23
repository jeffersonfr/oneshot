<?php
// migrations/versions/2024_01_01_000001_create_shared_items_table.php

return [
    'up' => function($pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS shared_items (
                id SERIAL PRIMARY KEY,
                uuid VARCHAR(36) NOT NULL UNIQUE,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50) NOT NULL,
                file_size INTEGER NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                view_count INTEGER DEFAULT 0,
                is_destroyed BOOLEAN DEFAULT FALSE
            )
        ";
        $pdo->exec($sql);
        
        // Create indexes
        $pdo->exec("CREATE INDEX idx_shared_items_uuid ON shared_items(uuid)");
        $pdo->exec("CREATE INDEX idx_shared_items_expires ON shared_items(expires_at)");
        $pdo->exec("CREATE INDEX idx_shared_items_destroyed ON shared_items(is_destroyed)");
    },
    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS shared_items CASCADE");
    }
];
?>