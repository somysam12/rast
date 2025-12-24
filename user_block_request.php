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

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $keyId = (int)($_POST['key_id'] ?? 0);
    $requestType = $_POST['request_type'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    if ($keyId <= 0 || !in_array($requestType, ['block', 'reset'])) {
        $error = 'Invalid request details.';
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
    $stmt = $pdo->prepare('SELECT kr.id, kr.key_id, kr.request_type, kr.mod_name, kr.reason, kr.created_at
                           FROM key_requests kr
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
        .form-group { margin-bottom: 1rem; }
        .form-label { font-weight: 500; color: var(--text); margin-bottom: 0.5rem; }
        .form-control { border-radius: 8px; border: 1px solid var(--border); padding: 0.75rem; }
        .form-control:focus { border-color: var(--purple); box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25); }
        .btn { border-radius: 8px; padding: 0.75rem 1.5rem; font-weight: 500; }
        .btn-primary { background: var(--purple); border: none; color: white; }
        .btn-primary:hover { background: #7c3aed; color: white; }
        .btn-danger { background: #ef4444; border: none; color: white; }
        .btn-danger:hover { background: #dc2626; color: white; }
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
                    <a class="nav-link" href="user_balance.php"><i class="fas fa-wallet"></i>Balance</a>
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
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Select License Key *</label>
                                    <select class="form-control" name="key_id" required>
                                        <option value="">-- Select a key --</option>
                                        <?php foreach ($purchasedKeys as $key): ?>
                                        <option value="<?php echo $key['id']; ?>">
                                            <?php echo htmlspecialchars($key['mod_name']); ?> - <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
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
                
                <!-- Pending Requests -->
                <div class="card-section">
                    <h5><i class="fas fa-hourglass-half me-2"></i>Pending Requests</h5>
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
                                        <th>Mod Name</th>
                                        <th>Request Type</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Submitted On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['mod_name']); ?></td>
                                        <td><span class="badge bg-warning text-dark"><?php echo ucfirst($req['request_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars(substr($req['reason'], 0, 50)) . (strlen($req['reason']) > 50 ? '...' : ''); ?></td>
                                        <td><span class="badge bg-info">Pending</span></td>
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
</body>
</html>
