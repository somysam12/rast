<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_dashboard.php');
    }
    exit();
}

$error = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $forceLogout = isset($_POST['force_logout']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $loginResult = login($username, $password, $forceLogout);
        if ($loginResult === true) {
            if (isAdmin()) {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: user_dashboard.php');
            }
            exit();
        } else if ($loginResult === 'already_logged_in') {
            $error = 'You are already logged in from another device. Check "Force logout" to login from this device.';
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
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
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .login-container { width: 100%; position: relative; z-index: 10; }
        .login-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-large);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .login-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .login-body {
            padding: 2.5rem 2rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid var(--border-light);
            padding: 12px 16px;
            transition: all 0.2s ease;
            background: var(--card-bg);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: var(--card-bg);
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
            transition: all 0.2s ease;
        }
        
        .btn-login:hover {
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
            color: white;
        }
        
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            box-shadow: var(--shadow-medium);
        }
        
        .theme-toggle:hover {
            color: var(--purple);
            box-shadow: var(--shadow-large);
        }

        #pixelGrid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .grid-pixel {
            position: absolute;
            width: 35px;
            height: 35px;
            border: 1.5px solid rgba(139, 92, 246, 0.2);
            border-radius: 5px;
            background: transparent;
            cursor: pointer;
            transition: all 0.12s ease;
            pointer-events: auto;
        }

        .grid-pixel:hover {
            background: linear-gradient(135deg, #ffffff 0%, #a78bfa 50%, #8b5cf6 100%);
            border-color: #8b5cf6;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.8);
            transform: scale(1.18) rotate(6deg);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        @media (max-width: 768px) {
            .login-header { padding: 2rem 1.5rem; }
            .login-body { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <div id="pixelGrid"></div>
    <button class="theme-toggle" onclick="toggleDarkMode()" title="Dark Mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5 col-xl-4">
                    <div class="login-card">
                        <div class="login-header">
                            <div class="mb-3"><i class="fas fa-crown fa-3x"></i></div>
                            <h3 class="mb-2">SilentMultiPanel</h3>
                            <p class="mb-0">Welcome back! Sign in to your account</p>
                        </div>
                        <div class="login-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Username or Email" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="force_logout" name="force_logout">
                                    <label class="form-check-label" for="force_logout">Force logout from other devices</label>
                                </div>
                                <button type="submit" class="btn btn-login w-100 mb-4">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </form>
                            
                            <div class="text-center">
                                <p>Don't have an account? <a href="register.php" class="text-decoration-none" style="color: var(--purple);">Create one</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tapColors = [
            'linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%)',
            'linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%)',
            'linear-gradient(135deg, #ffd93d 0%, #f5a623 100%)',
            'linear-gradient(135deg, #a8e6cf 0%, #56ab2f 100%)',
            'linear-gradient(135deg, #ff8b94 0%, #ff6b6b 100%)',
            'linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)',
            'linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%)',
            'linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%)'
        ];

        function createGrid() {
            const grid = document.getElementById('pixelGrid');
            const size = 35, gap = 3;
            const cols = Math.ceil(window.innerWidth / (size + gap));
            const rows = Math.ceil(window.innerHeight / (size + gap));
            
            for (let i = 0; i < cols * rows; i++) {
                const px = document.createElement('div');
                px.className = 'grid-pixel';
                px.style.left = ((i % cols) * (size + gap) + 15) + 'px';
                px.style.top = (Math.floor(i / cols) * (size + gap) + 15) + 'px';
                
                px.addEventListener('mouseenter', function() {
                    this.style.background = 'linear-gradient(135deg, #ffffff 0%, #a78bfa 50%, #8b5cf6 100%)';
                    this.style.boxShadow = '0 0 15px rgba(139, 92, 246, 0.8)';
                });
                
                px.addEventListener('mouseleave', function() {
                    this.style.background = 'transparent';
                    this.style.boxShadow = 'none';
                });
                
                px.addEventListener('click', function() {
                    const col = tapColors[Math.floor(Math.random() * tapColors.length)];
                    this.style.background = col;
                    this.style.boxShadow = '0 0 25px rgba(139, 92, 246, 0.9)';
                    setTimeout(() => {
                        this.style.background = 'transparent';
                        this.style.boxShadow = 'none';
                    }, 800);
                });
                
                grid.appendChild(px);
            }
        }

        function toggleDarkMode() {
            const body = document.body;
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                document.getElementById('darkModeIcon').className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                document.getElementById('darkModeIcon').className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
        
        document.addEventListener('DOMContentLoaded', createGrid);
    </script>
</body>
</html>
