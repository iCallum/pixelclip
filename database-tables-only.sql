-- PixelClip Database Schema - Tables Only
-- Use this if your database already exists and you just need to create tables
-- Import with: mysql -u USERNAME -p DATABASE_NAME < database-tables-only.sql

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    api_token VARCHAR(64) NOT NULL UNIQUE,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_api_token (api_token),
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uploads table
CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size BIGINT NOT NULL,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invite codes table
CREATE TABLE IF NOT EXISTS invite_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    used_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_used_by (used_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create default admin user (username: admin, password: changeme)
-- You should change this password immediately after setup!
INSERT INTO users (username, email, password_hash, api_token, is_admin) VALUES (
    'admin',
    'admin@pixelclip.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: changeme
    CONCAT(MD5(RAND()), MD5(RAND())),
    TRUE
) ON DUPLICATE KEY UPDATE id=id;
