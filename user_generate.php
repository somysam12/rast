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

        .filter-select {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid rgba(148, 163, 184, 0.1);
            color: white;
            border-radius: 12px;
            padding: 10px 15px;
        }
        
        .filter-select:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #8b5cf6;
            color: white;
            outline: none;
        }

        .duration-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .duration-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }
        .search-container {
            margin-bottom: 2.5rem;
            position: relative;
        }
        .stylish-search-wrapper {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 2px solid #8b5cf6;
            border-radius: 24px;
            padding: 8px 25px;
            display: flex;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
        }
        .stylish-search-wrapper:focus-within {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 50px rgba(139, 92, 246, 0.4);
            border-color: #06b6d4;
        }
        .stylish-search-wrapper i {
            color: #8b5cf6;
            font-size: 1.4rem;
            margin-right: 15px;
            transition: all 0.3s ease;
        }
        .stylish-search-wrapper:focus-within i {
            color: #06b6d4;
            transform: rotate(15deg);
        }
        .product-search-input {
            background: transparent !important;
            border: none !important;
            color: white !important;
            height: 50px;
            width: 100%;
            outline: none !important;
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .product-search-input::placeholder {
            color: rgba(255, 255, 255, 0.3);
            font-weight: 400;
        }
        
        .no-results {
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
            <h4 class="m-0 text-neon fw-bold">SilentMultiPanel</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div class="user-avatar-header" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
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
                <i class="fas fa-search"></i>
                <input type="text" id="productSearch" class="product-search-input" placeholder="Search applications, duration, or price..." autocomplete="off">
            </div>
        </div>

        <div id="noResults" class="no-results mb-4">
            <i class="fas fa-search-minus text-secondary mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
            <h5 class="text-secondary">We couldn't find any products matching your search.</h5>
            <p class="text-muted small">Try checking your spelling or using different keywords.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="cyber-card">
                    <h5 class="mb-4"><i class="fas fa-filter text-primary me-2"></i> Filter Mods</h5>
                    <form method="GET">
                        <div class="mb-3">
                            <select name="mod_id" class="form-select filter-select w-100" onchange="this.form.submit()">
                                <option value="">All Applications</option>
                                <?php foreach ($mods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>" <?php echo $modId == $mod['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($modId): ?>
                            <a href="user_generate.php" class="btn btn-outline-secondary w-100 rounded-3">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-8">
                <?php if (empty($keysByMod)): ?>
                    <div class="cyber-card text-center py-5">
                        <i class="fas fa-key text-secondary mb-3" style="font-size: 3rem; opacity: 0.3;"></i>
                        <h5 class="text-secondary">No keys available for the selected mod.</h5>
                    </div>
                <?php else: ?>
                    <?php foreach ($keysByMod as $modName => $keys): ?>
                        <div class="cyber-card mb-4 mod-card-container">
                            <h5 class="mb-4 text-white mod-title"><i class="fas fa-cube text-secondary me-2"></i> <?php echo htmlspecialchars($modName); ?></h5>
                            <div class="row g-3">
                                <?php foreach ($keys as $key): ?>
                                    <div class="col-12 duration-item-container">
                                        <div class="duration-item d-flex justify-content-between align-items-center flex-wrap gap-3">
                                            <div>
                                                <div class="fw-bold text-white duration-name"><?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?></div>
                                                <div class="small text-secondary"><?php echo formatCurrency($key['price']); ?> | <?php echo $key['key_count']; ?> available</div>
                                            </div>
                                            <form method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="key_id" value="<?php echo $key['min_id']; ?>">
                                                <input type="number" name="quantity" class="form-control bg-dark border-secondary text-white text-center" value="1" min="1" max="<?php echo $key['key_count']; ?>" style="width: 70px; border-radius: 8px;">
                                                <button type="submit" name="purchase_key" class="cyber-btn py-2">
                                                    <i class="fas fa-shopping-cart"></i> Buy
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
        
        // Product Search Logic
        const productSearch = document.getElementById('productSearch');
        const modCards = document.querySelectorAll('.mod-card-container');

        if (productSearch) {
            productSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const noResults = document.getElementById('noResults');
                const modCards = document.querySelectorAll('.mod-card-container'); // Re-select to be safe
                let totalVisibleMods = 0;
                
                modCards.forEach(card => {
                    const modTitleElem = card.querySelector('.mod-title');
                    // Get only the text nodes to avoid icon tags interfering
                    let modName = "";
                    if (modTitleElem) {
                        modTitleElem.childNodes.forEach(node => {
                            if (node.nodeType === Node.TEXT_NODE) modName += node.textContent;
                        });
                    }
                    modName = modName.toLowerCase().trim();
                    
                    const durationItems = card.querySelectorAll('.duration-item-container');
                    let visibleDurationsInCard = 0;

                    durationItems.forEach(item => {
                        const durationNameElem = item.querySelector('.duration-name');
                        const durationName = durationNameElem ? durationNameElem.textContent.toLowerCase().trim() : '';
                        
                        const smallTextElem = item.querySelector('.small');
                        const smallText = smallTextElem ? smallTextElem.textContent.toLowerCase().trim() : '';

                        if (query === '' || modName.includes(query) || durationName.includes(query) || smallText.includes(query)) {
                            item.style.setProperty('display', 'block', 'important');
                            visibleDurationsInCard++;
                        } else {
                            item.style.setProperty('display', 'none', 'important');
                        }
                    });

                    if (visibleDurationsInCard > 0) {
                        card.style.setProperty('display', 'block', 'important');
                        totalVisibleMods++;
                    } else {
                        card.style.setProperty('display', 'none', 'important');
                    }
                });

                if (totalVisibleMods === 0 && query !== '') {
                    noResults.style.setProperty('display', 'block', 'important');
                } else {
                    noResults.style.setProperty('display', 'none', 'important');
                }
            });
        }

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