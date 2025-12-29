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
    $stmt = $pdo->query('SELECT m.name AS mod_name, lk.mod_id, lk.duration, lk.duration_type, lk.price, COUNT(*) as key_count, MIN(lk.id) as min_id
                          FROM license_keys lk
                          LEFT JOIN mods m ON m.id = lk.mod_id
                          WHERE lk.sold_to IS NULL
                          GROUP BY m.name, lk.mod_id, lk.duration, lk.duration_type, lk.price
                          ORDER BY m.name, lk.duration');
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
    <title>Generate Key - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            padding: 2rem 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            left: -280px;
        }

        .sidebar.active { transform: translateX(280px); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; text-align: center; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 0; padding: 1.5rem; transition: margin-left 0.3s ease; max-width: 1200px; margin: 0 auto; }

        @media (min-width: 993px) {
            .sidebar { left: 0; }
            .main-content { margin-left: 280px; }
        }

        .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; display: none; }
        @media (max-width: 992px) { .hamburger { display: block; } }

        .header-card { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            padding: 1.5rem; 
            border-radius: 24px; 
            margin-bottom: 2rem; 
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        .search-container {
            margin-bottom: 2rem;
            position: relative;
        }
        .stylish-search {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 2px solid var(--primary);
            border-radius: 18px;
            padding: 12px 20px 12px 45px;
            color: white;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .stylish-search:focus {
            outline: none;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            border-color: var(--secondary);
        }
        .search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .mod-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1px solid var(--border-light);
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .mod-card:hover { transform: translateY(-5px); border-color: var(--primary); }
        .mod-title { font-weight: 800; color: var(--primary); font-size: 1.2rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }

        .duration-option {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-light);
            border-radius: 18px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .duration-option:hover { background: rgba(139, 92, 246, 0.1); border-color: var(--primary); }

        .btn-buy {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 8px 20px;
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        .btn-buy:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3); }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }

        #noResults { display: none; text-align: center; padding: 3rem; opacity: 0.5; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link active" href="user_generate.php"><i class="fas fa-plus"></i>Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key"></i>Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt"></i>Applications</a>
            <a class="nav-link" href="user_stock_alert.php"><i class="fas fa-warehouse"></i>Stock Alert</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog"></i>Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history"></i>Transactions</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-8">
                    <h2 class="text-white mb-1" style="font-weight: 800;">Generate Key</h2>
                    <p class="text-white opacity-75 mb-0">Select a product to get your license</p>
                </div>
                <div class="col-4 text-end">
                    <div class="bg-black bg-opacity-25 px-3 py-2 rounded-3 d-inline-block text-start">
                        <div class="small opacity-50 text-white">My Balance</div>
                        <div class="h5 mb-0 fw-bold"><?php echo formatCurrency($user['balance']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-container">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="modSearch" class="stylish-search" placeholder="Search product by name..." autocomplete="off">
        </div>

        <div id="noResults">
            <i class="fas fa-search-minus mb-3" style="font-size: 3rem;"></i>
            <h5>No products found matching your search.</h5>
        </div>

        <div class="row" id="modContainer">
            <?php foreach ($keysByMod as $modName => $durations): ?>
            <div class="col-md-6 mod-card-wrapper" data-name="<?php echo strtolower($modName); ?>">
                <div class="mod-card">
                    <div class="mod-title">
                        <i class="fas fa-cube"></i>
                        <?php echo htmlspecialchars($modName); ?>
                    </div>
                    <?php foreach ($durations as $d): ?>
                    <div class="duration-option" onclick="buyKey(<?php echo $d['min_id']; ?>, '<?php echo htmlspecialchars($modName); ?> (<?php echo $d['duration'] . ' ' . $d['duration_type']; ?>)', <?php echo $d['price']; ?>)">
                        <div>
                            <div class="fw-bold text-white"><?php echo $d['duration'] . ' ' . ucfirst($d['duration_type']); ?></div>
                            <div class="small text-dim"><?php echo $d['key_count']; ?> keys available</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-primary mb-2">₹<?php echo number_format($d['price'], 2); ?></div>
                            <button class="btn-buy">Buy Now</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <form id="purchaseForm" method="POST" style="display:none;">
        <input type="hidden" name="purchase_key" value="1">
        <input type="hidden" name="key_id" id="keyIdInput">
        <input type="hidden" name="quantity" value="1">
    </form>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const modSearch = document.getElementById('modSearch');
        const modWrappers = document.querySelectorAll('.mod-card-wrapper');
        const noResults = document.getElementById('noResults');

        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        modSearch.oninput = (e) => {
            const val = e.target.value.toLowerCase().trim();
            let hasResults = false;
            
            modWrappers.forEach(w => {
                const name = w.getAttribute('data-name');
                if (name.includes(val)) {
                    w.style.display = 'block';
                    hasResults = true;
                } else {
                    w.style.display = 'none';
                }
            });
            
            noResults.style.display = hasResults ? 'none' : 'block';
        };

        function buyKey(id, name, price) {
            Swal.fire({
                title: 'Confirm Purchase',
                html: `Purchase license for <b style="color:#8b5cf6">${name}</b> for <b style="color:#10b981">₹${price}</b>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirm Purchase',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#8b5cf6',
                background: '#0a0e27',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('keyIdInput').value = id;
                    document.getElementById('purchaseForm').submit();
                }
            });
        }
    </script>
</body>
</html>