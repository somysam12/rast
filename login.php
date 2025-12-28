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
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.8);
            --text-main: #f9fafb;
            --text-dim: #9ca3af;
            --border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(124, 58, 237, 0.15) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 28px;
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        .brand-section h1 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .brand-section p {
            color: var(--text-dim);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-field {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            color: white;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }

        .field-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 18px;
            transition: color 0.3s;
        }

        .form-group:focus-within .field-icon {
            color: var(--primary);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 14px;
            padding: 14px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.4);
            filter: brightness(1.1);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-dim);
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-check {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-dim);
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 30px 20px;
                border-radius: 0;
                background: transparent;
                border: none;
                box-shadow: none;
            }
            body {
                background: var(--bg);
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
                    <input type="password" name="password" class="input-field" placeholder="Password" required>
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
</body>
</html>