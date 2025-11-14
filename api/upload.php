<?php
$UPLOAD_TOKEN = "CHANGE_THIS_TO_A_LONG_RANDOM_SECRET";
$UPLOAD_DIR = __DIR__ . "/../i/";
$BASE_URL = "https://pixelclip.me/i/";

$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Missing auth header"]);
    exit;
}

$token = trim(str_replace("Bearer", "", $headers['Authorization']));
if ($token !== $UPLOAD_TOKEN) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Invalid token"]);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "No file uploaded"]);
    exit;
}

$file = $_FILES['file'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (!$ext) $ext = "png";

$filename = bin2hex(random_bytes(8)) . "." . $ext;
$filepath = $UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Failed to save file"]);
    exit;
}

echo json_encode([
    "success" => true,
    "url" => $BASE_URL . $filename
]);
?>