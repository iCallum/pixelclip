<?php
// Database Connection Test & Admin Account Diagnostic
// DELETE THIS FILE after testing for security!

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PixelClip Database Diagnostics</h1>";
echo "<style>body{font-family:monospace;background:#0a0a0a;color:#fff;padding:20px;}h2{color:#667eea;margin-top:20px;}pre{background:#1a1a1a;padding:10px;border-radius:8px;overflow-x:auto;}.success{color:#86efac;}.error{color:#fca5a5;}.warning{color:#fbbf24;}</style>";

// 1. Check if config.php exists and is readable
echo "<h2>1. Config File Check</h2>";
if (file_exists('config.php')) {
    echo "<span class='success'>✓ config.php exists</span><br>";
    require_once 'config.php';
    echo "<span class='success'>✓ config.php loaded successfully</span><br>";
} else {
    echo "<span class='error'>✗ config.php not found!</span><br>";
    die();
}

// 2. Check database configuration
echo "<h2>2. Database Configuration</h2>";
echo "Host: " . DB_HOST . "<br>";
echo "Database: " . DB_NAME . "<br>";
echo "User: " . DB_USER . "<br>";
echo "Password: " . (DB_PASS === 'CHANGE_THIS_PASSWORD' ? "<span class='error'>NOT SET (still default)</span>" : "<span class='success'>SET</span>") . "<br>";

// 3. Test database connection
echo "<h2>3. Database Connection Test</h2>";
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<span class='success'>✓ Successfully connected to database</span><br>";
} catch (PDOException $e) {
    echo "<span class='error'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    die();
}

// 4. Check if tables exist
echo "<h2>4. Table Check</h2>";
$tables = ['users', 'uploads', 'invite_codes'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<span class='success'>✓ Table '$table' exists ($count rows)</span><br>";
    } catch (PDOException $e) {
        echo "<span class='error'>✗ Table '$table' missing or inaccessible</span><br>";
    }
}

// 5. Check admin user
echo "<h2>5. Admin User Check</h2>";
try {
    $stmt = $pdo->query("SELECT id, username, email, password_hash, api_token, is_admin, created_at FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        echo "<span class='success'>✓ Admin user found</span><br>";
        echo "<pre>";
        echo "ID: " . $admin['id'] . "\n";
        echo "Username: " . $admin['username'] . "\n";
        echo "Email: " . $admin['email'] . "\n";
        echo "Is Admin: " . ($admin['is_admin'] ? 'Yes' : 'No') . "\n";
        echo "Created: " . $admin['created_at'] . "\n";
        echo "Password Hash: " . substr($admin['password_hash'], 0, 30) . "...\n";
        echo "API Token: " . substr($admin['api_token'], 0, 20) . "...\n";
        echo "</pre>";
    } else {
        echo "<span class='error'>✗ Admin user NOT found in database</span><br>";
        echo "<span class='warning'>The INSERT statement may have failed. Try manually creating admin user.</span><br>";
    }
} catch (PDOException $e) {
    echo "<span class='error'>✗ Error querying users table: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// 6. Password verification test
echo "<h2>6. Password Verification Test</h2>";
if (isset($admin) && $admin) {
    $test_password = 'changeme';

    echo "Testing password: '<strong>$test_password</strong>'<br>";

    if (password_verify($test_password, $admin['password_hash'])) {
        echo "<span class='success'>✓ Password verification SUCCESS - login should work!</span><br>";
    } else {
        echo "<span class='error'>✗ Password verification FAILED</span><br>";
        echo "<span class='warning'>The password hash in the database is incorrect.</span><br>";

        // Generate correct hash
        echo "<br><strong>Fix: Use this SQL to set the correct password:</strong><br>";
        $correct_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<pre>UPDATE users SET password_hash = '$correct_hash' WHERE username = 'admin';</pre>";
    }
} else {
    echo "<span class='error'>Cannot test - admin user doesn't exist</span><br>";

    // Provide SQL to create admin user
    echo "<br><strong>Fix: Run this SQL to create admin user:</strong><br>";
    $password_hash = password_hash('changeme', PASSWORD_DEFAULT);
    $api_token = bin2hex(random_bytes(32));
    echo "<pre>INSERT INTO users (username, email, password_hash, api_token, is_admin) VALUES (
    'admin',
    'admin@pixelclip.local',
    '$password_hash',
    '$api_token',
    1
);</pre>";
}

// 7. Session test
echo "<h2>7. Session Configuration</h2>";
echo "Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='success'>Yes</span>" : "<span class='error'>No</span>") . "<br>";
echo "Session cookie secure: " . ini_get('session.cookie_secure') . " <span class='warning'>(Set to 0 if testing without HTTPS)</span><br>";
echo "Session cookie httponly: " . ini_get('session.cookie_httponly') . "<br>";

// 8. Upload directory check
echo "<h2>8. Upload Directory Check</h2>";
if (is_dir(UPLOAD_DIR)) {
    echo "<span class='success'>✓ Upload directory exists: " . UPLOAD_DIR . "</span><br>";
    if (is_writable(UPLOAD_DIR)) {
        echo "<span class='success'>✓ Upload directory is writable</span><br>";
    } else {
        echo "<span class='error'>✗ Upload directory is NOT writable</span><br>";
    }
} else {
    echo "<span class='error'>✗ Upload directory does not exist: " . UPLOAD_DIR . "</span><br>";
    echo "<span class='warning'>Run: mkdir i && chmod 755 i</span><br>";
}

echo "<hr>";
echo "<p><strong class='error'>⚠️ IMPORTANT: Delete this file (test-db.php) after testing for security!</strong></p>";
?>
