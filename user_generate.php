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
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    
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
            $totalPrice = $price * $quantity;
            
            if ($currentBalance < $totalPrice) {
                throw new Exception('Insufficient balance. Need ₹' . number_format($totalPrice, 2) . ' for ' . $quantity . ' key(s), have ₹' . number_format($currentBalance, 2));
            }

            // Get available keys of this type
            $stmt = $pdo->prepare('SELECT id FROM license_keys WHERE id >= ? AND sold_to IS NULL ORDER BY id LIMIT 100');
            $stmt->execute([$keyId]);
            $allKeys = $stmt->fetchAll();
            $keysToSell = array_slice($allKeys, 0, $quantity);
            
            if (count($keysToSell) < $quantity) {
                throw new Exception('Only ' . count($keysToSell) . ' key(s) available, but you requested ' . $quantity);
            }

            // Deduct balance
            $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$totalPrice, $user['id']]);

            // Purchase each key
            $keysSold = 0;
            foreach ($keysToSell as $keyData) {
                $stmt = $pdo->prepare('UPDATE license_keys SET sold_to = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$user['id'], $keyData['id']]);
                $keysSold++;
            }

            try {
                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, "debit", ?, ?, CURRENT_TIMESTAMP)');
                $desc = $quantity === 1 ? 'License key purchase' : $quantity . ' License keys purchase';
                $stmt->execute([$user['id'], $totalPrice, $desc]);
            } catch (Throwable $ignored) {}

            $pdo->commit();
            $success = 'Purchased ' . $keysSold . ' license key(s) successfully!';

            $stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$modId = $_GET['mod_id'] ?? '';

