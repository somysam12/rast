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
            padding: 100px 0 50px 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: left;
        }

        .hero-image {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
        }

        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 70px;
            z-index: 2001;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            box-shadow: var(--shadow-medium);
        }

        @media (max-width: 991px) {
            .hero-content {
                text-align: center;
                margin-bottom: 3rem;
            }
            .hero-content .d-flex {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 120px 0 60px 0;
                min-height: auto;
            }

            .hero-content h1 {
                font-size: 2rem !important;
            }

            .hero-content h1 span {
                font-size: 2.2rem !important;
            }

            .theme-toggle {
                top: 12px;
                right: 60px;
            }
        }
    </style>
    <link href="assets/css/dark-mode-button.css" rel="stylesheet">
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
</head>
<body>
    <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <nav class="navbar navbar-expand-lg navbar-custom" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-crown me-2"></i>SilentMultiPanel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="btn btn-primary-enhanced ms-2" href="<?php echo $dashboardUrl; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary-enhanced ms-2" href="register.php">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-3 fw-bold mb-4 fade-in" style="background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: 1px; text-shadow: 0 0 30px rgba(251, 191, 36, 0.3); line-height: 1.2;">
                            Welcome To <span class="d-block" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.8rem; font-weight: 900; letter-spacing: 1px; text-shadow: 0 0 40px rgba(251, 191, 36, 0.5);">SilentMultiPanel</span>
                        </h1>
                        <p class="lead mb-5 fade-in">
                            Best Multipanel And Instant Support.
                        </p>
                        <div class="d-flex flex-wrap gap-3 fade-in">
                            <?php if ($isLoggedIn): ?>
                                <a href="<?php echo $dashboardUrl; ?>" class="btn-cta">
                                    <i class="fas fa-crown me-2"></i>Go To MultiPanel
                                </a>
                            <?php else: ?>
                                <a href="register.php" class="btn-cta">
                                    <i class="fas fa-rocket me-2"></i>Get Started Free
                                </a>
                                <a href="login.php" class="btn-outline-cta">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image fade-in">
                        <img src="assets/images/hero-logo.jpg" alt="SilentMultiPanel Hero" style="max-width: 400px;">
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <section class="stats-section" id="about">
        <div class="container">
            <div class="row align-items-center justify-content-center">
                <div class="col-lg-8 text-center fade-in">
                    <h1 class="display-3 fw-bold mb-4" style="background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        <i class="fas fa-crown me-3" style="color: var(--purple);"></i>SilentMultiPanel
                    </h1>
                    <p class="lead text-muted mb-0">Best Multipanel And Instant Support</p>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-crown me-2"></i>SilentMultiPanel</h5>
                    <p class="text-muted">Best Multipanel And Instant Support</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2024 SilentMultiPanel. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDarkMode() {
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
        
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);
        
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(el => {
                observer.observe(el);
            });
            
            const heroElements = document.querySelectorAll('.hero-content .fade-in');
            heroElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('visible');
                }, index * 200);
            });
        });
        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
