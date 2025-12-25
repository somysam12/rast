<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

try {
    $pdo = getDBConnection();
    $user = getUserData();
    
    // Convert SQLite syntax to MySQL if necessary
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as logins_today,
        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as logins_week,
        COUNT(*) as total_sessions
        FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $account_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($_POST) {
        if (isset($_POST['update_profile'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            if (empty($username) || empty($email)) {
                $error = 'Please fill in all fields';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user['id']]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    if ($stmt->execute([$username, $email, $user['id']])) {
                        $_SESSION['username'] = $username;
                        $success = 'Profile updated successfully!';
                        $user = getUserData();
                    }
                }
            }
        } elseif (isset($_POST['change_password'])) {
            $curr = $_POST['current_password'];
            $new = $_POST['new_password'];
            $conf = $_POST['confirm_password'];
            
            if ($new !== $conf) $error = 'New passwords do not match';
            elseif (strlen($new) < 6) $error = 'Password too short';
            elseif (!password_verify($curr, $user['password'])) $error = 'Current password incorrect';
            else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hash, $user['id']])) $success = 'Password changed!';
            }
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: #8b5cf6;"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'AD', 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus me-2"></i>Add Mod</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload me-2"></i>Upload APK</a>
                    <a class="nav-link" href="mod_list.php"><i class="fas fa-list me-2"></i>Mod List</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key me-2"></i>Add License</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                    <a class="nav-link active" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                    <hr>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Account Settings</h2>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card p-4" style="border-radius: 12px; border: 1px solid #e2e8f0; background: white;">
                            <h5>Profile Information</h5>
                            <form method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" style="background: #8b5cf6; border: none;">Update Profile</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card p-4" style="border-radius: 12px; border: 1px solid #e2e8f0; background: white;">
                            <h5>Change Password</h5>
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" style="background: #8b5cf6; border: none;">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    </script>
</body>
</html>