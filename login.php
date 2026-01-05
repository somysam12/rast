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
        // First check 2FA requirement
        require_once 'config/database.php';
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, password, two_factor_enabled, two_factor_secret FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user_row = $stmt->fetch();

        if ($user_row && password_verify($password, $user_row['password'])) {
            if ($user_row['two_factor_enabled']) {
                if (isset($_POST['otp_code'])) {
                    if (file_exists('includes/GoogleAuthenticator.php')) {
                        require_once 'includes/GoogleAuthenticator.php';
                        $ga = new PHPGangsta_GoogleAuthenticator();
                        if ($ga->verifyCode($user_row['two_factor_secret'], $_POST['otp_code'], 2)) {
                            // Valid 2FA code, proceed to login using the auth.php function
                            $loginResult = login($user_row['username'], $password, $forceLogout);
                            if ($loginResult === true) {
                                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                                    header('Location: admin_dashboard.php');
                                } else {
                                    header('Location: user_dashboard.php');
                                }
                                exit();
                            } else if ($loginResult === 'already_logged_in') {
                                $error = 'You are already logged in from another device. Check the force logout option.';
                            } else if ($loginResult === 'device_locked') {
                                $error = 'Your account is locked for 24 hours due to a device change. Please wait.';
                            } else {
                                $error = 'Invalid username or password';
                            }
                        } else {
                            $error = 'Invalid 2FA code';
                            $show_2fa = true;
                        }
                    } else {
                        $error = '2FA system error';
                    }
                } else {
                    $show_2fa = true;
                }
            }
        }

        if (!$error && (!isset($show_2fa) || !$show_2fa)) {
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
            } else if ($loginResult === 'device_locked') {
                $error = 'Your account is locked for 24 hours due to a device change. Please wait.';
            } else {
                $error = 'Invalid username or password';
            }
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
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .login-wrapper:hover {
            transform: scale(1.01);
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(40px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
                border-color: rgba(139, 92, 246, 0.5);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
                border-color: rgba(6, 182, 212, 0.5);
            }
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 32px;
            padding: 45px;
            box-shadow: 
                0 0 60px rgba(139, 92, 246, 0.15),
                0 0 20px rgba(6, 182, 212, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .glass-card:hover {
            transform: translateY(-5px);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .glass-card > * {
            position: relative;
            z-index: 2;
        }

        .brand-section {
            text-align: center;
            margin-bottom: 36px;
        }

        .brand-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
            box-shadow: 
                0 15px 35px rgba(139, 92, 246, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset -2px -2px 5px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .brand-section h1 {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #f8fafc, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-section p {
            color: var(--text-dim);
            font-size: 14px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid var(--border-light);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            color: white;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .input-field::placeholder {
            color: rgba(148, 163, 184, 0.6);
        }

        .input-field:focus {
            outline: none;
            background: rgba(139, 92, 246, 0.05);
            border-color: var(--primary);
            box-shadow: 
                0 0 0 4px rgba(139, 92, 246, 0.15),
                inset 0 1px 2px rgba(255, 255, 255, 0.05);
        }

        .field-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-group:focus-within .field-icon {
            color: var(--primary);
            transform: translateY(-50%) scale(1.2);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 14px;
            padding: 14px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.5);
            filter: brightness(1.15);
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
                <?php if (isset($show_2fa) && $show_2fa): ?>
                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                    <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
                    <?php if (isset($forceLogout) && $forceLogout): ?>
                        <input type="hidden" name="force_logout" value="1">
                    <?php endif; ?>
                    <div class="brand-section">
                        <p class="text-white">Enter the 6-digit code from your authenticator app</p>
                    </div>
                    <div class="form-group">
                        <i class="fas fa-shield-alt field-icon"></i>
                        <input type="text" name="otp_code" class="input-field" placeholder="000000" maxlength="6" required autofocus>
                    </div>
                    <button type="submit" class="btn-submit">Verify 2FA</button>
                    <div class="footer-text">
                        <a href="login.php">← Back to login</a>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" name="username" class="input-field" placeholder="Username" required 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" name="password" class="input-field" placeholder="Password" required>
                    </div>

                    <div class="form-group">
                        <label class="custom-check">
                            <input type="checkbox" name="force_logout">
                            Force logout from other devices
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">Sign In</button>
                <?php endif; ?>
            </form>

            <div class="footer-text">
                Don't have an account? <a href="register.php">Create one</a>
            </div>
        </div>
    </div>
</body>
</html>