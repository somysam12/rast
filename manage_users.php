<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Create force_logouts table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS force_logouts (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        logged_out_by INTEGER NOT NULL,
        logout_limit INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Handle delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
        if ($stmt->execute([$_GET['delete']])) {
            $success = 'User deleted successfully!';
        } else {
            $error = 'Failed to delete user.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting user: ' . $e->getMessage();
    }
}

// Get all users with force logout counts
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT u.*, 
                            COUNT(fl.id) as force_logout_count,
                            COALESCE(fl.logout_limit, 0) as logout_limit
                            FROM users u 
                            LEFT JOIN force_logouts fl ON u.id = fl.user_id
                            WHERE u.role IN ('user', 'reseller')
                            GROUP BY u.id
                            ORDER BY u.created_at DESC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $users = [];
    }
} catch (Exception $e) {
    $users = [];
    $error = 'Failed to fetch users: ' . $e->getMessage();
}

// Get user statistics
$userStats = [
    'total_users' => 0,
    'total_balance' => 0,
    'users_with_balance' => 0,
    'avg_balance' => 0
];

try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_users,
            COALESCE(SUM(balance), 0) as total_balance,
            COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance,
            COALESCE(AVG(balance), 0) as avg_balance
            FROM users WHERE role = 'user'");
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $userStats;
    }
} catch (Exception $e) {
    // Use defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --border-light: #e2e8f0;
        }
    </style>
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: var(--purple);"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'AD', 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>

    <div class="container-fluid" style="display: flex; min-height: 100vh;">
        <div class="sidebar" id="sidebar" style="background: #fff; border-right: 1px solid #e0e0e0; width: 280px; padding: 20px;">
            <h5 style="margin-bottom: 30px;"><i class="fas fa-crown me-2"></i>SilentMultiPanel</h5>
            <nav class="nav flex-column gap-2">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                <a class="nav-link" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                <a class="nav-link active" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transaction</a>
            </nav>
        </div>

        <div style="flex: 1; padding: 30px;">
            <h2 style="margin-bottom: 30px;">User Management</h2>

            <?php if (isset($success)): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #7f1d1d; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Users</div>
                    <h3><?php echo (int)($userStats['total_users'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Balance</div>
                    <h3><?php echo formatCurrency($userStats['total_balance'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Active Wallets</div>
                    <h3><?php echo (int)($userStats['users_with_balance'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Avg Balance</div>
                    <h3><?php echo formatCurrency($userStats['avg_balance'] ?? 0); ?></h3>
                </div>
            </div>

            <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                <h4 style="margin-bottom: 20px;">Manage User Accounts</h4>

                <?php if (!empty($users)): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                    <th style="padding: 12px; text-align: left;">ID</th>
                                    <th style="padding: 12px; text-align: left;">Username</th>
                                    <th style="padding: 12px; text-align: left;">Email</th>
                                    <th style="padding: 12px; text-align: left;">Balance</th>
                                    <th style="padding: 12px; text-align: left;">Role</th>
                                    <th style="padding: 12px; text-align: left;">Join Date</th>
                                    <th style="padding: 12px; text-align: left;">Reset Limit (24h)</th>
                                    <th style="padding: 12px; text-align: left;">Usage (24h)</th>
                                    <th style="padding: 12px; text-align: left;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['id'] ?? ''); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                        <td style="padding: 12px; font-weight: 500;"><?php echo formatCurrency($user['balance'] ?? 0); ?></td>
                                        <td style="padding: 12px;"><span style="background: #8b5cf6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;"><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'user')); ?></span></td>
                                        <td style="padding: 12px;"><?php echo formatDate($user['created_at'] ?? ''); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($user['logout_limit'] ?? 0); ?></td>
                                        <td style="padding: 12px; font-weight: 600; color: #ef4444;"><?php echo htmlspecialchars($user['force_logout_count'] ?? 0); ?>/<?php echo htmlspecialchars($user['logout_limit'] ?? 0); ?></td>
                                        <td style="padding: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" style="display: inline-flex; align-items: center; gap: 6px; background: #8b5cf6; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#7c3aed'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#8b5cf6'; this.style.transform='translateY(0)';"><i class="fas fa-edit"></i>Edit</a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>#balance" style="display: inline-flex; align-items: center; gap: 6px; background: #10b981; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#059669'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#10b981'; this.style.transform='translateY(0)';"><i class="fas fa-wallet"></i>Balance</a>
                                            <a href="force_logout.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Force logout this user?');" style="display: inline-flex; align-items: center; gap: 6px; background: #f59e0b; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#d97706'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#f59e0b'; this.style.transform='translateY(0)';"><i class="fas fa-sign-out-alt"></i>Logout</a>
                                            <a href="?delete=<?php echo $user['id']; ?>" onclick="return confirm('Delete this user?');" style="display: inline-flex; align-items: center; gap: 6px; background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: 600; transition: all 0.2s ease;" onmouseover="this.style.background='#dc2626'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#ef4444'; this.style.transform='translateY(0)';"><i class="fas fa-trash"></i>Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #888; padding: 40px;">No user accounts have been registered yet.</p>
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
