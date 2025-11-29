# PixelClip - Developer Context

This file contains comprehensive information about the PixelClip project architecture, implementation details, and workflows. It's designed to help new developers or AI assistants quickly understand how the project works.

**⚠️ This file is git-ignored and should NOT be committed to the repository.**

---

## Project Overview

**PixelClip** is a self-hosted image hosting service with user authentication and ShareX integration. Users can register (with invite codes), upload screenshots via ShareX, and manage their uploads through a web dashboard.

### Tech Stack
- **Backend**: PHP 7.4+ (no frameworks, vanilla PHP)
- **Database**: MySQL/MariaDB with PDO
- **Frontend**: Pure HTML/CSS (no JavaScript frameworks)
- **Design**: Glassmorphic dark mode with CSS animations
- **Integration**: ShareX custom uploader (.sxcu files)

### Key Features
- User authentication with invite-only registration
- Per-user API tokens for ShareX
- Personal dashboards with upload galleries
- Admin panel for user/invite management
- Modern glassmorphic UI design

---

## Project Structure

```
pixelclip.me/
├── config.php              # Database config & helper functions
├── database.sql            # Full schema (includes CREATE DATABASE)
├── database-tables-only.sql # Schema without CREATE DATABASE (for shared hosting)
├── test-db.php             # Database diagnostic tool (delete after use)
├── change-password.php     # Password reset utility (delete after use)
├── system_upgrade.php      # One-time script to update database schema (delete after use)
│
├── index.php               # Landing page (public)
├── login.php               # User login page
├── register.php            # User registration (requires invite code)
├── logout.php              # Session destruction
├── dashboard.php           # User dashboard (requires login)
├── config-download.php     # Generate personalized ShareX config
│
├── api/
│   └── upload.php          # Upload endpoint (ShareX integration)
│
├── admin/
│   └── index.php           # Admin panel (requires admin flag)
│
├── i/                      # Upload directory (created automatically) 
│   ├── .htaccess           # Forbids directory listing, handles URL rewriting
│   ├── index.php           # Prevents direct access / directory listing (403 Forbidden)
│   ├── view.php            # Rewrites public image URLs to find files 
                            # in user subdirectories, enforces expiration and view limits.
│   └── [username]/         # User-specific subdirectories
│       └── [uploaded files] # Random filenames: a1b2c3d4.png (e.g., /i/john_doe/a1b2c3d4.png)│
└── pixelclip.sxcu          # Example ShareX config (not used in app)
```

---

## Database Schema

### Configuration
- **Database Name**: `ebitlrcj_pixelclip` (shared hosting format)
- **Database User**: `ebitlrcj`
- **Connection**: PDO with prepared statements (SQL injection protection)

### Tables

#### `users`                                                           

Stores user accounts and authentication data.                          

                                                                       

```sql                                                                 

id              INT PRIMARY KEY AUTO_INCREMENT                         

username        VARCHAR(50) UNIQUE NOT NULL                            

email           VARCHAR(255) UNIQUE NOT NULL                           

password_hash   VARCHAR(255) NOT NULL           # bcrypt hash          

api_token       VARCHAR(64) UNIQUE NOT NULL     # 64-char hex string   

is_admin        BOOLEAN DEFAULT FALSE                                  

storage_quota   BIGINT NULL DEFAULT 1073741824  # User's max storage in bytes (default 1GB), NULL for unlimited

created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP                    

last_login      TIMESTAMP NULL                                         

```                                                                    

                                                                       

**Indexes**: api_token, username, email                                

                                                                       

#### `uploads`                                                         

Tracks all uploaded files and links them to users.                     

                                                                       

```sql                                                                 

id              INT PRIMARY KEY AUTO_INCREMENT                         

user_id         INT NOT NULL                    # FK to users.id       

filename        VARCHAR(255) NOT NULL           # e.g., john_doe/a1b2c3d4.png   

original_name   VARCHAR(255) NOT NULL           # User's original filename                                                                    

file_size       BIGINT NOT NULL                 # Bytes                

mime_type       VARCHAR(100)                    # e.g., image/png      

uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP                    

expires_at      TIMESTAMP NULL                  # Time when upload automatically expires and is deleted

views_limit     INT NULL                        # Max number of views before upload is automatically deleted

views_count     INT DEFAULT 0                   # Current number of views

```

**Foreign Keys**: user_id → users.id (CASCADE DELETE)
**Indexes**: user_id, uploaded_at

