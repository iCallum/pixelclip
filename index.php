<?php
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$username = $_SESSION['username'] ?? null;
?>
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
            position: relative;
            overflow-x: hidden; /* Prevent horizontal scroll from animations */
        }

        /* Animated background gradient orbs */
        .bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.3;
            animation: float 20s infinite ease-in-out;
            z-index: 0;
            will-change: transform;
        }

        .orb-1 {
            width: 600px;
            height: 600px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            top: -300px;
            right: -200px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            bottom: -200px;
            left: -150px;
            animation-delay: -10s;
        }

        .orb-3 {
            width: 400px;
            height: 400px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(50px, -50px) scale(1.1); }
            66% { transform: translate(-30px, 30px) scale(0.9); }
        }

        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }

        /* Navbar */
        .navbar {
            position: relative;
            z-index: 100;
            padding: 20px 0;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(270deg, #667eea, #764ba2, #f093fb, #00f2fe);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            letter-spacing: -0.5px;
            animation: gradient-shift 10s ease infinite;
        }

        .navbar-links {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .navbar-links > * {
            display: flex;
            align-items: center;
            position: relative;
        }

        .navbar-links > *:not(:last-child) {
            margin-right: 18px;
            padding-right: 18px;
        }

        .navbar-links > *:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 18px;
            background: rgba(255, 255, 255, 0.2);
        }

        .navbar-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s;
        }

        .navbar-links a:hover {
            color: rgba(255, 255, 255, 1);
        }

        .navbar-links .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .navbar-links .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .navbar-user {
            color: #667eea;
            font-weight: 600;
        }

        /* Hero Section */
        .hero {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 100px 40px 150px;
            max-width: 900px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hero h1 {
            font-size: 64px;
            font-weight: 800;
            background: linear-gradient(270deg, #667eea, #764ba2, #f093fb, #4facfe);
            background-size: 300% 300%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 24px;
            letter-spacing: -2px;
            line-height: 1.1;
            animation: gradient-shift 8s ease infinite;
            display: inline-block;
        }
        
        /* Typing cursor effect wrapper */
        .typewriter-wrapper {
            display: inline-block;
            overflow: hidden; /* Ensures the content is not revealed until the animation */
            white-space: nowrap; /* Keeps the content on a single line */
            border-right: .15em solid #f093fb; /* The typwriter cursor */
            animation: typing 3.5s steps(40, end), blink-caret .75s step-end infinite;
            margin: 0 auto;
            max-width: 100%;
        }
        
        /* Only apply typing effect on larger screens where it fits on one line */
        @media (min-width: 768px) {
            .hero h1 {
                /* Reset for typing animation */
                white-space: nowrap;
                overflow: hidden;
                border-right: 3px solid rgba(255,255,255,0.5);
                width: 0;
                animation: typing 2s cubic-bezier(0.4, 0, 0.2, 1) forwards, gradient-shift 8s ease infinite;
                animation-delay: 0.2s; /* Slight delay before typing starts */
            }
            
            /* Remove border after typing finishes */
            .hero h1.typing-done {
                border-right: none;
            }
        }

        .hero p {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
            opacity: 0;
            animation: fadeInUp 0.8s ease forwards 1.5s; /* Delays until after title */
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0;
            animation: fadeInUp 0.8s ease forwards 1.8s;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Stats Section */
        .stats-section {
            position: relative;
            z-index: 10;
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 40px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 32px 24px;
            text-align: center;
            transition: all 0.3s;
            opacity: 0; /* Hidden initially for scroll reveal */
            transform: translateY(30px);
        }

        .stat-card.visible {
            animation: fadeInUp 0.8s ease forwards;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        /* Features Section */
        .features-section {
            position: relative;
            z-index: 10;
            max-width: 1200px;
            margin: 100px auto;
            padding: 0 40px;
        }

        .section-title {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 60px;
            background: linear-gradient(135deg, #667eea 0%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }
        
        .section-title.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 32px;
            transition: transform 0.1s ease, border-color 0.3s ease, background 0.3s ease; /* Faster transform for tilt */
            opacity: 0;
            transform: translateY(30px);
            will-change: transform;
        }
        
        .feature-card.visible {
            animation: fadeInUp 0.8s ease forwards;
        }

        /* Removed generic hover transform to let JS handle 3D tilt */
        .feature-card:hover {
            border-color: rgba(102, 126, 234, 0.3);
            background: rgba(255, 255, 255, 0.08);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
        }

        .feature-card h3 {
            font-size: 20px;
            margin-bottom: 12px;
            color: white;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.6;
            font-size: 14px;
        }

        /* Footer */
        .footer {
            position: relative;
            z-index: 10;
            margin-top: 120px;
            padding: 60px 40px 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-section h4 {
            font-size: 16px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #667eea 0%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-section p,
        .footer-section a {
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            line-height: 2;
            text-decoration: none;
            display: block;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: rgba(255, 255, 255, 0.8);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.4);
            font-size: 14px;
        }

        /* Scroll Down Indicator */
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0) translateX(-50%); }
            40% { transform: translateY(-15px) translateX(-50%); }
            60% { transform: translateY(-8px) translateX(-50%); }
        }

        .scroll-down {
            position: absolute;
            bottom: 50px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.8);
            animation: bounce 2s infinite;
            cursor: pointer;
            z-index: 20;
            transition: color 0.3s, transform 0.3s;
        }

        .scroll-down:hover {
            color: #ffffff;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 20px;
            }

            .navbar-links a {
                font-size: 13px;
            }

            .navbar-links > *:not(:last-child) {
                margin-right: 12px;
                padding-right: 12px;
            }

            .navbar-links > *:not(:last-child)::after {
                height: 14px;
            }

            .hero {
                padding: 60px 20px 100px;
                min-height: calc(100svh - 80px);
            }

            .hero h1 {
                font-size: 40px;
            }

            .hero p {
                font-size: 16px;
            }

            .stats-section,
            .features-section {
                padding: 0 20px;
            }

            .section-title {
                font-size: 32px;
            }

            .footer {
                padding: 40px 20px 20px;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Orbs -->
    <div class="bg-orb orb-1" id="orb1"></div>
    <div class="bg-orb orb-2" id="orb2"></div>
    <div class="bg-orb orb-3" id="orb3"></div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-logo">PixelClip</a>
            <div class="navbar-links">
                <?php if ($isLoggedIn): ?>
                    <span class="navbar-user"><?php echo htmlspecialchars($username); ?></span>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php" class="btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <h1 id="hero-title">Your Private Image Host</h1>
        <p>Upload, share, and manage your screenshots seamlessly with ShareX integration. Fast, secure, and beautifully simple.</p>
        <div class="hero-buttons">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-gradient">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    Go to Dashboard
                </a>
            <?php else: ?>
                <a href="register.php" class="btn btn-gradient">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <line x1="20" y1="8" x2="20" y2="14"/>
                        <line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                    Get Started
                </a>
                <a href="login.php" class="btn btn-outline">Sign In</a>
            <?php endif; ?>
        </div>

        <div class="scroll-down" onclick="document.querySelector('.stats-section').scrollIntoView({behavior: 'smooth'})">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M7 13l5 5 5-5M7 6l5 5 5-5" stroke="url(#scroll-gradient)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <defs>
                    <linearGradient id="scroll-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                        <stop offset="100%" style="stop-color:#f093fb;stop-opacity:1" />
                    </linearGradient>
                </defs>
            </svg>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-card reveal-on-scroll">
                <div class="stat-number count-up" data-target="<?php
                    $upload_dir = __DIR__ . '/i/';
                    $count = 0;
                    if (is_dir($upload_dir)) {
                        // Recursive count for nested folders
                        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($upload_dir), RecursiveIteratorIterator::SELF_FIRST);
                        foreach($objects as $name => $object){
                            // Skip . and .. and directories
                            if(!$object->isDir() && $object->getFilename() != '.gitkeep' && $object->getFilename() != '.htaccess' && $object->getFilename() != 'index.php' && $object->getFilename() != 'view.php') {
                                $count++;
                            }
                        }
                    }
                    echo $count;
                ?>">0</div>
                <div class="stat-label">Total Uploads</div>
            </div>
            <div class="stat-card reveal-on-scroll" style="animation-delay: 0.2s;">
                <div class="stat-number">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="url(#grad1)" stroke-width="2"/>
                        <path d="M12 6v6l4 2" stroke="url(#grad1)" stroke-width="2" stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#f093fb;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <div class="stat-label">Always Online</div>
            </div>
            <div class="stat-card reveal-on-scroll" style="animation-delay: 0.4s;">
                <div class="stat-number">Fast</div>
                <div class="stat-label">Upload Speed</div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <h2 class="section-title reveal-on-scroll">Why Choose PixelClip?</h2>
        <div class="features-grid">
            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.1s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <h3>Secure & Private</h3>
                <p>Your images are stored securely with per-user authentication. Only you have access to your uploads.</p>
            </div>

            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.2s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h3>ShareX Integration</h3>
                <p>Download your personalized config file and start uploading screenshots instantly with ShareX.</p>
            </div>

            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.3s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
                <h3>Lightning Fast</h3>
                <p>Built with performance in mind. Upload and share your images at blazing speeds.</p>
            </div>

            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.4s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </div>
                <h3>Personal Dashboard</h3>
                <p>Manage all your uploads in one place. View, organize, and delete with a beautiful interface.</p>
            </div>

            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.5s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </div>
                <h3>Easy Uploads</h3>
                <p>Drag, drop, or use ShareX. Multiple upload methods make sharing images effortless.</p>
            </div>

            <div class="feature-card reveal-on-scroll tilt-card" style="animation-delay: 0.6s;">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <h3>User Management</h3>
                <p>Invite-only registration keeps your instance private. Full control over who has access.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-section">
                <h4>PixelClip</h4>
                <p>Your private, self-hosted image hosting solution with ShareX integration.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <?php if ($isLoggedIn): ?>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="config-download.php">Download Config</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
            <div class="footer-section">
                <h4>Resources</h4>
                <a href="https://getsharex.com/" target="_blank">Download ShareX</a>
            </div>
        </div>
        <div class="footer-bottom">
                            &copy; <?php echo date('Y'); ?> PixelClip.        </div>
    </footer>

    <script>
        // 1. Hero Typing Effect Cleanup
        const heroTitle = document.getElementById('hero-title');
        if (heroTitle) {
            heroTitle.addEventListener('animationend', (e) => {
                if (e.animationName === 'typing') {
                    heroTitle.classList.add('typing-done');
                }
            });
        }

        // 2. Intersection Observer for Scroll Reveal
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    
                    // Trigger count animation if this is a stat card
                    const counter = entry.target.querySelector('.count-up');
                    if (counter) {
                        animateValue(counter, 0, parseInt(counter.getAttribute('data-target')), 2000);
                        counter.classList.remove('count-up'); // Prevent re-running
                    }
                    
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.reveal-on-scroll').forEach(el => {
            observer.observe(el);
        });

        // 3. Count Up Animation
        function animateValue(obj, start, end, duration) {
            if (end === 0) return;
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        // 4. Mouse Parallax for Orbs
        document.addEventListener('mousemove', (e) => {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            const moveOrb = (id, speed) => {
                const orb = document.getElementById(id);
                const xOffset = (x - 0.5) * speed;
                const yOffset = (y - 0.5) * speed;
                orb.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
            }
            
            moveOrb('orb1', 30);
            moveOrb('orb2', -20);
            moveOrb('orb3', 10);
        });

        // 5. 3D Tilt Effect for Feature Cards
        document.querySelectorAll('.tilt-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // Calculate rotation based on mouse position (max 10deg)
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = ((y - centerY) / centerY) * -5; // Invert Y axis
                const rotateY = ((x - centerX) / centerX) * 5;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
            });
        });
    </script>
</body>
</html>