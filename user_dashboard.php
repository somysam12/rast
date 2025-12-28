<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$stats = ['total_purchases' => 0, 'total_spent' => 0];
try {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) as total_purchases, COALESCE(SUM(price), 0) as total_spent FROM license_keys WHERE sold_to = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_purchases' => 0, 'total_spent' => 0];
} catch (Exception $e) {}

try {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $recentTransactions = []; }

function formatCurrency($amount) { return '₹' . number_format($amount, 2); }
function formatDate($date) { return $date ? date('d M Y H:i', strtotime($date)) : 'N/A'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <style>
        body { padding-top: 60px; }
        .sidebar { width: 260px; position: fixed; top: 60px; bottom: 0; left: 0; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: 260px; padding: 2rem; transition: margin-left 0.3s ease; }
        .header { height: 60px; position: fixed; top: 0; left: 0; right: 0; z-index: 1001; background: rgba(5,7,10,0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }

        /* Activity List Styling */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .activity-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateX(5px);
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .activity-icon.debit {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .activity-icon.credit {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        .activity-details h6 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: #fff;
        }
        .activity-details p {
            margin: 0;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
        }
        .activity-amount {
            text-align: right;
        }
        .activity-amount .amount {
            display: block;
            font-weight: 700;
            font-size: 1rem;
        }
        .activity-amount .date {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn text-white p-0 d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 text-neon fw-bold">SilentMultiPanel</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">User</div>
            </div>
            <div class="user-avatar-header" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link active" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
            <p class="text-secondary mb-0">Manage your mod keys and balance from your futuristic dashboard.</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-4">
                <div class="cyber-card">
                    <div class="text-secondary small fw-bold mb-1">CURRENT BALANCE</div>
                    <h2 class="m-0 text-white"><?php echo formatCurrency($user['balance']); ?></h2>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="cyber-card">
                    <div class="text-secondary small fw-bold mb-1">TOTAL PURCHASES</div>
                    <h2 class="m-0 text-white"><?php echo $stats['total_purchases']; ?></h2>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="cyber-card">
                    <div class="text-secondary small fw-bold mb-1">TOTAL SPENT</div>
                    <h2 class="m-0 text-white"><?php echo formatCurrency($stats['total_spent']); ?></h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="cyber-card h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="m-0"><i class="fas fa-history text-primary me-2"></i> Recent Activity</h5>
                        <a href="user_transactions.php" class="small text-primary text-decoration-none">View All</a>
                    </div>
                    
                    <div class="activity-list">
                        <?php if (empty($recentTransactions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-receipt text-secondary mb-3" style="font-size: 2rem; opacity: 0.2;"></i>
                                <p class="text-secondary m-0">No recent transactions to show.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $tx): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="activity-icon <?php echo $tx['type'] == 'add' || $tx['type'] == 'credit' ? 'credit' : 'debit'; ?>">
                                            <i class="fas <?php echo $tx['type'] == 'add' || $tx['type'] == 'credit' ? 'fa-arrow-down' : 'fa-shopping-cart'; ?>"></i>
                                        </div>
                                        <div class="activity-details">
                                            <h6><?php echo htmlspecialchars($tx['description'] ?: ucfirst($tx['type'])); ?></h6>
                                            <p><?php echo htmlspecialchars($tx['reference'] ?: 'No reference'); ?></p>
                                        </div>
                                    </div>
                                    <div class="activity-amount">
                                        <span class="amount <?php echo $tx['type'] == 'add' || $tx['type'] == 'credit' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($tx['type'] == 'add' || $tx['type'] == 'credit' ? '+' : '-') . formatCurrency(abs($tx['amount'])); ?>
                                        </span>
                                        <span class="date"><?php echo date('d M, h:i A', strtotime($tx['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="cyber-card h-100">
                    <h5 class="mb-4"><i class="fas fa-bolt text-secondary me-2"></i> Quick Actions</h5>
                    <div class="d-grid gap-3">
                        <a href="user_generate.php" class="cyber-btn"><i class="fas fa-plus"></i> Generate New Key</a>
                        <a href="user_applications.php" class="cyber-btn" style="background: rgba(255,255,255,0.05) !important; box-shadow: none !important; border: 1px solid rgba(255,255,255,0.1) !important;">
                            <i class="fas fa-mobile-alt"></i> Download Applications
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
    </script>
</body>
</html>