#### `invite_codes`
Manages registration invite codes (invite-only system).

```sql
id              INT PRIMARY KEY AUTO_INCREMENT
code            VARCHAR(32) UNIQUE NOT NULL     # Random hex string
created_by      INT NOT NULL                    # FK to users.id (admin who created it)
used_by         INT NULL                        # FK to users.id (user who used it)
created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
used_at         TIMESTAMP NULL
```

**Foreign Keys**:
- created_by → users.id (CASCADE DELETE)
- used_by → users.id (SET NULL on delete)

**Indexes**: code, used_by

---

## Authentication Flow

### Registration Flow
1. User visits `register.php`
2. User enters: username, email, password, password_confirm, invite_code
3. Server validates:
   - All fields present
   - Username 3-50 chars
   - Valid email format
   - Password >= 8 chars
   - Passwords match
   - Invite code exists in DB
   - Invite code not already used
   - Username/email not already taken
4. Server creates user:
   - Hash password with `password_hash($password, PASSWORD_DEFAULT)`
   - Generate API token with `bin2hex(random_bytes(32))` (64 chars)
   - Insert into `users` table
   - Mark invite code as used
5. User redirected to login page

### Login Flow
1. User visits `login.php`
2. User enters: username (or email), password
3. Server:
   - Queries `users` table for username OR email match
   - Verifies password with `password_verify($password, $hash)`
   - Updates `last_login` timestamp
   - Sets session variables: `$_SESSION['user_id']`, `$_SESSION['username']`
4. User redirected to `dashboard.php`

### Session Management
- Sessions started in `config.php` (included in all pages)
- Session settings:
  - `session.cookie_httponly = 1` (XSS protection)
  - `session.use_strict_mode = 1` (session fixation protection)
  - `session.cookie_secure = 1` (HTTPS only - set to 0 for local dev)
- Helper functions in `config.php`:
  - `isLoggedIn()`: Checks if `$_SESSION['user_id']` is set
  - `requireLogin()`: Redirects to login if not authenticated
  - `getCurrentUser()`: Fetches full user data from DB

---

## Upload Flow (ShareX Integration)

### ShareX Configuration
Each user gets a personalized `.sxcu` file from `config-download.php`:

```json
{
  "Version": "18.0.1",
  "Name": "PixelClip - username",
  "DestinationType": "ImageUploader",
  "RequestMethod": "POST",
  "RequestURL": "https://pixelclip.me/api/upload.php",
  "Headers": {
    "Authorization": "Bearer USER_API_TOKEN_HERE"
  },
  "Body": "MultipartFormData",
  "Arguments": {
    "file": "@file"
  },
  "FileFormName": "file",
  "URL": "{json:url}"
}
```

### Upload Process
1. ShareX sends `POST` to `/api/upload.php` with:
   - Header: `Authorization: Bearer <user's api_token>`
   - Body: multipart/form-data with file
2. Server (`api/upload.php`):
   - Extracts token from Authorization header
   - Queries DB for user with matching `api_token`
   - Validates:
     - File was uploaded
     - File size <= MAX_FILE_SIZE (10MB default)
     - File extension is allowed (jpg, jpeg, png, gif, webp, bmp, svg, ico)
      - Generates random filename and creates a user-specific subdirectory
        (e.g., `i/john_doe/a1b2c3d4.png`) if it doesn't exist.           
      - Saves file to the user's subdirectory within `UPLOAD_DIR`.       
      - Inserts record into `uploads` table with the full relative path 
        (e.g., `john_doe/a1b2c3d4.png`) in the `filename` column.        
      - Returns JSON: `{"success": true, "url": "https://pixelclip.me/i/fi
        basename.png"}` (Note: The URL omits the username for privacy, 
        relying on URL rewriting to resolve the actual file path).        
   3. ShareX receives the "clean" URL and copies to clipboard. Requests to 
      this URL are handled by `i/view.php` via `.htaccess` rewrite rules, 
      which locates and serves the file from the correct user subdirectory.

---

## Key Files & Functions

### `config.php`
Central configuration and database connection.

**Constants**:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: Database credentials
- `BASE_URL`: Site URL (used in generated URLs)
- `UPLOAD_DIR`: Base path to the upload directory. User-specific subfolders are created within this directory.
- `MAX_FILE_SIZE`: Maximum upload size in bytes

