<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'user_dashboard.php'));
    exit();
}

$error = $success = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $referralCode = trim($_POST['referral_code']);
    
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($referralCode)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        try {
            $pdo = getDBConnection();
            $referredBy = null;
            $referralType = null;
            
            $stmt = $pdo->prepare("SELECT created_by FROM referral_codes WHERE code = ? AND status = 'active' AND expires_at > NOW()");
            $stmt->execute([$referralCode]);
            $adminReferral = $stmt->fetchColumn();
            
            if ($adminReferral) {
                $referredBy = $adminReferral;
                $referralType = 'admin';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'user'");
                $stmt->execute([$referralCode]);
                $referredBy = $stmt->fetchColumn();
                if ($referredBy) {
                    $referralType = 'user';
                }
            }
            
            if (!$referredBy) {
                $error = 'Invalid or expired referral code';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
                    $pdo->beginTransaction();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
                    
                    if ($referralType === 'admin') {
                        $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE code = ?");
                        $stmt->execute([$referralCode]);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET balance = balance + 50 WHERE id = ?");
                    $stmt->execute([$referredBy]);
                    $pdo->commit();
                    $success = 'Registration successful! Redirecting...';
                    header('refresh:2;url=login.php');
                }
            }
        } catch (Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $error = 'Registration failed';
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
    <link href="assets/css/mobile.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 25%, #4facfe 50%, #00f2fe 75%, #667eea 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        #pixelGrid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            opacity: 0.3;
        }

        .grid-pixel {
            position: absolute;
            width: 35px;
            height: 35px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            background: transparent;
            cursor: pointer;
            transition: all 0.12s ease;
            pointer-events: auto;
        }

        .grid-pixel:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .register-wrapper {
            position: relative;
            z-index: 100;
            width: 100%;
            max-width: 600px;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .register-card:hover {
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.25);
            transform: translateY(-5px);
        }

        .register-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 50px 30px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(20px); }
        }

        .user-plus-icon {
            font-size: 3.5rem;
            margin-bottom: 15px;
            display: inline-block;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .register-header h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
            position: relative;
            z-index: 2;
        }

        .register-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .register-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 0.95rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
            color: #333;
        }

        .form-control:focus {
            border-color: #f5576c;
            background: white;
            box-shadow: 0 0 0 4px rgba(245, 87, 108, 0.1);
            outline: none;
        }

        .form-control::placeholder {
            color: #999;
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

        .btn-register {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 700;
            color: white;
            width: 100%;
            font-size: 1rem;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(245, 87, 108, 0.3);
            margin-bottom: 25px;
            margin-top: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(245, 87, 108, 0.4);
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .register-footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .register-footer p {
            color: #666;
            font-size: 0.9rem;
            margin: 0;
        }

        .register-footer a {
            color: #f5576c;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .register-footer a:hover {
            color: #f093fb;
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            border: 2px solid #ff6b6b;
            background: #fff5f5;
            color: #c92a2a;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .alert-success {
            border-color: #51cf66;
            background: #f1fdf5;
            color: #2f8a44;
        }

        .theme-toggle {
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            color: #f5576c;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .theme-toggle:hover {
            transform: scale(1.1) rotate(20deg);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 600px) {
            .register-header { padding: 35px 25px 30px; }
            .register-body { padding: 30px 20px; }
            .register-header h1 { font-size: 1.8rem; }
            .user-plus-icon { font-size: 2.8rem; }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div id="pixelGrid"></div>
    <button class="theme-toggle" title="Toggle Dark Mode"><i class="fas fa-sun"></i></button>
    
    <div class="register-wrapper">
        <div class="register-card">
            <div class="register-header">
                <div class="user-plus-icon"><i class="fas fa-user-plus"></i></div>
                <h1>Create Account</h1>
                <p>Join SilentMultiPanel today</p>
            </div>
            
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" autocomplete="off">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user me-2"></i>Username</label>
                            <input type="text" class="form-control" name="username" placeholder="Choose username" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                            <input type="email" class="form-control" name="email" placeholder="Your email" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Min 6 characters" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm</label>
                            <input type="password" class="form-control" id="confirm" name="confirm_password" placeholder="Confirm password" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-row full">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-gift me-2"></i>Referral Code</label>
                            <input type="text" class="form-control" name="referral_code" placeholder="Enter your referral code" required autocomplete="off">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-register">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </form>
                
                <div class="register-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tapColors = [
            'linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%)',
            'linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%)',
            'linear-gradient(135deg, #ffd93d 0%, #f5a623 100%)',
            'linear-gradient(135deg, #a8e6cf 0%, #56ab2f 100%)',
            'linear-gradient(135deg, #ff8b94 0%, #ff6b6b 100%)',
            'linear-gradient(135deg, #74b9ff 0%, #0984e3 100%)',
            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
        ];

        function createGrid() {
            const grid = document.getElementById('pixelGrid');
            const size = 35, gap = 3;
            const cols = Math.ceil(window.innerWidth / (size + gap));
            const rows = Math.ceil(window.innerHeight / (size + gap));
            
            const activatePixel = (px) => {
                const col = tapColors[Math.floor(Math.random() * tapColors.length)];
                px.style.background = col;
                px.style.boxShadow = '0 0 20px rgba(245, 87, 108, 0.7)';
                px.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    px.style.background = 'transparent';
                    px.style.boxShadow = 'none';
                    px.style.transform = 'scale(1)';
                }, 700);
            };
            
            for (let i = 0; i < cols * rows; i++) {
                const px = document.createElement('div');
                px.className = 'grid-pixel';
                px.style.left = ((i % cols) * (size + gap) + 15) + 'px';
                px.style.top = (Math.floor(i / cols) * (size + gap) + 15) + 'px';
                
                px.addEventListener('click', (e) => {
                    e.stopPropagation();
                    activatePixel(px);
                }, false);
                
                px.addEventListener('touchstart', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    activatePixel(px);
                }, false);
                
                grid.appendChild(px);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', createGrid);
        } else {
            createGrid();
        }
    </script>
</body>
</html>
