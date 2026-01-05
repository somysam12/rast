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
                    
                    // Insert the user
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by, used_referral) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy, $referralCode]);
                    $userId = $pdo->lastInsertId();
                    
                    // Add bonus amount if applicable
                    if ($refData && isset($refData['bonus_amount']) && $refData['bonus_amount'] > 0) {
                        $bonus = $refData['bonus_amount'];
                        
                        // Update user balance
                        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$bonus, $userId]);
                        
                        // Log transaction for the user
                        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status, reference) VALUES (?, ?, 'credit', ?, 'completed', ?)");
                        $stmt->execute([$userId, $bonus, "Referral Bonus (Code: $referralCode)", "REF-$referralCode"]);
                        
                        // Update usage count for the referral code
                        $stmt = $pdo->prepare("UPDATE referral_codes SET usage_count = usage_count + 1 WHERE code = ?");
                        $stmt->execute([$referralCode]);
                    }
                    
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
            transition: all 0.3s ease;
        }

        .register-wrapper:hover {
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
            background: linear-gradient(135deg, #f8fafc, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-section p {
            color: var(--text-dim); 
            font-size: 14px;
            font-weight: 500;
            margin-top: 8px;
        }

        .form-group { 
            margin-bottom: 18px; 
            position: relative; 
        }

        .input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid var(--border-light);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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