# PixelClip

A lightweight, self-hosted image hosting service designed for use with ShareX. Upload screenshots and images automatically with a simple, secure API.

## Features

- **ShareX Integration**: Drop-in .sxcu configuration file for instant setup
- **Token-based Authentication**: Secure uploads with Bearer token authorization
- **Random Filenames**: Automatically generated unique filenames for privacy
- **Admin Panel**: Simple web interface to view and manage uploaded images
- **Lightweight**: Pure PHP with no dependencies or database required

## Project Structure

```
pixelclip.me/
├── index.html              # Landing page
├── pixelclip.sxcu          # ShareX configuration file
├── api/
│   └── upload.php          # Upload API endpoint
├── admin/
│   └── index.php           # Admin panel for managing uploads
└── i/                      # Upload directory (auto-created)
```

## Setup

### 1. Configure Upload Token

Edit `api/upload.php` and set a secure random token:

```php
$UPLOAD_TOKEN = "CHANGE_THIS_TO_A_LONG_RANDOM_SECRET";
```

### 2. Update Base URL

In `api/upload.php`, set your domain:

```php
$BASE_URL = "https://pixelclip.me/i/";
```

### 3. Create Upload Directory

```bash
mkdir i
chmod 755 i
```

### 4. Configure Admin Credentials

Edit `admin/index.php` and change the default credentials:

```php
$USER = "admin";
$PASS = "changeme";
```

### 5. Update ShareX Configuration

Edit `pixelclip.sxcu` with your domain and upload token:

```json
{
  "RequestURL": "https://pixelclip.me/api/upload.php",
  "Headers": {
    "Authorization": "Bearer YOUR_TOKEN_HERE"
  }
}
```

### 6. Import to ShareX

1. Double-click `pixelclip.sxcu` or import via ShareX Destinations settings
2. ShareX will automatically use this configuration for uploads
3. Take a screenshot and it will be uploaded automatically

## Usage

### Uploading via ShareX

Once configured, simply take a screenshot with ShareX and the image will be automatically uploaded to your PixelClip instance. The URL will be copied to your clipboard.

### Admin Panel

Access the admin panel at `https://pixelclip.me/admin/` to:
- View all uploaded images
- Delete images you no longer need

Default credentials:
- Username: `admin`
- Password: `changeme` (change this!)

## API Reference

### Upload Endpoint

**POST** `/api/upload.php`

**Headers:**
```
Authorization: Bearer YOUR_TOKEN_HERE
```

**Body:**
- `file`: Image file (multipart/form-data)

**Response:**
```json
{
  "success": true,
  "url": "https://pixelclip.me/i/a1b2c3d4e5f6g7h8.png"
}
```

## Security Notes

- **Change all default credentials** before deploying
- Use HTTPS in production
- Consider adding rate limiting to prevent abuse
- The upload token should be long and random (32+ characters recommended)
- Set appropriate file permissions on the `i/` directory

## Requirements

- PHP 7.0 or higher
- Web server (Apache, Nginx, etc.)
- Write permissions for upload directory

## License

This is a personal project. Use at your own risk.
