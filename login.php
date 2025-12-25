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
            $error = 'You are already logged in from another device. Check the "Force logout from other devices" option to login from this device.';
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
    <link href="assets/css/main.css" rel="stylesheet">
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .login-container {
            width: 100%;
        }
        
        .login-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-large);
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            border-top: 4px solid var(--purple-light);
        }
        
        .login-header .logo {
            margin-bottom: 0;
        }
        
        .login-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .login-body {
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
        
        .btn-login {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            color: white;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, var(--purple-dark) 0%, var(--purple) 100%);
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
            box-shadow: var(--shadow-medium);
        }
        
        .register-link {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .register-link:hover {
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
        
        .info-card {
            background-color: #f1f5f9;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 1rem;
            border: 1px solid var(--border-light);
        }
        
        .info-card small {
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        
        .info-card strong {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .form-check {
            text-align: left;
        }
        
        .form-check-input {
            margin-top: 0.25rem;
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: var(--text-primary);
            cursor: pointer;
        }
        
        .form-text {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 10;
            transition: all 0.2s ease;
        }
        
        .form-control.with-icon {
            padding-left: 44px;
        }
        
        .form-control.with-icon:focus + .input-icon {
            color: var(--purple);
        }
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
            padding: 4px;
        }
        
        .password-toggle:hover {
            color: var(--purple);
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1050;
            background: var(--card) !important;
            border: 2px solid var(--line) !important;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            font-size: 20px;
            color: var(--text);
        }
        .theme-toggle:hover {
            transform: scale(1.1);
            color: var(--accent);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }
        .theme-toggle:active {
            transform: scale(0.95);
        }
        @media (max-width: 768px) {
            .theme-toggle {
                width: 50px !important;
                height: 50px !important;
                font-size: 18px !important;
                top: 12px !important;
                right: 12px !important;
                z-index: 1050 !important;
            }
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
        
        [data-theme="dark"] .login-card {
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
        
        [data-theme="dark"] .info-card {
            background-color: #334155;
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .alert-danger {
            background-color: #450a0a;
            border-color: #7f1d1d;
            color: #fca5a5;
        }
        
        @media (max-width: 768px) {
            .login-header {
                padding: 2rem 1.5rem;
            }
            
            .login-body {
                padding: 2rem 1.5rem;
            }
            
            .form-control {
                padding: 10px 14px;
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            .form-control.with-icon {
                padding-left: 40px;
            }
            
            .input-icon {
                left: 14px;
            }
            
            .password-toggle {
                right: 14px;
            }
            
            .btn-login {
                padding: 12px 20px;
            }
            
