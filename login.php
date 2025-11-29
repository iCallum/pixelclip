<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request (CSRF token mismatch). Please refresh and try again.';
    } else {
        // Rate Limiting (File-based)
        $ip = $_SERVER['REMOTE_ADDR'];
        $limit_file = sys_get_temp_dir() . '/pixelclip_login_attempts.json';
        $attempts = [];
        if (file_exists($limit_file)) {
            $attempts = json_decode(file_get_contents($limit_file), true) ?? [];
        }

        // Clean up old attempts (older than 15 mins)
        $window = 15 * 60;
        $now = time();
        if (isset($attempts[$ip])) {
            $attempts[$ip] = array_filter($attempts[$ip], function($time) use ($now, $window) {
                return ($now - $time) < $window;
            });
        }

        // Check limit (5 attempts)
        if (isset($attempts[$ip]) && count($attempts[$ip]) >= 5) {
            $error = 'Too many login attempts. Please try again in 15 minutes.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
        
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required';
            } else {
                $db = getDB();
                $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
        
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Clear attempts on success
                    unset($attempts[$ip]);
                    file_put_contents($limit_file, json_encode($attempts));

                    // Update last login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
        
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
        
                    header('Location: dashboard.php');
                    exit;
                } else {
                    // Record failed attempt
                    $attempts[$ip][] = time();
                    file_put_contents($limit_file, json_encode($attempts));
                    
                    $error = 'Invalid username or password';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PixelClip</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 450px;
            width: 100%;
        }

        .logo {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 32px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.08);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .footer-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-link a {
            color: #667eea;
            text-decoration: none;
        }

        .footer-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="logo">PixelClip</h1>
        <p class="subtitle">Login to your account</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <div class="footer-link">
            Don't have an account? <a href="register.php">Register</a> | <a href="index.php">Home</a>
        </div>
    </div>
</body>
</html>
