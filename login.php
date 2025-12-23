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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        #pixelGrid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            opacity: 0.3;
        }

        .grid-pixel {
            position: absolute;
            width: 35px;
            height: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            background: transparent;
            cursor: pointer;
            transition: all 0.12s ease;
            pointer-events: auto;
        }

        .grid-pixel:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .login-wrapper {
            position: relative;
            z-index: 100;
            width: 100%;
            max-width: 450px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .login-card:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.25);
            transform: translateY(-5px);
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px 30px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(20px); }
        }

        .crown-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            display: inline-block;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .login-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 2;
        }

        .login-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 0.95rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
            color: #333;
        }

        .form-control:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: #999;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .form-check-label {
            color: #666;
            font-size: 0.9rem;
            cursor: pointer;
            margin: 0;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            color: white;
            width: 100%;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            margin-bottom: 25px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .login-footer p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            border: 2px solid #ff6b6b;
            background: #fff5f5;
            color: #c92a2a;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .theme-toggle {
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            color: #667eea;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .theme-toggle:hover {
            transform: scale(1.1) rotate(20deg);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 600px) {
            .login-header { padding: 35px 25px 30px; }
            .login-body { padding: 30px 20px; }
            .login-header h1 { font-size: 1.8rem; }
            .crown-icon { font-size: 2.8rem; }
        }
    </style>
</head>
<body>
    <div id="pixelGrid"></div>
    <button class="theme-toggle" title="Toggle Dark Mode"><i class="fas fa-sun"></i></button>
    
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="crown-icon"><i class="fas fa-crown"></i></div>
                <h1>SilentMultiPanel</h1>
                <p>Welcome back! Sign in to continue</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user me-2"></i>Username or Email</label>
                        <input type="text" class="form-control" name="username" placeholder="Enter your username or email" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Enter your password" required autocomplete="off">
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="forceLogout" name="force_logout">
                        <label class="form-check-label" for="forceLogout">Force logout from other devices</label>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>
                
                <div class="login-footer">
                    <p>Don't have an account? <a href="register.php">Create one now</a></p>
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
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
        ];

        function createGrid() {
            const grid = document.getElementById('pixelGrid');
            const size = 35, gap = 3;
            const cols = Math.ceil(window.innerWidth / (size + gap));
            const rows = Math.ceil(window.innerHeight / (size + gap));
            
            const activatePixel = (px) => {
                const col = tapColors[Math.floor(Math.random() * tapColors.length)];
                px.style.background = col;
                px.style.boxShadow = '0 0 20px rgba(255, 107, 107, 0.7)';
                px.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    px.style.background = 'transparent';
                    px.style.boxShadow = 'none';
                    px.style.transform = 'scale(1)';
                }, 700);
            };
            
            for (let i = 0; i < cols * rows; i++) {
                const px = document.createElement('div');
                px.className = 'grid-pixel';
                px.style.left = ((i % cols) * (size + gap) + 15) + 'px';
                px.style.top = (Math.floor(i / cols) * (size + gap) + 15) + 'px';
                
                px.addEventListener('click', (e) => {
                    e.stopPropagation();
                    activatePixel(px);
                }, false);
                
                px.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    activatePixel(px);
                }, false);
                
                grid.appendChild(px);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createGrid);
        } else {
            createGrid();
        }
    </script>
</body>
</html>
