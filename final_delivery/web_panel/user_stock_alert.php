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
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    mod_id INT NOT NULL,
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
    <title>Stock Alert - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            position: relative; 
            overflow: hidden; 
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.3);
        }

        .glass-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(30px); 
            -webkit-backdrop-filter: blur(30px); 
            border: 1px solid var(--border-light); 
            border-radius: 24px; 
            padding: 25px; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); 
            margin-bottom: 2rem; 
        }

        .form-control, .form-select { 
            background: rgba(15, 23, 42, 0.5); 
            border: 1.5px solid var(--border-light); 
            border-radius: 14px; 
            padding: 14px; 
            color: white; 
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus { 
            outline: none; 
            border-color: var(--primary); 
            background: rgba(15, 23, 42, 0.7); 
            color: white; 
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2); 
        }

        .btn-submit { 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            border: none; 
            border-radius: 14px; 
            padding: 14px 24px; 
            color: white; 
            font-weight: 700; 
            transition: all 0.3s; 
            width: 100%; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3); }

        .alert-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .alert-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(139, 92, 246, 0.3);
            transform: scale(1.02);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .badge-resolved { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            opacity: 0.5;
        }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: var(--primary); }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus"></i>Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key"></i>Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt"></i>Applications</a>
            <a class="nav-link active" href="user_stock_alert.php"><i class="fas fa-warehouse"></i>Stock Alert</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog"></i>Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history"></i>Transactions</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="text-white mb-1" style="font-weight: 800;">Stock Alerts</h2>
                    <p class="text-white opacity-75 mb-0">Help us restock by reporting missing items</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-inline-block text-start bg-black bg-opacity-25 px-3 py-2 rounded-3">
                        <div class="small text-white opacity-50">Current Balance</div>
                        <div class="h5 mb-0 fw-bold"><?php echo formatCurrency($user['balance']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="glass-card">
                    <h5 class="mb-4 fw-bold text-white"><i class="fas fa-bell me-2 text-primary"></i>Notify Admin</h5>
                    <form id="stockAlertForm">
                        <div class="mb-4">
                            <label class="form-label small text-dim fw-bold">SELECT SOLD OUT PRODUCT</label>
                            <select name="mod_id" id="modSelect" class="form-select" required>
                                <option value="">Choose a product...</option>
                                <?php foreach ($mods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>" data-name="<?php echo htmlspecialchars($mod['name']); ?>">
                                        <?php echo htmlspecialchars($mod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" onclick="submitStockAlert()" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Send Alert
                        </button>
                    </form>
                </div>
                
                <div class="glass-card bg-opacity-25" style="background: rgba(139, 92, 246, 0.05);">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                            <i class="fas fa-info-circle text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-white fw-bold mb-1">How it works?</h6>
                            <p class="small text-dim mb-0">When you alert us about a product being out of stock, our team prioritizes restocking it immediately. You'll see the status change here once restocked.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="glass-card">
                    <h5 class="mb-4 fw-bold text-white"><i class="fas fa-history me-2 text-primary"></i>Your Alert History</h5>
                    <div class="alert-list">
                        <?php if (empty($myAlerts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h6 class="text-white">No alerts yet</h6>
                                <p class="small text-dim">Products you report will appear here</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($myAlerts as $alert): ?>
                                <div class="alert-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-white fw-bold mb-1"><?php echo htmlspecialchars($alert['mod_name']); ?></h6>
                                            <div class="small text-dim">
                                                <i class="far fa-clock me-1"></i> <?php echo formatDate($alert['created_at']); ?>
                                            </div>
                                        </div>
                                        <span class="status-badge <?php echo $alert['status'] === 'pending' ? 'badge-pending' : 'badge-resolved'; ?>">
                                            <?php echo ucfirst($alert['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');

        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        function submitStockAlert() {
            const modSelect = document.getElementById('modSelect');
            const modId = modSelect.value;
            const modName = modSelect.options[modSelect.selectedIndex].getAttribute('data-name');
            
            if (!modId) {
                Swal.fire({ icon: 'error', title: 'Oops...', text: 'Please select a product first!', background: '#0a0e27', color: '#fff' });
                return;
            }
            
            Swal.fire({
                title: 'Send Alert?',
                text: `Notify admin that "${modName}" is out of stock?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#8b5cf6',
                cancelButtonColor: '#1e293b',
                confirmButtonText: 'Yes, Send!',
                background: '#0a0e27',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('mod_id', modId);
                    formData.append('mod_name', modName);
                    formData.append('submit_stock_alert', '1');
                    
                    fetch(window.location.href, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ icon: 'success', title: 'Sent!', text: 'Admin has been notified.', background: '#0a0e27', color: '#fff', timer: 2000, showConfirmButton: false })
                            .then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: data.message, background: '#0a0e27', color: '#fff' });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>