**Functions**:
- `getDB()`: Returns PDO instance (singleton pattern)
- `isLoggedIn()`: Returns true if user is authenticated
- `requireLogin()`: Redirects to login if not authenticated
- `getCurrentUser()`: Returns current user's data array, including `storage_quota`.
- `generateToken($length)`: Generates random hex string
- `formatBytes($bytes, $precision)`: Formats bytes to human-readable

### `index.php`
Landing page with dynamic content based on login status.

**Features**:
- Starts session to check login status
- Shows different navbar/hero buttons for logged in vs logged out users
- Displays upload count from `/i/` directory
- Responsive glassmorphic design with animated background orbs

### `dashboard.php`
User's personal upload management page.

**Features**:                                                          

- Requires login (`requireLogin()`)                                    

- Displays stats: total uploads, storage used, account type, **storage quota, and remaining space**.            

- **Web Uploader**: Drag & drop and browse functionality for direct browser uploads, with options for **expiration (time or views)**.

- **Lightbox Viewer**: Clickable images open a modal with full image preview, details (filename, size, dates, expiration info), and quick actions (copy URL, download, delete).

- **Bulk Delete**: Checkboxes allow selecting multiple uploads for bulk deletion.

- Gallery view of all user's uploads (ordered by newest first).         

- Delete functionality (validates ownership before deleting).           

- Download ShareX config button.                                        

- AJAX “Refresh Gallery” button (fetches `dashboard.php?refresh=1` and 

swaps gallery HTML).                                                    

- Responsive grid layout.

**Implementation Notes**:
- `renderGallerySection($uploads)` centralizes gallery markup so both the full page and AJAX responses stay in sync.
- `?refresh=1` requests return `{"html": "<div class='gallery'>...</div>"}` and exit early before outputting the page shell.

**Delete Flow**:
1. User clicks "Delete" on an upload
2. JavaScript `confirm()` asks for confirmation
3. If confirmed, sends `?delete=123` (upload ID)
4. Server verifies upload belongs to current user
5. Deletes physical file from `/i/`
6. Deletes record from `uploads` table
7. Redirects with success message

### `admin/index.php`
Admin panel for system management.

**Features**:
- Requires login AND `is_admin = TRUE`
- System stats: total users, uploads, storage, unused invites
- **Invite Code Management**:
  - Generate 1-10 codes at once
  - View all unused codes
  - Delete unused codes
- **User Management**:                                                 
  - View all users with stats, including current storage usage and assigned quota.                                                 
  - **Manage Storage Quotas**: Set storage limits (e.g., 100MB, 1GB, Unlimited) for individual users.
  - Delete users (cascades to uploads and invite codes)                
  - Cannot delete yourself
**Security**:
- Checks `$user['is_admin']` before rendering
- Returns 403 if non-admin tries to access
- User deletion includes physical file cleanup

### `change-password.php`
Utility script for changing user passwords.

**Features**:
- Simple configuration via variables in the file
- Validates password requirements (8+ characters)
- Finds user by username
- Generates bcrypt hash
- Updates database
- Verifies new password works

**Usage**:
1. Edit file to set username and new password
2. Visit in browser or run via CLI
3. **Delete immediately after use**

**Security Note**: This file should be deleted after use as it can change any user's password.

### `api/upload.php`                                                   
Upload endpoint for both ShareX and web interface.                                                
                                                                       
**Process**:                                                           
1. Authenticate user via session (for web uploads) OR Authorization header (for ShareX).                                         
2. Extract Bearer token if using token auth.                                                
3. Find user by `api_token` or session `user_id`, fetching `storage_quota`.                                            
4. Validate file upload (presence, size).
5. **Check user's storage quota**: If file exceeds quota, return 403.                                                
6. Validate file type (extension).
7. Handle optional expiration parameters (`expires_in` seconds or `views_limit` from `$_POST`).
8. Generate unique filename and ensure user-specific subdirectory exists.
9. Move uploaded file.                                                  
10. Record in database, including `expires_at` and `views_limit`.                                                  
11. Return JSON response with "clean" URL (basename).
**Error Responses**:
- 401: Missing/invalid token
- 400: No file, file too large, invalid file type
- 500: Failed to save file or database error

---

## Design System

### Color Palette
```css
Background:        #0a0a0a (near black)
Text Primary:      #ffffff (white)
Text Secondary:    rgba(255, 255, 255, 0.6)
Gradient Primary:  #667eea → #764ba2 (purple)
Gradient Secondary: #f093fb → #f5576c (pink)
Gradient Accent:   #4facfe → #00f2fe (blue)
```

