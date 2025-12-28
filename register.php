<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Redirect if already logged in
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $referralCode = trim($_POST['referral_code'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($referralCode)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username or email already exists';
            } else {
                // Check if referral code is provided and valid
                $referredBy = null;
                $referralType = null;
                $bonusAmount = 50.00;
                
                $stmt = $pdo->prepare("SELECT created_by, status, expires_at, bonus_amount, usage_limit, usage_count FROM referral_codes WHERE code = ?");
                $stmt->execute([$referralCode]);
                $refData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($refData) {
                    if ($refData['status'] !== 'active') {
                        $error = 'This referral code has already been used or is inactive';
                    } elseif ($refData['expires_at'] !== null && strtotime($refData['expires_at']) < time()) {
                        $error = 'This referral code has expired';
                    } elseif (!empty($refData['usage_limit']) && !empty($refData['usage_count']) && $refData['usage_count'] >= $refData['usage_limit']) {
                        $error = 'This referral code usage limit has been reached';
                    } else {
                        $referredBy = $refData['created_by'];
                        $referralType = 'admin';
                        $bonusAmount = $refData['bonus_amount'] ?? 50.00;
                    }
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'user' LIMIT 1");
                    $stmt->execute([$referralCode]);
                    $referredBy = $stmt->fetchColumn();
                    if ($referredBy) {
                        $referralType = 'user';
                    } else {
                        $error = 'Invalid referral code';
                    }
                }
                
                if (!$error) {
                    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    $pdo->beginTransaction();
                    
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by, logout_limit) VALUES (?, ?, 1)");
                    $stmt->execute([$userId, 1]);
                    
                    if ($referredBy) {
                        $bonusToGive = $bonusAmount ?? 50.00;
                        
                        if ($referralType === 'admin') {
                            $stmt = $pdo->prepare("UPDATE referral_codes SET usage_count = usage_count + 1 WHERE code = ?");
                            $stmt->execute([$referralCode]);
                            
                            $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE code = ? AND usage_count >= usage_limit");
                            $stmt->execute([$referralCode]);
                        } else if ($referralType === 'user') {
                            $newUserReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                            $stmt->execute([$newUserReferralCode, $referredBy]);
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$bonusToGive, $userId]);
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'balance_add', ?, 'Referral signup bonus', CURRENT_TIMESTAMP)");
                            $stmt->execute([$userId, $bonusToGive]);
                        } catch (Exception $e) {}
                        
                        if ($referralType === 'admin') {
                            $referrerBonus = $bonusToGive * 0.5;
                            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                            $stmt->execute([$referrerBonus, $referredBy]);
                            
                            try {
                                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'balance_add', ?, 'Referral reward for user signup', CURRENT_TIMESTAMP)");
                                $stmt->execute([$referredBy, $referrerBonus]);
                            } catch (Exception $e) {}
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Registration successful! Redirecting to login...';
                    header('refresh:2;url=login.php');
                }
            }
        } catch (Exception $e) {
            if (isset($pdo)) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
            }
            $error = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

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

        .register-wrapper {
            width: 100%;
            max-width: 550px;
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

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .register-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .register-header {
            background: var(--gradient-primary);
            color: white;
            padding: 3rem 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
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

        .register-header .icon-wrapper {
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

        .register-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .register-header p {
            opacity: 0.95;
            font-size: 0.95rem;
            font-weight: 500;
            margin: 0;
        }

        .register-body {
            padding: 2.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            animation: slideInForm 0.6s ease-out;
            animation-fill-mode: both;
        }

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

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .referral-info {
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            border: 1px solid #e0e7ff;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            gap: 0.75rem;
        }

        .referral-info i {
            color: #8b5cf6;
            min-width: 18px;
        }

        .btn-register {
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

        .btn-register:hover {
            background: var(--gradient-primary-hover);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .btn-register:active {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }

        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .auth-footer {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
        }

        .auth-footer p {
            margin: 0;
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
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }

        [data-theme="dark"] body {
            background: linear-gradient(135deg, #0f172a 0%, #1a1f3a 100%);
        }

        [data-theme="dark"] .register-card {
            background: #1e293b;
            border-color: #334155;
        }

        [data-theme="dark"] .form-control {
            background: #0f172a;
            border-color: #334155;
            color: var(--text-primary);
        }

        [data-theme="dark"] .referral-info {
            background: linear-gradient(135deg, #334155 0%, #3d4558 100%);
            border-color: #475569;
        }

        [data-theme="dark"] .alert-danger {
            background: linear-gradient(135deg, #450a0a 0%, #5f0f0f 100%);
            border-left-color: #dc2626;
        }

        [data-theme="dark"] .alert-success {
            background: linear-gradient(135deg, #052e16 0%, #166534 100%);
            border-left-color: #16a34a;
        }

        [data-theme="dark"] .theme-toggle {
            background: #1e293b;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        @media (max-width: 768px) {
            .register-wrapper {
                max-width: 100%;
            }

            .register-card {
                margin: 0 -1rem;
                border-radius: 24px 24px 0 0;
            }

            .register-header {
                padding: 2.5rem 2rem;
            }

            .register-body {
                padding: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .btn-register {
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
</head>
<body>
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>

    <div class="register-wrapper">
        <div class="register-card">
            <div class="register-header">
                <div class="icon-wrapper">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Create Account</h2>
                <p>Join SilentMultiPanel today</p>
            </div>
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i>Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="Choose a username" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope"></i>Email
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i>Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   placeholder="Min 8 characters" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i>Confirm Password
                            </label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   placeholder="Confirm password" required>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label for="referral_code" class="form-label">
                                <i class="fas fa-gift"></i>Referral Code
                            </label>
                            <input type="text" class="form-control" id="referral_code" name="referral_code"
                                   value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>"
                                   placeholder="Enter referral code" required>
                            <div class="referral-info">
                                <i class="fas fa-info-circle"></i>
                                <div>Referral code is required to create an account</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i>Create Account
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Already have an account?
                        <a href="login.php" class="auth-link">
                            Sign in here
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </p>
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

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = form.querySelector('.btn-register');
            
            // Basic validation
            if (form.checkValidity() === false) {
                e.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Creating account...';
            form.submit();
        });
    </script>
</body>
</html>