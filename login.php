<?php require_once "includes/optimization.php"; ?>
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
            $error = 'Already logged in from another device. Check force logout option.';
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(240, 147, 251, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            top: -300px;
            left: -300px;
            animation: float1 20s ease-in-out infinite;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(79, 172, 254, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -250px;
            right: -250px;
            animation: float2 25s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(100px, 100px); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-100px, -100px); }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 100px rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.8);
            padding: 0;
            overflow: hidden;
            animation: slideIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        .crown-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: crown-bounce 2s ease-in-out infinite;
            display: inline-block;
            position: relative;
            z-index: 1;
        }

        @keyframes crown-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .login-header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 10px 0 0 0;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .login-body {
            padding: 45px 40px;
        }

        .form-group {
            margin-bottom: 25px;
            animation: formSlide 0.6s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes formSlide {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: #667eea;
            font-size: 14px;
            width: 20px;
            text-align: center;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8ecf1;
            border-radius: 15px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            color: #2d3748;
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), 0 10px 30px rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.3s ease;
            z-index: 2;
            padding: 5px;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid #e8ecf1;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .checkbox-group:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #fafbff 100%);
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #667eea;
        }

        .checkbox-label {
            font-size: 14px;
            color: #2d3748;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .btn-login:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }

        .footer-text {
            text-align: center;
            font-size: 13px;
            color: #718096;
            margin-bottom: 15px;
        }

        .footer-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .footer-link:hover {
            color: #764ba2;
            gap: 12px;
        }

        .divider-text {
            text-align: center;
            color: #cbd5e0;
            font-size: 12px;
            margin: 20px 0;
            position: relative;
        }

        .divider-text::before,
        .divider-text::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #e8ecf1;
        }

        .divider-text::before { left: 0; }
        .divider-text::after { right: 0; }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            animation: alertSlide 0.4s ease-out;
            border: 1px solid #fc8181;
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            color: #c53030;
        }

        @keyframes alertSlide {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .theme-toggle {
            position: fixed;
            top: 25px;
            right: 25px;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #667eea;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2);
        }

        @media (max-width: 768px) {
            .login-card {
                border-radius: 20px;
                margin: 20px;
            }

            .login-header {
                padding: 40px 30px;
            }

            .login-body {
                padding: 30px;
            }

            .login-header h1 {
                font-size: 26px;
            }

            .crown-icon {
                font-size: 50px;
                margin-bottom: 15px;
            }

            .theme-toggle {
                width: 45px;
                height: 45px;
                font-size: 18px;
                top: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Toggle theme">
        <i class="fas fa-moon"></i>
    </button>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="crown-icon">👑</div>
                <h1>Welcome Back</h1>
                <p>Access your SilentMultiPanel account</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i>Username or Email
                        </label>
                        <div class="input-wrapper">
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($username ?? ''); ?>"
                                   placeholder="Enter your username or email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i>Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="force_logout" id="forceLogout">
                        <label class="checkbox-label" for="forceLogout">
                            Force logout from other devices
                        </label>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-arrow-right"></i>Sign In
                    </button>
                </form>

                <div class="footer-text">
                    Don't have an account? 
                    <a href="register.php" class="footer-link">
                        Create one
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="divider-text">or</div>

                <div class="footer-text">
                    <a href="reset_device.php" class="footer-link">
                        <i class="fas fa-mobile-alt"></i>Reset Device
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.querySelector('input[name="password"]');
            const icon = document.querySelector('.password-toggle i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-login');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Signing in...';

            const formData = new FormData(this);
            fetch('login.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(data => {
                if (data.includes('Invalid') || data.includes('already logged')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Access Denied',
                        text: 'Invalid credentials',
                        background: '#fff',
                        confirmButtonColor: '#667eea'
                    });
                    btn.disabled = false;
                    btn.innerHTML = original;
                } else {
                    Swal.fire({
                        icon: 'success',
                        title: 'Welcome Back!',
                        text: 'Redirecting...',
                        background: '#fff',
                        confirmButtonColor: '#667eea',
                        allowOutsideClick: false,
                        didOpen: () => {
                            setTimeout(() => window.location.href = 'user_dashboard.php', 1500);
                        }
                    });
                }
            })
            .catch(e => {
                btn.disabled = false;
                btn.innerHTML = original;
                alert('Error occurred');
            });
        });

        document.getElementById('themeToggle').addEventListener('click', function() {
            document.body.style.filter = document.body.style.filter === 'invert(1)' ? 'invert(0)' : 'invert(1)';
        });
    </script>
</body>
</html>