<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

// AJAX endpoint for key lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_lookup'])) {
    header('Content-Type: application/json');
    try {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }
        
        $pdo = getDBConnection();
        $licenseKey = trim($_POST['license_key'] ?? '');
        
        if (empty($licenseKey)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a license key']);
            exit;
        }
        
        $stmt = $pdo->prepare('SELECT lk.id, lk.license_key, lk.duration, lk.duration_type, lk.mod_id, m.name AS mod_name
                               FROM license_keys lk
                               LEFT JOIN mods m ON m.id = lk.mod_id
                               WHERE lk.license_key = ? AND lk.sold_to = ? LIMIT 1');
        $stmt->execute([$licenseKey, $_SESSION['user_id']]);
        $key = $stmt->fetch();
        
        if (!$key) {
            echo json_encode(['success' => false, 'message' => 'Key not found or you do not own this key']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'key' => [
                'id' => $key['id'],
                'license_key' => $key['license_key'],
                'mod_name' => $key['mod_name'] ?? 'Unknown',
                'duration' => $key['duration'],
                'duration_type' => ucfirst($key['duration_type'])
            ]
        ]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

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
$selectedKeyDetails = null;

// Handle key lookup via API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_key'])) {
    $licenseKey = trim($_POST['license_key'] ?? '');
    if (empty($licenseKey)) {
        $error = 'Please enter a license key.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT lk.id, lk.license_key, lk.duration, lk.duration_type, lk.mod_id, m.name AS mod_name
                                   FROM license_keys lk
                                   LEFT JOIN mods m ON m.id = lk.mod_id
                                   WHERE lk.license_key = ? AND lk.sold_to = ? LIMIT 1');
            $stmt->execute([$licenseKey, $user['id']]);
            $key = $stmt->fetch();
            if(!$key){
                $error = 'Key not found or you do not own this key.';
            } else {
                $selectedKeyDetails = $key;
            }
        } catch (Throwable $e) {
            $error = 'Error looking up key: ' . $e->getMessage();
        }
    }
}

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $keySelection = $_POST['key_selection'] ?? ''; // 'dropdown' or 'pasted'
    $keyId = null;
    $requestType = $_POST['request_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if ($keySelection === 'dropdown') {
        $keyId = (int)($_POST['key_id'] ?? 0);
    } elseif ($keySelection === 'pasted') {
        $licenseKey = trim($_POST['license_key'] ?? '');
        if (!empty($licenseKey)) {
            $stmt = $pdo->prepare('SELECT id FROM license_keys WHERE license_key = ? AND sold_to = ? LIMIT 1');
            $stmt->execute([$licenseKey, $user['id']]);
            $key = $stmt->fetch();
            $keyId = $key ? $key['id'] : null;
        }
    }
    
    if (!$keyId || !in_array($requestType, ['block', 'reset'])) {
        $error = 'Invalid request details. Please select a key and request type.';
    } elseif (empty($reason)) {
        $error = 'Please provide a reason for your request.';
    } else {
        try {
            $pdo->beginTransaction();

            // Get key details
            $stmt = $pdo->prepare('SELECT id, license_key, mod_id FROM license_keys WHERE id = ? AND sold_to = ? LIMIT 1');
            $stmt->execute([$keyId, $user['id']]);
            $key = $stmt->fetch();
            if(!$key){
                throw new Exception('Key not found or you do not own this key.');
            }

            // Get mod name
            $stmt = $pdo->prepare('SELECT name FROM mods WHERE id = ? LIMIT 1');
            $stmt->execute([$key['mod_id']]);
            $mod = $stmt->fetch();
            $modName = $mod ? $mod['name'] : 'Unknown';

            // Check if request already exists
            $stmt = $pdo->prepare('SELECT id FROM key_requests WHERE user_id = ? AND key_id = ? AND status = "pending" LIMIT 1');
            $stmt->execute([$user['id'], $keyId]);
            $existingRequest = $stmt->fetch();
            if($existingRequest){
                throw new Exception('You already have a pending request for this key.');
            }

            // Create request
            $stmt = $pdo->prepare('INSERT INTO key_requests (user_id, key_id, request_type, mod_name, reason, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, "pending", CURRENT_TIMESTAMP)');
            $stmt->execute([$user['id'], $keyId, $requestType, $modName, $reason]);

            $pdo->commit();
            $success = 'Your ' . ucfirst($requestType) . ' request has been submitted. Admin will review it soon.';
            $selectedKeyDetails = null;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
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

// Get pending requests with search
$searchQuery = trim($_GET['search'] ?? '');
$pendingRequests = [];
try {
    if (!empty($searchQuery)) {
        $stmt = $pdo->prepare('SELECT kr.id, kr.key_id, kr.request_type, kr.mod_name, kr.reason, kr.created_at, lk.license_key, lk.duration, lk.duration_type
                               FROM key_requests kr
                               LEFT JOIN license_keys lk ON lk.id = kr.key_id
                               WHERE kr.user_id = ? AND kr.status = "pending" AND (kr.mod_name LIKE ? OR lk.license_key LIKE ?)
                               ORDER BY kr.created_at DESC');
        $searchTerm = '%' . $searchQuery . '%';
        $stmt->execute([$user['id'], $searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->prepare('SELECT kr.id, kr.key_id, kr.request_type, kr.mod_name, kr.reason, kr.created_at, lk.license_key, lk.duration, lk.duration_type
                               FROM key_requests kr
                               LEFT JOIN license_keys lk ON lk.id = kr.key_id
                               WHERE kr.user_id = ? AND kr.status = "pending"
                               ORDER BY kr.created_at DESC');
        $stmt->execute([$user['id']]);
    }
    $pendingRequests = $stmt->fetchAll();
} catch (Throwable $e) {
    $pendingRequests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block & Reset Requests - Mod APK Manager</title>
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
        .card-section { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        .card-section h5 { color: var(--purple); font-weight: 600; margin-bottom: 1.5rem; }
        .key-display { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
        .key-detail-item { margin: 0.5rem 0; }
        .key-detail-label { font-weight: 600; color: var(--purple); }
        .key-detail-value { color: var(--text); }
        .form-group { margin-bottom: 1rem; }
        .form-label { font-weight: 500; color: var(--text); margin-bottom: 0.5rem; }
        .form-control { border-radius: 8px; border: 1px solid var(--border); padding: 0.75rem; }
        .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25); }
        .btn { border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 500; }
        .btn-primary { background: var(--purple); border: none; color: white; }
        .btn-primary:hover { background: #7c3aed; color: white; }
        .tab-content { margin-top: 1.5rem; }
        .nav-tabs { border-bottom: 2px solid var(--border); }
        .nav-tabs .nav-link { color: var(--muted); border: none; border-bottom: 2px solid transparent; }
        .nav-tabs .nav-link.active { color: var(--purple); border-bottom-color: var(--purple); background: none; }
        .table { border-radius: 12px; }
        .table thead th { background: var(--purple); color: white; border: none; padding: 1rem; }
        .table tbody td { padding: 1rem; border-bottom: 1px solid var(--border); }
        .badge { padding: 0.35rem 0.75rem; border-radius: 6px; }
        .empty-state { text-align: center; padding: 2rem; color: var(--muted); }
        .alert { border-radius: 8px; border: none; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-4 border-bottom">
                    <h4 style="color: var(--purple); font-weight: 700; margin-bottom: 0;"><i class="fas fa-crown me-2"></i>SilentMultiPanel</h4>
                    <p class="text-muted small mb-0">User Panel</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key"></i>Manage Keys</a>
                    <a class="nav-link" href="user_generate.php"><i class="fas fa-plus"></i>Generate</a>
                    <a class="nav-link" href="user_transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt"></i>Applications</a>
                    <a class="nav-link active" href="user_block_request.php"><i class="fas fa-ban"></i>Block & Reset</a>
                    <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell"></i>Notifications</a>
                    <a class="nav-link" href="user_settings.php"><i class="fas fa-cog"></i>Settings</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-ban me-2"></i>Block & Reset Requests</h2>
                            <p class="text-muted mb-0">Submit block or reset requests for your license keys</p>
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
                
                <!-- Submit Request Form -->
                <div class="card-section">
                    <h5><i class="fas fa-plus-circle me-2"></i>Submit New Request</h5>
                    
                    <div class="mb-4">
                        <label class="form-label">Search & Select License Key</label>
                        <div class="input-group">
                            <input type="text" id="keySearchInput" class="form-control" placeholder="Type license key or mod name to search...">
                            <button class="btn btn-outline-primary" type="button" onclick="filterKeyList()"><i class="fas fa-search"></i></button>
                        </div>
                        <div id="keySearchResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; display: none;">
                            <?php foreach ($purchasedKeys as $key): ?>
                            <button type="button" class="list-group-item list-group-item-action key-item" 
                                    data-id="<?php echo $key['id']; ?>" 
                                    data-key="<?php echo htmlspecialchars($key['license_key']); ?>"
                                    data-mod="<?php echo htmlspecialchars($key['mod_name']); ?>"
                                    onclick="selectKey('<?php echo $key['id']; ?>', '<?php echo htmlspecialchars($key['mod_name']); ?>', '<?php echo htmlspecialchars($key['license_key']); ?>')">
                                <strong><?php echo htmlspecialchars($key['mod_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($key['license_key']); ?></small>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="POST" id="requestForm">
                        <input type="hidden" name="key_selection" value="dropdown">
                        <input type="hidden" name="key_id" id="selectedKeyId" required>
                        
                        <div id="selectedKeyDisplay" class="key-display" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1" id="displayModName"></h6>
                                    <code id="displayLicenseKey"></code>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearSelection()"><i class="fas fa-times"></i></button>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Request Type *</label>
                                    <select class="form-control" name="request_type" required>
                                        <option value="">-- Select type --</option>
                                        <option value="block">Block Key</option>
                                        <option value="reset">Reset Key</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reason for Request *</label>
                            <textarea class="form-control" name="reason" rows="4" placeholder="Explain why you need to block or reset this key..." required></textarea>
                        </div>
                        <button type="submit" name="submit_request" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Request
                        </button>
                    </form>
                </div>

                <script>
                function filterKeyList() {
                    const input = document.getElementById('keySearchInput').value.toLowerCase();
                    const results = document.getElementById('keySearchResults');
                    const items = results.getElementsByClassName('key-item');
                    let hasMatch = false;

                    if (input.length < 1) {
                        results.style.display = 'none';
                        return;
                    }

                    for (let item of items) {
                        const key = item.getAttribute('data-key').toLowerCase();
                        const mod = item.getAttribute('data-mod').toLowerCase();
                        if (key.includes(input) || mod.includes(input)) {
                            item.style.display = 'block';
                            hasMatch = true;
                        } else {
                            item.style.display = 'none';
                        }
                    }
                    results.style.display = hasMatch ? 'block' : 'none';
                }

                document.getElementById('keySearchInput').addEventListener('input', filterKeyList);

                function selectKey(id, mod, key) {
                    document.getElementById('selectedKeyId').value = id;
                    document.getElementById('displayModName').textContent = mod;
                    document.getElementById('displayLicenseKey').textContent = key;
                    document.getElementById('selectedKeyDisplay').style.display = 'block';
                    document.getElementById('keySearchResults').style.display = 'none';
                    document.getElementById('keySearchInput').value = '';
                }

                function clearSelection() {
                    document.getElementById('selectedKeyId').value = '';
                    document.getElementById('selectedKeyDisplay').style.display = 'none';
                }
                </script>
                
                <!-- Pending Requests -->
                <div class="card-section">
                    <h5><i class="fas fa-hourglass-half me-2"></i>Pending Requests</h5>
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Search by mod name or license key..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
                            <?php if (!empty($searchQuery)): ?>
                            <a href="user_block_request.php" class="btn btn-secondary"><i class="fas fa-times me-1"></i>Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if (empty($pendingRequests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--purple); opacity: 0.5; margin-bottom: 1rem;"></i>
                            <p>No pending requests</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>License Key</th>
                                        <th>Duration</th>
                                        <th>Request Type</th>
                                        <th>Status</th>
                                        <th>Submitted On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['mod_name']); ?></td>
                                        <td><code style="background: #f8fafc; padding: 0.5rem; border-radius: 6px; font-size: 0.85em;"><?php echo htmlspecialchars(substr($req['license_key'], 0, 20)) . '...'; ?></code></td>
                                        <td><span class="badge bg-info"><?php echo $req['duration'] . ' ' . ucfirst($req['duration_type']); ?></span></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo ucfirst($req['request_type']); ?></span></td>
                                        <td><span class="badge bg-secondary">Pending</span></td>
                                        <td><?php echo formatDate($req['created_at']); ?></td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('lookupBtn').addEventListener('click', async function() {
            const keyInput = document.getElementById('pasteKeyInput').value.trim();
            const container = document.getElementById('keyResultContainer');
            const form = document.getElementById('pasteForm');
            
            if (!keyInput) {
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Please enter a license key</div>';
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ajax_lookup', '1');
                formData.append('license_key', keyInput);
                
                const response = await fetch('user_block_request.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const key = data.key;
                    container.innerHTML = `
                        <div class="key-display">
                            <strong><i class="fas fa-check-circle me-2" style="color: #10b981;"></i>Key Found!</strong>
                            <div class="key-detail-item mt-2">
                                <span class="key-detail-label">Product:</span>
                                <span class="key-detail-value">${key.mod_name}</span>
                            </div>
                            <div class="key-detail-item">
                                <span class="key-detail-label">Duration:</span>
                                <span class="key-detail-value">${key.duration} ${key.duration_type}</span>
                            </div>
                            <div class="key-detail-item">
                                <span class="key-detail-label">License Key:</span>
                                <span class="key-detail-value" style="font-family: monospace;">${key.license_key}</span>
                            </div>
                        </div>
                    `;
                    document.getElementById('licenseKeyHidden').value = key.license_key;
                    form.style.display = 'block';
                } else {
                    container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>${data.message}</div>`;
                    form.style.display = 'none';
                }
            } catch (error) {
                container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Error: ${error.message}</div>`;
                form.style.display = 'none';
            }
        });
        
        document.getElementById('pasteKeyInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('lookupBtn').click();
            }
        });
    </script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
