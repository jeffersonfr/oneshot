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
            // unlink($item['file_path']);
            secureDelete($item['file_path']);
        }
        
        // Mark as destroyed
        $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE WHERE id = :id");
        $destroyStmt->execute(['id' => $item['id']]);
    }
    
    echo "Cleanup completed. Removed " . count($oldItems) . " expired files.\n";
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}

function secureDelete($filepath, $passes = 1) {
    if (!file_exists($filepath)) {
        return false;
    }
    
    $size = filesize($filepath);
    $fp = fopen($filepath, 'r+');
    
    if (!$fp) {
        return false;
    }
    
    // Sobrescreve com padrões aleatórios
    for ($pass = 0; $pass < $passes; $pass++) {
        fseek($fp, 0);
        $data = random_bytes($size);
        fwrite($fp, $data);
        fflush($fp);
    }
    
    // Passada final com zeros
    fseek($fp, 0);
    fwrite($fp, str_repeat("\0", $size));
    fflush($fp);
    
    fclose($fp);
    
    // Remove o arquivo
    return unlink($filepath);
}
?>
