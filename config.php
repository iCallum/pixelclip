<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ebitlrcj_pixelclip');
define('DB_USER', 'ebitlrcj');
define('DB_PASS', 'CHANGE_THIS_PASSWORD'); // Set your database password here

// Site Configuration
define('BASE_URL', 'https://pixelclip.me');
define('UPLOAD_DIR', __DIR__ . '/i/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Set to 0 if not using HTTPS in development
session_start();

// Database Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function getCurrentUser() {                                            

    if (!isLoggedIn()) {                                               

        return null;                                                   

    }                                                                  

                                                                       

    $db = getDB();                                                     

    $stmt = $db->prepare("SELECT id, username, email, api_token, is_admin, storage_quota FROM users WHERE id = ?");                                          

    $stmt->execute([$_SESSION['user_id']]);                            

    return $stmt->fetch();                                             

}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
