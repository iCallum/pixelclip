<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$db = getDB();
$user = null;

// 1. Authentication (Session or Token)
if (isLoggedIn()) {
    $user = getCurrentUser();
} else {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = trim(str_replace("Bearer", "", $headers['Authorization']));
        $stmt = $db->prepare("SELECT id, username, storage_quota FROM users WHERE api_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
    }
}

if (!$user) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

// 2. Check File Presence
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded"]);
    exit;
}

$file = $_FILES['file'];

// 3. Validate File Size
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "File too large (max " . formatBytes(MAX_FILE_SIZE) . ")"]);
    exit;
}

// 4. Check Storage Quota
if (isset($user['storage_quota']) && $user['storage_quota'] > 0) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(file_size), 0) as total_used FROM uploads WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $used = $stmt->fetch()['total_used'];

    if (($used + $file['size']) > $user['storage_quota']) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Storage quota exceeded"]);
        exit;
    }
}

// 5. Validate File Type
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!$ext) $ext = "png";

$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
if (!in_array($ext, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid file type"]);
    exit;
}

// 6. Handle Expiration & Limits
$expires_at = null;
if (isset($_POST['expires_in']) && is_numeric($_POST['expires_in'])) {
    $seconds = (int)$_POST['expires_in'];
    if ($seconds > 0) {
        $expires_at = date('Y-m-d H:i:s', time() + $seconds);
    }
}

$views_limit = null;
if (isset($_POST['views_limit']) && is_numeric($_POST['views_limit'])) {
    $limit = (int)$_POST['views_limit'];
    if ($limit > 0) {
        $views_limit = $limit;
    }
}

// 7. Save File
$userFolder = $user['username'];
$filename = $userFolder . '/' . bin2hex(random_bytes(8)) . "." . $ext;
$filepath = UPLOAD_DIR . $filename;

$userUploadDir = UPLOAD_DIR . $userFolder;
if (!is_dir($userUploadDir)) {
    mkdir($userUploadDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save file"]);
    exit;
}

$mime_type = mime_content_type($filepath);

// 8. Record in Database
try {
    $stmt = $db->prepare("INSERT INTO uploads (user_id, filename, original_name, file_size, mime_type, expires_at, views_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        $filename,
        $file['name'],
        $file['size'],
        $mime_type,
        $expires_at,
        $views_limit
    ]);

    echo json_encode([
        "success" => true,
        "url" => BASE_URL . "/i/" . basename($filename)
    ]);
} catch (Exception $e) {
    unlink($filepath);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error"]);
}
?>