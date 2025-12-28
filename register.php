<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
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
        $error = 'All fields are required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 digits';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username or email already exists';
            } else {
                $stmt = $pdo->prepare("SELECT created_by, status, expires_at, bonus_amount, usage_limit, usage_count FROM referral_codes WHERE code = ?");
                $stmt->execute([$referralCode]);
                $refData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $referredBy = null;
                if ($refData && $refData['status'] === 'active') {
                    $referredBy = $refData['created_by'];
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'user' LIMIT 1");
                    $stmt->execute([$referralCode]);
                    $referredBy = $stmt->fetchColumn();
                }

                if (!$referredBy) {
                    $error = 'Invalid referral code';
                } else {
                    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
                    $userId = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by, logout_limit) VALUES (?, ?, 1)");
                    $stmt->execute([$userId, 1]);
                    
                    $pdo->commit();
                    $success = 'Account created successfully!';
                    header('refresh:2;url=login.php');
                }
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
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

        .register-wrapper { 
            width: 100%; 
            max-width: 520px; 
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
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: white;
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        .input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 12px 16px 12px 44px;
            color: white;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 12px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        .btn-submit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
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

        .alert-box { 
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

        .alert-error { 
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 1.5px solid rgba(239, 68, 68, 0.3); 
            color: #fca5a5; 
        }

        .alert-success { 
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            border: 1.5px solid rgba(16, 185, 129, 0.3); 
            color: #6ee7b7; 
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
                padding-top: 20px; 
            }
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <div class="glass-card">
            <div class="brand-section">
                <div class="brand-icon"><i class="fas fa-user-plus"></i></div>
                <h1>Create Account</h1>
                <p style="color: var(--text-dim); font-size: 14px; margin-top: 8px;">Join SilentMultiPanel today</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-box alert-error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-box alert-success"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" name="username" class="input-field" placeholder="Username" required 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-envelope field-icon"></i>
                            <input type="email" name="email" class="input-field" placeholder="Email" required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" name="password" class="input-field" placeholder="Password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-shield field-icon"></i>
                            <input type="password" name="confirm_password" class="input-field" placeholder="Confirm" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <i class="fas fa-gift field-icon"></i>
                    <input type="text" name="referral_code" class="input-field" placeholder="Referral Code (Mandatory)" required
                           value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn-submit">Register Now</button>
            </form>

            <div class="footer-text">
                Already have an account? <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>