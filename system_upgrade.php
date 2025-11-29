<?php
require_once 'config.php';

// Only allow admin to run this, or if it's a CLI run
if (php_sapi_name() !== 'cli' && (!isLoggedIn() || !getCurrentUser()['is_admin'])) {
    die('Access Denied. Please log in as Admin to run this upgrade script.');
}

$db = getDB();
echo "<pre>"; // Use <pre> for web output to preserve newlines

echo "Starting database upgrade...\n";

// Add storage_quota to users table
try {
    $db->query("SELECT storage_quota FROM users LIMIT 1");
    echo "Column 'storage_quota' already exists in 'users'.\n";
} catch (PDOException $e) {
    $db->exec("ALTER TABLE users ADD COLUMN storage_quota BIGINT NULL DEFAULT 1073741824"); // Default 1GB
    echo "Added 'storage_quota' to 'users' table.\n";
}

// Add expiration columns to uploads table
try {
    $db->query("SELECT expires_at FROM uploads LIMIT 1");
    echo "Column 'expires_at' already exists in 'uploads'.\n";
} catch (PDOException $e) {
    $db->exec("ALTER TABLE uploads ADD COLUMN expires_at TIMESTAMP NULL DEFAULT NULL");
    echo "Added 'expires_at' to 'uploads' table.\n";
}

try {
    $db->query("SELECT views_limit FROM uploads LIMIT 1");
    echo "Column 'views_limit' already exists in 'uploads'.\n";
} catch (PDOException $e) {
    $db->exec("ALTER TABLE uploads ADD COLUMN views_limit INT NULL DEFAULT NULL");
    echo "Added 'views_limit' to 'uploads' table.\n";
}

try {
    $db->query("SELECT views_count FROM uploads LIMIT 1");
    echo "Column 'views_count' already exists in 'uploads'.\n";
} catch (PDOException $e) {
    $db->exec("ALTER TABLE uploads ADD COLUMN views_count INT DEFAULT 0");
    echo "Added 'views_count' to 'uploads' table.\n";
}

echo "Database upgrade complete.</pre>";

// Clean up the script itself
if (php_sapi_name() !== 'cli') {
    // Optionally redirect or show a message then self-delete.
    // For now, just show message. User must manually delete this file.
    // echo "<p><strong>IMPORTANT:</strong> Please delete this `system_upgrade.php` file from your server for security reasons.</p>";
}
?>