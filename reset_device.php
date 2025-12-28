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
$success = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $resetResult = resetDevice($username, $password);
        if ($resetResult === 'success') {
            $success = 'Successfully logged out from all devices. You can now login from any device.';
        } else if ($resetResult === 'user_not_found') {
            $error = 'Username or email not found';
        } else if ($resetResult === 'invalid_password') {
            $error = 'Invalid password';
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
    <title>Reset Device - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .reset-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-large);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .reset-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .reset-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            border-top: 4px solid #fca5a5;
        }
        
        .reset-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .reset-body {
            padding: 2.5rem 2rem;
            background: var(--card-bg);
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid var(--border-light);
            padding: 12px 16px;
            transition: all 0.2s ease;
            background: var(--card-bg);
            font-size: 0.95rem;
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: var(--card-bg);
            outline: none;
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            color: white;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-reset:hover {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-reset:active {
            transform: translateY(0);
            box-shadow: var(--shadow-medium);
        }
        
        .login-link {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .login-link:hover {
            color: var(--purple-dark);
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .warning-info {
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 1.5rem;
        }
        
        .warning-info small {
            color: #92400e;
            font-size: 0.8rem;
        }
        
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
        
        [data-theme="dark"] .reset-card {
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
        
        [data-theme="dark"] .warning-info {
            background-color: #451a03;
            border-color: #92400e;
        }
        
        [data-theme="dark"] .warning-info small {
            color: #fbbf24;
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
            
            .reset-header {
                padding: 2rem 1.5rem;
            }
            
            .reset-body {
                padding: 2rem 1.5rem;
            }
            
            .form-control {
                padding: 10px 14px;
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            .btn-reset {
                padding: 12px 20px;
            }
            
                top: 16px;
                right: 16px;
                width: 40px;
                height: 40px;
            }
        }
    </style>
    <link href="assets/css/dark-mode-button.css" rel="stylesheet">
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <!-- Theme Toggle -->
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="reset-card">
                    <div class="reset-header">
                        <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                        <h3>Reset Device</h3>
                        <p class="mb-0">Logout from all devices</p>
                    </div>
                    <div class="reset-body">
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
                        
                        <div class="warning-info">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Warning:</strong> This will logout your account from all devices. You'll need to login again on any device you want to use.
                            </small>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Username or Email *
                                </label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                       placeholder="Enter your username or email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Password *
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-reset w-100 mb-4">
                                <i class="fas fa-mobile-alt me-2"></i>Reset All Devices
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-3">Remember your login? 
                                <a href="login.php" class="login-link">Sign in here</a>
                            </p>
                            
                            <p class="mb-0">Don't have an account? 
                                <a href="register.php" class="login-link">Create one here</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode functionality
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
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>