<?php
// cleanup.php - Run this via cron job to clean up expired files
require_once 'config.php';

try {
    // Delete files that are older than 24 hours (even if not viewed)
    $stmt = $pdo->prepare("SELECT * FROM shared_items WHERE created_at < NOW() - INTERVAL '24 hours' AND is_destroyed = FALSE");
    $stmt->execute();
    $oldItems = $stmt->fetchAll();
    
    foreach ($oldItems as $item) {
        // Delete file if it exists
        if (file_exists($item['file_path'])) {
            unlink($item['file_path']);
        }
        
        // Mark as destroyed
        $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE WHERE id = :id");
        $destroyStmt->execute(['id' => $item['id']]);
    }
    
    echo "Cleanup completed. Removed " . count($oldItems) . " expired files.\n";
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>