### Glassmorphic Style
```css
background: rgba(255, 255, 255, 0.05);
backdrop-filter: blur(20px);
border-radius: 16px;
border: 1px solid rgba(255, 255, 255, 0.1);
```

### Gradient Text
```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
```

### Animated Background Orbs
- 3 large gradient orbs with blur(100px)
- 20s float animation with scale and translate
- Positioned: top-right, bottom-left, center
- z-index: 0 (behind all content)

---

## Security Considerations

### Password Security
- Passwords hashed with `password_hash()` using bcrypt (PASSWORD_DEFAULT)
- Password verification uses `password_verify()` (constant-time comparison)
- Minimum 8 characters enforced

### SQL Injection Protection
- All queries use PDO prepared statements
- No string concatenation in SQL queries
- Example: `$stmt->execute([$user_id])`

### Authentication
- Per-user API tokens (64 random hex characters)
- Tokens stored in database and sent via Authorization header
- Session-based authentication for web interface

### File Upload Security
- File type whitelist (only image extensions)
- File size limit (10MB default)
- Random filenames prevent directory traversal                         
- MIME type validation with `mime_content_type()`                      
- **User-specific subdirectories**: Files are stored in `i/<username>/` to better organize and isolate user uploads.
- **Directory Listing Prevention**: `.htaccess` (Options -Indexes) and `i/index.php` (403 Forbidden) are used to prevent unauthorized browsing of the upload directories.
- **URL Privacy**: Public URLs for uploaded files (`/i/a1b2c3d4.png`) h
ide the user's subdirectory, relying on `i/view.php` and URL rewriting 
for access.                                                            
- **Storage Quotas**: Admins can set storage limits per user; uploads are rejected if the quota is exceeded.
- **Expirable Uploads**: Files can be set to expire after a certain time (`expires_at`) or a number of views (`views_limit`), enhancing temporary sharing security.
### Session Security
- HttpOnly cookies (JavaScript cannot access)
- Secure cookies (HTTPS only in production)
- Strict session mode (prevents fixation attacks)

### Access Control
- User can only view/delete their own uploads
- Admin panel checks `is_admin` flag
- Invite-only registration prevents spam

### Known Security Considerations
1. **Session Cookie Secure Flag**: Set to 0 for local development without HTTPS
2. **File Type Validation**: Basic extension check - could be enhanced with image validation
3. **Rate Limiting**: Not implemented - consider adding for production
4. **CSRF Protection**: Not implemented - consider adding for admin actions

---

## Configuration & Deployment

### Initial Setup

1. **Database Setup**:
   ```bash
   mysql -u ebitlrcj -p ebitlrcj_pixelclip < database-tables-only.sql
   ```

2. **Configure `config.php`**:
   - Update `DB_PASS` with actual password
   - Set `BASE_URL` to your domain
   - Adjust `session.cookie_secure` (0 for HTTP, 1 for HTTPS)

3. **Create Upload Directory**:
   ```bash
   mkdir i
   chmod 755 i
   ```

4. **Default Admin Account**:
   - Username: `admin`
   - Password: `changeme`
   - **IMPORTANT**: Change password immediately using `change-password.php`!

5. **Generate Invite Codes**:
   - Login as admin → Admin Panel → Generate codes

### Environment-Specific Settings

**Local Development** (no HTTPS):
```php
ini_set('session.cookie_secure', 0);
```

**Production** (with HTTPS):
```php
ini_set('session.cookie_secure', 1);
```

### File Permissions
- PHP files: 644 (owner read/write, group/others read)
- Directories: 755 (owner rwx, group/others rx)
- Upload directory (`/i/`): 755 (web server needs write access)

---

## Common Development Tasks

### Add a New User Manually (SQL)
```sql
INSERT INTO users (username, email, password_hash, api_token, is_admin) VALUES (
    'newuser',
    'newuser@example.com',
    -- Generate hash with: echo password_hash('password123', PASSWORD_DEFAULT);
    '$2y$10$...',
    -- Generate token with: echo bin2hex(random_bytes(32));
    'a1b2c3d4...',
    0  -- or 1 for admin
);
```

### Generate Invite Code Manually (SQL)
```sql
INSERT INTO invite_codes (code, created_by) VALUES (
    -- Generate code with: echo bin2hex(random_bytes(16));
    'a1b2c3d4e5f6g7h8',
    1  -- admin user ID
);
```

