<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

function formatCurrency($amount){
    return '₹' . number_format((float)$amount, 2, '.', ',');
}
function formatDate($dt){
    if(!$dt){ return '-'; }
    return date('d M Y, h:i A', strtotime($dt));
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    die("Database connection failed");
}

$stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if(!$user){
    session_destroy();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle key purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_key'])) {
    $keyId = (int)($_POST['key_id'] ?? 0);
    if ($keyId <= 0) {
        $error = 'Invalid key.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, mod_id, price FROM license_keys WHERE id = ? AND sold_to IS NULL LIMIT 1');
            $stmt->execute([$keyId]);
            $key = $stmt->fetch();
            if(!$key){
                throw new Exception('This key is no longer available.');
            }

            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            $currentBalance = (float)$row['balance'];
            $price = (float)$key['price'];
            if ($currentBalance < $price) {
                throw new Exception('Insufficient balance. Need ₹' . number_format($price, 2) . ', have ₹' . number_format($currentBalance, 2));
            }

            $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$price, $user['id']]);

            $stmt = $pdo->prepare('UPDATE license_keys SET sold_to = ?, sold_at = NOW() WHERE id = ?');
            $stmt->execute([$user['id'], $keyId]);

            try {
                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, "debit", ?, "License key purchase", NOW())');
                $stmt->execute([$user['id'], $price]);
            } catch (Throwable $ignored) {}

            $pdo->commit();
            $success = 'License key purchased successfully!';

            $stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
}

// Get available keys
$availableKeys = [];
try {
    $stmt = $pdo->query('SELECT lk.id, lk.mod_id, lk.duration, lk.duration_type, lk.price, m.name AS mod_name
                          FROM license_keys lk
                          LEFT JOIN mods m ON m.id = lk.mod_id
                          WHERE lk.sold_to IS NULL
                          ORDER BY lk.id DESC LIMIT 50');
    $availableKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $availableKeys = [];
}

// Get user's purchased keys
$purchasedKeys = [];
try {
    $stmt = $pdo->prepare('SELECT lk.*, m.name AS mod_name
                           FROM license_keys lk
                           LEFT JOIN mods m ON m.id = lk.mod_id
                           WHERE lk.sold_to = ?
                           ORDER BY lk.sold_at DESC');
    $stmt->execute([$user['id']]);
    $purchasedKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $purchasedKeys = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate - Mod APK Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        :root { --bg: #f8fafc; --sidebar-bg: #fff; --purple: #8b5cf6; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; }
        * { font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .sidebar { background: var(--sidebar-bg); border-right: 1px solid var(--border); position: fixed; width: 280px; height: 100vh; left: 0; top: 0; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-link { color: var(--muted); padding: 12px 20px; margin: 4px 16px; border-radius: 8px; transition: all 0.2s; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: var(--text); }
        .sidebar .nav-link.active { background: var(--purple); color: white; }
        .sidebar .nav-link i { width: 20px; margin-right: 12px; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .page-header { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .page-header h2 { color: var(--purple); font-weight: 600; }
        .table-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .table-card h5 { color: var(--purple); font-weight: 600; margin-bottom: 1.5rem; }
        .table { border-radius: 12px; }
        .table thead th { background: var(--purple); color: white; border: none; padding: 1rem; }
        .table tbody td { padding: 1rem; border-bottom: 1px solid var(--border); }
        .license-key { font-family: 'Courier New', monospace; font-size: 0.9em; background: #f8fafc; padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border); word-break: break-all; }
        .btn-primary { background: var(--purple); border: none; border-radius: 8px; padding: 0.5rem 1rem; color: white; }
        .btn-primary:hover { background: #7c3aed; color: white; }
        .btn-success { background: #10b981; border: none; border-radius: 8px; padding: 0.5rem 1rem; color: white; }
        .btn-success:hover { background: #059669; color: white; }
        .empty-state { text-align: center; padding: 3rem; color: var(--muted); }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .alert { border-radius: 8px; border: none; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-4 border-bottom">
                    <h4 style="color: var(--purple); font-weight: 700; margin-bottom: 0;">
                        <i class="fas fa-crown me-2"></i>SilentMultiPanel
                    </h4>
                    <p class="text-muted small mb-0">User Panel</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key"></i>Manage Keys</a>
                    <a class="nav-link active" href="user_generate.php"><i class="fas fa-plus"></i>Generate</a>
                    <a class="nav-link" href="user_balance.php"><i class="fas fa-wallet"></i>Balance</a>
                    <a class="nav-link" href="user_transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt"></i>Applications</a>
                    <a class="nav-link" href="user_settings.php"><i class="fas fa-cog"></i>Settings</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-plus me-2"></i>Generate</h2>
                            <p class="text-muted mb-0">Purchase new license keys for mod applications</p>
                        </div>
                        <div class="d-none d-md-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted">Balance: <?php echo formatCurrency($user['balance']); ?></small>
                            </div>
                            <div class="user-avatar"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <!-- Available Keys -->
                <div class="table-card">
                    <h5><i class="fas fa-gift me-2"></i>Available Keys to Generate</h5>
                    <?php if (empty($availableKeys)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--purple); opacity: 0.5; margin-bottom: 1rem;"></i>
                            <h5>No Keys Available</h5>
                            <p>No license keys available for purchase at this moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mod Name</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availableKeys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['mod_name'] ?? 'Unknown'); ?></td>
                                        <td><span class="badge bg-primary"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></span></td>
                                        <td><?php echo formatCurrency($key['price']); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                                <button type="submit" name="purchase_key" class="btn btn-sm btn-success">
                                                    <i class="fas fa-shopping-cart me-1"></i>Generate
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- My Purchased Keys -->
                <div class="table-card">
                    <h5><i class="fas fa-shopping-bag me-2"></i>My Purchased Keys</h5>
                    <?php if (empty($purchasedKeys)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box" style="font-size: 3rem; color: var(--purple); opacity: 0.5; margin-bottom: 1rem;"></i>
                            <h5>No Purchased Keys</h5>
                            <p>You haven't purchased any keys yet. Generate one above!</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Mod Name</th>
                                        <th>License Key</th>
                                        <th>Duration</th>
                                        <th>Price</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchasedKeys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['mod_name'] ?? 'Unknown'); ?></td>
                                        <td><div class="license-key"><?php echo htmlspecialchars($key['license_key']); ?></div></td>
                                        <td><span class="badge bg-primary"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></span></td>
                                        <td><?php echo formatCurrency($key['price']); ?></td>
                                        <td><?php echo formatDate($key['sold_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')" title="Copy Key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.createElement('div');
                toast.className = 'alert alert-success position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
                toast.innerHTML = '<i class="fas fa-check me-2"></i>License key copied!';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }).catch(() => alert('Could not copy. Please copy manually.'));
        }
    </script>
</body>
</html>
