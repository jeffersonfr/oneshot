<?php
// view.php
require_once 'config.php';

$error = '';
$showForm = false;
$uuid = $_GET['id'] ?? '';

// Handle password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'], $_POST['uuid'])) {
    $submittedPassword = $_POST['password'];
    $uuid = $_POST['uuid'];
    
    try {
        $pdo->beginTransaction();
        
        // Select row for update to prevent concurrent access
        $stmt = $pdo->prepare("SELECT * FROM shared_items WHERE uuid = :uuid AND is_destroyed = FALSE FOR UPDATE");
        $stmt->execute(['uuid' => $uuid]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $error = 'File not found or has already been destroyed.';
            $pdo->rollBack();
        } elseif ($item['locked_until'] && strtotime($item['locked_until']) > time()) {
            $error = 'Too many failed attempts. This file is locked for 1 hour.';
            $pdo->rollBack();
        } else {
            // Verify password
            if (password_verify($submittedPassword, $item['password_hash'])) {
                // Password correct: reset attempts and serve file
                $updateStmt = $pdo->prepare("UPDATE shared_items SET password_attempts = 0, locked_until = NULL WHERE id = :id");
                $updateStmt->execute(['id' => $item['id']]);
                
                // Get file contents and delete
                if (file_exists($item['file_path'])) {
                    $content = file_get_contents($item['file_path']);
                    unlink($item['file_path']);
                    
                    // Mark as destroyed
                    $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE, view_count = view_count + 1 WHERE id = :id");
                    $destroyStmt->execute(['id' => $item['id']]);
                    
                    $pdo->commit();
                    
                    // Serve file
                    header('Content-Type: ' . $item['mime_type']);
                    header('Content-Disposition: inline; filename="' . $item['original_name'] . '"');
                    header('Content-Length: ' . strlen($content));
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    header('Pragma: no-cache');
                    echo $content;
                    exit;
                } else {
                    $error = 'File missing on server.';
                    $pdo->rollBack();
                }
            } else {
                // Password incorrect: increment attempts and possibly lock
                $newAttempts = $item['password_attempts'] + 1;
                if ($newAttempts >= MAX_PASSWORD_ATTEMPTS) {
                    $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                    $updateStmt = $pdo->prepare("UPDATE shared_items SET password_attempts = :attempts, locked_until = :lock WHERE id = :id");
                    $updateStmt->execute(['attempts' => $newAttempts, 'lock' => $lockUntil, 'id' => $item['id']]);
                    $error = 'Too many failed attempts. This file is now locked for 1 hour.';
                } else {
                    $updateStmt = $pdo->prepare("UPDATE shared_items SET password_attempts = :attempts WHERE id = :id");
                    $updateStmt->execute(['attempts' => $newAttempts, 'id' => $item['id']]);
                    $error = 'Incorrect password. ' . (MAX_PASSWORD_ATTEMPTS - $newAttempts) . ' attempts remaining.';
                }
                $pdo->commit();
                $showForm = true; // Show form again with error
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in view.php (POST): " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
} 
// Initial GET request
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($uuid)) {
    try {
        // First check if file exists without locking
        $stmt = $pdo->prepare("SELECT * FROM shared_items WHERE uuid = :uuid AND is_destroyed = FALSE");
        $stmt->execute(['uuid' => $uuid]);
        $item = $stmt->fetch();
        
        if (!$item) {
            $error = 'File not found or has already been destroyed.';
        } elseif ($item['locked_until'] && strtotime($item['locked_until']) > time()) {
            $error = 'Too many failed attempts. This file is locked for 1 hour.';
        } elseif (!empty($item['password_hash'])) {
            // Password protected - show form
            $showForm = true;
        } else {
            // No password - serve immediately with transaction
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT * FROM shared_items WHERE uuid = :uuid AND is_destroyed = FALSE FOR UPDATE");
                $stmt->execute(['uuid' => $uuid]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    $error = 'File not found or has already been destroyed.';
                    $pdo->rollBack();
                } elseif (file_exists($item['file_path'])) {
                    $content = file_get_contents($item['file_path']);
                    unlink($item['file_path']);
                    
                    $destroyStmt = $pdo->prepare("UPDATE shared_items SET is_destroyed = TRUE, view_count = view_count + 1 WHERE id = :id");
                    $destroyStmt->execute(['id' => $item['id']]);
                    
                    $pdo->commit();
                    
                    header('Content-Type: ' . $item['mime_type']);
                    header('Content-Disposition: inline; filename="' . $item['original_name'] . '"');
                    header('Content-Length: ' . strlen($content));
                    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                    header('Pragma: no-cache');
                    echo $content;
                    exit;
                } else {
                    $error = 'File missing on server.';
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } catch (Exception $e) {
        error_log("Error in view.php (GET): " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
} else {
    $error = 'Invalid request.';
}

// If we reach here, either error or need to show password form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneShot - <?php echo $showForm ? 'Enter Password' : 'File Status'; ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <a href="/">
            <div class="header">
                <h1>🔒 OneShot</h1>
            </div>
        </a>

        <div class="content">
            <?php if ($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($showForm): ?>
                <div class="password-form">
                    <h2>🔐 Password Protected</h2>
                    <p>This file is protected. Please enter the password to view it.</p>
                    
                    <form method="post">
                        <input type="hidden" name="uuid" value="<?php echo htmlspecialchars($uuid); ?>">
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" name="password" id="password" required autofocus>
                        </div>
                        <button type="submit" class="btn">View File</button>
                    </form>
                    
                    <div class="info">
                        <p>⚠️ After successful viewing, the file will be permanently destroyed.</p>
                    </div>
                </div>
            <?php elseif (!$error && !$showForm): ?>
                <!-- This case should not happen normally, but just in case -->
                <div class="info">
                    <p>No file information available.</p>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" class="back-link">← Upload another file</a>
            </div>
        </div>
    </div>
</body>
</html>
