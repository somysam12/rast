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
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get all users for filter dropdown
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'user' ORDER BY username");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $users = [];
    }
} catch (Exception $e) {
    $users = [];
}

// Get transactions
try {
    if ($pdo) {
        $transactions = getAllTransactions($filters);
    } else {
        $transactions = [];
    }
} catch (Exception $e) {
    $transactions = [];
}

// Get transaction statistics
$transactionStats = [
    'total_transactions' => 0,
    'completed_transactions' => 0,
    'total_income' => 0,
    'total_expenses' => 0
];

try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_transactions,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
            COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_expenses
            FROM transactions");
        $transactionStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $transactionStats;
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transactions - SilentMultiPanel</title>
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
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                    <a class="nav-link active" href="transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transaction</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <h2 class="mb-4">Transaction History</h2>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Income</div>
                            <h3><?php echo formatCurrency($transactionStats['total_income'] ?? 0); ?></h3>
                        </div>
                    </div>
                </div>

                <div class="table-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo $transaction['created_at']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['username'] ?? 'N/A'); ?></td>
                                    <td style="<?php echo $transaction['amount'] < 0 ? 'color: #ef4444;' : 'color: #10b981;'; ?>">
                                        <?php echo ($transaction['amount'] < 0 ? '-' : '+') . formatCurrency(abs($transaction['amount'] ?? 0)); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                    <td><span class="badge bg-<?php echo $transaction['status'] === 'completed' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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