<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

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
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username or email already exists';
            } else {
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
                    $success = 'Account created! Redirecting to login...';
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 25%, #4facfe 50%, #00f2fe 75%, #43e97b 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: fixed;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(245, 87, 108, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            top: -350px;
            right: -350px;
            animation: float1 20s ease-in-out infinite;
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(79, 172, 254, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -300px;
            left: -300px;
            animation: float2 25s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-100px, 100px); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(100px, -100px); }
        }

        .register-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 560px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 100px rgba(245, 87, 108, 0.1);
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

        .register-header {
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
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

        .plus-icon {
            font-size: 60px;
            margin-bottom: 20px;
            animation: plus-bounce 2s ease-in-out infinite;
            display: inline-block;
            position: relative;
            z-index: 1;
        }

        @keyframes plus-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .register-header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -1px;
            position: relative;
            z-index: 1;
        }

        .register-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 10px 0 0 0;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .register-body {
            padding: 45px 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            animation: formSlide 0.6s ease-out;
            animation-fill-mode: both;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }

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
            color: #f5576c;
            font-size: 14px;
            width: 20px;
            text-align: center;
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
            border-color: #f5576c;
            box-shadow: 0 0 0 4px rgba(245, 87, 108, 0.1), 0 10px 30px rgba(245, 87, 108, 0.2);
            transform: translateY(-2px);
        }

        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(245, 87, 108, 0.3);
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(245, 87, 108, 0.4);
        }

        .btn-register:active {
            transform: translateY(-1px);
        }

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

        .alert.success {
            border: 1px solid #9ae6b4;
            background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
            color: #22543d;
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

        .footer-text {
            text-align: center;
            font-size: 13px;
            color: #718096;
        }

        .footer-link {
            color: #f5576c;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .footer-link:hover {
            color: #f093fb;
            gap: 12px;
        }

        .referral-hint {
            font-size: 12px;
            color: #a0aec0;
            margin-top: 8px;
            padding: 10px 12px;
            background: rgba(245, 87, 108, 0.05);
            border-radius: 10px;
            border-left: 3px solid #f5576c;
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
            color: #f5576c;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .theme-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 40px rgba(245, 87, 108, 0.2);
        }

        @media (max-width: 768px) {
            .register-card {
                border-radius: 20px;
                margin: 0;
            }

            .register-header {
                padding: 40px 30px;
            }

            .register-body {
                padding: 30px;
            }

            .register-header h1 {
                font-size: 26px;
            }

            .plus-icon {
                font-size: 50px;
                margin-bottom: 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
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

    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="plus-icon">✨</div>
                <h1>Create Account</h1>
                <p>Join SilentMultiPanel Today</p>
            </div>
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="registerForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i>Username
                            </label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   placeholder="Choose a username" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>Email
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>Password
                            </label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Min 8 characters" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>Confirm Password
                            </label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Confirm password" required>
                        </div>
                    </div>

                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-gift"></i>Referral Code
                            </label>
                            <input type="text" name="referral_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>"
                                   placeholder="Enter referral code" required>
                            <div class="referral-hint">
                                <i class="fas fa-info-circle"></i> Referral code is required to create account
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus"></i>Create Account
                    </button>
                </form>

                <div class="footer-text">
                    Already have an account? 
                    <a href="login.php" class="footer-link">
                        Sign in
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('.btn-register');
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Creating account...';
            this.submit();
        });

        document.getElementById('themeToggle').addEventListener('click', function() {
            document.body.style.filter = document.body.style.filter === 'invert(1)' ? 'invert(0)' : 'invert(1)';
        });
    </script>
</body>
</html>