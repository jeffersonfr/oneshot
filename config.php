<?php
// config.php
session_start();

define('UPLOAD_DIR', '/var/www/html/uploads/');
define('ALLOWED_IMAGES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEOS', ['mp4', 'webm', 'ogg', 'mov', 'avi']);
define('ALLOWED_DOCUMENTS', ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx']);

// Database configuration for PostgreSQL
define('DB_HOST', 'db'); // This matches the service name in docker-compose
define('DB_PORT', '5432');
define('DB_NAME', 'oneshot');
define('DB_USER', 'oneshot_user');
define('DB_PASS', 'oneshot_password'); // Change this in production!
define('DB_SSL_MODE', 'disable');

// config.php (add near other defines)
define('MAX_PASSWORD_ATTEMPTS', 3);
define('LOCKOUT_DURATION', 3600); // 1 hour in seconds

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Database connection for PostgreSQL
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=" . DB_SSL_MODE;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function getFileType($extension) {
    if (in_array($extension, ALLOWED_IMAGES)) return 'image';
    if (in_array($extension, ALLOWED_VIDEOS)) return 'video';
    if (in_array($extension, ALLOWED_DOCUMENTS)) return 'document';
    return 'unknown';
}

function sanitizeFilename($filename) {
    // Remove any path information and sanitize
    $filename = basename($filename);
    // Replace any non-alphanumeric characters except dots and dashes
    $filename = preg_replace("/[^a-zA-Z0-9.-]/", "_", $filename);
    // Limit filename length
    if (strlen($filename) > 100) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $filename = substr($name, 0, 90) . '.' . $ext;
    }
    return $filename;
}

function esperarIntervalo() {
    // Calcula um valor aleatório entre 200 e 1000 milissegundos
    $milissegundos = rand(200, 1000);
    
    // Converte para microssegundos (usleep espera em microssegundos)
    $microssegundos = $milissegundos * 1000;
    
    // Aguarda o tempo determinado
    usleep($microssegundos);
}
?>
