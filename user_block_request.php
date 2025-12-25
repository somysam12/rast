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
                               WHERE lk.sold_to = ? AND (lk.license_key LIKE ? OR m.name LIKE ?) 
                               LIMIT 10');
        $searchTerm = "%" . $licenseKey . "%";
        $stmt->execute([$_SESSION['user_id'], $searchTerm, $searchTerm]);
        $keys = $stmt->fetchAll();
        
        if (empty($keys)) {
            echo json_encode(['success' => false, 'message' => 'No matching keys found in your account']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'keys' => array_map(function($k) {
                return [
                    'id' => $k['id'],
                    'license_key' => $k['license_key'],
                    'mod_name' => $k['mod_name'] ?? 'Unknown',
                    'duration' => $k['duration'],
                    'duration_type' => ucfirst($k['duration_type'])
                ];
            }, $keys)
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

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $keySelection = 'pasted';
    $keyId = null;
    $requestType = $_POST['request_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $licenseKey = trim($_POST['license_key'] ?? '');

    if (!empty($licenseKey)) {
        $stmt = $pdo->prepare('SELECT id FROM license_keys WHERE license_key = ? AND sold_to = ? LIMIT 1');
        $stmt->execute([$licenseKey, $user['id']]);
        $key = $stmt->fetch();
        $keyId = $key ? $key['id'] : null;
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

// Get pending requests
$pendingRequests = [];
try {
    $stmt = $pdo->prepare('SELECT kr.id, kr.key_id, kr.request_type, kr.mod_name, kr.reason, kr.created_at, lk.license_key, lk.duration, lk.duration_type
                           FROM key_requests kr
                           LEFT JOIN license_keys lk ON lk.id = kr.key_id
                           WHERE kr.user_id = ? AND kr.status = "pending"
                           ORDER BY kr.created_at DESC');
    $stmt->execute([$user['id']]);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .table { border-radius: 12px; }
        .table thead th { background: var(--purple); color: white; border: none; padding: 1rem; }
        .table tbody td { padding: 1rem; border-bottom: 1px solid var(--border); }
        .badge { padding: 0.35rem 0.75rem; border-radius: 6px; }
        .empty-state { text-align: center; padding: 2rem; color: var(--muted); }
        .alert { border-radius: 8px; border: none; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .bg-purple-soft { background: #f3e8ff; }
        .text-purple { color: #8b5cf6; }
        .cursor-pointer { cursor: pointer; }
        .hover-shadow:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transition: all 0.2s; }
        @media (max-width: 991.98px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; padding-top: 20px !important; }
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
                        <div class="d-flex align-items-center">
                            <a href="user_dashboard.php" class="btn btn-outline-primary btn-sm me-3" title="Back to Dashboard">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div>
                                <h2 class="mb-2"><i class="fas fa-ban me-2"></i>Block & Reset</h2>
                                <p class="text-muted mb-0">Submit block or reset requests for your license keys</p>
                            </div>
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
                    <h5><i class="fas fa-search me-2"></i>Find & Select License</h5>
                    
                    <div class="mb-4">
                        <label class="form-label">Search License Key</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-purple"></i></span>
                            <input type="text" id="licenseSearchInput" class="form-control border-start-0 ps-0" placeholder="Type or paste license key..." style="box-shadow: none;">
                        </div>
                        <div id="searchResults" class="list-group mt-2 shadow-sm" style="display: none; position: absolute; width: calc(100% - 4rem); z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                        <div id="searchLoading" class="text-center mt-3" style="display: none;">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="ms-2">Searching...</span>
                        </div>
                    </div>

                    <div id="userKeysList" class="mb-4">
                        <label class="form-label text-muted small uppercase fw-bold">Your Available Licenses</label>
                        <div class="row g-2" id="keysContainer">
                            <?php foreach ($purchasedKeys as $key): ?>
                            <div class="col-md-6 key-item" data-key="<?php echo htmlspecialchars($key['license_key']); ?>" data-mod="<?php echo htmlspecialchars($key['mod_name']); ?>">
                                <div class="p-3 border rounded bg-light hover-shadow cursor-pointer" onclick="selectFromList('<?php echo addslashes($key['license_key']); ?>')">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($key['mod_name']); ?></div>
                                            <code class="small text-muted"><?php echo htmlspecialchars($key['license_key']); ?></code>
                                        </div>
                                        <span class="badge bg-purple-soft text-purple"><?php echo $key['duration'] . ' ' . $key['duration_type']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <form method="POST" id="requestForm">
                        <input type="hidden" name="license_key" id="verifiedLicenseKey">
                        
                        <div id="selectedKeyDisplay" class="key-display shadow-sm border-0" style="display: none; background: linear-gradient(145deg, #ffffff, #f8fafc); border: 1px solid #e2e8f0 !important;">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="badge bg-primary mb-2">Selected License</div>
                                    <h5 class="mb-1 text-dark" id="displayModName" style="font-weight: 700;"></h5>
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-clock me-1"></i>Duration: <span id="displayDuration" class="fw-bold text-dark"></span>
                                    </p>
                                    <div class="p-3 bg-white rounded border">
                                        <code id="displayLicenseKey" class="text-primary fw-bold" style="word-break: break-all; font-size: 0.95rem;"></code>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="clearSelection()" title="Clear selection">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </button>
                            </div>
                        </div>

                        <div id="requestDetails" style="display: none;">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="form-label fw-bold">Select Action *</label>
                                        <div class="d-flex gap-3">
                                            <div class="flex-fill">
                                                <input type="radio" class="btn-check" name="request_type" id="type_block" value="block" autocomplete="off" required>
                                                <label class="btn btn-outline-danger w-100 py-3 d-flex flex-column align-items-center justify-content-center" for="type_block" style="border-width: 2px;">
                                                    <i class="fas fa-ban fa-lg mb-2"></i> <span>Block Key</span>
                                                </label>
                                            </div>
                                            <div class="flex-fill">
                                                <input type="radio" class="btn-check" name="request_type" id="type_reset" value="reset" autocomplete="off">
                                                <label class="btn btn-outline-primary w-100 py-3 d-flex flex-column align-items-center justify-content-center" for="type_reset" style="border-width: 2px;">
                                                    <i class="fas fa-redo fa-lg mb-2"></i> <span>Reset HWID</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mt-3">
                                <label class="form-label fw-bold">Reason for Request *</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Please explain why you want to perform this action..." required style="background: #f8fafc;"></textarea>
                            </div>
                            <button type="submit" name="submit_request" class="btn btn-primary w-100 mt-4 py-3 shadow-sm" style="font-size: 1.1rem; font-weight: 600;">
                                <i class="fas fa-paper-plane me-2"></i>Send Request to Admin
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                const searchInput = document.getElementById('licenseSearchInput');
                const resultsDiv = document.getElementById('searchResults');
                const loading = document.getElementById('searchLoading');
                const display = document.getElementById('selectedKeyDisplay');
                const requestDetails = document.getElementById('requestDetails');
                const verifiedKeyInput = document.getElementById('verifiedLicenseKey');
                const keyItems = document.querySelectorAll('.key-item');

                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    
                    // Filter the existing list visually
                    keyItems.forEach(item => {
                        const key = item.getAttribute('data-key').toLowerCase();
                        const mod = item.getAttribute('data-mod').toLowerCase();
                        if (key.includes(query) || mod.includes(query)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    clearTimeout(searchTimeout);
                    if (query.length < 3) {
                        resultsDiv.style.display = 'none';
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        performSearch(query);
                    }, 300);
                });

                function performSearch(query) {
                    loading.style.display = 'block';
                    resultsDiv.style.display = 'none';

                    const formData = new FormData();
                    formData.append('ajax_lookup', '1');
                    formData.append('license_key', query);

                    fetch('user_block_request.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        loading.style.display = 'none';
                        if (data.success) {
                            resultsDiv.innerHTML = '';
                            data.keys.forEach(key => {
                                const item = document.createElement('a');
                                item.href = 'javascript:void(0)';
                                item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                                item.innerHTML = `
                                    <div>
                                        <div class="fw-bold">${key.mod_name}</div>
                                        <small class="text-muted">${key.license_key}</small>
                                    </div>
                                    <span class="badge bg-purple rounded-pill">${key.duration} ${key.duration_type}</span>
                                `;
                                item.onclick = () => {
                                    showDetails(key);
                                    resultsDiv.style.display = 'none';
                                    searchInput.value = key.license_key;
                                };
                                resultsDiv.appendChild(item);
                            });
                            resultsDiv.style.display = 'block';
                        } else {
                            if (query.length > 5 && !document.querySelector('.swal2-container')) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Oops...',
                                    text: 'This key is wrong or not generated from your account!',
                                    confirmButtonColor: '#8b5cf6',
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                            }
                            resultsDiv.innerHTML = '<div class="list-group-item text-muted">No matching license key found</div>';
                            resultsDiv.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        loading.style.display = 'none';
                        console.error('Search error:', err);
                    });
                }

                function showDetails(keyData) {
                    document.getElementById('displayModName').textContent = keyData.mod_name;
                    document.getElementById('displayDuration').textContent = `${keyData.duration} ${keyData.duration_type}`;
                    document.getElementById('displayLicenseKey').textContent = keyData.license_key;
                    verifiedKeyInput.value = keyData.license_key;
                    
                    display.style.display = 'block';
                    requestDetails.style.display = 'block';
                }

                function hideDetails() {
                    display.style.display = 'none';
                    requestDetails.style.display = 'none';
                    verifiedKeyInput.value = '';
                }

                function clearSelection() {
                    searchInput.value = '';
                    hideDetails();
                    resultsDiv.style.display = 'none';
                    keyItems.forEach(item => item.style.display = 'block');
                }

                function selectFromList(key) {
                    searchInput.value = key;
                    const event = new Event('input');
                    searchInput.dispatchEvent(event);
                }

                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                        resultsDiv.style.display = 'none';
                    }
                });
                </script>
                
                <!-- Pending Requests -->
                <div class="card-section">
                    <h5><i class="fas fa-hourglass-half me-2"></i>Pending Requests</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>License Key</th>
                                    <th>Duration</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?php echo htmlspecialchars($req['mod_name']); ?></td>
                                    <td><code class="text-primary"><?php echo htmlspecialchars($req['license_key']); ?></code></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $req['duration'] . ' ' . $req['duration_type']; ?></span></td>
                                    <td>
                                        <span class="badge bg-<?php echo $req['request_type'] === 'block' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($req['request_type']); ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?php echo formatDate($req['created_at']); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($pendingRequests)): ?>
                                <tr>
                                    <td colspan="5" class="empty-state">No pending requests found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>