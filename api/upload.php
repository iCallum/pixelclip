<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Get Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Missing auth header"]);
    exit;
}

// Extract token
$token = trim(str_replace("Bearer", "", $headers['Authorization']));

// Find user by API token
$db = getDB();
$stmt = $db->prepare("SELECT id, username FROM users WHERE api_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Invalid token"]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded"]);
    exit;
}

$file = $_FILES['file'];

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "File too large (max " . formatBytes(MAX_FILE_SIZE) . ")"]);
    exit;
}

// Get file extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!$ext) {
    $ext = "png";
}

// Validate file type (basic check)
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'];
if (!in_array($ext, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Invalid file type"]);
    exit;
}

// Generate unique filename
$filename = bin2hex(random_bytes(8)) . "." . $ext;
$filepath = UPLOAD_DIR . $filename;

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save file"]);
    exit;
}

// Get MIME type
$mime_type = mime_content_type($filepath);

// Record upload in database
try {
    $stmt = $db->prepare("INSERT INTO uploads (user_id, filename, original_name, file_size, mime_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        $filename,
        $file['name'],
        $file['size'],
        $mime_type
    ]);

    echo json_encode([
        "success" => true,
        "url" => BASE_URL . "/i/" . $filename
    ]);
} catch (Exception $e) {
    // If database insert fails, delete the uploaded file
    unlink($filepath);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to record upload"]);
}
?>
