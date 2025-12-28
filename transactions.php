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
    $error = 'Failed to fetch users: ' . $e->getMessage();
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
    $error = 'Failed to fetch transactions: ' . $e->getMessage();
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
} catch (Exception $e) {
    // Use defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transactions - SilentMultiPanel</title>
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
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid" style="display: flex; min-height: 100vh;">
        <div style="background: #fff; border-right: 1px solid #e0e0e0; width: 280px; padding: 20px;">
            <h5 style="margin-bottom: 30px;"><i class="fas fa-crown me-2"></i>SilentMultiPanel</h5>
            <nav class="nav flex-column gap-2">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                <a class="nav-link" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                <a class="nav-link active" href="transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transaction</a>
            </nav>
        </div>

        <div style="flex: 1; padding: 30px;">
            <h2 style="margin-bottom: 30px;">Transaction History</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Transactions</div>
                    <h3><?php echo (int)($transactionStats['total_transactions'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Income</div>
                    <h3><?php echo formatCurrency($transactionStats['total_income'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Total Expenses</div>
                    <h3><?php echo formatCurrency($transactionStats['total_expenses'] ?? 0); ?></h3>
                </div>
                <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                    <div style="color: #888; font-size: 12px; margin-bottom: 8px;">Completed</div>
                    <h3><?php echo (int)($transactionStats['completed_transactions'] ?? 0); ?></h3>
                </div>
            </div>

            <div style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0;">
                <h4 style="margin-bottom: 20px;">Transaction Filters</h4>
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <input type="text" name="search" placeholder="Search username or reference..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <select name="user_id" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo isset($filters['user_id']) && $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" style="padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo $filters['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $filters['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $filters['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                    <button type="submit" style="background: var(--purple-dark); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">Apply Filters</button>
                    <a href="transactions.php" style="background: #666; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-align: center; text-decoration: none;">Reset Filters</a>
                </form>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                                <th style="padding: 12px; text-align: left;">Date</th>
                                <th style="padding: 12px; text-align: left;">User</th>
                                <th style="padding: 12px; text-align: left;">Amount</th>
                                <th style="padding: 12px; text-align: left;">Description</th>
                                <th style="padding: 12px; text-align: left;">Type</th>
                                <th style="padding: 12px; text-align: left;">Status</th>
                                <th style="padding: 12px; text-align: left;">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr style="border-bottom: 1px solid #e0e0e0;">
                                        <td style="padding: 12px;"><?php echo formatDate($transaction['created_at'] ?? ''); ?></td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($transaction['username'] ?? 'N/A'); ?></td>
                                        <td style="padding: 12px; font-weight: 500; <?php echo $transaction['amount'] < 0 ? 'color: #ef4444;' : 'color: #10b981;'; ?>">
                                            <?php echo ($transaction['amount'] < 0 ? '-' : '+') . formatCurrency(abs($transaction['amount'] ?? 0)); ?>
                                        </td>
                                        <td style="padding: 12px;">
                                            <div style="font-size: 0.9em; color: #333; font-weight: 500;"><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></div>
                                            <?php if (!empty($transaction['reference']) && strpos($transaction['reference'], 'License purchase #') === 0): ?>
                                                <?php 
                                                $keyId = str_replace('License purchase #', '', $transaction['reference']);
                                                $stmt = $pdo->prepare("SELECT license_key FROM license_keys WHERE id = ?");
                                                $stmt->execute([$keyId]);
                                                $lkey = $stmt->fetchColumn();
                                                if ($lkey): ?>
                                                    <div style="font-size: 0.8em; color: #8b5cf6; margin-top: 4px; font-family: monospace; background: #f3f0ff; padding: 2px 6px; border-radius: 4px; display: inline-block;">
                                                        <i class="fas fa-key me-1"></i><?php echo htmlspecialchars($lkey); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($transaction['type'] ?? ''); ?></td>
                                        <td style="padding: 12px;">
                                            <?php 
                                            $status = $transaction['status'] ?? 'unknown';
                                            if ($status == 'completed') {
                                                $bg = 'background: #d1fae5; color: #065f46;';
                                            } elseif ($status == 'pending') {
                                                $bg = 'background: #fef3c7; color: #92400e;';
                                            } else {
                                                $bg = 'background: #fee2e2; color: #7f1d1d;';
                                            }
                                            ?>
                                            <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; <?php echo $bg; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px;"><?php echo htmlspecialchars($transaction['reference'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="padding: 20px; text-align: center; color: #888;">No transactions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/scroll-restore.js"></script>
</body>
</html>
