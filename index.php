<?php
require_once 'includes/auth.php';

$isLoggedIn = isLoggedIn();
$isAdmin = $isLoggedIn && isAdmin();

$dashboardUrl = '';
if ($isLoggedIn) {
    $dashboardUrl = $isAdmin ? 'admin_dashboard.php' : 'user_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SilentMultiPanel - Best Multipanel And Instant Support</title>
    <meta name="description" content="SilentMultiPanel - Best Multipanel And Instant Support">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="20" fill="url(%23dots)"/></svg>');
            opacity: 0.5;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }
        
        .hero-image {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-large);
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar-custom.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-section {
            background: var(--card-bg);
            padding: 5rem 0;
            border-top: 1px solid var(--border-light);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-cta {
            background: white;
            color: var(--purple);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-cta:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-large);
            color: var(--purple-dark);
        }
        
        .btn-outline-cta {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .btn-outline-cta:hover {
            background: white;
            color: var(--purple);
            transform: translateY(-1px);
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1050;
            background: var(--card-bg);
            border: 2px solid var(--border-light);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            font-size: 20px;
            color: #333;
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
            color: var(--purple);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }
        
        .theme-toggle:active {
            transform: scale(0.95);
        }
        
        @media (max-width: 768px) {
            .theme-toggle {
                width: 50px !important;
                height: 50px !important;
                font-size: 18px !important;
                top: 12px !important;
                right: 12px !important;
                z-index: 1050 !important;
            }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        [data-theme="dark"] .navbar-custom {
            background: rgba(30, 41, 59, 0.95);
        }
        
        [data-theme="dark"] .feature-card {
            background: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .stats-section {
            background: var(--card-bg);
            border-color: var(--border-light);
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .feature-card {
                padding: 1.5rem;
            }
            
            .feature-icon {
                width: 56px;
                height: 56px;
                font-size: 1.3rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-custom">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="/">SilentMultiPanel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#stats">Stats</a></li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-sm btn-outline-primary ms-2" href="<?php echo $dashboardUrl; ?>">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-sm btn-primary ms-2" href="login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle dark mode">☀️</button>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="display-4 fw-bold mb-4">Welcome to SilentMultiPanel</h1>
                    <p class="lead mb-5">The best multipanel solution for instant support and APK management. Manage your licenses and distribute APK files with ease.</p>
                    <div class="gap-3">
                        <?php if ($isLoggedIn): ?>
                            <a href="<?php echo $dashboardUrl; ?>" class="btn btn-cta me-3">
                                <i class="fas fa-arrow-right me-2"></i> Go to Dashboard
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-cta me-3">
                                <i class="fas fa-sign-in-alt me-2"></i> Login Now
                            </a>
                            <a href="register.php" class="btn btn-outline-cta">
                                <i class="fas fa-user-plus me-2"></i> Create Account
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 hero-image d-none d-lg-flex">
                    <i class="fas fa-mobile-alt" style="font-size: 150px; color: white; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2">Powerful Features</h2>
                <p class="text-secondary">Everything you need for license and APK management</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card fade-in">
                        <div class="feature-icon"><i class="fas fa-key"></i></div>
                        <h5 class="fw-bold">License Keys</h5>
                        <p class="text-secondary">Manage and distribute license keys efficiently</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card fade-in">
                        <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <h5 class="fw-bold">APK Upload</h5>
                        <p class="text-secondary">Upload and manage your APK files</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card fade-in">
                        <div class="feature-icon"><i class="fas fa-moon"></i></div>
                        <h5 class="fw-bold">Dark Mode</h5>
                        <p class="text-secondary">Comfortable viewing in any lighting</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card fade-in">
                        <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                        <h5 class="fw-bold">Mobile Ready</h5>
                        <p class="text-secondary">Fully responsive design for all devices</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section id="stats" class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">1000+</div>
                        <p class="text-secondary">Active Users</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">5000+</div>
                        <p class="text-secondary">Licenses Distributed</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <p class="text-secondary">Uptime</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            html.setAttribute('data-theme', 'dark');
            themeToggle.textContent = '☀️';
        }
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            themeToggle.textContent = newTheme === 'dark' ? '☀️' : '☀️';
            localStorage.setItem('theme', newTheme);
        });
        
        // Scroll animations
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        });
        
        document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
        
        // Navbar scroll effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
