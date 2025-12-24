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
</head>
<body>
    <div class="container-fluid" style="display: flex; min-height: 100vh;">
        <!-- Sidebar -->
        <div class="sidebar" style="background: #fff; border-right: 1px solid #e0e0e0; width: 280px; padding: 20px;">
            <div style="margin-bottom: 30px;">
                <h5><i class="fas fa-crown me-2"></i>SilentMultiPanel</h5>
            </div>
            <nav class="nav flex-column gap-2">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                <a class="nav-link active" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt me-2"></i>Transaction</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div style="flex: 1; padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2>Add User Balance</h2>
                <div><span style="background: #7c3aed; color: white; padding: 8px 16px; border-radius: 20px;"><?php echo htmlspecialchars($_SESSION['username'] ?? 'admin'); ?></span></div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Stats Cards -->
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

            <!-- Add Balance Form -->
            <div style="background: white; padding: 30px; border-radius: 12px; border: 1px solid #e0e0e0; max-width: 500px;">
                <h4 style="margin-bottom: 20px;">Add Balance</h4>
                <form method="POST">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Select User</label>
                        <select name="user_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($allUsers as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?> (₹<?php echo number_format($user['balance'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Amount (₹)</label>
                        <input type="number" name="amount" step="0.01" min="0" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="0.00" required>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Reference</label>
                        <input type="text" name="reference" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Optional">
                    </div>
                    <button type="submit" style="background: #7c3aed; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; width: 100%; font-weight: 500;">Add Balance</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
