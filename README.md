# PixelClip

A modern, self-hosted image hosting service with user authentication and ShareX integration. Upload screenshots automatically with personalized API tokens and manage your uploads through a beautiful web dashboard.

## Features

- **User Authentication**: Secure login system with invite code registration
- **Personal Dashboards**: Each user has their own gallery with upload statistics
- **ShareX Integration**: Personalized .sxcu configuration files per user
- **Per-User API Tokens**: Each user gets a unique, secure API token
- **Upload Management**: View, organize, and delete your uploads
- **Live Gallery Refresh**: Update your dashboard gallery instantly without reloading
- **Admin Panel**: Comprehensive admin interface to manage users and invite codes
- **Modern UI**: Glassmorphic dark mode design with responsive layouts
- **Usage Statistics**: Track upload counts and storage usage per user

## Project Structure

```
pixelclip.me/
├── index.php               # Landing page with glassmorphic design
├── config.php              # Database and application configuration
├── database.sql            # Database schema (full)
├── database-tables-only.sql # Database schema (no CREATE DATABASE)
├── login.php               # User login page
├── register.php            # User registration (requires invite code)
├── logout.php              # Logout handler
├── dashboard.php           # User dashboard with gallery
├── config-download.php     # Generate personalized ShareX config
├── change-password.php     # Password reset utility (delete after use)
├── test-db.php             # Database diagnostics (delete after use)
├── api/
│   └── upload.php          # Upload API endpoint with user authentication
├── admin/
│   └── index.php           # Admin panel for user/invite management
└── i/                      # Upload directory (auto-created)
```

## Requirements

- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache, Nginx, etc.)
- PDO MySQL extension
- Write permissions for upload directory

## Installation

### 1. Database Setup

Create a MySQL database and user:

```sql
CREATE DATABASE pixelclip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pixelclip_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON pixelclip.* TO 'pixelclip_user'@'localhost';
FLUSH PRIVILEGES;
```

Import the database schema:

```bash
mysql -u pixelclip_user -p pixelclip < database.sql
```

### 2. Configure Application

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelclip');
define('DB_USER', 'pixelclip_user');
define('DB_PASS', 'your_secure_password');

define('BASE_URL', 'https://pixelclip.me');
```

### 3. Create Upload Directory

```bash
mkdir i
chmod 755 i
```

### 4. First Time Setup

The database schema includes a default admin account:
- **Username:** `admin`
- **Password:** `changeme`

**IMPORTANT:** Change the admin password immediately using one of these methods:

#### Method A: Using the Password Change Script (Easiest)

1. Edit `change-password.php` and set your new password:
   ```php
   $username = 'admin';
   $new_password = 'YourSecurePassword123!';
   ```

2. Visit `https://pixelclip.me/change-password.php` in your browser

3. **Delete the script immediately** for security:
   ```bash
   rm change-password.php
   ```

#### Method B: Manual SQL Update

```php
// Generate a new password hash
php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
```

Then update in MySQL:

```sql
UPDATE users SET password_hash = 'paste_hash_here' WHERE username = 'admin';
```

### 5. Generate Invite Codes

1. Login to the admin panel at `/admin/`
2. Use the "Generate" button to create invite codes
3. Share invite codes with users you want to grant access

## Usage

### For Users

1. **Register**: Go to `/register.php` with an invite code
2. **Login**: Access your dashboard at `/dashboard.php`
3. **Download Config**: Click "Download ShareX Config" to get your personalized .sxcu file
4. **Import to ShareX**: Double-click the downloaded file or import via ShareX settings
5. **Upload**: Take screenshots - they'll automatically upload to your account
6. **Manage**: View and delete your uploads from the dashboard
7. **Refresh**: Tap the “Refresh Gallery” button to pull in new uploads without reloading the page

### For Admins

Access the admin panel at `/admin/` to:
- View system statistics (users, uploads, storage)
- Generate invite codes for new users
- Manage existing users
- Delete users and their uploads
- Monitor unused invite codes

## API Reference

### Upload Endpoint

**POST** `/api/upload.php`

**Headers:**
```
Authorization: Bearer USER_API_TOKEN
```

**Body:**
- `file`: Image file (multipart/form-data)
- Supported formats: jpg, jpeg, png, gif, webp, bmp, svg, ico
- Max file size: 10MB (configurable in config.php)

**Response:**
```json
{
  "success": true,
  "url": "https://pixelclip.me/i/a1b2c3d4e5f6g7h8.png"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Invalid token"
}
```

## Configuration Options

In `config.php`:

```php
// Maximum upload file size (bytes)
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Upload directory
define('UPLOAD_DIR', __DIR__ . '/i/');

// Session security (set to 0 if not using HTTPS in development)
ini_set('session.cookie_secure', 1);
```

## Security Features

- Password hashing with bcrypt
- Per-user API tokens (64 characters)
- Prepared SQL statements to prevent injection
- Session security with httponly and secure flags
- File type validation
- File size limits
- Invite-only registration system
- User upload isolation

## Security Best Practices

1. **Change default admin password immediately**
2. **Use HTTPS in production** (required for secure cookies)
3. **Set strong database passwords**
4. **Regularly review and delete unused invite codes**
5. **Monitor storage usage and set limits**
6. **Keep PHP and MySQL updated**
7. **Set appropriate file permissions** (755 for directories, 644 for files)
8. **Consider adding rate limiting** to prevent abuse

## Database Schema

### Users Table
- Stores user accounts with hashed passwords
- Each user has a unique API token
- Tracks admin status and login times

### Uploads Table
- Links uploads to users
- Stores file metadata (size, type, original name)
- Tracks upload timestamps

### Invite Codes Table
- Manages registration invite codes
- Tracks who created and used each code
- Allows admin to control new registrations

## Troubleshooting

### "Database connection failed"
- Check database credentials in `config.php`
- Ensure MySQL/MariaDB is running
- Verify database user has correct permissions
- Run `test-db.php` for detailed diagnostics

### "Failed to save file"
- Check `i/` directory exists
- Verify web server has write permissions
- Check disk space

### "Invalid token"
- User needs to download fresh ShareX config from dashboard
- Verify API token hasn't been regenerated

### Session issues
- If using HTTPS, set `session.cookie_secure` to 1
- For local development without HTTPS, set it to 0

### Can't login / Forgot password
1. Use `change-password.php` to reset any user's password
2. Edit the file to set username and new password
3. Visit it in your browser
4. Delete the file after use

### Database diagnostics
- Run `test-db.php` to check:
  - Database connection
  - Table existence
  - Admin user status
  - Password hash verification
  - Session configuration
- **Delete the file after use**

## Upgrading from Old Version

If upgrading from the original simple version:

1. Backup your existing `i/` directory
2. Backup `pixelclip.sxcu` if customized
3. Run `database.sql` to create tables
4. Update `config.php` with your settings
5. Images in `i/` won't be linked to users initially
6. Users must re-register and download new configs

## License

This is a personal project. Use at your own risk.

## Credits

Built with PHP, MySQL, and modern CSS (glassmorphic design). No JavaScript frameworks required.
