<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';

// Redirect if already logged in
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
            $error = 'You are already logged in from another device. Check the "Force logout from other devices" option to login from this device.';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gradient-primary: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            --gradient-primary-hover: linear-gradient(135deg, #7c3aed 0%, #8b5cf6 100%);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(139, 92, 246, 0.15);
            --shadow-lg: 0 20px 40px rgba(139, 92, 246, 0.2);
            --shadow-xl: 0 25px 50px rgba(139, 92, 246, 0.25);
            --bg-dark: #0f172a;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            right: -50%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
            z-index: -1;
        }

        body::after {
            content: '';
            position: fixed;
            bottom: -50%;
            left: -50%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 25s ease-in-out infinite reverse;
            z-index: -1;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, 30px); }
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .login-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .login-header {
            background: var(--gradient-primary);
            color: white;
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .login-header .icon-wrapper {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            backdrop-filter: blur(10px);
            animation: bounceIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes bounceIn {
            0% { transform: scale(0); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .login-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .login-header p {
            opacity: 0.95;
            font-size: 0.95rem;
            font-weight: 500;
            margin: 0;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            animation: slideInForm 0.6s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }

        @keyframes slideInForm {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.6rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: #8b5cf6;
            width: 18px;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #fff;
            color: var(--text-primary);
        }

        .form-control:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
            outline: none;
            transform: translateY(-1px);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
            padding: 0.5rem;
            font-size: 1rem;
        }

        .password-toggle:hover {
            color: #8b5cf6;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 12px;
            margin: 1.5rem 0;
            transition: all 0.3s ease;
        }

        .form-check:hover {
            background: #f1f5f9;
        }

        .form-check-input {
            width: 18px;
            height: 18px;
            margin: 0;
            border-radius: 6px;
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background: var(--gradient-primary);
            border-color: #8b5cf6;
            box-shadow: var(--shadow-md);
        }

        .form-check-label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            display: block;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: slideDown 0.4s ease-out;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-danger {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .btn-login {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 14px 2rem;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1rem;
            font-family: inherit;
        }

        .btn-login:hover {
            background: var(--gradient-primary-hover);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .info-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            border: 1px solid #e0e7ff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            gap: 0.75rem;
        }

        .info-card i {
            color: #8b5cf6;
            min-width: 18px;
        }

        .auth-footer {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
        }

        .auth-footer p {
            margin: 0.75rem 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .auth-link {
            color: #8b5cf6;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .auth-link:hover {
            color: #7c3aed;
            gap: 1rem;
        }

        .theme-toggle {
            position: fixed;
            top: 2rem;
            right: 2rem;
            width: 44px;
            height: 44px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .theme-toggle:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
            color: #8b5cf6;
        }

        /* Dark theme */
        [data-theme="dark"] {
            --bg-light: #0f172a;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #1a1f3a 100%);
        }

        [data-theme="dark"] .login-card {
            background: #1e293b;
            border-color: #334155;
        }

        [data-theme="dark"] .form-control {
            background: #0f172a;
            border-color: #334155;
            color: var(--text-primary);
        }

        [data-theme="dark"] .form-check {
            background: #334155;
        }

        [data-theme="dark"] .form-check:hover {
            background: #475569;
        }

        [data-theme="dark"] .info-card {
            background: linear-gradient(135deg, #334155 0%, #3d4558 100%);
            border-color: #475569;
        }

        [data-theme="dark"] .alert-danger {
            background: linear-gradient(135deg, #450a0a 0%, #5f0f0f 100%);
            border-left-color: #dc2626;
        }

        [data-theme="dark"] .theme-toggle {
            background: #1e293b;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        @media (max-width: 768px) {
            .login-wrapper {
                max-width: 100%;
            }

            .login-card {
                margin: 0 -1rem;
                border-radius: 24px 24px 0 0;
            }

            .login-header {
                padding: 2.5rem 2rem;
            }

            .login-body {
                padding: 2rem;
            }

            .btn-login {
                padding: 12px 1.5rem;
                font-size: 0.95rem;
            }

            .theme-toggle {
                top: 1rem;
                right: 1rem;
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
    <link href="assets/css/dark-mode-button.css" rel="stylesheet">
</head>
<body>
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-wrapper">
                    <i class="fas fa-crown"></i>
                </div>
                <h2>Welcome Back</h2>
                <p>Sign in to access your account</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm" novalidate>
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>Username or Email
                        </label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               placeholder="Enter your username or email" required>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>Password
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" id="toggleBtn" onclick="togglePassword(event)">
                                <i class="fas fa-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="force_logout" name="force_logout">
                            <label class="form-check-label" for="force_logout">
                                Force logout from other devices
                            </label>
                        </div>
                        <div class="form-text">
                            Check this if you want to login from this device only
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>Sign In
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Don't have an account?
                        <a href="register.php" class="auth-link">
                            Create one here
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </p>
                    <p>
                        <a href="reset_device.php" class="auth-link">
                            <i class="fas fa-mobile-alt"></i>Reset Device
                        </a>
                    </p>
                </div>

                <div class="info-card">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>One Device Login:</strong> You can only be logged in from one device at a time.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle
        document.getElementById('themeToggle').addEventListener('click', function() {
            const icon = document.getElementById('darkModeIcon');
            if (document.body.getAttribute('data-theme') === 'dark') {
                document.body.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                document.body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        });

        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }

        function togglePassword(e) {
            e.preventDefault();
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
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
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const forceLogout = document.getElementById('force_logout').checked;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Signing in...';

            fetch('login.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password) + '&force_logout=' + (forceLogout ? '1' : '0')
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Invalid') || data.includes('already logged in')) {
                    Swal.fire({
                        html: '<div style="color: #dc2626;"><i class="fas fa-times fa-4x mb-3"></i><h3 style="margin: 1rem 0;">Access Denied</h3><p>Invalid username or password</p></div>',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#fff',
                        confirmButtonText: 'Try Again'
                    });
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sign-in-alt"></i>Sign In';
                } else {
                    Swal.fire({
                        html: '<div style="color: #10b981;"><i class="fas fa-check-circle fa-4x mb-3"></i><h3 style="margin: 1rem 0;">Access Granted</h3><p>Redirecting...</p></div>',
                        background: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#fff',
                        showConfirmButton: false,
                        willClose: () => window.location.href = data.includes('admin') ? 'admin_dashboard.php' : 'user_dashboard.php'
                    });
                    setTimeout(() => window.location.href = data.includes('admin') ? 'admin_dashboard.php' : 'user_dashboard.php', 2000);
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sign-in-alt"></i>Sign In';
                alert('An error occurred. Please try again.');
            });
        });
    </script>
</body>
</html>