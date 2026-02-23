<?php
// migrations/versions/2024_01_04_000004_create_audit_log_table.php

return [
    'up' => function($pdo) {
        // Create audit log table
        $sql = "
            CREATE TABLE IF NOT EXISTS audit_log (
                id SERIAL PRIMARY KEY,
                shared_item_id INTEGER REFERENCES shared_items(id) ON DELETE SET NULL,
                action VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                details JSONB,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($sql);
        
        // Create indexes
        $pdo->exec("CREATE INDEX idx_audit_log_item ON audit_log(shared_item_id)");
        $pdo->exec("CREATE INDEX idx_audit_log_created ON audit_log(created_at)");
        $pdo->exec("CREATE INDEX idx_audit_log_action ON audit_log(action)");
    },
    'down' => function($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS audit_log CASCADE");
    }
];
?>