### Reset User Password

#### Method A: Using change-password.php (Easiest)
1. Edit the script:
   ```php
   $username = 'admin';  // or any username
   $new_password = 'NewSecurePassword123!';
   ```
2. Visit `https://pixelclip.me/change-password.php`
3. Delete the file: `rm change-password.php`

#### Method B: Manual SQL
```sql
-- Generate new hash with PHP:
php -r "echo password_hash('newpassword', PASSWORD_DEFAULT);"

-- Update in database:
UPDATE users SET password_hash = '$2y$10$...' WHERE username = 'admin';
```

#### Method C: Command Line One-Liner
```bash
php -r "require 'config.php'; \$db = getDB(); \$hash = password_hash('NewPassword', PASSWORD_DEFAULT); \$stmt = \$db->prepare('UPDATE users SET password_hash = ? WHERE username = ?'); \$stmt->execute([\$hash, 'admin']); echo 'Password changed!\n';"
```

### Check Upload Statistics
```sql
-- Total uploads per user
SELECT u.username, COUNT(up.id) as upload_count, SUM(up.file_size) as total_bytes
FROM users u
LEFT JOIN uploads up ON u.id = up.user_id
GROUP BY u.id;

-- Recent uploads
SELECT u.username, up.filename, up.uploaded_at
FROM uploads up
JOIN users u ON up.user_id = u.id
ORDER BY up.uploaded_at DESC
LIMIT 10;
```

### Add New File Type
In `api/upload.php`, update the allowed extensions array:
```php
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'webm'];
```

### Change Max Upload Size
In `config.php`:
```php
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
```

Also update `php.ini`:
```ini
upload_max_filesize = 50M
post_max_size = 50M
```

---

## Troubleshooting

### "Database connection failed"
- Check `config.php` credentials
- Verify MySQL is running: `systemctl status mysql`
- Test connection: `mysql -u ebitlrcj -p ebitlrcj_pixelclip`

