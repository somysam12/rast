<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

function formatDate($dt){
    if(!$dt){ return '-'; }
    return date('d M Y, h:i A', strtotime($dt));
}

// Handle admin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($requestId && in_array($action, ['approve', 'reject'])) {
        try {
            // Get request details
            $stmt = $pdo->prepare("SELECT kr.*, lk.license_key, lk.duration, lk.duration_type FROM key_requests kr 
                                   LEFT JOIN license_keys lk ON lk.id = kr.key_id WHERE kr.id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                
                // Update request status
                $stmt = $pdo->prepare("UPDATE key_requests SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $requestId]);
                
                // Create confirmation notification
                $msgText = "Your request to {$request['request_type']} the key for {$request['mod_name']} has been " . ($action === 'approve' ? 'approved' : 'rejected') . ".";
                
                $stmt = $pdo->prepare("INSERT INTO key_confirmations (user_id, request_id, action_type, message) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$request['user_id'], $requestId, $request['request_type'], $msgText]);
                
                $message = $action === 'approve' ? 'Request approved!' : 'Request rejected!';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get pending requests
$pendingRequests = [];
try {
    $stmt = $pdo->query("SELECT kr.*, u.username, lk.license_key 
                        FROM key_requests kr 
                        JOIN users u ON kr.user_id = u.id 
                        LEFT JOIN license_keys lk ON lk.id = kr.key_id
                        WHERE kr.status = 'pending'
                        ORDER BY kr.created_at DESC");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block And Reset Requests - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: #8b5cf6;"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod Name</a>
                    <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload Mod APK</a>
                    <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod APK List</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License Key</a>
                    <a class="nav-link" href="licence_key_list.php"><i class="fas fa-key"></i>License Key List</a>
                    <a class="nav-link" href="available_keys.php"><i class="fas fa-key"></i>Available Keys</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
                    <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Code</a>
                    <a class="nav-link active" href="admin_block_reset_requests.php"><i class="fas fa-shield-alt"></i>Block & Reset Requests</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="page-header">
                    <h2 class="mb-2">Block And Reset Requests</h2>
                    <p class="text-muted">Manage user requests for key resets or blocks</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Mod</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingRequests as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['username']); ?></td>
                                    <td><span class="badge bg-<?php echo $req['request_type'] === 'reset' ? 'warning' : 'danger'; ?>"><?php echo strtoupper($req['request_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($req['mod_name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['reason']); ?></td>
                                    <td><?php echo formatDate($req['created_at']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    </script>
</body>
</html>