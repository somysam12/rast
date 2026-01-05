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
    <title>Reset Device - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        html, body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%) !important;
            background-attachment: fixed !important;
            width: 100%;
            height: 100%;
        }

        body { color: var(--text-main); overflow-x: hidden; position: relative; }

        .sidebar {
            position: fixed; left: 0; top: 0; width: 280px; height: 100vh;
            background: var(--card-bg); backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px); border-right: 1.5px solid var(--border-light);
            z-index: 1000; overflow-y: auto; transition: transform 0.3s ease; padding: 1.5rem 0;
        }

        .sidebar-brand { padding: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-brand h4 { background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800; font-size: 1.4rem; }
        
        .sidebar .nav { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 16px; border-radius: 12px; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; transform: translateX(4px); }

        .main-content { margin-left: 280px; padding: 1.5rem; min-height: 100vh; }

        .top-bar { display: none; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .hamburger-btn { background: var(--primary); border: none; color: white; padding: 10px 12px; border-radius: 12px; }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); border: 1.5px solid var(--border-light); border-radius: 32px; padding: 30px; max-width: 500px; margin: 0 auto; }

        .form-control { background: rgba(15, 23, 42, 0.5); border: 1.5px solid var(--border-light); border-radius: 12px; padding: 12px; color: white; }
        .form-control:focus { outline: none; border-color: var(--primary); background: rgba(15, 23, 42, 0.7); color: white; box-shadow: 0 0 15px rgba(139, 92, 246, 0.2); }

        .btn-reset { background: linear-gradient(135deg, #ef4444, #dc2626); border: none; border-radius: 12px; padding: 12px; color: white; font-weight: 700; width: 100%; transition: all 0.3s; }
        .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3); }

        .mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .mobile-overlay.show { display: block; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-bar { display: flex; }
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    <?php if (isLoggedIn() && isAdmin()): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand"><h4>SILENT PANEL</h4></div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link" href="admin_block_reset_requests.php"><i class="fas fa-ban"></i>Requests</a>
            <a class="nav-link active" href="reset_device.php"><i class="fas fa-sync"></i>Reset Device</a>
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <div class="top-bar">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <h4 style="margin: 0; font-weight: 800; background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">RESET DEVICE</h4>
            <div style="width: 44px;"></div>
        </div>

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

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        function toggle() { sidebar.classList.toggle('show'); overlay.classList.toggle('show'); }
        if (hamburgerBtn) hamburgerBtn.onclick = toggle;
        if (overlay) overlay.onclick = toggle;
    </script>
</body>
</html>