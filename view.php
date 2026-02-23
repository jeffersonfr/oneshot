<?php
// view.php
require_once 'config.php';

$error = '';
$content = null;
$fileType = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'Invalid or missing file ID.';
} else {
    $uuid = $_GET['id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get the file info and lock the row (PostgreSQL syntax)
        $stmt = $pdo->prepare("SELECT * FROM shared_items WHERE uuid = :uuid AND is_destroyed = FALSE FOR UPDATE");
        $stmt->execute(['uuid' => $uuid]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $error = 'File not found or has already been destroyed.';
            $pdo->rollBack();

            esperarIntervalo();
        } else {
            // Check if file exists on disk
            if (!file_exists($item['file_path'])) {
                // Mark as destroyed in database
                $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE WHERE id = :id");
                $destroyStmt->execute(['id' => $item['id']]);
                $pdo->commit();
                $error = 'File not found on server.';
            } else {
                // Get file contents
                $content = file_get_contents($item['file_path']);
                $fileType = $item['file_type'];
                $filename = $item['original_name'];
                $mimeType = $item['mime_type'];
                
                // Delete the file from disk
                unlink($item['file_path']);
                
                // Mark as destroyed in database
                $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE WHERE id = :id");
                $destroyStmt->execute(['id' => $item['id']]);
                
                // Update view count
                $viewStmt = $pdo->prepare("UPDATE shared_items SET view_count = view_count + 1 WHERE id = :id");
                $viewStmt->execute(['id' => $item['id']]);
                
                $pdo->commit();
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in view.php: " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

// If we have content, serve it
if ($content !== null) {
    // Set appropriate headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    
    // Output the file
    echo $content;
    exit;
}

// If there's an error or file is destroyed, show error page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneShot - File Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            text-align: center;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .content {
            padding: 40px;
        }
        
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
        }
        
        .error-message {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .info {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>OneShot</h1>
        </div>
        
        <div class="content">
            <div class="error-icon">⚠️</div>
            
            <div class="error-message">
                <?php echo htmlspecialchars($error ?: 'This file has been destroyed after being viewed.'); ?>
            </div>
            
            <a href="index.php" class="btn">Upload New File</a>
            
            <div class="info">
                <p>Files on OneShot are automatically destroyed after the first view to ensure your privacy and security.</p>
            </div>
        </div>
    </div>
</body>
</html>
