<?php
/**
 * Admin Password Change Script
 *
 * Usage:
 * 1. Edit the $username and $new_password variables below
 * 2. Visit this file in your browser OR run via CLI: php change-password.php
 * 3. DELETE this file immediately after use for security
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== CONFIGURATION =====
// Edit these values:
$username = 'admin';              // Username to change password for
$new_password = 'your_new_password_here';  // New password (change this!)
// =========================

echo "<h1>Password Change Script</h1>";
echo "<style>body{font-family:monospace;background:#0a0a0a;color:#fff;padding:20px;}h2{color:#667eea;}.success{color:#86efac;}.error{color:#fca5a5;}.warning{color:#fbbf24;}</style>";

// Validation
if ($new_password === 'your_new_password_here') {
    echo "<p class='error'>ERROR: You must edit this file and set a new password!</p>";
    echo "<p>Open this file in a text editor and change the \$new_password variable.</p>";
    exit;
}

if (strlen($new_password) < 8) {
    echo "<p class='error'>ERROR: Password must be at least 8 characters long.</p>";
    exit;
}

// Load config
if (!file_exists('config.php')) {
    echo "<p class='error'>ERROR: config.php not found!</p>";
    exit;
}

require_once 'config.php';

echo "<h2>Step 1: Connecting to Database</h2>";
try {
    $db = getDB();
    echo "<p class='success'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<h2>Step 2: Finding User</h2>";
try {
    $stmt = $db->prepare("SELECT id, username, email, is_admin FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        echo "<p class='error'>✗ User '$username' not found in database</p>";
        echo "<p>Available users:</p><ul>";

        $stmt = $db->query("SELECT username FROM users ORDER BY username");
        while ($row = $stmt->fetch()) {
            echo "<li>" . htmlspecialchars($row['username']) . "</li>";
        }
        echo "</ul>";
        exit;
    }

    echo "<p class='success'>✓ User found:</p>";
    echo "<ul>";
    echo "<li>ID: " . $user['id'] . "</li>";
    echo "<li>Username: " . htmlspecialchars($user['username']) . "</li>";
    echo "<li>Email: " . htmlspecialchars($user['email']) . "</li>";
    echo "<li>Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "</li>";
    echo "</ul>";

} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<h2>Step 3: Generating Password Hash</h2>";
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);
echo "<p class='success'>✓ Password hashed using bcrypt</p>";
echo "<p style='font-size:11px;color:#666;'>Hash: " . substr($password_hash, 0, 40) . "...</p>";

echo "<h2>Step 4: Updating Password</h2>";
try {
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$password_hash, $user['id']]);

    echo "<p class='success'>✓ Password updated successfully!</p>";

    // Verify it worked
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $updated_user = $stmt->fetch();

    if (password_verify($new_password, $updated_user['password_hash'])) {
        echo "<p class='success'>✓ Password verification successful - new password is working!</p>";
    } else {
        echo "<p class='error'>✗ Warning: Password verification failed!</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ Error updating password: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p class='success'><strong>SUCCESS!</strong> Password for user '" . htmlspecialchars($username) . "' has been changed.</p>";
echo "<p>You can now login with:</p>";
echo "<ul>";
echo "<li>Username: <strong>" . htmlspecialchars($username) . "</strong></li>";
echo "<li>Password: <strong>(the new password you set)</strong></li>";
echo "</ul>";

echo "<hr>";
echo "<p class='error'><strong>⚠️ IMPORTANT: DELETE THIS FILE NOW!</strong></p>";
echo "<p>For security, run this command:</p>";
echo "<pre style='background:#1a1a1a;padding:10px;border-radius:8px;'>rm change-password.php</pre>";
echo "<p>Or delete it via FTP/file manager.</p>";
?>