### "Failed to save file"
- Check `/i/` directory exists (and the user's subdirectory within `i/`)                                         
- Check permissions: `chmod 755 i` (and ensure the web server has write permissions to create subdirectories within `i/`)- Check disk space: `
df -h`                                                                 
- Check if user's storage quota is full.
- Check PHP error log

### Login Issues / Forgot Password
- Run `test-db.php` to diagnose password hash issues
- Use `change-password.php` to reset password:
  1. Edit the file with username and new password
  2. Visit it in browser
  3. Delete the file
- Check password hash in database
- Verify session.cookie_secure setting matches HTTPS usage
- Clear browser cookies/session

### Upload Issues (ShareX)
- Verify API token in config matches database
- Check file size under limit
- Check allowed file extensions
- Test with `curl`:
  ```bash
  curl -X POST https://pixelclip.me/api/upload.php \
    -H "Authorization: Bearer YOUR_TOKEN" \
    -F "file=@test.png"
  ```

### Session Expires Immediately
- Check `session.cookie_secure` setting
- If testing locally without HTTPS, set to 0
- Clear browser cookies

---

## API Reference

### Upload Endpoint

**POST** `/api/upload.php`

**Headers**:
```
Authorization: Bearer {user_api_token}
Content-Type: multipart/form-data
```

**Body**:                                                              
```                                                                    
file: (binary image data)                                              
expires_in: (optional) integer, number of seconds until file expires.
views_limit: (optional) integer, max number of views before file expires.
```
**Success Response (200)**:
```json
{
  "success": true,
  "url": "https://pixelclip.me/i/a1b2c3d4e5f6g7h8.png"
}
```

**Error Responses**:

401 Unauthorized:
```json
{
  "success": false,
  "error": "Missing auth header"
}
```
```json
{
  "success": false,
  "error": "Invalid token"
}
```

400 Bad Request:
```json
{
  "success": false,
  "error": "No file uploaded"
}
```
```json
{
  "success": false,
  "error": "File too large (max 10.00 MB)"
}
```
```json
{
  "success": false,
  "error": "Invalid file type"
}
```

500 Internal Server Error:
```json
{
  "success": false,
  "error": "Failed to save file"
}
```
```json
{
  "success": false,
  "error": "Failed to record upload"
}
```

---

## Future Enhancements

### Potential Features                                                 
- [ ] Image thumbnails for gallery view                                
- [ ] Search/filter uploads                                            
- [ ] Upload history/timeline view                                     
- [ ] Custom upload URLs/slugs                                         
- [ ] Public/private upload toggle                                     
- [ ] Image preview before upload                                      
- [ ] Image optimization/compression                                   
- [ ] Multi-file upload support                                        
- [ ] API rate limiting                                                
- [ ] 2FA authentication                                               
- [ ] Email notifications                                              
- [ ] Activity logs                                                    
- [ ] Dark/light theme toggle                                          
### Performance Optimizations
- [ ] Image thumbnail generation
- [ ] CDN integration
- [ ] Database query caching
- [ ] Lazy loading for galleries
- [ ] Image lazy loading
- [ ] Gzip compression
- [ ] Browser caching headers

### Security Enhancements
- [ ] CSRF token implementation
- [ ] Rate limiting (per IP, per user)
- [ ] Brute force protection
- [ ] File content validation (beyond extension)
- [ ] Virus scanning integration
- [ ] Content Security Policy headers
- [ ] XSS protection headers

---

## Coding Conventions

### PHP Style
- Use `<?php` tags (not short tags)
- Include `require_once` at top of files
- No closing `?>` tag at end of PHP-only files
- PSR-12 formatting (mostly)
- PDO for all database queries
- Prepared statements always

### HTML/CSS Style
- Indentation: 4 spaces
- Use semantic HTML5 elements
- Mobile-first responsive design
- CSS in `<style>` tags (no external files)
- Inline SVG for icons

### Security Practices
- Always validate user input
- Escape output with `htmlspecialchars()`
- Use prepared statements
- Never trust client data
- Validate on server side

### Database Practices
- Use foreign keys with CASCADE/SET NULL
- Add indexes for frequently queried columns
- Use transactions for multi-step operations
- Always handle PDO exceptions

---

## Testing Checklist

### User Registration
- [ ] Valid registration with invite code
- [ ] Duplicate username rejected
- [ ] Duplicate email rejected
- [ ] Invalid invite code rejected
- [ ] Used invite code rejected
- [ ] Password mismatch rejected
- [ ] Short password rejected

### User Login
- [ ] Login with username
- [ ] Login with email
- [ ] Wrong password rejected
- [ ] Non-existent user rejected
- [ ] Session persists across pages
- [ ] Logout clears session

### Password Management
- [ ] change-password.php successfully changes password
- [ ] New password works for login
- [ ] Password under 8 chars rejected
- [ ] Non-existent username shows error

### File Upload (ShareX & Web)

- [ ] Upload PNG image

- [ ] Upload JPG image

- [ ] Large file rejected

- [ ] Invalid file type rejected

- [ ] Invalid token rejected

- [ ] Missing file rejected

- [ ] URL returned and accessible

- [ ] Uploaded file accessible via "clean" URL (without username in path)

- [ ] Direct access to user subdirectory in `i/` is forbidden (403 or blank page)

- [ ] Upload with expiration time set works (file expires and is deleted)

- [ ] Upload with view limit set works (file expires after N views)

- [ ] Upload rejected if user exceeds storage quota



### Dashboard

- [ ] Stats display correctly (including storage quota and remaining space)

- [ ] Web uploader (drag & drop/browse) works

- [ ] Web uploader respects file size limits and types

- [ ] Web uploader sends expiration parameters correctly

- [ ] Lightbox opens correctly for images

- [ ] Lightbox displays correct image details and actions (copy URL, download, delete)

- [ ] Bulk delete: select multiple and delete works

- [ ] Gallery shows user's uploads

- [ ] Delete removes file and record

- [ ] Cannot delete other user's files

- [ ] ShareX config download works

- [ ] Refresh button updates the gallery without full page reload



### Admin Panel

- [ ] Non-admin cannot access

- [ ] Invite code generation works

- [ ] User list displays correctly (including current storage and quota)

- [ ] User storage quota can be updated

- [ ] User deletion works (cascades and cleans up files)

- [ ] Cannot delete self

- [ ] Stats accurate

---

## Contact & Support

This is a self-hosted personal project. For issues or questions:
- Check the README.md for setup instructions
- Review this CONTEXT.md for implementation details
- Check GitHub issues: https://github.com/iCallum/pixelclip/issues

---

**Last Updated**: 2025-11-29                                           
**Version**: 2.1 (Enhanced Dashboard and Security)
