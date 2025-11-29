<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$db = getDB();

// Handle bulk delete request
if (isset($_POST['bulk_delete'])) {
    verifyCSRFToken($_POST['csrf_token'] ?? '');
    $upload_ids = $_POST['upload_ids'] ?? [];
    if (!empty($upload_ids) && is_array($upload_ids)) {
        foreach ($upload_ids as $upload_id) {
            $upload_id = (int)$upload_id;
            // Verify ownership
            $stmt = $db->prepare("SELECT filename FROM uploads WHERE id = ? AND user_id = ?");
            $stmt->execute([$upload_id, $user['id']]);
            $upload = $stmt->fetch();

            if ($upload) {
                $filepath = UPLOAD_DIR . $upload['filename'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload_id]);
            }
        }
        header('Location: dashboard.php?deleted_count=' . count($upload_ids));
        exit;
    }
}

// Handle single delete request (existing logic, updated for relative path)
if (isset($_GET['delete'])) {
    verifyCSRFToken($_GET['csrf_token'] ?? '');
    $upload_id = (int)$_GET['delete'];

    // Verify ownership
    $stmt = $db->prepare("SELECT filename FROM uploads WHERE id = ? AND user_id = ?");
    $stmt->execute([$upload_id, $user['id']]);
    $upload = $stmt->fetch();

    if ($upload) {
        $filepath = UPLOAD_DIR . $upload['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$upload_id]);

        header('Location: dashboard.php?deleted=1');
        exit;
    }
}

// Get user stats
$stmt = $db->prepare("SELECT COUNT(*) as count, COALESCE(SUM(file_size), 0) as total_size FROM uploads WHERE user_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

// Calculate remaining quota
$remaining_quota = 'Unlimited';
if (isset($user['storage_quota']) && $user['storage_quota'] > 0) {
    $remaining = $user['storage_quota'] - $stats['total_size'];
    $remaining_quota = formatBytes(max(0, $remaining));
}

// Get uploads with expiration info
$stmt = $db->prepare("SELECT id, filename, original_name, file_size, mime_type, uploaded_at, expires_at, views_limit, views_count FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user['id']]);
$uploads = $stmt->fetchAll();

function renderGallerySection(array $uploads): string {
    ob_start();
    ?>
    <?php if (empty($uploads)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            <p>No uploads yet</p>
            <p style="font-size: 13px; margin-top: 8px;">Download your ShareX config or use the uploader below!</p>
        </div>
    <?php else: ?>
        <form id="gallery-form" method="POST" action="dashboard.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="gallery">
                <?php foreach ($uploads as $upload):
                    $public_url = BASE_URL . "/i/" . basename($upload['filename']);
                    $expiry_info = 'Never';
                    if ($upload['expires_at']) {
                        $expiry_info = 'Expires ' . date('M j, Y H:i', strtotime($upload['expires_at']));
                    }
                    if ($upload['views_limit'] !== null) {
                        $views_left = max(0, $upload['views_limit'] - $upload['views_count']);
                        $expiry_info .= ($expiry_info === 'Never' ? '' : ' | ') . "{$views_left} views left";
                        if ($views_left === 0) $expiry_info = 'Expired (view limit)';
                    }
                ?>
                    <div class="upload-card">
                        <input type="checkbox" name="upload_ids[]" value="<?php echo $upload['id']; ?>" class="upload-checkbox">
                        <img src="<?php echo htmlspecialchars($public_url); ?>"
                             alt="<?php echo htmlspecialchars($upload['original_name']); ?>"
                             class="upload-image gallery-item"
                             loading="lazy"
                             data-id="<?php echo $upload['id']; ?>"
                             data-filename="<?php echo htmlspecialchars(basename($upload['filename'])); ?>"
                             data-original-name="<?php echo htmlspecialchars($upload['original_name']); ?>"
                             data-file-size="<?php echo formatBytes($upload['file_size']); ?>"
                             data-uploaded-at="<?php echo date('M j, Y H:i', strtotime($upload['uploaded_at'])); ?>"
                             data-mime-type="<?php echo htmlspecialchars($upload['mime_type']); ?>"
                             data-public-url="<?php echo htmlspecialchars($public_url); ?>"
                             data-expires-at="<?php echo htmlspecialchars($expiry_info); ?>"
                             >
                        <div class="upload-info">
                            <div class="upload-name" title="<?php echo htmlspecialchars($upload['original_name']); ?>">
                                <?php echo htmlspecialchars($upload['original_name']); ?>
                            </div>
                            <div class="upload-date">
                                <?php echo date('M j, Y g:i A', strtotime($upload['uploaded_at'])); ?>
                            </div>
                            <div class="upload-actions">
                                <a href="<?php echo htmlspecialchars($public_url); ?>" target="_blank">View</a>
                                <a href="?delete=<?php echo $upload['id']; ?>&csrf_token=<?php echo generateCSRFToken(); ?>"
                                   class="delete"
                                   onclick="return confirm('Delete this upload?')">Delete</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

if (isset($_GET['refresh']) && $_GET['refresh'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'html' => renderGallerySection($uploads),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PixelClip</title>
    <style>
        /* Existing CSS (omitted for brevity, assume it's there) */
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
            align-items: center;
            gap: 0;
            flex-wrap: wrap;
        }

        .nav > * {
            display: flex;
            align-items: center;
            position: relative;
        }

        .nav > *:not(:last-child) {
            margin-right: 16px;
            padding-right: 16px;
        }

        .nav > *:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 18px;
            background: rgba(255, 255, 255, 0.2);
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

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.85);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
            .nav > *:not(:last-child) {
                margin-right: 12px;
                padding-right: 12px;
            }

            .nav > *:not(:last-child)::after {
                height: 14px;
            }

            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 12px;
            }
        }

        /* New CSS for drag-drop area */
        .drop-zone {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .drop-zone.dragover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #667eea;
        }

        .drop-zone p {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 15px;
        }

        .drop-zone .btn {
            margin-top: 15px;
        }

        .expiration-options {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .expiration-options label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.7);
        }

        .expiration-options input[type="radio"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            width: 16px;
            height: 16px;
            transition: all 0.2s ease;
            position: relative;
        }

        .expiration-options input[type="radio"]:checked {
            border-color: #667eea;
            background-color: #667eea;
        }

        .expiration-options input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #0a0a0a;
        }

        .expiration-options input[type="number"] {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: white;
            padding: 6px 10px;
            width: 70px;
        }

        /* Lightbox Modal */
        .lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
        }

        .lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .lightbox-content {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            max-width: 90%;
            max-height: 90%;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .lightbox-image-wrapper {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 0; /* Allow image to shrink */
        }

        .lightbox-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }

        .lightbox-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }

        .lightbox-details strong {
            color: white;
            display: block;
            margin-bottom: 3px;
        }

        .lightbox-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .lightbox-actions .btn {
            padding: 8px 15px;
            font-size: 13px;
        }

        .lightbox-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            color: white;
            cursor: pointer;
            z-index: 1001;
        }
        
        .storage-status {
            font-size: 14px;
            color: rgba(255,255,255,0.7);
            margin-top: 10px;
        }
        .storage-status strong {
            color: #667eea;
        }
        
        /* Bulk Delete */
        .bulk-actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .upload-card {
            position: relative; /* For checkbox positioning */
        }
        
        .upload-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
            width: 20px;
            height: 20px;
            accent-color: #667eea;
            cursor: pointer;
        }
        
        /* Hide checkbox initially, show on gallery hover or checked */
        .upload-checkbox {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .upload-card:hover .upload-checkbox,
        .upload-checkbox:checked {
            opacity: 1;
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
        <?php if (isset($_GET['deleted_count'])): ?>
            <div class="success-message"><?php echo (int)$_GET['deleted_count']; ?> uploads deleted successfully</div>
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
                <div class="stat-label">Storage Quota</div>
                <div class="stat-value" style="font-size: 20px;">
                    <?php echo ($user['storage_quota'] > 0) ? formatBytes($user['storage_quota']) : 'Unlimited'; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Remaining Space</div>
                <div class="stat-value" style="font-size: 20px;">
                    <?php echo $remaining_quota; ?>
                </div>
            </div>
        </div>

        <div class="action-buttons">
            <a href="config-download.php" class="btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 3V16M12 16L16 12M12 16L8 12"/>
                    <path d="M3 17V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V17"/>
                </svg>
                ShareX Config
            </a>
            <button type="button" class="btn btn-outline" id="refresh-gallery">
                Refresh Gallery
            </button>
        </div>

        <!-- Drag & Drop Uploader -->
        <div class="drop-zone" id="dropZone">
            <p>Drag & drop files here to upload</p>
            <input type="file" id="fileInput" multiple accept="image/*" style="display: none;">
            <button type="button" class="btn btn-outline" onclick="document.getElementById('fileInput').click()">Browse Files</button>
            
            <div class="expiration-options">
                <label><input type="radio" name="expiration" value="none" checked> No Expiration</label>
                <label><input type="radio" name="expiration" value="time"> Expires in
                    <input type="number" id="expireTime" value="60" min="1"> Minutes
                </label>
                <label><input type="radio" name="expiration" value="views"> Expires after
                    <input type="number" id="viewLimit" value="1" min="1"> Views
                </label>
            </div>
            <div id="uploadStatus" class="storage-status"></div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <button type="button" class="btn delete" id="bulkDeleteBtn" disabled>Delete Selected</button>
        </div>

        <div id="gallery-root">
            <?php echo renderGallerySection($uploads); ?>
        </div>
    </div>

    <!-- Lightbox Modal -->
    <div class="lightbox-overlay" id="lightboxOverlay">
        <button class="lightbox-close" id="lightboxClose">&times;</button>
        <div class="lightbox-content">
            <div class="lightbox-image-wrapper">
                <img src="" alt="Full Image" class="lightbox-image" id="lightboxImage">
            </div>
            <div class="lightbox-details">
                <div><strong>Filename:</strong> <span id="lbFilename"></span></div>
                <div><strong>Original Name:</strong> <span id="lbOriginalName"></span></div>
                <div><strong>Size:</strong> <span id="lbFileSize"></span></div>
                <div><strong>Uploaded:</strong> <span id="lbUploadedAt"></span></div>
                <div><strong>Type:</strong> <span id="lbMimeType"></span></div>
                <div><strong>Expires:</strong> <span id="lbExpiresAt"></span></div>
            </div>
            <div class="lightbox-actions">
                <button class="btn btn-outline" id="lbCopyUrl">Copy URL</button>
                <a href="" class="btn btn-outline" id="lbDownload" download>Download</a>
                <button class="btn delete" id="lbDelete">Delete</button>
            </div>
        </div>
    </div>

    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const CSRF_TOKEN = '<?php echo generateCSRFToken(); ?>';
        const MAX_FILE_SIZE = <?php echo MAX_FILE_SIZE; ?>;
        const USER_API_TOKEN = '<?php echo htmlspecialchars($user["api_token"]); ?>'; // Assuming we can use this for web upload, or set it to null for session uploads

        // --- Gallery Refresh (Existing) ---
        (function () {
            const refreshBtn = document.getElementById('refresh-gallery');
            const galleryRoot = document.getElementById('gallery-root');
            if (!refreshBtn || !galleryRoot) {
                return;
            }

            const defaultLabel = refreshBtn.textContent.trim();

            async function refreshGallery() {
                refreshBtn.disabled = true;
                refreshBtn.textContent = 'Refreshing...';

                try {
                    const response = await fetch('dashboard.php?refresh=1', {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    const data = await response.json();
                    galleryRoot.innerHTML = data.html;
                } catch (error) {
                    alert('Failed to refresh gallery. Please try again.');
                } finally {
                    refreshBtn.disabled = false;
                    refreshBtn.textContent = defaultLabel;
                }
            }

            refreshBtn.addEventListener('click', refreshGallery);
        })();

        // --- Drag & Drop Uploader ---
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const uploadStatus = document.getElementById('uploadStatus');
        const expireTimeInput = document.getElementById('expireTime');
        const viewLimitInput = document.getElementById('viewLimit');
        const expirationRadios = document.querySelectorAll('input[name="expiration"]');

        if (dropZone && fileInput) {
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                handleFiles(files);
            });

            fileInput.addEventListener('change', (e) => {
                const files = e.target.files;
                handleFiles(files);
            });

            async function handleFiles(files) {
                if (files.length === 0) return;

                for (const file of files) {
                    if (!file.type.startsWith('image/')) {
                        uploadStatus.textContent = `Skipping non-image file: ${file.name}`;
                        uploadStatus.style.color = '#fca5a5';
                        continue;
                    }
                    if (file.size > MAX_FILE_SIZE) {
                        uploadStatus.textContent = `File too large: ${file.name} (${formatBytes(file.size)})`;
                        uploadStatus.style.color = '#fca5a5';
                        continue;
                    }
                    
                    uploadStatus.textContent = `Uploading ${file.name}...`;
                    uploadStatus.style.color = '#86efac';

                    const formData = new FormData();
                    formData.append('file', file);
                    
                    let expires_in = null;
                    let views_limit = null;

                    const selectedExpiration = document.querySelector('input[name="expiration"]:checked').value;
                    if (selectedExpiration === 'time') {
                        expires_in = parseInt(expireTimeInput.value) * 60; // Convert minutes to seconds
                        if (isNaN(expires_in) || expires_in <= 0) expires_in = null;
                    } else if (selectedExpiration === 'views') {
                        views_limit = parseInt(viewLimitInput.value);
                        if (isNaN(views_limit) || views_limit <= 0) views_limit = null;
                    }

                    if (expires_in !== null) formData.append('expires_in', expires_in);
                    if (views_limit !== null) formData.append('views_limit', views_limit);

                    try {
                        const response = await fetch('api/upload.php', {
                            method: 'POST',
                            headers: {
                                // For session-based uploads, no need for Authorization header if session is active
                                // If API token is preferred/required, can send:
                                // 'Authorization': `Bearer ${USER_API_TOKEN}`
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            uploadStatus.textContent = `${file.name} uploaded successfully!`;
                            uploadStatus.style.color = '#86efac';
                            // Refresh gallery after successful upload
                            refreshGallerySection();
                        } else {
                            uploadStatus.textContent = `Failed to upload ${file.name}: ${result.error || 'Unknown error'}`;
                            uploadStatus.style.color = '#fca5a5';
                        }
                    } catch (error) {
                        uploadStatus.textContent = `Error uploading ${file.name}: ${error.message}`;
                        uploadStatus.style.color = '#fca5a5';
                    }
                }
            }

            function formatBytes(bytes, precision = 2) {
                const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                bytes = Math.max(bytes, 0);
                const pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
                const p = Math.min(pow, units.length - 1);
                return `${(bytes / Math.pow(1024, p)).toFixed(precision)} ${units[p]}`;
            }

            async function refreshGallerySection() {
                try {
                    const response = await fetch('dashboard.php?refresh=1', {
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const data = await response.json();
                    document.getElementById('gallery-root').innerHTML = data.html;
                    attachLightboxListeners(); // Re-attach listeners for new elements
                    attachCheckboxListeners(); // Re-attach listeners for new checkboxes
                } catch (error) {
                    console.error('Failed to refresh gallery:', error);
                }
            }
            
            // Initial attachment for existing elements
            attachLightboxListeners();
            attachCheckboxListeners();
        }

        // --- Lightbox Modal ---
        const lightboxOverlay = document.getElementById('lightboxOverlay');
        const lightboxClose = document.getElementById('lightboxClose');
        const lightboxImage = document.getElementById('lightboxImage');
        const lbFilename = document.getElementById('lbFilename');
        const lbOriginalName = document.getElementById('lbOriginalName');
        const lbFileSize = document.getElementById('lbFileSize');
        const lbUploadedAt = document.getElementById('lbUploadedAt');
        const lbMimeType = document.getElementById('lbMimeType');
        const lbExpiresAt = document.getElementById('lbExpiresAt');
        const lbCopyUrl = document.getElementById('lbCopyUrl');
        const lbDownload = document.getElementById('lbDownload');
        const lbDelete = document.getElementById('lbDelete');
        let currentUploadId = null;

        function openLightbox(upload) {
            lightboxImage.src = upload.dataset.publicUrl;
            lbFilename.textContent = upload.dataset.filename;
            lbOriginalName.textContent = upload.dataset.originalName;
            lbFileSize.textContent = upload.dataset.fileSize;
            lbUploadedAt.textContent = upload.dataset.uploadedAt;
            lbMimeType.textContent = upload.dataset.mimeType;
            lbExpiresAt.textContent = upload.dataset.expiresAt;
            lbDownload.href = upload.dataset.publicUrl;
            currentUploadId = upload.dataset.id;
            
            lightboxOverlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling background
        }

        function closeLightbox() {
            lightboxOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        lightboxClose.addEventListener('click', closeLightbox);
        lightboxOverlay.addEventListener('click', (e) => {
            if (e.target === lightboxOverlay) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && lightboxOverlay.classList.contains('active')) {
                closeLightbox();
            }
        });

        lbCopyUrl.addEventListener('click', () => {
            navigator.clipboard.writeText(lightboxImage.src).then(() => {
                alert('URL copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy URL:', err);
            });
        });

        lbDelete.addEventListener('click', async () => {
            if (confirm('Are you sure you want to delete this upload?')) {
                try {
                    // Use standard delete method (GET request with ?delete=ID)
                    const response = await fetch(`dashboard.php?delete=${currentUploadId}&csrf_token=${CSRF_TOKEN}`);
                    if (response.ok) {
                        alert('Upload deleted!');
                        closeLightbox();
                        refreshGallerySection();
                    } else {
                        alert('Failed to delete upload.');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    alert('Error deleting upload.');
                }
            }
        });

        function attachLightboxListeners() {
            document.querySelectorAll('.gallery-item').forEach(item => {
                item.addEventListener('click', () => openLightbox(item));
            });
        }

        // --- Bulk Delete ---
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const galleryForm = document.getElementById('gallery-form');

        function attachCheckboxListeners() {
            const checkboxes = document.querySelectorAll('.upload-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkDeleteButton);
            });
            updateBulkDeleteButton(); // Initial check
        }

        function updateBulkDeleteButton() {
            const checkedCount = document.querySelectorAll('.upload-checkbox:checked').length;
            bulkDeleteBtn.disabled = checkedCount === 0;
            bulkDeleteBtn.textContent = checkedCount > 0 ? `Delete Selected (${checkedCount})` : 'Delete Selected';
        }
        
        if (bulkDeleteBtn && galleryForm) {
            bulkDeleteBtn.addEventListener('click', () => {
                const checkedCount = document.querySelectorAll('.upload-checkbox:checked').length;
                if (checkedCount > 0 && confirm(`Delete ${checkedCount} selected uploads?`)) {
                    // Create a hidden input for bulk_delete action
                    const bulkDeleteInput = document.createElement('input');
                    bulkDeleteInput.type = 'hidden';
                    bulkDeleteInput.name = 'bulk_delete';
                    bulkDeleteInput.value = '1';
                    galleryForm.appendChild(bulkDeleteInput);
                    
                    galleryForm.submit();
                }
            });
        }
    </script>
</body>
</html>