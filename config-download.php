<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Generate ShareX configuration
$config = [
    "Version" => "18.0.1",
    "Name" => "PixelClip - " . $user['username'],
    "DestinationType" => "ImageUploader",
    "RequestMethod" => "POST",
    "RequestURL" => BASE_URL . "/api/upload.php",
    "Headers" => [
        "Authorization" => "Bearer " . $user['api_token']
    ],
    "Body" => "MultipartFormData",
    "Arguments" => [
        "file" => "@file"
    ],
    "FileFormName" => "file",
    "URL" => "{json:url}"
];

$filename = "pixelclip-" . $user['username'] . ".sxcu";
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));

echo $json;
?>
