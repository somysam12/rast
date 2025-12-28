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
    <link href="assets/css/global.css" rel="stylesheet">
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
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(40px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
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

        .password-toggle-wrapper {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            z-index: 10;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            color: var(--primary);
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

                <div class="form-group password-toggle-wrapper">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" name="password" class="input-field" placeholder="Password" required>
                    <button type="button" class="password-toggle-btn">
                        <i class="fas fa-eye-slash"></i>
                    </button>
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
    <script src="assets/js/password-toggle.js"></script>
</body>
</html>