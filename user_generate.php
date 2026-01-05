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
            
            $keysString = implode("\n", $allPurchasedKeys);
            $success = "Purchased $keysSold license key(s) successfully!";
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    const keys = " . json_encode($keysString) . ";
                    
                    function copyToClipboard(text) {
                        if (navigator.clipboard && window.isSecureContext) {
                            return navigator.clipboard.writeText(text).then(() => true).catch(() => fallbackCopy(text));
                        } else {
                            return fallbackCopy(text);
                        }
                    }

                    function fallbackCopy(text) {
                        const textArea = document.createElement('textarea');
                        textArea.value = text;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-9999px';
                        textArea.style.top = '0';
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        try {
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            return true;
                        } catch (err) {
                            document.body.removeChild(textArea);
                            return false;
                        }
                    }

                    const confetti = document.createElement('div');
                    confetti.className = 'confetti-burst';
                    document.body.appendChild(confetti);
                    
                    // Attempt automatic copy
                    const autoCopied = copyToClipboard(keys);
                    
                    Swal.fire({
                        title: 'Purchase Successful!',
                        html: `
                            <div class=\"mt-3 mb-3 p-3 bg-dark text-info border border-secondary rounded\" style=\"font-family: monospace; font-size: 0.9rem; max-height: 150px; overflow-y: auto; text-align: left; word-break: break-all; white-space: pre-wrap;\">\${keys}</div>
                            <button id=\"manualCopyBtn\" class=\"btn \${autoCopied ? 'btn-success' : 'btn-primary'} w-100\">
                                <i class=\"fas \${autoCopied ? 'fa-check' : 'fa-copy'}\"></i> \${autoCopied ? 'Automatically Copied!' : 'Copy to Clipboard'}
                            </button>
                        `,
                        icon: 'success',
                        background: 'rgba(15, 23, 42, 0.95)',
                        color: '#fff',
                        showConfirmButton: true,
                        confirmButtonText: 'Done',
                        confirmButtonColor: '#8b5cf6',
                        didOpen: () => {
                            const btn = document.getElementById('manualCopyBtn');
                            btn.onclick = () => {
                                if(copyToClipboard(keys)) {
                                    btn.innerHTML = '<i class=\"fas fa-check\"></i> Copied!';
                                    btn.className = 'btn btn-success w-100';
                                    setTimeout(() => {
                                        btn.innerHTML = '<i class=\"fas fa-copy\"></i> Copy Again';
                                        btn.className = 'btn btn-primary w-100';
                                    }, 2000);
                                }
                            };
                        }
                    });
                    
                    setTimeout(() => confetti.remove(), 3000);
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
        
        .user-nav-wrapper { position: relative; }
        .user-avatar-header { cursor:pointer; transition:all 0.3s ease; }
        .user-avatar-header:hover { transform:scale(1.05); box-shadow:0 0 15px rgba(139, 92, 246, 0.4); }
        .avatar-dropdown { position:absolute; top:calc(100% + 15px); right:0; width:220px; background:rgba(10, 15, 25, 0.95); backdrop-filter:blur(20px); border:1px solid rgba(139, 92, 246, 0.3); border-radius:16px; padding:10px; z-index:1002; display:none; box-shadow:0 10px 30px rgba(0,0,0,0.5); animation:dropdownFade 0.3s ease; }
        .avatar-dropdown.show { display:block; }
        @keyframes dropdownFade { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .dropdown-item-cyber { display:flex; align-items:center; gap:12px; padding:10px 15px; color:rgba(255, 255, 255, 0.7); text-decoration:none; border-radius:10px; transition:all 0.2s ease; font-size:0.9rem; }
        .dropdown-item-cyber:hover { background:rgba(139, 92, 246, 0.1); color:#fff; transform:translateX(5px); }
        .dropdown-item-cyber i { width:20px; text-align:center; color:var(--primary); }
        .dropdown-divider { height:1px; background:rgba(255, 255, 255, 0.05); margin:8px 0; }
        
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

        .mod-selector-wrapper {
            position: relative;
            margin-bottom: 2rem;
            padding: 2px;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.5));
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
            transition: all 0.3s ease;
        }

        .mod-selector-wrapper:hover {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.4);
        }

        .mod-trigger-btn {
            width: 100%;
            background: #0a0f19;
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            padding: 1.2rem 2rem;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .mod-popup-menu {
            position: absolute;
            top: calc(100% + 15px);
            left: 0;
            right: 0;
            background: rgba(10, 15, 25, 0.98);
            backdrop-filter: blur(25px);
            border: 2px solid #8b5cf6;
            border-radius: 24px;
            padding: 1.5rem;
            z-index: 100;
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8), 0 0 40px rgba(139, 92, 246, 0.2);
            animation: popupReveal 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            max-height: 400px;
            overflow-y: auto;
        }

        .mod-popup-menu.show {
            display: grid;
        }

        @keyframes popupReveal {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .mod-option-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 18px;
            padding: 1.2rem;
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .mod-option-item i {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .mod-option-item:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: #8b5cf6;
            color: white;
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .mod-option-item.active {
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            color: white;
            border: none;
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
        }

        .mod-option-item.active i {
            background: none;
            -webkit-text-fill-color: white;
            color: white;
        }

        .results-container {
            position: relative;
            padding: 3px;
            border-radius: 30px;
            background: linear-gradient(45deg, #8b5cf6, #06b6d4, #ec4899, #8b5cf6, #06b6d4);
            background-size: 300% 300%;
            animation: gradientBorder 8s linear infinite;
            display: none;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.3);
        }

        .results-container.show {
            display: block;
            animation: slideUp 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes gradientBorder {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .results-inner {
            background: #05070c;
            border-radius: 27px;
            padding: 2.5rem;
        }

        .duration-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .duration-item:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateX(10px);
            box-shadow: -5px 0 15px rgba(139, 92, 246, 0.1);
        }

        .search-container {
            margin-bottom: 2rem;
            position: relative;
        }
        .stylish-search-wrapper {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 2px solid #8b5cf6;
            border-radius: 20px;
            padding: 8px 25px;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.1);
        }
        .stylish-search-wrapper:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.3);
            border-color: #06b6d4;
        }
        .product-search-input {
            background: transparent !important;
            border: none !important;
            color: white !important;
            height: 50px;
            width: 100%;
            outline: none !important;
            font-size: 1.1rem;
            font-weight: 500;
        }
        .product-search-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }
        #noResults {
            display: none;
            text-align: center;
            padding: 3rem;
            background: rgba(15, 23, 42, 0.4);
            border-radius: 20px;
            border: 1px dashed rgba(139, 92, 246, 0.3);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn text-white p-0 d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="logo-wrapper d-flex align-items-center gap-2">
                <i class="fas fa-bolt text-primary fs-3"></i>
                <h4 class="m-0 text-neon fw-bold">SilentMultiPanel</h4>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div class="user-nav-wrapper">
                <div class="user-avatar-header" onclick="toggleAvatarDropdown()" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                </div>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="px-3 py-2">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="text-secondary small">ID: #<?php echo $user['id']; ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="user_transactions.php" class="dropdown-item-cyber">
                        <i class="fas fa-history"></i> Transactions
                    </a>
                    <a href="user_generate.php" class="dropdown-item-cyber">
                        <i class="fas fa-plus"></i> Generate Key
                    </a>
                    <a href="user_settings.php" class="dropdown-item-cyber">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item-cyber text-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link active" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_stock_alert.php"><i class="fas fa-warehouse me-2"></i> Stock Alert</a>
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

        <div class="search-container">
            <div class="stylish-search-wrapper">
                <i class="fas fa-search text-primary me-3 fs-5"></i>
                <input type="text" id="productSearch" class="product-search-input" placeholder="Search for applications or mods..." autocomplete="off">
            </div>
        </div>

        <div id="noResults" class="no-results mb-4">
            <i class="fas fa-search-minus text-secondary mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
            <h5 class="text-secondary">No products found matching your search.</h5>
        </div>

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
                        <div class="mb-5 last-child-mb-0 mod-section" data-mod-name="<?php echo htmlspecialchars($modName); ?>">
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
        function toggleAvatarDropdown() { document.getElementById('avatarDropdown').classList.toggle('show'); }
        function toggleModPopup() { document.getElementById('modPopup').classList.toggle('show'); }

        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar-header')) {
                var dropdowns = document.getElementsByClassName("avatar-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('productSearch');
            const noResults = document.getElementById('noResults');
            const resultsContainer = document.querySelector('.results-container');
            const modSections = document.querySelectorAll('.mod-section');

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    let anyVisible = false;

                    modSections.forEach(section => {
                        const modName = section.getAttribute('data-mod-name').toLowerCase();
                        const durationItems = section.querySelectorAll('.duration-item');
                        let matchedCount = 0;

                        durationItems.forEach(item => {
                            const text = item.textContent.toLowerCase();
                            if (query === '' || text.includes(query)) {
                                item.closest('.col-12').style.display = 'block';
                                matchedCount++;
                            } else {
                                item.closest('.col-12').style.display = 'none';
                            }
                        });

                        if (query === '' || modName.includes(query) || matchedCount > 0) {
                            section.style.display = 'block';
                            anyVisible = true;
                            if(modName.includes(query) && query !== '') {
                                section.querySelectorAll('.col-12').forEach(c => c.style.display = 'block');
                            }
                        } else {
                            section.style.display = 'none';
                        }
                    });

                    if (!anyVisible && query !== '') {
                        noResults.style.display = 'block';
                        resultsContainer.style.display = 'none';
                    } else {
                        noResults.style.display = 'none';
                        resultsContainer.style.display = 'block';
                    }
                });
            }
        });

        document.addEventListener('click', function(e) {
            const popup = document.getElementById('modPopup');
            const trigger = document.querySelector('.mod-trigger-btn');
            if (popup && !popup.contains(e.target) && !trigger.contains(e.target)) {
                popup.classList.remove('show');
            }
        });
        
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