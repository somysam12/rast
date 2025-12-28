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
            --bg: #030712;
            --card-bg: rgba(17, 24, 39, 0.8);
            --text-main: #f9fafb;
            --text-dim: #9ca3af;
            --border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background-color: var(--bg);
            background-image: radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%), radial-gradient(at 0% 100%, rgba(124, 58, 237, 0.15) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .register-wrapper { width: 100%; max-width: 500px; padding: 20px; animation: slideUp 0.6s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 28px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-section { text-align: center; margin-bottom: 32px; }
        .brand-icon { width: 64px; height: 64px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 28px; box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3); }
        .brand-section h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; }

        .form-group { margin-bottom: 18px; position: relative; }
        .input-field {
            width: 100%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px 14px 48px;
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .input-field:focus { outline: none; border-color: var(--primary); background: rgba(255, 255, 255, 0.05); box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1); }
        .field-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 16px; }
        .form-group:focus-within .field-icon { color: var(--primary); }

        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); border: none; border-radius: 14px; padding: 14px; color: white; font-weight: 700; font-size: 16px; margin-top: 10px; cursor: pointer; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139, 92, 246, 0.4); }

        .footer-text { text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-dim); }
        .footer-text a { color: var(--primary); text-decoration: none; font-weight: 600; }

        .alert-box { padding: 12px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #6ee7b7; }

        @media (max-width: 480px) {
            .glass-card { padding: 30px 20px; border-radius: 0; background: transparent; border: none; box-shadow: none; }
            body { background: var(--bg); align-items: flex-start; padding-top: 20px; }
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