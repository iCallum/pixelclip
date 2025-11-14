<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelClip - Your Private Image Host</title>
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
            overflow: hidden;
            position: relative;
        }

        /* Animated background gradient orbs */
        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            top: -200px;
            right: -200px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            bottom: -150px;
            left: -150px;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            33% {
                transform: translate(30px, -50px) scale(1.1);
            }
            66% {
                transform: translate(-20px, 20px) scale(0.9);
            }
        }

        .container {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        .logo {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .tagline {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 40px;
            font-weight: 300;
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 40px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .download-btn:active {
            transform: translateY(0);
        }

        .download-icon {
            width: 20px;
            height: 20px;
        }

        .footer-link {
            margin-top: 32px;
            font-size: 13px;
        }

        .footer-link a {
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-link a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        @media (max-width: 600px) {
            .logo {
                font-size: 36px;
            }

            .container {
                padding: 40px 24px;
            }

            .stats {
                gap: 24px;
            }

            .stat-number {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>

    <div class="container">
        <h1 class="logo">PixelClip</h1>
        <p class="tagline">Your private ShareX-powered image host</p>

        <div class="stats">
            <div class="stat-item">
                <div class="stat-number" id="upload-count">
                    <?php
                    $upload_dir = __DIR__ . '/i/';
                    if (is_dir($upload_dir)) {
                        $files = array_diff(scandir($upload_dir), ['.', '..', '.gitkeep']);
                        echo count($files);
                    } else {
                        echo '0';
                    }
                    ?>
                </div>
                <div class="stat-label">Uploads</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">
                    <svg class="download-icon" style="width: 36px; height: 36px; display: inline-block; vertical-align: middle;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="url(#grad)" stroke-width="2"/>
                        <path d="M8 12L12 16L16 12M12 8V16" stroke="url(#grad)" stroke-width="2" stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#f093fb;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div class="stat-label">Active</div>
            </div>
        </div>

        <a href="pixelclip.sxcu" class="download-btn" download>
            <svg class="download-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3V16M12 16L16 12M12 16L8 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 17V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Download Config
        </a>

        <div class="footer-link">
            <a href="admin/">Admin Panel</a>
        </div>
    </div>
</body>
</html>
