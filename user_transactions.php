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

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    die("Database connection failed");
}

$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get user transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function formatCurrencyLocal($amount) {
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

if (!function_exists('formatDateLocal')) {
    function formatDateLocal($dt) {
        if (!$dt) { return '-'; }
        $date = new DateTime($dt, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $date->format('d M Y, h:i A');
    }
}

// Stats calculation
$totalTransactions = count($transactions);
$completedTransactions = 0;
$totalSpent = 0;
$totalAdded = 0;

foreach ($transactions as $tx) {
    if ($tx['status'] === 'completed') {
        $completedTransactions++;
        if ($tx['amount'] < 0) {
            $totalSpent += abs($tx['amount']);
        } else {
            $totalAdded += $tx['amount'];
        }
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
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

        .tx-card {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .tx-card:hover {
            background: rgba(139, 92, 246, 0.05);
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .tx-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.2rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .tx-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.2rem;
        }
        .tx-info-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .tx-info-value {
            font-weight: 600;
            color: #fff;
            font-size: 0.95rem;
        }
        .tx-badge {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .tx-badge.debit { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .tx-badge.credit { background: rgba(16, 185, 129, 0.1); color: #10b981; }

        .view-key-btn {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none !important;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .view-key-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
            color: white;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-mini-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            padding: 1.2rem;
            text-align: center;
        }
        .stat-mini-card i { font-size: 1.5rem; margin-bottom: 10px; display: block; }
        
        /* Scrollable container for many transactions */
        .tx-list-container {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 5px;
        }
        .tx-list-container::-webkit-scrollbar { width: 5px; }
        .tx-list-container::-webkit-scrollbar-track { background: transparent; }
        .tx-list-container::-webkit-scrollbar-thumb { background: rgba(139, 92, 246, 0.3); border-radius: 10px; }

        @media (max-width: 576px) {
            .tx-card { gap: 1rem; padding: 1rem; }
            .tx-icon { width: 40px; height: 40px; font-size: 1rem; }
            .tx-amount { font-size: 1rem; }
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
                <div class="text-secondary small">Balance: <?php echo formatCurrencyLocal($user['balance']); ?></div>
            </div>
            <div class="user-avatar-header" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link active" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Financial Records</h2>
            <p class="text-secondary mb-0">Track all your credits, debits, and purchases in one place.</p>
        </div>

        <div class="stats-row">
            <div class="stat-mini-card">
                <i class="fas fa-exchange-alt text-primary"></i>
                <div class="small text-secondary fw-bold">TOTAL LOGS</div>
                <div class="h5 text-white mb-0"><?php echo $totalTransactions; ?></div>
            </div>
            <div class="stat-mini-card">
                <i class="fas fa-arrow-up text-success"></i>
                <div class="small text-secondary fw-bold">TOTAL ADDED</div>
                <div class="h5 text-success mb-0"><?php echo formatCurrencyLocal($totalAdded); ?></div>
            </div>
            <div class="stat-mini-card">
                <i class="fas fa-arrow-down text-danger"></i>
                <div class="small text-secondary fw-bold">TOTAL SPENT</div>
                <div class="h5 text-danger mb-0"><?php echo formatCurrencyLocal($totalSpent); ?></div>
            </div>
        </div>

        <div class="cyber-card">
            <h5 class="mb-4 text-white"><i class="fas fa-list-ul text-primary me-2"></i> Transaction History</h5>
            
            <div class="tx-list-container">
                <?php if (empty($transactions)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-receipt text-secondary opacity-25 mb-3" style="font-size: 3rem;"></i>
                        <p class="text-secondary">No transaction records found yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <?php 
                        $isDebit = $tx['amount'] < 0;
                        $txType = $tx['type'] ?? 'transaction';
                        $isPurchase = stripos($tx['description'], 'purchase') !== false;
                        $productName = $tx['description'];
                        $keyId = null;
                        
                        if ($isPurchase) {
                            if (preg_match('/#(\d+)/', $tx['reference'], $matches)) {
                                $keyId = $matches[1];
                            }
                        }
                        ?>
                        <div class="tx-card">
                            <div class="tx-header">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="tx-badge <?php echo $isDebit ? 'debit' : 'credit'; ?>">
                                        <?php echo $isDebit ? 'Purchase' : 'Credit'; ?>
                                    </div>
                                    <div class="tx-info-value"><?php echo htmlspecialchars($productName ?: ucfirst($txType)); ?></div>
                                </div>
                                <div class="tx-amount <?php echo $isDebit ? 'negative' : 'positive'; ?>">
                                    <?php echo ($isDebit ? '-' : '+') . formatCurrencyLocal(abs($tx['amount'])); ?>
                                </div>
                            </div>
                            
                            <div class="tx-body">
                                <div>
                                    <div class="tx-info-label">Transaction Date</div>
                                    <div class="tx-info-value"><?php echo formatDateLocal($tx['created_at']); ?></div>
                                </div>
                                <div>
                                    <div class="tx-info-label">Reference ID</div>
                                    <div class="tx-info-value">#<?php echo htmlspecialchars($tx['id']); ?></div>
                                </div>
                                <div>
                                    <div class="tx-info-label">Status</div>
                                    <div class="tx-info-value text-success">
                                        <i class="fas fa-check-circle me-1"></i> Completed
                                    </div>
                                </div>
                                <div>
                                    <div class="tx-info-label">Payment Method</div>
                                    <div class="tx-info-value">Wallet Balance</div>
                                </div>
                            </div>
                            
                            <?php if ($isPurchase && $keyId): ?>
                                <div class="mt-3">
                                    <a href="user_manage_keys.php?key_id=<?php echo $keyId; ?>" class="view-key-btn">
                                        <i class="fas fa-key"></i> View License Key Details
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
    </script>
</body>
</html>