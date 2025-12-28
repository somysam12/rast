<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

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

function formatCurrency($amount){
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

if (!function_exists('formatDate')) {
    function formatDate($dt){
        if(!$dt){ return '-'; }
        $date = new DateTime($dt, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $date->format('d M Y, h:i A');
    }
}

$success = $_SESSION['success'] ?? '';
$showCancelAnimation = !empty($success);
unset($_SESSION['success']);
$error = '';

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $requestType = $_POST['request_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $licenseKey = trim($_POST['license_key'] ?? '');

    if (empty($licenseKey) || !in_array($requestType, ['block', 'reset'])) {
        $error = 'Invalid request details. Please select a key and request type.';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for your request.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, license_key, mod_id FROM license_keys WHERE license_key = ? AND sold_to = ? LIMIT 1');
            $stmt->execute([$licenseKey, $user['id']]);
            $key = $stmt->fetch();
            if(!$key){
                throw new Exception('Key not found or you do not own this key.');
            }

            $stmt = $pdo->prepare('SELECT name FROM mods WHERE id = ? LIMIT 1');
            $stmt->execute([$key['mod_id']]);
            $mod = $stmt->fetch();
            $modName = $mod ? $mod['name'] : 'Unknown';

            $stmt = $pdo->prepare('SELECT id FROM key_requests WHERE user_id = ? AND key_id = ? AND status = "pending" LIMIT 1');
            $stmt->execute([$user['id'], $key['id']]);
            if($stmt->fetch()){
                throw new Exception('You already have a pending request for this key.');
            }

            $stmt = $pdo->prepare('INSERT INTO key_requests (user_id, key_id, request_type, mod_name, reason, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, "pending", CURRENT_TIMESTAMP)');
            $stmt->execute([$user['id'], $key['id'], $requestType, $modName, $reason]);

            $pdo->commit();
            $success = 'Your ' . ucfirst($requestType) . ' request has been submitted successfully.';
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Request Submitted!',
                        text: 'Your request has been submitted and admin will process your request soon.',
                        icon: 'success',
                        background: 'rgba(15, 23, 42, 0.95)',
                        color: '#fff',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        backdrop: `rgba(139, 92, 246, 0.1)`,
                        customClass: {
                            popup: 'cyber-swal'
                        }
                    });
                });
            </script>";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
}

