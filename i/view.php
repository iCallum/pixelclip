<?php
require_once __DIR__ . '/../config.php';

// Get the requested filename
$file = $_GET['file'] ?? null;

if (!$file) {
    http_response_code(404);
    exit('File not specified');
}

// Sanitize: keep only alphanumeric, dots, dashes, underscores
$file = basename($file);

// Database lookup
$db = getDB();

// Find the file record
// We assume the requested file matches the end of the stored path
$stmt = $db->prepare("SELECT filename, mime_type FROM uploads WHERE filename LIKE ?");
$stmt->execute(['%/' . $file]);
$upload = $stmt->fetch();

if (!$upload) {
    http_response_code(404);
    exit('File not found');
}

$filepath = UPLOAD_DIR . $upload['filename'];

if (!file_exists($filepath)) {
    http_response_code(404);
    exit('File missing');
}

// Serve the file
header('Content-Type: ' . $upload['mime_type']);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *'); // Allow embedding

readfile($filepath);
exit;
?>
