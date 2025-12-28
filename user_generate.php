<?php require_once "includes/optimization.php"; ?>
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

            // Purchased each key
            $keysSold = 0;
            $allPurchasedKeys = [];
            foreach ($keysToSell as $keyData) {
                $stmt = $pdo->prepare("UPDATE license_keys SET status = 'sold', sold_to = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$user['id'], $keyData['id']]);
                
                // Get the actual key string
                $stmt_key = $pdo->prepare('SELECT license_key FROM license_keys WHERE id = ?');
                $stmt_key->execute([$keyData['id']]);
                $allPurchasedKeys[] = $stmt_key->fetchColumn();
                
                $keysSold++;
            }

            try {
                // Get Mod Name for description
                $stmt = $pdo->prepare('SELECT name FROM mods WHERE id = ?');
                $stmt->execute([$key['mod_id']]);
                $modData = $stmt->fetch();
                $modName = $modData['name'] ?? 'Unknown Mod';

                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, reference, status, created_at) VALUES (?, "debit", ?, ?, ?, "completed", CURRENT_TIMESTAMP)');
                $desc = ($quantity === 1 ? 'License key purchase' : $quantity . ' License keys purchase') . " - " . $modName;
                $ref = "License purchase #" . $keysToSell[0]['id'] . ($quantity > 1 ? " (+" . ($quantity-1) . " more)" : "");
                $stmt->execute([$user['id'], -$totalPrice, $desc, $ref]);
            } catch (Throwable $ignored) {}

            $pdo->commit();
            
            $keysString = implode("\\n", $allPurchasedKeys);
            $success = "Purchased $keysSold license key(s) successfully!";
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const keys = `" . $keysString . "`;
                    navigator.clipboard.writeText(keys).then(() => {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Key(s) purchased and copied to clipboard!',
                            icon: 'success',
                            background: '#111827',
                            color: '#fff',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true
                        });
                    });
                });
            </script>";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Key - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { padding-top: 60px; background: #0f172a; }
        .header { height: 60px; position: fixed; top: 0; left: 0; right: 0; z-index: 1001; background: rgba(15,23,42,0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        .sidebar { width: 260px; position: fixed; top: 60px; bottom: 0; left: 0; z-index: 1000; background: #111827; border-right: 1px solid rgba(255,255,255,0.05); }
        .main-content { margin-left: 260px; padding: 2rem; color: white; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .main-content { margin-left: 0; }
        }

        /* High-End Stylish UI */
        .mod-selector-container {
            position: relative;
            margin-bottom: 2rem;
        }
        .mod-trigger {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.3s;
        }
        .mod-trigger:hover {
            border-color: #8b5cf6;
            background: rgba(30, 41, 59, 0.8);
        }
        .mod-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid #8b5cf6;
            border-radius: 12px;
            margin-top: 8px;
            z-index: 100;
            display: none;
            padding: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        .mod-dropdown.show { display: block; }
        .mod-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .mod-item:hover, .mod-item.active {
            background: #8b5cf6;
            color: white;
        }

        .results-container {
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(139, 92, 246, 0.3);
            background: rgba(30, 41, 59, 0.3);
        }
        .mod-group {
            padding: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .mod-group:last-child { border-bottom: none; }
        
        .duration-card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        .duration-card:hover {
            transform: translateY(-5px);
            border-color: #8b5cf6;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.1);
        }
        .price-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8b5cf6;
        }
        .stock-tag {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .buy-btn {
            background: linear-gradient(135deg, #8b5cf6, #6366f1);
            border: none;
            border-radius: 10px;
            padding: 0.6rem 1rem;
            color: white;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        .buy-btn:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <h4 class="m-0 fw-bold text-white"><i class="fas fa-shield-halved text-primary me-2"></i>SilentMultiPanel</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div style="width:35px; height:35px; border-radius:50%; background:#8b5cf6; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="user_generate.php"><i class="fas fa-bolt me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-layer-group me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-shield me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="mb-4">
            <h2 class="fw-bold">Generate License</h2>
            <p class="text-secondary">Choose your application and duration to get started.</p>
        </div>

        <div class="mod-selector-container">
            <div class="mod-trigger" id="modTrigger">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-search text-primary"></i>
                    <span>
                        <?php 
                        $selected = "Search Applications...";
                        foreach($mods as $m) if($m['id'] == $modId) $selected = $m['name'];
                        echo htmlspecialchars($selected);
                        ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down opacity-50"></i>
            </div>
            <div class="mod-dropdown" id="modDropdown">
                <a href="user_generate.php" class="mod-item <?php echo !$modId ? 'active' : ''; ?>">All Applications</a>
                <?php foreach($mods as $mod): ?>
                    <a href="user_generate.php?mod_id=<?php echo $mod['id']; ?>" class="mod-item <?php echo $modId == $mod['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($mod['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="results-container">
            <?php if(empty($keysByMod)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open text-secondary fs-1 mb-3 opacity-25"></i>
                    <h5 class="text-secondary">No licenses available in this category.</h5>
                </div>
            <?php else: ?>
                <?php foreach($keysByMod as $modName => $keys): ?>
                    <div class="mod-group">
                        <h4 class="mb-4 text-white d-flex align-items-center gap-2">
                            <span style="width:4px; height:20px; background:#8b5cf6; border-radius:2px;"></span>
                            <?php echo htmlspecialchars($modName); ?>
                        </h4>
                        <div class="row g-4">
                            <?php foreach($keys as $key): ?>
                                <div class="col-12 col-md-6 col-xl-4">
                                    <div class="duration-card">
                                        <div class="d-flex justify-content-between mb-3">
                                            <div>
                                                <h5 class="m-0 text-white"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></h5>
                                                <span class="stock-tag"><?php echo $key['key_count']; ?> In Stock</span>
                                            </div>
                                            <div class="price-text"><?php echo formatCurrency($key['price']); ?></div>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="key_id" value="<?php echo $key['min_id']; ?>">
                                            <div class="d-flex gap-2">
                                                <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white text-center" value="1" min="1" max="<?php echo $key['key_count']; ?>" style="width:70px;">
                                                <button type="submit" name="purchase_key" class="buy-btn">Buy Now</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const trigger = document.getElementById('modTrigger');
        const dropdown = document.getElementById('modDropdown');
        
        trigger.onclick = (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        };

        document.onclick = () => dropdown.classList.remove('show');
    </script>
</body>
</html>