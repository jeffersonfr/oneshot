<?php
// migrations/versions/2024_01_03_000003_add_expiration_policies.php

return [
    'up' => function($pdo) {
        // Add expiration policy columns
        $sql = "
            ALTER TABLE shared_items 
            ADD COLUMN IF NOT EXISTS max_views INTEGER DEFAULT 1,
            ADD COLUMN IF NOT EXISTS expiration_type VARCHAR(20) DEFAULT 'first_view',
            ADD COLUMN IF NOT EXISTS custom_expiration_hours INTEGER NULL,
            ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL
        ";
        $pdo->exec($sql);
        
        // Update existing records
        $pdo->exec("UPDATE shared_items SET max_views = 1, expiration_type = 'first_view' WHERE max_views IS NULL");
    },
    'down' => function($pdo) {
        $sql = "
            ALTER TABLE shared_items 
            DROP COLUMN IF EXISTS max_views,
            DROP COLUMN IF EXISTS expiration_type,
            DROP COLUMN IF EXISTS custom_expiration_hours,
            DROP COLUMN IF EXISTS deleted_at
        ";
        $pdo->exec($sql);
    }
];
?>