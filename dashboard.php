<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get user stats
$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM uploads WHERE user_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Get recent uploads
$stmt = $db->prepare("SELECT * FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$uploads = $stmt->fetchAll();

// Handle delete request
if (isset($_GET['delete'])) {
    $upload_id = (int)$_GET['delete'];

    // Verify ownership
    $stmt = $db->prepare("SELECT filename FROM uploads WHERE id = ? AND user_id = ?");
    $stmt->execute([$upload_id, $user['id']]);
    $upload = $stmt->fetch();

    if ($upload) {
        // Delete file
        $filepath = UPLOAD_DIR . $upload['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        // Delete database record
        $stmt = $db->prepare("DELETE FROM uploads WHERE id = ?");
        $stmt->execute([$upload_id]);

        header('Location: dashboard.php?deleted=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PixelClip</title>
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
            max-width: 1200px;
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

        .nav a, .nav button {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .nav a:hover, .nav button:hover {
            color: rgba(255, 255, 255, 1);
        }

        .username {
            color: #667eea;
            font-weight: 500;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .action-buttons {
            margin-bottom: 32px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .upload-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .upload-card:hover {
            transform: translateY(-4px);
            border-color: rgba(102, 126, 234, 0.5);
        }

        .upload-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.02);
        }

        .upload-info {
            padding: 12px;
        }

        .upload-name {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .upload-date {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
        }

        .upload-actions {
            display: flex;
            gap: 8px;
        }

        .upload-actions a {
            flex: 1;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 11px;
            text-align: center;
            transition: all 0.2s;
        }

        .upload-actions a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .upload-actions .delete {
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .upload-actions .delete:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.5);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.5);
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
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

        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="logo">PixelClip</h1>
        <div class="nav">
            <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
            <?php if ($user['is_admin']): ?>
                <a href="admin/">Admin</a>
            <?php endif; ?>
            <a href="index.php">Home</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-message">Upload deleted successfully</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Uploads</div>
                <div class="stat-value"><?php echo number_format($stats['count']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Storage Used</div>
                <div class="stat-value"><?php echo formatBytes($stats['total_size']); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Account Type</div>
                <div class="stat-value" style="font-size: 20px;">
                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="config-download.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3V16M12 16L16 12M12 16L8 12"/>
                    <path d="M3 17V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V17"/>
                </svg>
                Download ShareX Config
            </a>
        </div>

        <?php if (empty($uploads)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
                <p>No uploads yet</p>
                <p style="font-size: 13px; margin-top: 8px;">Download your ShareX config and start uploading!</p>
            </div>
        <?php else: ?>
            <div class="gallery">
                <?php foreach ($uploads as $upload): ?>
                    <div class="upload-card">
                        <img src="i/<?php echo htmlspecialchars($upload['filename']); ?>"
                             alt="<?php echo htmlspecialchars($upload['original_name']); ?>"
                             class="upload-image"
                             loading="lazy">
                        <div class="upload-info">
                            <div class="upload-name" title="<?php echo htmlspecialchars($upload['original_name']); ?>">
                                <?php echo htmlspecialchars($upload['original_name']); ?>
                            </div>
                            <div class="upload-date">
                                <?php echo date('M j, Y g:i A', strtotime($upload['uploaded_at'])); ?>
                            </div>
                            <div class="upload-actions">
                                <a href="i/<?php echo htmlspecialchars($upload['filename']); ?>" target="_blank">View</a>
                                <a href="?delete=<?php echo $upload['id']; ?>"
                                   class="delete"
                                   onclick="return confirm('Delete this upload?')">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
