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
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-dark: #7c3aed;
            --border-light: #e2e8f0;
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .register-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-large);
            border: 1px solid var(--border-light);
            overflow: hidden;
            position: relative;
            z-index: 10;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .register-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .register-body {
            padding: 2.5rem 2rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid var(--border-light);
            padding: 12px 16px;
            background: var(--card-bg);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: var(--card-bg);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
            transition: all 0.2s ease;
        }
        
        .theme-toggle {
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
            color: var(--purple);
            box-shadow: var(--shadow-medium);
        }

        #pixelGrid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: 999;
            pointer-events: none;
        }

        .grid-pixel {
            position: absolute;
            width: 35px;
            height: 35px;
            border: 1.5px solid rgba(139, 92, 246, 0.2);
            border-radius: 5px;
            background: transparent;
            cursor: pointer;
            transition: all 0.12s ease;
            pointer-events: auto;
        }
        
        .register-card {
            position: relative;
            z-index: 1000;
        }
        
        .form-control, .btn, input, textarea, select {
            position: relative;
            z-index: 1001;
        }

        .grid-pixel:hover {
            background: linear-gradient(135deg, #ffffff 0%, #a78bfa 50%, #8b5cf6 100%);
            border-color: #8b5cf6;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.8);
            transform: scale(1.18) rotate(6deg);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --border-light: #334155;
        }
    </style>
</head>
<body>
    <div id="pixelGrid"></div>
    <button class="theme-toggle" onclick="toggleDarkMode()"><i class="fas fa-moon" id="darkModeIcon"></i></button>
    
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
                        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-user me-2"></i>Username *</label>
                                        <input type="text" class="form-control" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-envelope me-2"></i>Email *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-lock me-2"></i>Password *</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm *</label>
                                        <input type="password" class="form-control" id="confirm" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-gift me-2"></i>Referral Code *</label>
                                <input type="text" class="form-control" name="referral_code" placeholder="Enter code" required>
                            </div>
                            <button type="submit" class="btn btn-register w-100"><i class="fas fa-user-plus me-2"></i>Create Account</button>
                        </form>
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have account? <a href="login.php" style="color: var(--purple);">Login</a></p>
                        </div>
                    </div>
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
            'linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%)',
            'linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%)'
        ];

        function createGrid() {
            const grid = document.getElementById('pixelGrid');
            const size = 35, gap = 3;
            const cols = Math.ceil(window.innerWidth / (size + gap));
            const rows = Math.ceil(window.innerHeight / (size + gap));
            
            for (let i = 0; i < cols * rows; i++) {
                const px = document.createElement('div');
                px.className = 'grid-pixel';
                px.style.left = ((i % cols) * (size + gap) + 15) + 'px';
                px.style.top = (Math.floor(i / cols) * (size + gap) + 15) + 'px';
                
                px.addEventListener('mouseenter', function() {
                    this.style.background = 'linear-gradient(135deg, #ffffff 0%, #a78bfa 50%, #8b5cf6 100%)';
                    this.style.boxShadow = '0 0 15px rgba(139, 92, 246, 0.8)';
                });
                
                px.addEventListener('mouseleave', function() {
                    this.style.background = 'transparent';
                    this.style.boxShadow = 'none';
                });
                
                px.addEventListener('click', function() {
                    const col = tapColors[Math.floor(Math.random() * tapColors.length)];
                    this.style.background = col;
                    this.style.boxShadow = '0 0 25px rgba(139, 92, 246, 0.9)';
                    setTimeout(() => {
                        this.style.background = 'transparent';
                        this.style.boxShadow = 'none';
                    }, 800);
                });
                
                grid.appendChild(px);
            }
        }

        function toggleDarkMode() {
            const body = document.body;
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                document.getElementById('darkModeIcon').className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                document.getElementById('darkModeIcon').className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        if (localStorage.getItem('theme') === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
        
        document.addEventListener('DOMContentLoaded', createGrid);
    </script>
</body>
</html>
