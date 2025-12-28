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
                        // Success animation
                        const container = document.querySelector('.main-content');
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti-burst';
                        document.body.appendChild(confetti);
                        
                        Swal.fire({
                            title: 'Success!',
                            text: 'Key(s) purchased and copied to clipboard!',
                            icon: 'success',
                            background: 'rgba(15, 23, 42, 0.95)',
                            color: '#fff',
                            showConfirmButton: false,
                            timer: 2000,
                            timerProgressBar: true,
                            backdrop: `rgba(0,0,123,0.1)`,
                            customClass: {
                                popup: 'cyber-swal'
                            }
                        });
                        
                        setTimeout(() => confetti.remove(), 3000);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Generate Key - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        .cyber-swal {
            border: 2px solid;
            border-image: linear-gradient(135deg, #8b5cf6, #06b6d4) 1;
            border-radius: 20px !important;
        }
        
        @keyframes confettiFall {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        
        .confetti-piece {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #8b5cf6;
            top: -10px;
            z-index: 9999;
            animation: confettiFall 3s linear forwards;
        }

        /* Ultra Modern Redesign */
        :root {
            --neon-primary: #8b5cf6;
            --neon-secondary: #06b6d4;
            --neon-accent: #f43f5e;
            --cyber-bg: #030712;
            --glass-bg: rgba(15, 23, 42, 0.7);
        }

        .mod-selector-wrapper {
            position: relative;
            margin-bottom: 2.5rem;
            padding: 3px;
            border-radius: 25px;
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.3), inset 0 0 15px rgba(255,255,255,0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .mod-selector-wrapper:hover {
            transform: scale(1.01);
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.5);
        }

        .mod-trigger-btn {
            width: 100%;
            background: var(--cyber-bg);
            backdrop-filter: blur(20px);
            border: none;
            border-radius: 23px;
            padding: 1.5rem 2.5rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }

        .mod-trigger-btn .mod-icon-glow {
            width: 50px;
            height: 50px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }

        .mod-trigger-btn:hover .mod-icon-glow {
            background: rgba(139, 92, 246, 0.2);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.4);
            transform: rotate(10deg);
        }

        .mod-popup-menu {
            position: absolute;
            top: calc(100% + 20px);
            left: 0;
            right: 0;
            background: rgba(3, 7, 18, 0.98);
            backdrop-filter: blur(30px);
            border: 2px solid var(--neon-primary);
            border-radius: 30px;
            padding: 2rem;
            z-index: 1000;
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            box-shadow: 0 30px 100px rgba(0,0,0,0.9), 0 0 50px rgba(139, 92, 246, 0.2);
            animation: popupReveal 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 500px;
            overflow-y: auto;
        }

        .mod-popup-menu.show { display: grid; }

        .mod-option-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.4s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .mod-option-item i {
            font-size: 2.2rem;
            background: linear-gradient(45deg, var(--neon-primary), var(--neon-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 8px rgba(139, 92, 246, 0.4));
        }

        .mod-option-item span {
            font-weight: 700;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.8);
        }

        .mod-option-item:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: var(--neon-primary);
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
        }

        .mod-option-item.active {
            background: linear-gradient(135deg, var(--neon-primary), var(--neon-secondary));
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
            border: none;
        }

        .mod-option-item.active i {
            -webkit-text-fill-color: white;
            filter: none;
        }

        /* Animated Results Frame */
        .results-container {
            position: relative;
            padding: 4px;
            border-radius: 35px;
            background: linear-gradient(var(--cyber-bg), var(--cyber-bg)) padding-box,
                        linear-gradient(45deg, var(--neon-primary), #ec4899, var(--neon-secondary), var(--neon-primary)) border-box;
            border: 4px solid transparent;
            background-size: 300% 300%;
            animation: borderFlow 6s linear infinite;
            display: none;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2);
        }

        @keyframes borderFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .results-container.show { display: block; animation: slideIn 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        .results-inner { padding: 3rem; background: transparent; }

        .duration-item {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 25px;
            padding: 1.8rem;
            margin-bottom: 1.2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .duration-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.03), transparent);
            transition: 0.6s;
        }

        .duration-item:hover {
            background: rgba(139, 92, 246, 0.05);
            border-color: var(--neon-primary);
            transform: scale(1.02) translateX(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .duration-item:hover::after { left: 100%; }

        .price-tag {
            background: rgba(139, 92, 246, 0.1);
            color: var(--neon-primary);
            padding: 5px 15px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.1rem;
            border: 1px solid rgba(139, 92, 246, 0.2);
        }

        .stock-badge {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 4px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .logo-glow {
            filter: drop-shadow(0 0 10px var(--neon-primary));
            animation: pulseGlow 2s infinite alternate;
        }

        @keyframes pulseGlow {
            from { filter: drop-shadow(0 0 5px var(--neon-primary)); }
            to { filter: drop-shadow(0 0 15px var(--neon-secondary)); }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn text-white p-0 d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="logo-wrapper d-flex align-items-center gap-3">
                <div class="logo-glow">
                    <i class="fas fa-atom text-primary fs-2"></i>
                </div>
                <h4 class="m-0 text-neon fw-bold tracking-wider" style="letter-spacing: 1px;">SILENT <span class="text-primary">CORE</span></h4>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div class="user-avatar-header" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--neon-primary), var(--neon-secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold; box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="user_generate.php"><i class="fas fa-bolt me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-layer-group me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-satellite-dish me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-user-shield me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-microchip me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-terminal me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-power-off me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-5" style="border-left: 5px solid var(--neon-primary);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="text-white mb-1 fw-800">License Forge</h2>
                    <p class="text-secondary mb-0">Select your module and forge your access key.</p>
                </div>
                <div class="d-none d-md-block">
                    <i class="fas fa-vial text-primary opacity-25" style="font-size: 3rem;"></i>
                </div>
            </div>
        </div>

        <!-- Stylish Mod Selector -->
        <div class="mod-selector-wrapper">
            <div class="mod-trigger-btn" onclick="toggleModPopup()">
                <div class="d-flex align-items-center gap-4">
                    <div class="mod-icon-glow">
                        <i class="fas fa-dragon text-primary fs-3"></i>
                    </div>
                    <div>
                        <div class="fw-bold fs-5 text-white">
                            <?php 
                            $currentModName = "Module Repository";
                            foreach($mods as $m) if($m['id'] == $modId) $currentModName = $m['name'];
                            echo htmlspecialchars($currentModName);
                            ?>
                        </div>
                        <div class="small text-secondary fw-600">Forge access to a new module</div>
                    </div>
                </div>
                <i class="fas fa-chevron-down opacity-50 fs-5"></i>
            </div>
            
            <div class="mod-popup-menu" id="modPopup">
                <a href="user_generate.php" class="mod-option-item <?php echo !$modId ? 'active' : ''; ?>">
                    <i class="fas fa-dna"></i>
                    <span>Full Access</span>
                </a>
                <?php foreach ($mods as $mod): ?>
                    <a href="user_generate.php?mod_id=<?php echo $mod['id']; ?>" class="mod-option-item <?php echo $modId == $mod['id'] ? 'active' : ''; ?>">
                        <i class="fas fa-microchip"></i>
                        <span><?php echo htmlspecialchars($mod['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4 p-4 rounded-4 shadow-sm">
                <i class="fas fa-biohazard me-3 fs-5"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="results-container <?php echo !empty($keysByMod) ? 'show' : ''; ?>">
            <div class="results-inner">
                <?php if (empty($keysByMod)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ghost text-secondary mb-4" style="font-size: 4rem; opacity: 0.2;"></i>
                        <h4 class="text-secondary fw-700">Forge Empty</h4>
                        <p class="text-muted">No available modules in this sector.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($keysByMod as $modName => $keys): ?>
                        <div class="mb-5 last-child-mb-0">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <i class="fas fa-code-branch text-primary fs-4"></i>
                                <h3 class="text-white m-0 fw-800 tracking-tight"><?php echo htmlspecialchars($modName); ?></h3>
                            </div>
                            <div class="row g-4">
                                <?php foreach ($keys as $key): ?>
                                    <div class="col-12 col-xl-6">
                                        <div class="duration-item">
                                            <div class="d-flex justify-content-between align-items-start mb-4">
                                                <div>
                                                    <div class="fw-800 text-white fs-4 mb-1"><?php echo $key['duration'] . ' ' . strtoupper($key['duration_type']); ?></div>
                                                    <span class="stock-badge">
                                                        <i class="fas fa-check-circle me-1"></i> <?php echo $key['key_count']; ?> Ready
                                                    </span>
                                                </div>
                                                <div class="price-tag">
                                                    <?php echo formatCurrency($key['price']); ?>
                                                </div>
                                            </div>
                                            <form method="POST" class="d-flex align-items-center gap-3">
                                                <input type="hidden" name="key_id" value="<?php echo $key['min_id']; ?>">
                                                <div class="input-group" style="width: 140px;">
                                                    <span class="input-group-text bg-dark border-secondary text-secondary" style="border-radius: 15px 0 0 15px;"><i class="fas fa-boxes"></i></span>
                                                    <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white text-center" value="1" min="1" max="<?php echo $key['key_count']; ?>" style="border-radius: 0 15px 15px 0; font-weight: 700;">
                                                </div>
                                                <button type="submit" name="purchase_key" class="cyber-btn py-3 flex-grow-1" style="border-radius: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">
                                                    <i class="fas fa-plug me-2"></i> Connect
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
        
        function toggleModPopup() {
            const popup = document.getElementById('modPopup');
            popup.classList.toggle('show');
            if(popup.classList.contains('show')) {
                const btn = document.querySelector('.mod-trigger-btn i.fa-chevron-down');
                btn.style.transform = 'rotate(180deg)';
            } else {
                const btn = document.querySelector('.mod-trigger-btn i.fa-chevron-down');
                btn.style.transform = 'rotate(0deg)';
            }
        }

        // Close popup when clicking outside
        document.addEventListener('click', function(e) {
            const popup = document.getElementById('modPopup');
            const trigger = document.querySelector('.mod-trigger-btn');
            if (popup && !popup.contains(e.target) && !trigger.contains(e.target)) {
                popup.classList.remove('show');
                const btn = document.querySelector('.mod-trigger-btn i.fa-chevron-down');
                if(btn) btn.style.transform = 'rotate(0deg)';
            }
        });

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="text-neon mb-1">Generate License Key</h2>
                    <p class="text-secondary mb-0">Select a mod and duration to purchase your key.</p>
                </div>
            </div>
        </div>

        <!-- Stylish Mod Selector -->
        <div class="mod-selector-wrapper">
            <div class="mod-trigger-btn" onclick="toggleModPopup()">
                <div class="d-flex align-items-center gap-3">
                    <i class="fas fa-th-large text-primary fs-4"></i>
                    <div>
                        <div class="fw-bold">
                            <?php 
                            $currentModName = "All Applications";
                            foreach($mods as $m) if($m['id'] == $modId) $currentModName = $m['name'];
                            echo htmlspecialchars($currentModName);
                            ?>
                        </div>
                        <div class="small text-secondary">Tap to browse available mods</div>
                    </div>
                </div>
                <i class="fas fa-chevron-down opacity-50"></i>
            </div>
            
            <div class="mod-popup-menu" id="modPopup">
                <a href="user_generate.php" class="mod-option-item <?php echo !$modId ? 'active' : ''; ?>">
                    <i class="fas fa-globe"></i>
                    <span>All Mods</span>
                </a>
                <?php foreach ($mods as $mod): ?>
                    <a href="user_generate.php?mod_id=<?php echo $mod['id']; ?>" class="mod-option-item <?php echo $modId == $mod['id'] ? 'active' : ''; ?>">
                        <i class="fas fa-cube"></i>
                        <span><?php echo htmlspecialchars($mod['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="results-container <?php echo !empty($keysByMod) ? 'show' : ''; ?>">
            <div class="results-inner">
                <?php if (empty($keysByMod)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-key text-secondary mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                        <h5 class="text-secondary">No keys available for the selected mod.</h5>
                    </div>
                <?php else: ?>
                    <?php foreach ($keysByMod as $modName => $keys): ?>
                        <div class="mb-5 last-child-mb-0">
                            <h4 class="mb-4 text-white"><i class="fas fa-shield-alt text-primary me-2"></i> <?php echo htmlspecialchars($modName); ?></h4>
                            <div class="row g-3">
                                <?php foreach ($keys as $key): ?>
                                    <div class="col-12 col-md-6 col-xl-4">
                                        <div class="duration-item">
                                            <div class="mb-3">
                                                <div class="fw-bold text-white fs-5"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></div>
                                                <div class="text-secondary">₹<?php echo number_format($key['price'], 2); ?> | <span class="text-success"><?php echo $key['key_count']; ?> In Stock</span></div>
                                            </div>
                                            <form method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="key_id" value="<?php echo $key['min_id']; ?>">
                                                <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white text-center" value="1" min="1" max="<?php echo $key['key_count']; ?>" style="width: 70px; border-radius: 8px;">
                                                <button type="submit" name="purchase_key" class="cyber-btn py-2 flex-grow-1">
                                                    <i class="fas fa-shopping-cart"></i> Buy Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
        
        function toggleModPopup() {
            document.getElementById('modPopup').classList.toggle('show');
        }

        // Close popup when clicking outside
        document.addEventListener('click', function(e) {
            const popup = document.getElementById('modPopup');
            const trigger = document.querySelector('.mod-trigger-btn');
            if (popup && !popup.contains(e.target) && !trigger.contains(e.target)) {
                popup.classList.remove('show');
            }
        });
        
        // Confetti burst logic
        const createConfetti = () => {
            const colors = ['#8b5cf6', '#06b6d4', '#ec4899', '#f59e0b'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti-piece';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.width = Math.random() * 10 + 5 + 'px';
                confetti.style.height = confetti.style.width;
                document.body.appendChild(confetti);
                setTimeout(() => confetti.remove(), 5000);
            }
        };

        <?php if ($success): ?>
            createConfetti();
        <?php endif; ?>
    </script>
</body>
</html>