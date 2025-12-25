<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

$pdo = getDBConnection();

// Get user statistics for dashboard
$userStats = [
    'total_users' => 0,
    'total_balance' => 0,
    'users_with_balance' => 0,
    'avg_balance' => 0
];

try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        COALESCE(SUM(balance), 0) as total_balance,
        COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance,
        COALESCE(AVG(balance), 0) as avg_balance
        FROM users WHERE role = 'user'");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $userStats;
} catch (Exception $e) {
    // Use defaults
}

// Pre-select user if provided in URL
$selectedUserId = $_GET['user_id'] ?? '';

// Get all users for dropdown
$allUsers = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, balance FROM users WHERE role = 'user' ORDER BY username");
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allUsers = [];
}

if ($_POST) {
    try {
        $userId = $_POST['user_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($userId) || $amount <= 0) {
            $error = 'Please select a user and enter a valid amount';
        } else {
            if (updateBalance($userId, $amount, 'balance_add', $reference)) {
                $success = 'Balance added successfully!';
                $selectedUserId = $userId;
            } else {
                $error = 'Failed to add balance';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User Balance - SilentMultiPanel</title>
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
                    <a class="nav-link active" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                    <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transaction</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <h2>Add User Balance</h2>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Users</div>
                            <h3><?php echo (int)($userStats['total_users'] ?? 0); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Balance</div>
                            <h3><?php echo formatCurrency($userStats['total_balance'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Add Balance Form -->
                <div class="form-card" style="background: white; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 500px;">
                    <h4 style="margin-bottom: 20px;">Add Balance</h4>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Select User</label>
                            <select name="user_id" class="form-control" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($allUsers as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username']); ?> (₹<?php echo number_format($user['balance'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" name="amount" step="0.01" min="0" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" name="reference" class="form-control" placeholder="Optional">
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="background: #8b5cf6; border: none;">Add Balance</button>
                    </form>
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