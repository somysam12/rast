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
    $date = new DateTime($dt, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $date->format('d M Y, h:i A');
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

// Handle stock alert submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_stock_alert'])) {
    $modId = $_POST['mod_id'] ?? '';
    $modName = $_POST['mod_name'] ?? '';
    
    if ($modId && $modName && ctype_digit((string)$modId)) {
        try {
            // Check if table exists, create if not
            try {
                $pdo->exec("SELECT 1 FROM stock_alerts LIMIT 1");
            } catch (Exception $e) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS stock_alerts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    mod_id INTEGER NOT NULL,
                    mod_name VARCHAR(150),
                    username VARCHAR(100),
                    status VARCHAR(50) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
                )");
            }
            
            // Insert stock alert
            $stmt = $pdo->prepare('INSERT INTO stock_alerts (user_id, mod_id, mod_name, username, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $modId, $modName, $user['username'], 'pending']);
            
            // Return JSON response
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Stock alert sent successfully!']);
            exit;
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
}

// Get available mods
$mods = [];
try {
    $stmt = $pdo->prepare('SELECT id, name FROM mods WHERE status = "active" ORDER BY name');
    $stmt->execute();
    $mods = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get user's stock alerts
$myAlerts = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM stock_alerts WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $myAlerts = $stmt->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Stock Alert - SilentMultiPanel</title>
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
            border: 2px solid rgba(139, 92, 246, 0.5) !important;
            border-radius: 24px !important;
            box-shadow: 0 0 40px rgba(139, 92, 246, 0.2) !important;
        }
        
        .alert-box {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .badge-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .badge-resolved {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
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
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link active" href="user_stock_alert.php"><i class="fas fa-warehouse me-2"></i> Stock Alert</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-neon mb-1">Stock Alert</h2>
                    <p class="text-secondary mb-0">Report sold-out or unavailable products to admin</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="cyber-card">
                    <h5 class="mb-4 text-white"><i class="fas fa-triangle-exclamation text-warning me-2"></i> Report Stock Alert</h5>
                    
                    <div class="alert-box">
                        <p class="text-secondary mb-0 small">
                            <i class="fas fa-info-circle me-2"></i> Let the admin know when a product is sold out or unavailable. Your report will help them restock quickly.
                        </p>
                    </div>

                    <form id="stockAlertForm">
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">SELECT PRODUCT</label>
                            <select name="mod_id" id="modSelect" class="form-select" required>
                                <option value="">Choose a product...</option>
                                <?php foreach ($mods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>" data-name="<?php echo htmlspecialchars($mod['name']); ?>">
                                        <?php echo htmlspecialchars($mod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="button" onclick="submitStockAlert()" class="submit-btn w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Alert to Admin
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="cyber-card h-100">
                    <h5 class="mb-4 text-white"><i class="fas fa-list text-secondary me-2"></i> Your Alerts</h5>
                    <?php if (empty($myAlerts)): ?>
                        <div class="text-center py-5 text-secondary">
                            <i class="fas fa-inbox d-block mb-3" style="font-size: 2rem; opacity: 0.2;"></i>
                            No stock alerts yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($myAlerts as $alert): ?>
                            <div style="background: rgba(15, 23, 42, 0.5); border: 1px solid rgba(139, 92, 246, 0.2); padding: 12px; border-radius: 10px; margin-bottom: 12px;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="fw-bold text-white"><?php echo htmlspecialchars($alert['mod_name']); ?></div>
                                    <span class="<?php echo $alert['status'] === 'pending' ? 'badge-pending' : 'badge-resolved'; ?>">
                                        <?php echo ucfirst($alert['status']); ?>
                                    </span>
                                </div>
                                <small class="text-secondary"><?php echo formatDate($alert['created_at']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
        
        function toggleAvatarDropdown() {
            document.getElementById('avatarDropdown').classList.toggle('show');
        }

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
        
        function submitStockAlert() {
            const form = document.getElementById('stockAlertForm');
            const modSelect = document.getElementById('modSelect');
            const modId = modSelect.value;
            const modName = modSelect.options[modSelect.selectedIndex].getAttribute('data-name');
            
            if (!modId) {
                Swal.fire({
                    title: 'Error',
                    text: 'Please select a product',
                    icon: 'error',
                    background: 'rgba(15, 23, 42, 0.95)',
                    color: '#fff',
                    customClass: { popup: 'cyber-swal' }
                });
                return;
            }
            
            // Show confirmation
            Swal.fire({
                title: 'Send Stock Alert?',
                html: `
                    <div style="text-align: left; margin: 20px 0;">
                        <div style="background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.3); padding: 12px; border-radius: 8px; font-size: 0.9rem;">
                            <strong style="color: #fff;">Product:</strong> <span style="color: rgba(255,255,255,0.7);">${modName}</span>
                        </div>
                        <div style="margin-top: 15px; color: rgba(255,255,255,0.6); font-size: 0.9rem;">
                            Admin will be notified that this product is sold out.
                        </div>
                    </div>
                `,
                icon: 'question',
                background: 'rgba(15, 23, 42, 0.95)',
                color: '#fff',
                showCancelButton: true,
                confirmButtonText: 'Send Alert',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                customClass: { popup: 'cyber-swal' }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('mod_id', modId);
                    formData.append('mod_name', modName);
                    formData.append('submit_stock_alert', '1');
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Alert Sent!',
                                text: data.message,
                                icon: 'success',
                                background: 'rgba(15, 23, 42, 0.95)',
                                color: '#fff',
                                showConfirmButton: false,
                                timer: 2000,
                                customClass: { popup: 'cyber-swal' }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'An error occurred',
                                icon: 'error',
                                background: 'rgba(15, 23, 42, 0.95)',
                                color: '#fff',
                                customClass: { popup: 'cyber-swal' }
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to send alert',
                            icon: 'error',
                            background: 'rgba(15, 23, 42, 0.95)',
                            color: '#fff',
                            customClass: { popup: 'cyber-swal' }
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
