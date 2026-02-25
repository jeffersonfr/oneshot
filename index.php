<?php
// index.php
require_once 'config.php';

$message = '';
$messageType = '';
$shareLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload failed. Error code: ' . $file['error'];
        $messageType = 'error';
    } else {
        // Validate file size (max 100MB)
        if ($file['size'] > 100 * 1024 * 1024) {
            $message = 'File too large. Maximum size is 100MB.';
            $messageType = 'error';
        } else {
            // Get file extension and validate
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileType = getFileType($extension);
            
            if ($fileType === 'unknown') {
                $message = 'File type not allowed. Please upload images, videos, or documents.';
                $messageType = 'error';
            } else {
                // Generate UUID and prepare file paths
                $uuid = generateUUID();
                $sanitizedFilename = sanitizeFilename($file['name']);
                $uniqueFilename = $uuid . '_' . $sanitizedFilename;
                $filePath = UPLOAD_DIR . $uniqueFilename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filePath)) {
                    // Save to database
                    try {
			// Inside the upload handling block, after file validation
			$password = $_POST['password'] ?? '';
			$passwordHash = null;
			if (!empty($password)) {
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
			}

			// Then in the INSERT statement, add password_hash
			$stmt = $pdo->prepare("INSERT INTO shared_items (uuid, filename, original_name, file_path, file_type, file_size, mime_type, password_hash) 
						    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute([
			$uuid,
			$uniqueFilename,
			$sanitizedFilename,
			$filePath,
			$fileType,
			$file['size'],
			$file['type'],
			$passwordHash
			]);

                        $shareLink = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                                    $_SERVER['HTTP_HOST'] . 
                                    dirname($_SERVER['SCRIPT_NAME']) . 
                                    '/view.php?id=' . $uuid;
                        
                        $message = 'File uploaded successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Database error: ' . $e->getMessage();
                        $messageType = 'error';
                        // Clean up uploaded file
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $message = 'Failed to move uploaded file.';
                    $messageType = 'error';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneShot - Self-Destructing File Sharing</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 OneShot</h1>
            <p>Share files securely - they self-destruct after first view</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($shareLink): ?>
                <div class="share-link">
                    <h3>📋 Your shareable link:</h3>
                    <div class="link-container">
                        <input type="text" id="shareLink" value="<?php echo htmlspecialchars($shareLink); ?>" readonly>
                        <button class="copy-btn" onclick="copyLink()">Copy</button>
                    </div>
		    <?php if (!empty($password)): ?>
		        <p style="margin-top: 10px; color: #e74c3c; font-size: 0.9em;">
		            🔒 Password protected: The recipient will need to enter the password you set.
		        </p>
		    <?php endif; ?>
                    <p style="margin-top: 10px; color: #666; font-size: 0.9em;">
                        ⚠️ This link will work only once and self-destruct after viewing!
                    </p>
                </div>
            <?php endif; ?>
            
            <form id="uploadForm" method="post" enctype="multipart/form-data">
                <div class="upload-area" id="uploadArea">
                    <i>📁</i>
                    <h3>Drag & drop your file here</h3>
                    <p>or click to browse</p>
                    <p style="margin-top: 10px; font-size: 0.85em; color: #999;">
                        Supports: Images, Videos, Documents (Max: 100MB)
                    </p>
                    <input type="file" name="file" id="fileInput" style="display: none;" required>
                    <div class="file-info" id="fileInfo"></div>
                </div>
                
		<div class="password-option">
		    <label for="password">Optional Password:</label>
		    <input type="password" name="password" id="password" placeholder="Set a password (optional)">
		    <small>If set, recipient must enter this password to view the file.</small>
		</div>

                <button type="submit" class="btn" id="submitBtn" disabled>Upload & Generate Link</button>
            </form>
            
            <div class="features">
                <div class="feature">
                    <i>🔐</i>
                    <h4>Secure</h4>
                    <p>End-to-end encryption</p>
                </div>
                <div class="feature">
                    <i>⚡</i>
                    <h4>Instant</h4>
                    <p>Self-destruct after view</p>
                </div>
                <div class="feature">
                    <i>🔗</i>
                    <h4>Simple</h4>
                    <p>One-time shareable link</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const submitBtn = document.getElementById('submitBtn');
        
        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Highlight drop area when dragging over it
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            uploadArea.classList.add('dragover');
        }
        
        function unhighlight() {
            uploadArea.classList.remove('dragover');
        }
        
        // Handle dropped files
        uploadArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            }
        }
        
        // Handle click on upload area
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Handle file selection via input
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateFileInfo(e.target.files[0]);
            }
        });
        
        function updateFileInfo(file) {
            const fileSize = (file.size / (1024 * 1024)).toFixed(2);
            const fileType = file.type || 'Unknown type';
            
            fileInfo.innerHTML = `
                <strong>Selected:</strong> ${file.name}<br>
                <strong>Size:</strong> ${fileSize} MB<br>
                <strong>Type:</strong> ${fileType}
            `;
            
            submitBtn.disabled = false;
        }
        
        function copyLink() {
            const linkInput = document.getElementById('shareLink');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            } catch (err) {
                alert('Failed to copy link. Please select and copy manually.');
            }
        }
        
        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', (e) => {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a file first!');
            }
        });
    </script>
</body>
</html>
