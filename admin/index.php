<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$user = getCurrentUser();

// Check if user is admin
if (!$user['is_admin']) {
    http_response_code(403);
    die('Access denied');
}

$db = getDB();

// Get system stats
$stmt = $db->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

$stmt = $db->query("SELECT COUNT(*) as total_uploads FROM uploads");
$total_uploads = $stmt->fetch()['total_uploads'];

$stmt = $db->query("SELECT COALESCE(SUM(file_size), 0) as total_storage FROM uploads");
$total_storage = $stmt->fetch()['total_storage'];

$stmt = $db->query("SELECT COUNT(*) as unused_invites FROM invite_codes WHERE used_by IS NULL");
$unused_invites = $stmt->fetch()['unused_invites'];

// Handle invite code generation
if (isset($_POST['generate_invite'])) {
    $count = (int)($_POST['count'] ?? 1);
    $count = max(1, min(10, $count)); // 1-10 codes at a time

    for ($i = 0; $i < $count; $i++) {
        $code = generateToken(16);
        $stmt = $db->prepare("INSERT INTO invite_codes (code, created_by) VALUES (?, ?)");
        $stmt->execute([$code, $user['id']]);
    }

    header('Location: index.php?generated=' . $count);
    exit;
}

// Handle user deletion
if (isset($_GET['delete_user'])) {
    $user_id = (int)$_GET['delete_user'];

    if ($user_id !== $user['id']) { // Can't delete yourself
        // Get user's uploads to delete files
        $stmt = $db->prepare("SELECT filename FROM uploads WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $uploads = $stmt->fetchAll();

        // Delete files
        foreach ($uploads as $upload) {
            $filepath = UPLOAD_DIR . $upload['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        // Delete user (cascade will handle uploads and invite codes)
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        header('Location: index.php?user_deleted=1');
        exit;
    }
}

// Handle user quota update
if (isset($_POST['update_quota'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_quota_mb = (int)$_POST['quota_mb'];
    $new_quota_bytes = $new_quota_mb * 1024 * 1024; // Convert MB to Bytes

    if ($target_user_id > 0) {
        $stmt = $db->prepare("UPDATE users SET storage_quota = ? WHERE id = ?");
        $stmt->execute([$new_quota_bytes, $target_user_id]);
        header('Location: index.php?quota_updated=1');
        exit;
    }
}

// Handle invite code deletion
if (isset($_GET['delete_invite'])) {
    $invite_id = (int)$_GET['delete_invite'];
    $stmt = $db->prepare("DELETE FROM invite_codes WHERE id = ? AND used_by IS NULL");
    $stmt->execute([$invite_id]);
    header('Location: index.php?invite_deleted=1');
    exit;
}

// Get all users
$stmt = $db->query("
    SELECT u.*,
           COUNT(DISTINCT up.id) as upload_count,
           COALESCE(SUM(up.file_size), 0) as total_size
    FROM users u
    LEFT JOIN uploads up ON u.id = up.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Get unused invite codes
$stmt = $db->query("
    SELECT ic.*, u.username as created_by_name
    FROM invite_codes ic
    JOIN users u ON ic.created_by = u.id
    WHERE ic.used_by IS NULL
    ORDER BY ic.created_at DESC
");
$invites = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - PixelClip</title>
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
            padding: 20px;
        }

        .header {
            max-width: 1400px;
            margin: 0 auto 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .nav a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .nav a:hover {
            color: rgba(255, 255, 255, 1);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px;
        }

        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px;
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-admin {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }

        .badge-user {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
        }

        .action-link {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            margin-right: 12px;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        .action-link.delete {
            color: #fca5a5;
        }

        .code-box {
            background: rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 13px;
            display: inline-block;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .invite-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .invite-form input {
            width: 80px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: white;
            font-size: 14px;
        }

        .invite-form input:focus {
            outline: none;
            border-color: #667eea;
        }

        @media (max-width: 768px) {
            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="logo">PixelClip Admin</h1>
        <div class="nav">
            <a href="../dashboard.php">Dashboard</a>
            <a href="../index.php">Home</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['generated'])): ?>
            <div class="success-message">Generated <?php echo (int)$_GET['generated']; ?> invite code(s)</div>
        <?php endif; ?>

        <?php if (isset($_GET['user_deleted'])): ?>
            <div class="success-message">User deleted successfully</div>
        <?php endif; ?>

        <?php if (isset($_GET['invite_deleted'])): ?>
            <div class="success-message">Invite code deleted</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Uploads</div>
                <div class="stat-value"><?php echo number_format($total_uploads); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Storage Used</div>
                <div class="stat-value" style="font-size: 24px;"><?php echo formatBytes($total_storage); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unused Invites</div>
                <div class="stat-value"><?php echo $unused_invites; ?></div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Invite Codes</h2>
                <form method="POST" class="invite-form">
                    <input type="number" name="count" value="1" min="1" max="10">
                    <button type="submit" name="generate_invite" class="btn">Generate</button>
                </form>
            </div>

            <?php if (empty($invites)): ?>
                <p style="color: rgba(255, 255, 255, 0.5);">No unused invite codes</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invites as $invite): ?>
                            <tr>
                                <td><span class="code-box"><?php echo htmlspecialchars($invite['code']); ?></span></td>
                                <td><?php echo htmlspecialchars($invite['created_by_name']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($invite['created_at'])); ?></td>
                                <td>
                                    <a href="?delete_invite=<?php echo $invite['id']; ?>"
                                       class="action-link delete"
                                       onclick="return confirm('Delete this invite code?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="section">
            <div class="section-header">
                <h2 class="section-title">Users</h2>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Uploads</th>
                        <th>Storage</th>
                        <th>Joined</th>
                        <th>Quota</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $u['is_admin'] ? 'badge-admin' : 'badge-user'; ?>">
                                    <?php echo $u['is_admin'] ? 'Admin' : 'User'; ?>
                                </span>
                            </td>
                            <td><?php echo number_format($u['upload_count']); ?></td>
                            <td><?php echo formatBytes($u['total_size']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            <td><?php echo ($u['storage_quota'] > 0) ? formatBytes($u['storage_quota']) : 'Unlimited'; ?></td>
                            <td>
                                <form method="POST" style="display:inline-block; margin-right: 10px;">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <select name="quota_mb" onchange="this.form.submit()">
                                        <option value="-1" <?php echo ($u['storage_quota'] <= 0) ? 'selected' : ''; ?>>Unlimited</option>
                                        <option value="100" <?php echo ($u['storage_quota'] == 100 * 1024 * 1024) ? 'selected' : ''; ?>>100MB</option>
                                        <option value="500" <?php echo ($u['storage_quota'] == 500 * 1024 * 1024) ? 'selected' : ''; ?>>500MB</option>
                                        <option value="1024" <?php echo ($u['storage_quota'] == 1024 * 1024 * 1024) ? 'selected' : ''; ?>>1GB</option>
                                        <option value="5120" <?php echo ($u['storage_quota'] == 5120 * 1024 * 1024) ? 'selected' : ''; ?>>5GB</option>
                                    </select>
                                    <input type="hidden" name="update_quota" value="1">
                                </form>
                                <?php if ($u['id'] !== $user['id']): ?>
                                    <a href="?delete_user=<?php echo $u['id']; ?>"
                                       class="action-link delete"
                                       onclick="return confirm('Delete this user and all their uploads?')">Delete</a>
                                <?php else: ?>
                                    <span style="color: rgba(255, 255, 255, 0.3);">You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