// Get all active mods
$mods = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get available keys grouped by mod and duration
$availableKeys = [];
try {
    if ($modId !== '' && ctype_digit((string)$modId)) {
        $stmt = $pdo->prepare('SELECT m.name AS mod_name, lk.mod_id, lk.duration, lk.duration_type, lk.price, COUNT(*) as key_count, MIN(lk.id) as min_id
                               FROM license_keys lk
                               LEFT JOIN mods m ON m.id = lk.mod_id
                               WHERE lk.sold_to IS NULL AND lk.mod_id = ?
                               GROUP BY m.name, lk.mod_id, lk.duration, lk.duration_type, lk.price
                               ORDER BY m.name, lk.duration');
        $stmt->execute([$modId]);
    } else {
        $stmt = $pdo->query('SELECT m.name AS mod_name, lk.mod_id, lk.duration, lk.duration_type, lk.price, COUNT(*) as key_count, MIN(lk.id) as min_id
                              FROM license_keys lk
                              LEFT JOIN mods m ON m.id = lk.mod_id
                              WHERE lk.sold_to IS NULL
                              GROUP BY m.name, lk.mod_id, lk.duration, lk.duration_type, lk.price
                              ORDER BY m.name, lk.duration');
    }
    $availableKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $availableKeys = [];
}

// Group keys by mod
$keysByMod = [];
foreach ($availableKeys as $key) {
    $modName = $key['mod_name'] ?? 'Unknown';
    if (!isset($keysByMod[$modName])) {
        $keysByMod[$modName] = [];
    }
    $keysByMod[$modName][] = $key;
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
        .sidebar .nav-link { color: var(--muted); padding: 12px 20px; margin: 4px 16px; border-radius: 8px; }
        .sidebar .nav-link:hover { background: #f3f4f6; color: var(--text); }
        .sidebar .nav-link.active { background: var(--purple); color: white; }
        .sidebar .nav-link i { width: 20px; margin-right: 12px; }
        .main-content { margin-left: 280px; padding: 2rem; }
        .page-header { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .page-header h2 { color: var(--purple); font-weight: 600; }
        .filter-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .filter-card h4 { color: var(--text); font-weight: 600; margin-bottom: 1rem; }
        .key-card { background: white; border: 2px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1rem; }
        .key-card h4 { color: var(--purple); font-weight: 600; margin-bottom: 1rem; }
        .duration-option { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
        .duration-badge { background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85em; }
        .price { font-weight: 600; color: var(--text); font-size: 1.1em; }
        .available { background: #0ea5e9; color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.85em; }
        .btn-generate { background: #10b981; border: none; color: white; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; }
        .btn-generate:hover { background: #059669; color: white; }
        .empty-message { text-align: center; color: var(--muted); padding: 1rem; font-size: 0.95em; }
        .alert { border-radius: 8px; border: none; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; padding-top: 80px; }
            .page-header { padding: 1.25rem; }
            .page-header h2 { font-size: 1.5rem; word-break: break-word; overflow-wrap: break-word; }
            .page-header .d-flex { flex-direction: column; gap: 0.75rem; }
            .back-button { padding: 0.6rem 1rem; font-size: 0.9rem; }
            .key-card { padding: 1rem; }
            .duration-option { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .filter-card { padding: 1.25rem; }
            input[type="number"] { width: 60px !important; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem; padding-top: 75px; }
            .page-header { padding: 1rem; margin-bottom: 1rem; }
            .page-header h2 { font-size: 1.25rem; }
            .back-button { padding: 0.5rem 0.8rem; font-size: 0.85rem; }
            div[style*="position: absolute"] { position: static !important; margin-bottom: 1rem; }
            .key-card { padding: 0.75rem; margin-bottom: 0.75rem; }
            .filter-card { padding: 1rem; }
            .duration-option form { width: 100%; }
            input[type="number"] { width: 50px !important; padding: 0.4rem !important; }
            .btn-generate { padding: 0.4rem 0.8rem; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row" style="position: relative;">
            <div style="position: absolute; top: 20px; left: 20px; z-index: 999;">
                <a href="user_dashboard.php" class="back-button" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; border: none; padding: 0.7rem 1.4rem; border-radius: 10px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; text-decoration: none; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <i class="fas fa-arrow-left" style="font-size: 1rem;"></i><span>Back</span>
                </a>
            </div>
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-4 border-bottom">
                    <h4 style="color: var(--purple); font-weight: 700; margin-bottom: 0;"><i class="fas fa-crown me-2"></i>SilentMultiPanel</h4>
                    <p class="text-muted small mb-0">User Panel</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key"></i>Manage Keys</a>
                    <a class="nav-link active" href="user_generate.php"><i class="fas fa-plus"></i>Generate</a>
                    <a class="nav-link" href="user_transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt"></i>Applications</a>
                    <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban"></i>Block & Reset</a>
                    <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell"></i>Notifications</a>
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
                <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="filter-card">
                    <h4>FILTER BY MOD:</h4>
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <select class="form-control" name="mod_id" style="border-radius: 12px; border: 1px solid var(--border); padding: 0.75rem;">
                                <option value="">All Mods</option>
                                <?php foreach ($mods as $mod): ?>
                                <option value="<?php echo $mod['id']; ?>" <?php echo $modId == $mod['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-generate w-100"><i class="fas fa-filter me-2"></i>Filter</button>
                            <a href="user_generate.php" class="btn btn-secondary w-100 mt-2"><i class="fas fa-times me-2"></i>Clear</a>
                        </div>
                    </form>
                </div>
                
                <!-- Available Keys -->
                <div>
                    <h5 style="color: var(--purple); font-weight: 600; margin-bottom: 1.5rem;"><i class="fas fa-lock me-2"></i>Available Keys</h5>
                    
                    <?php if (empty($keysByMod)): ?>
                        <div class="alert alert-info">No available keys to purchase.</div>
                    <?php else: ?>
                        <?php foreach ($keysByMod as $modName => $keys): ?>
                        <div class="key-card">
                            <h4><i class="fas fa-box me-2"></i><?php echo htmlspecialchars($modName); ?></h4>
                            <div style="text-align: right; margin-bottom: 1rem;">
                                <span class="badge bg-purple" style="background: var(--purple);"><?php echo count($keys); ?> Duration Options</span>
                            </div>
                            
                            <?php foreach ($keys as $key): ?>
                            <div class="duration-option">
                                <div>
                                    <span class="duration-badge"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></span>
                                    <div style="margin-top: 0.5rem;">
                                        <div class="price"><?php echo formatCurrency($key['price']); ?></div>
                                        <span class="available"><?php echo 'Available: ' . $key['key_count']; ?></span>
                                    </div>
                                </div>
                                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                                    <input type="hidden" name="key_id" value="<?php echo $key['min_id']; ?>">
                                    <input type="number" name="quantity" min="1" max="<?php echo $key['key_count']; ?>" value="1" style="width: 70px; padding: 0.5rem; border: 1px solid var(--border); border-radius: 6px; text-align: center;">
                                    <button type="submit" name="purchase_key" class="btn-generate">
                                        <i class="fas fa-shopping-cart me-1"></i>Generate
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="empty-message">Select a duration option above to purchase</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Purchased Keys -->
                <div style="margin-top: 2rem;">
                    <h5 style="color: var(--purple); font-weight: 600; margin-bottom: 1.5rem;"><i class="fas fa-shopping-bag me-2"></i>My Purchased Keys</h5>
                    <?php if (empty($purchasedKeys)): ?>
                        <div class="alert alert-info">No purchased keys yet. Generate one above!</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" style="border-radius: 12px;">
                                <thead style="background: var(--purple); color: white;">
                                    <tr><th>Mod Name</th><th>License Key</th><th>Duration</th><th>Date</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchasedKeys as $key): ?>
                                    <tr style="border-bottom: 1px solid var(--border);">
                                        <td><?php echo htmlspecialchars($key['mod_name'] ?? 'Unknown'); ?></td>
                                        <td><code style="background: #f8fafc; padding: 0.5rem; border-radius: 6px;"><?php echo htmlspecialchars($key['license_key']); ?></code></td>
                                        <td><span class="badge bg-primary"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></span></td>
                                        <td><?php echo formatDate($key['sold_at']); ?></td>
                                        <td><button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')"><i class="fas fa-copy"></i></button></td>
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
                alert('License key copied to clipboard!');
            }).catch(() => alert('Failed to copy'));
        }
    </script>
</body>
</html>
