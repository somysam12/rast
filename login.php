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
            $error = 'You are already logged in from another device. Check the force logout option.';
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --accent: #ec4899;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.1);
            --border-glow: rgba(139, 92, 246, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: #0a0e27;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
        }

        .glass-card {
            background: #111827;
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #6366f1, #06b6d4);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }

        .brand-section h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: white;
            text-align: center;
        }

        .brand-section p {
            color: #94a3b8;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 15px;
            position: relative;
        }

        .input-field {
            width: 100%;
            background: #1f2937;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 14px 16px 14px 45px;
            color: white;
            font-size: 15px;
            transition: all 0.2s;
        }

        .input-field:focus {
            background: #1f2937;
            border-color: #6366f1;
            outline: none;
        }

        .field-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 18px;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(90deg, #7c3aed, #06b6d4);
            border: none;
            border-radius: 12px;
            padding: 14px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        .btn-submit:hover {
            filter: brightness(1.1);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:active {
            transform: translateY(-1px);
        }

        .footer-text {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: var(--text-dim);
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            position: relative;
        }

        .footer-text a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--primary);
            transition: width 0.3s;
        }

        .footer-text a:hover::after {
            width: 100%;
        }

        .footer-text a:hover {
            color: var(--secondary);
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
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

        .custom-check {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-dim);
            transition: color 0.3s;
            user-select: none;
        }

        .custom-check input[type="checkbox"] {
            cursor: pointer;
            accent-color: var(--primary);
        }

        .custom-check:hover {
            color: var(--text-main);
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 30px 20px;
                border-radius: 20px;
                border: 1.5px solid var(--border-light);
            }
            
            .brand-section h1 {
                font-size: 24px;
            }

            body {
                align-items: flex-start;
                padding-top: 40px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="glass-card">
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <h1>SilentMultiPanel</h1>
                <p>Login to your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <i class="fas fa-user field-icon"></i>
                    <input type="text" name="username" class="input-field" placeholder="Username" required 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" name="password" id="password" class="input-field" placeholder="Password" required>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>

                <div class="form-group">
                    <label class="custom-check">
                        <input type="checkbox" name="force_logout">
                        Force logout from other devices
                    </label>
                </div>

                <button type="submit" class="btn-submit">Sign In</button>
            </form>

            <div class="footer-text">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>