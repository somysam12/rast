<?php
require_once 'includes/auth.php';

// Redirect if already logged in (standard login check)
if (isLoggedIn() && !isAdmin()) {
    header('Location: user_dashboard.php');
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Device - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(255, 255, 255, 0.1);
        }
        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%) !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .reset-wrapper { width: 100%; max-width: 500px; z-index: 1; }
        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.15);
        }
        .form-control {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: 12px;
            color: white;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.7);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
        }
        .btn-reset {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            border-radius: 12px;
            padding: 12px;
            color: white;
            font-weight: 700;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }
        
        /* Sidebar styles for Admin */
        .sidebar {
            position: fixed; left: 0; top: 0; width: 280px; height: 100vh;
            background: var(--card-bg); backdrop-filter: blur(30px);
            border-right: 1.5px solid var(--border-light); z-index: 1000;
            transition: transform 0.3s ease; transform: translateX(-280px);
        }
        .sidebar.show { transform: translateX(0); }
        @media (min-width: 993px) {
            .sidebar { transform: translateX(0); }
            .reset-wrapper { margin-left: 280px; }
            .hamburger-menu { display: none !important; }
        }
        .hamburger-menu {
            position: fixed; top: 20px; left: 20px; z-index: 1100;
            background: var(--primary); color: white; border: none;
            padding: 10px 15px; border-radius: 10px; cursor: pointer;
        }
        .mobile-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 999; display: none;
        }
        .mobile-overlay.show { display: block; }
    </style>
</head>
<body>
    <?php if (isLoggedIn() && isAdmin()): ?>
        <?php include 'includes/admin_header.php'; ?>
        <div class="mobile-overlay" id="mobile-overlay"></div>
        <button class="hamburger-menu" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="sidebar" id="sidebar">
            <div style="padding: 2rem 1rem;">
                <h4 style="font-weight: 800; color: var(--primary); text-align: center;">SILENT PANEL</h4>
                <nav class="nav flex-column mt-4">
                    <a class="nav-link" href="admin_dashboard.php" style="color: var(--text-dim); padding: 12px; text-decoration: none;"><i class="fas fa-home me-2"></i>Dashboard</a>
                    <a class="nav-link" href="referral_codes.php" style="color: var(--text-dim); padding: 12px; text-decoration: none;"><i class="fas fa-tag me-2"></i>Referral Codes</a>
                    <a class="nav-link" href="admin_block_reset_requests.php" style="color: var(--text-dim); padding: 12px; text-decoration: none;"><i class="fas fa-ban me-2"></i>Requests</a>
                    <a class="nav-link active" href="reset_device.php" style="background: var(--primary); color: white; border-radius: 12px; padding: 12px; text-decoration: none;"><i class="fas fa-sync me-2"></i>Reset Device</a>
                    <a class="nav-link" href="logout.php" style="color: #ef4444; padding: 12px; text-decoration: none;"><i class="fas fa-sign-out me-2"></i>Logout</a>
                </nav>
            </div>
        </div>
    <?php endif; ?>

    <div class="reset-wrapper">
        <div class="glass-card">
            <div class="text-center mb-4">
                <i class="fas fa-mobile-alt fa-3x text-danger mb-3"></i>
                <h2 style="font-weight: 800;">Reset Device</h2>
                <p style="color: var(--text-dim);">Logout from all devices</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-dim">Username or Email</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-dim">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn-reset">Reset All Devices</button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="assets/js/menu-logic.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        if (document.getElementById('mobile-overlay')) {
            document.getElementById('mobile-overlay').onclick = toggleSidebar;
        }
    </script>
</body>
</html>