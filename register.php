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
        $error = 'Please fill in all required fields including Referral Code';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 digits long';
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
                $bonusAmount = 50.00; // Default bonus amount for user referrals
                
                    // First check admin-generated referral codes
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
                        // Check user-generated referral codes
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'user' LIMIT 1");
                        $stmt->execute([$referralCode]);
                        $referredBy = $stmt->fetchColumn();
                        if ($referredBy) {
                            $referralType = 'user';
                        } else {
                            $error = 'Wrong referral code! Please enter a valid code.';
                        }
                    }
                
                if (!$error) {
                    // Generate unique referral code for new user
                    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    $pdo->beginTransaction();
                    
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Set default force logout limit to 1 for new users
                    $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by, logout_limit) VALUES (?, ?, 1)");
                    $stmt->execute([$userId, 1]);
                    
                    // Handle referral bonuses only if referral code was used
                    if ($referredBy) {
                        $bonusToGive = $bonusAmount ?? 50.00;
                        
                        // Update usage count and deactivate if limit reached
                        if ($referralType === 'admin') {
                            $stmt = $pdo->prepare("UPDATE referral_codes SET usage_count = usage_count + 1 WHERE code = ?");
                            $stmt->execute([$referralCode]);
                            
                            $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE code = ? AND usage_count >= usage_limit");
                            $stmt->execute([$referralCode]);
                        } else if ($referralType === 'user') {
                            // For user codes, generate new code for referrer
                            $newUserReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
                            $stmt->execute([$newUserReferralCode, $referredBy]);
                        }
                        
                        // Give bonus to NEW USER
                        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$bonusToGive, $userId]);
                        
                        // Record referral transaction for NEW USER
                        try {
                            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'balance_add', ?, 'Referral signup bonus', CURRENT_TIMESTAMP)");
                            $stmt->execute([$userId, $bonusToGive]);
                        } catch (Exception $e) {
                            // Ignore transaction errors
                        }
                        
                        // Also give bonus to referrer if they earned a reward
                        if ($referralType === 'admin') {
                            $referrerBonus = $bonusToGive * 0.5; // 50% of what new user gets
                            $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                            $stmt->execute([$referrerBonus, $referredBy]);
                            
                            // Record referrer bonus transaction
                            try {
                                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'balance_add', ?, 'Referral reward for user signup', CURRENT_TIMESTAMP)");
                                $stmt->execute([$referredBy, $referrerBonus]);
                            } catch (Exception $e) {
                                // Ignore transaction errors
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $success = 'Registration successful! You can now login.';
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
    <title>Register - SilentMultiPanel Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: rgba(255, 255, 255, 0.1);
            --shadow-large: 0 0 20px rgba(139, 92, 246, 0.3);
            --glow-color: rgba(139, 92, 246, 0.5);
        }

        body {
            background: radial-gradient(circle at top right, #1e1b4b, #0f172a);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 3rem 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
        }
        
        .register-container {
            width: 100%;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .register-card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            box-shadow: var(--shadow-large);
            border: 2px solid var(--border-light);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--purple), transparent, var(--purple-dark));
            z-index: -1;
            opacity: 0.3;
            transition: opacity 0.4s;
        }
        
        .register-card:hover {
            transform: translateY(-5px);
            border-color: var(--purple-light);
            box-shadow: 0 0 30px var(--glow-color);
        }

        .register-card:hover::before {
            opacity: 0.6;
        }
        
        .register-header {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(124, 58, 237, 0.2) 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }
        
        .register-header h3 {
            font-weight: 800;
            font-size: 1.75rem;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, #fff, var(--purple-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .register-body {
            padding: 3rem 2.5rem;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid var(--border-light);
            padding: 14px 18px;
            transition: all 0.3s;
            background: rgba(15, 23, 42, 0.5);
            font-size: 1rem;
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
            background: rgba(15, 23, 42, 0.8);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 1rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.4);
            filter: brightness(1.1);
        }
        
        .login-link {
            color: var(--purple-light);
            text-decoration: none;
            font-weight: 700;
            transition: all 0.2s;
        }
        
        .login-link:hover {
            color: white;
            text-shadow: 0 0 10px var(--purple-light);
        }

        .referral-info {
            background: rgba(139, 92, 246, 0.1);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            margin-top: 1rem;
        }
    </style>
        
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
        
            color: var(--purple);
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
        }
        
        /* Dark theme styles */
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        [data-theme="dark"] .register-card {
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .form-control {
            background: var(--card-bg);
            border-color: var(--border-light);
            color: var(--text-primary);
        }
        
        [data-theme="dark"] .form-control::placeholder {
            color: var(--text-secondary);
        }
        
        [data-theme="dark"] .referral-info {
            background-color: #334155;
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .alert-danger {
            background-color: #450a0a;
            border-color: #7f1d1d;
            color: #fca5a5;
        }
        
        [data-theme="dark"] .alert-success {
            background-color: #052e16;
            border-color: #166534;
            color: #86efac;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .register-header {
                padding: 2rem 1.5rem;
            }
            
            .register-body {
                padding: 2rem 1.5rem;
            }
            
            .form-control {
                padding: 10px 14px;
                font-size: 16px;
            }
            
            .btn-register {
                padding: 12px 20px;
            }
            
                top: 16px;
                right: 16px;
                width: 40px;
                height: 40px;
            }
            
            .col-md-6 {
                margin-bottom: 0;
            }
        }
    </style>
    <link href="assets/css/dark-mode-button.css" rel="stylesheet">
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
</head>
<body>
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <div class="register-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card">
                    <div class="register-header">
                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                        <h3>Create Account</h3>
                        <p class="mb-0">Join SilentMultiPanel today</p>
                    </div>
                    <div class="register-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user me-2"></i>Username *
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email *
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Confirm Password *
                                        </label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="referral_code" class="form-label">
                                    <i class="fas fa-gift me-2"></i>Referral Code *
                                </label>
                                <input type="text" class="form-control" id="referral_code" name="referral_code" 
                                       value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>" 
                                       placeholder="Enter referral code" required>
                                <div class="referral-info">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Referral code is mandatory. Please enter a valid code to proceed.
                                    </small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-register w-100">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? 
                                <a href="login.php" class="login-link">Login here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
    </script>
</body>
</html>
