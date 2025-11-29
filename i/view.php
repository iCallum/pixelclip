<?php
require_once __DIR__ . '/../config.php';

$file = $_GET['file'] ?? null;

if (!$file) {
    http_response_code(404);
    exit('File not specified');
}

$file = basename($file);
$db = getDB();

// Find the file record (searching for suffix)
$stmt = $db->prepare("SELECT id, filename, mime_type, expires_at, views_limit, views_count FROM uploads WHERE filename LIKE ?");
$stmt->execute(['%/' . $file]);
$upload = $stmt->fetch();

if (!$upload) {
    http_response_code(404);
    exit('File not found');
}

$filepath = UPLOAD_DIR . $upload['filename'];

// Check Physical Existence
if (!file_exists($filepath)) {
    // Clean up DB if file missing
    $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload['id']]);
    http_response_code(404);
    exit('File missing');
}

// 1. Check Expiration (Time)
if ($upload['expires_at'] && strtotime($upload['expires_at']) < time()) {
    unlink($filepath);
    $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload['id']]);
    http_response_code(410); // Gone
    exit('File expired');
}

// 2. Check View Limit
if ($upload['views_limit'] !== null) {
    if ($upload['views_count'] >= $upload['views_limit']) {
        // Should have been deleted already, but just in case
        unlink($filepath);
        $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload['id']]);
        http_response_code(410);
        exit('File expired (view limit reached)');
    }

    // Increment view count
    $new_count = $upload['views_count'] + 1;
    $stmt = $db->prepare("UPDATE uploads SET views_count = ? WHERE id = ?");
    $stmt->execute([$new_count, $upload['id']]);

    // If this view hits the limit, we serve it NOW, but delete it afterwards
    if ($new_count >= $upload['views_limit']) {
        register_shutdown_function(function() use ($filepath, $db, $upload) {
            if (file_exists($filepath)) unlink($filepath);
            $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload['id']]);
        });
    }
}

// Serve the file
header('Content-Type: ' . $upload['mime_type']);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // Don't cache expirable content
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');

readfile($filepath);
exit;
?>