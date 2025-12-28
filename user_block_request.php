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

        .type-btn {
            border: 2px solid rgba(148, 163, 184, 0.1);
            background: rgba(15, 23, 42, 0.5);
            color: var(--text-secondary);
            padding: 20px;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            width: 100%;
        }

        .type-radio:checked + .type-btn {
            border-color: #8b5cf6;
            background: rgba(139, 92, 246, 0.1);
            color: white;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 12px;
            z-index: 1002;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .search-result-item {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .search-result-item:hover {
            background: rgba(139, 92, 246, 0.1);
        }
        .search-result-item:last-child {
            border-bottom: none;
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
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link active" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Block & Reset Requests</h2>
            <p class="text-secondary mb-0">Submit requests for key blocking or HWID resetting.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <div class="cyber-card">
                    <h5 class="mb-4 text-white"><i class="fas fa-search text-primary me-2"></i> Find & Select Key</h5>
                    
                    <div class="mb-4 position-relative">
                        <label class="form-label text-secondary small fw-bold">SEARCH LICENSE KEY</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary"><i class="fas fa-search"></i></span>
                            <input type="text" id="keySearch" class="form-control border-start-0" placeholder="Type key or mod name to search..." autocomplete="off">
                        </div>
                        <div id="searchResults" class="search-results"></div>
                    </div>

                    <h5 class="mb-4 text-white border-top pt-4"><i class="fas fa-paper-plane text-primary me-2"></i> New Request</h5>
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
                            <label class="form-label text-secondary small fw-bold">REQUEST TYPE</label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <input type="radio" name="request_type" id="type_block" value="block" class="type-radio d-none" required>
                                    <label for="type_block" class="type-btn">
                                        <i class="fas fa-ban d-block mb-2 fa-lg"></i> Block Key
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" name="request_type" id="type_reset" value="reset" class="type-radio d-none">
                                    <label for="type_reset" class="type-btn">
                                        <i class="fas fa-redo d-block mb-2 fa-lg"></i> Reset HWID
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">REASON</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Explain your request..." required></textarea>
                        </div>

                        <button type="submit" name="submit_request" class="cyber-btn w-100 py-3">
                            Submit Request
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
                            <div class="request-card mb-3">
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
                                    <button type="submit" name="cancel_request" class="btn btn-sm btn-outline-danger w-100 rounded-pill">
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
                        <div class="fw-bold text-white">${k.mod_name}</div>
                        <div class="small text-secondary">${k.license_key}</div>
                    `;
                    item.onclick = () => {
                        licenseSelect.value = k.license_key;
                        keySearch.value = k.mod_name + ' (' + k.license_key + ')';
                        searchResults.style.display = 'none';
                    };
                    searchResults.appendChild(item);
                });
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-secondary small">No matching keys found.</div>';
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