// Handle request cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_request'])) {
    $requestId = $_POST['request_id'] ?? '';
    if (ctype_digit((string)$requestId)) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM key_confirmations WHERE request_id = ?")->execute([$requestId]);
            $pdo->prepare("DELETE FROM key_requests WHERE id = ? AND user_id = ?")->execute([$requestId, $user['id']]);
            $pdo->commit();
            $_SESSION['success'] = 'Request cancelled successfully.';
            header('Location: user_block_request.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get user's purchased keys
$purchasedKeys = [];
try {
    $stmt = $pdo->prepare('SELECT lk.*, m.name AS mod_name FROM license_keys lk LEFT JOIN mods m ON m.id = lk.mod_id WHERE lk.sold_to = ? ORDER BY lk.sold_at DESC');
    $stmt->execute([$user['id']]);
    $purchasedKeys = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get pending requests
$pendingRequests = [];
try {
    $stmt = $pdo->prepare('SELECT kr.id, kr.request_type, kr.mod_name, kr.reason, kr.created_at, lk.license_key FROM key_requests kr LEFT JOIN license_keys lk ON lk.id = kr.key_id WHERE kr.user_id = ? AND kr.status = "pending" ORDER BY kr.created_at DESC');
    $stmt->execute([$user['id']]);
    $pendingRequests = $stmt->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Block & Reset - SilentMultiPanel</title>
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
        
        .request-card {
            background: rgba(10, 15, 25, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .form-select, .form-control {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid rgba(148, 163, 184, 0.1);
            color: white;
            border-radius: 12px;
            padding: 12px;
        }

        .form-select:focus, .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #8b5cf6;
            color: white;
            box-shadow: none;
        }

        /* Cool Stylish Search Option */
        .search-container {
            position: relative;
            margin-bottom: 2rem;
        }
        .stylish-search-wrapper {
            position: relative;
            background: rgba(15, 23, 42, 0.6);
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .stylish-search-wrapper:focus-within {
            border-color: #8b5cf6;
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.3);
            transform: translateY(-2px);
        }
        .stylish-search-wrapper i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #8b5cf6;
            font-size: 1.2rem;
            z-index: 1;
        }
        .stylish-search-input {
            width: 100%;
            background: transparent !important;
            border: none !important;
            padding: 15px 15px 15px 50px !important;
            color: white !important;
            font-size: 1rem;
            outline: none !important;
        }
        .stylish-search-input::placeholder {
            color: rgba(148, 163, 184, 0.4);
        }

        /* Cool Action Buttons */
        .type-radio:checked + .type-btn {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.15));
            color: white;
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.3);
            transform: translateY(-5px);
        }
        .type-btn {
            border: 2px solid rgba(148, 163, 184, 0.1);
            background: rgba(15, 23, 42, 0.5);
            color: var(--text-secondary);
            padding: 30px 20px;
            border-radius: 24px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .type-btn i {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: transform 0.3s ease;
        }
        .type-btn:hover {
            border-color: rgba(139, 92, 246, 0.5);
            transform: translateY(-3px);
        }
        .type-btn:hover i {
            transform: scale(1.1) rotate(5deg);
        }

        .submit-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border: none;
            border-radius: 16px;
            padding: 18px;
            color: white;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
            position: relative;
            overflow: hidden;
        }
        .submit-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(139, 92, 246, 0.6);
            filter: brightness(1.15);
        }
        .submit-btn:active {
            transform: translateY(-1px);
        }
        .submit-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }
        .submit-btn:hover::after {
            left: 100%;
        }

        /* Stylish Results */
        .search-results {
            position: absolute;
            top: calc(100% + 12px);
            left: 0;
            right: 0;
            background: rgba(10, 15, 25, 0.98);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 20px;
            z-index: 1002;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7);
            padding: 12px;
        }
        .search-result-item {
            padding: 16px;
            border-radius: 14px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid transparent;
        }
        .search-result-item:hover {
            background: rgba(139, 92, 246, 0.15);
            border-color: rgba(139, 92, 246, 0.2);
            transform: translateX(8px);
        }
        .search-result-item .mod-name {
            font-weight: 800;
            color: white;
            font-size: 1.05rem;
        }
        .search-result-item .key-text {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #8b5cf6;
            font-size: 0.85rem;
            opacity: 0.8;
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
        
        @keyframes slideOutAndFade {
            0% { opacity: 1; transform: translateX(0) scale(1); }
            50% { opacity: 1; transform: translateX(20px) scale(1.02); }
            100% { opacity: 0; transform: translateX(-100%) scale(0.95); }
        }
        
        .request-card.cancelled {
            animation: slideOutAndFade 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
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
            <a class="nav-link active" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_stock_alert.php"><i class="fas fa-warehouse me-2"></i> Stock Alert</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Block & Reset Requests</h2>
            <p class="text-secondary mb-0">Manage your device activations and key status securely.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="cyber-card">
                    <h5 class="mb-4 text-white"><i class="fas fa-search text-primary me-2"></i> Find & Select Key</h5>
                    
                    <div class="search-container">
                        <div class="stylish-search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="keySearch" class="stylish-search-input" placeholder="Search by key or application name..." autocomplete="off">
                        </div>
                        <div id="searchResults" class="search-results"></div>
                    </div>

                    <h5 class="mb-4 text-white border-top border-secondary border-opacity-10 pt-4">
                        <i class="fas fa-paper-plane text-primary me-2"></i> New Request
                    </h5>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">SELECTED LICENSE KEY</label>
                            <select name="license_key" id="license_key_select" class="form-select" required>
                                <option value="">Choose a key...</option>
                                <?php foreach ($purchasedKeys as $key): ?>
                                    <option value="<?php echo htmlspecialchars($key['license_key']); ?>">
                                        <?php echo htmlspecialchars($key['mod_name'] . ' (' . $key['license_key'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">CHOOSE ACTION</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <input type="radio" name="request_type" id="type_block" value="block" class="type-radio d-none" required>
                                    <label for="type_block" class="type-btn">
                                        <i class="fas fa-ban"></i>
                                        <span class="fw-bold">Block Key</span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" name="request_type" id="type_reset" value="reset" class="type-radio d-none">
                                    <label for="type_reset" class="type-btn">
                                        <i class="fas fa-sync-alt"></i>
                                        <span class="fw-bold">Reset HWID</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">REASON FOR REQUEST</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Explain why you need this action..." required></textarea>
                        </div>

                        <button type="submit" name="submit_request" class="submit-btn w-100">
                            Send Request to Admin
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="cyber-card h-100">
                    <h5 class="mb-4 text-white"><i class="fas fa-clock text-secondary me-2"></i> Pending Requests</h5>
                    <?php if (empty($pendingRequests)): ?>
                        <div class="text-center py-5 text-secondary">
                            <i class="fas fa-inbox d-block mb-3" style="font-size: 2rem; opacity: 0.2;"></i>
                            No pending requests.
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingRequests as $req): ?>
                            <div class="request-card mb-3 border border-secondary border-opacity-10">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary">
                                        <?php echo strtoupper($req['request_type']); ?>
                                    </span>
                                    <small class="text-secondary"><?php echo formatDate($req['created_at']); ?></small>
                                </div>
                                <div class="fw-bold text-white mb-1"><?php echo htmlspecialchars($req['mod_name']); ?></div>
                                <div class="small text-secondary mb-3"><?php echo htmlspecialchars($req['license_key']); ?></div>
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" name="cancel_request" class="btn btn-sm btn-outline-danger w-100 rounded-pill py-2">
                                        Cancel Request
                                    </button>
                                </form>
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
        
        <?php if ($showCancelAnimation): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Request Cancelled!',
                text: 'Your request has been successfully cancelled.',
                icon: 'success',
                background: 'rgba(15, 23, 42, 0.95)',
                color: '#fff',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                backdrop: `rgba(34, 197, 94, 0.1)`,
                customClass: {
                    popup: 'cyber-swal'
                },
                didOpen: (toast) => {
                    const confirmButton = toast.querySelector('[role="status"]');
                    if (confirmButton) {
                        confirmButton.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
                    }
                }
            });
        });
        <?php endif; ?>

        const keySearch = document.getElementById('keySearch');
        const searchResults = document.getElementById('searchResults');
        const licenseSelect = document.getElementById('license_key_select');
        const purchasedKeys = <?php echo json_encode($purchasedKeys); ?>;

        keySearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            searchResults.innerHTML = '';
            
            if (query.length < 1) {
                searchResults.style.display = 'none';
                return;
            }

            const filtered = purchasedKeys.filter(k => 
                k.license_key.toLowerCase().includes(query) || 
                k.mod_name.toLowerCase().includes(query)
            );

            if (filtered.length > 0) {
                filtered.forEach(k => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.innerHTML = `
                        <div>
                            <div class="mod-name">${k.mod_name}</div>
                            <div class="key-text">${k.license_key}</div>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    `;
                    item.onclick = () => {
                        licenseSelect.value = k.license_key;
                        keySearch.value = k.mod_name + ' (' + k.license_key + ')';
                        searchResults.style.display = 'none';
                        // Add selection animation
                        const selectBox = document.getElementById('license_key_select');
                        selectBox.style.borderColor = '#8b5cf6';
                        setTimeout(() => selectBox.style.borderColor = '', 1000);
                    };
                    searchResults.appendChild(item);
                });
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-secondary text-center small">No matching licenses found.</div>';
                searchResults.style.display = 'block';
            }
        });

        document.addEventListener('click', function(e) {
            if (!keySearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>