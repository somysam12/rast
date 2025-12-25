<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$error = '';
$success = '';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = null;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_POST && $user) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';
    
    try {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $hash, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $email, $role, $user_id]);
        }
        $success = 'User updated successfully!';
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit User - SilentMultiPanel</title>
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
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <a href="manage_users.php" class="btn btn-outline-secondary mb-4">
                    <i class="fas fa-arrow-left me-2"></i>Back to Users
                </a>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($user): ?>
                <div class="card p-4" style="border-radius: 12px; border: 1px solid #e2e8f0; background: white; max-width: 600px;">
                    <h4 class="mb-4">Edit User: <?php echo htmlspecialchars($user['username']); ?></h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-control">
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters">
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background: #8b5cf6; border: none; padding: 12px;">Update User</button>
                    </form>
                </div>
                <?php else: ?>
                    <div class="alert alert-danger">User not found.</div>
                <?php endif; ?>
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