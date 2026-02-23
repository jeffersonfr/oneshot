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
                        $stmt = $pdo->prepare("INSERT INTO shared_items (uuid, filename, original_name, file_path, file_type, file_size, mime_type) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $uuid,
                            $uniqueFilename,
                            $sanitizedFilename,
                            $filePath,
                            $fileType,
                            $file['size'],
                            $file['type']
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
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .content {
            padding: 40px;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .upload-area:hover,
        .upload-area.dragover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 15px;
            display: block;
        }
        
        .upload-area h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #666;
            font-size: 0.9em;
        }
        
        .file-info {
            margin-top: 15px;
            color: #667eea;
            font-weight: 500;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .share-link {
            background: #f8f9ff;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #667eea;
        }
        
        .share-link h3 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .link-container {
            display: flex;
            gap: 10px;
        }
        
        .link-container input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9em;
        }
        
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .copy-btn:hover {
            background: #764ba2;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
            text-align: center;
        }
        
        .feature {
            padding: 20px;
        }
        
        .feature i {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 10px;
            display: block;
        }
        
        .feature h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .feature p {
            color: #666;
            font-size: 0.85em;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 30px;
            }
            
            .content {
                padding: 20px;
            }
            
            .features {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
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
