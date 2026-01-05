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
                if ($action === 'approve') {
                    $msgText = "Your request to {$request['request_type']} the key for {$request['mod_name']} has been approved. Your key has been {$request['request_type']}ed.";
                    $actionType = $request['request_type'];
                } else {
                    $msgText = "Your request to {$request['request_type']} the key for {$request['mod_name']} has been rejected.";
                    $actionType = $request['request_type'];
                }
                
                $stmt = $pdo->prepare("INSERT INTO key_confirmations (user_id, request_id, action_type, message) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$request['user_id'], $requestId, $actionType, $msgText]);
                
                $message = $action === 'approve' ? 'Request approved!' : 'Request rejected!';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get pending requests with key details
$pendingRequests = [];
try {
    $stmt = $pdo->query("SELECT 
                            kr.id, 
                            kr.user_id, 
                            kr.key_id, 
                            kr.request_type, 
                            kr.mod_name, 
                            kr.reason, 
                            kr.status, 
                            kr.created_at, 
                            u.username, 
                            lk.license_key, 
                            lk.duration, 
                            lk.duration_type 
                        FROM key_requests kr 
                        JOIN users u ON kr.user_id = u.id 
                        LEFT JOIN license_keys lk ON lk.id = kr.key_id
                        WHERE kr.status = 'pending'
                        ORDER BY kr.created_at DESC");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requests - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        html, body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%) !important;
            background-attachment: fixed !important;
            width: 100%;
            height: 100%;
        }

        body { color: var(--text-main); overflow-x: hidden; position: relative; }

        .sidebar {
            position: fixed; left: 0; top: 0; width: 280px; height: 100vh;
            background: var(--card-bg); backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px); border-right: 1.5px solid var(--border-light);
            z-index: 1000; overflow-y: auto; transition: transform 0.3s ease; padding: 1.5rem 0;
        }

        .sidebar-brand { padding: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-brand h4 { background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800; font-size: 1.4rem; }
        
        .sidebar .nav { display: flex; flex-direction: column; gap: 0.5rem; padding: 0 1rem; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 16px; border-radius: 12px; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; transform: translateX(4px); }

        .main-content { margin-left: 280px; padding: 1.5rem; min-height: 100vh; }

        .top-bar { display: none; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .hamburger-btn { background: var(--primary); border: none; color: white; padding: 10px 12px; border-radius: 12px; }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); border: 1.5px solid var(--border-light); border-radius: 32px; padding: 30px; }

        .request-item { background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-light); border-radius: 20px; padding: 20px; margin-bottom: 15px; }
        .request-type { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; background: rgba(139, 92, 246, 0.2); color: var(--secondary); text-transform: uppercase; }
        .btn-approve { background: linear-gradient(135deg, #10b981, #06b6d4); border: none; border-radius: 8px; color: white; padding: 10px; font-weight: 700; }
        .btn-reject { background: linear-gradient(135deg, #ef4444, #ec4899); border: none; border-radius: 8px; color: white; padding: 10px; font-weight: 700; }

        .mobile-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .mobile-overlay.show { display: block; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .top-bar { display: flex; }
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand"><h4>SILENT PANEL</h4></div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link active" href="admin_block_reset_requests.php"><i class="fas fa-ban"></i>Requests</a>
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <h4 style="margin: 0; font-weight: 800; background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">REQUESTS</h4>
            <div style="width: 44px;"></div>
        </div>

        <div class="glass-card">
            <div class="text-center mb-4">
                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; font-size: 24px; color: white;">
                    <i class="fas fa-ban"></i>
                </div>
                <h2 style="font-weight: 800;">Requests</h2>
                <p style="color: var(--text-dim);">Block & Reset Management</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <p class="text-center mb-4">Pending: <strong><?php echo count($pendingRequests); ?></strong></p>

            <?php if (empty($pendingRequests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-3x text-success mb-3 opacity-50"></i>
                    <p style="color: var(--text-dim);">No pending requests.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="request-item">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span style="font-weight: 700;"><i class="fas fa-user me-2 text-primary"></i><?php echo htmlspecialchars($request['username']); ?></span>
                            <span class="request-type"><?php echo htmlspecialchars($request['request_type']); ?></span>
                        </div>
                        <div style="font-size: 13px; color: var(--text-dim);" class="mb-3">
                            <p class="mb-1"><strong>Mod:</strong> <?php echo htmlspecialchars($request['mod_name']); ?></p>
                            <p class="mb-1"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></p>
                            <p class="mb-0"><strong>Time:</strong> <?php echo formatDate($request['created_at']); ?></p>
                        </div>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve w-100">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-reject w-100">Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        function toggle() { sidebar.classList.toggle('show'); overlay.classList.toggle('show'); }
        if (hamburgerBtn) hamburgerBtn.onclick = toggle;
        if (overlay) overlay.onclick = toggle;
    </script>
</body>
</html>