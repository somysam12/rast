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

// Get pending requests
$pendingRequests = [];
try {
    $stmt = $pdo->query("SELECT kr.*, u.username, lk.license_key, lk.duration, lk.duration_type 
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
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

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
            padding: 20px;
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
            left: 0;
            transform: translateX(-280px);
        }

        .sidebar.show { transform: translateX(0); }
        .sidebar-brand { padding: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); text-align: center; }
        .sidebar-brand h4 { background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 0.75rem; font-size: 1.4rem; }
        .sidebar-brand .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: white; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .requests-wrapper { width: 100%; max-width: 700px; position: relative; z-index: 1; margin: 0 auto; }

        @media (min-width: 993px) {
            .sidebar { transform: translateX(0); }
            .requests-wrapper { margin-left: 280px; }
            .hamburger-btn { display: none !important; }
        }

        .hamburger-btn { position: fixed; top: 20px; left: 20px; z-index: 1100; background: linear-gradient(135deg, #06b6d4, #0891b2); border: 2px solid rgba(6, 182, 212, 0.4); color: white; padding: 10px 15px; border-radius: 10px; cursor: pointer; display: none; }
        @media (max-width: 992px) { .hamburger-btn { display: block; } }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); border: 2px solid; border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1; border-radius: 32px; padding: 45px; box-shadow: 0 0 60px rgba(139, 92, 246, 0.15); }
        .request-item { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(139, 92, 246, 0.2); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
        .btn-approve { background: linear-gradient(135deg, #10b981, #06b6d4); color: white; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 700; flex: 1; }
        .btn-reject { background: linear-gradient(135deg, #ef4444, #ec4899); color: white; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 700; flex: 1; }
        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>
    <div class="overlay" id="overlay"></div>
    <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fas fa-bolt"></i></div>
            <h4>SILENT PANEL</h4>
            <p>Admin Control Center</p>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
            <a class="nav-link active" href="admin_block_reset_requests.php"><i class="fas fa-ban"></i>Block & Reset</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="requests-wrapper">
        <div class="glass-card">
            <h1 class="text-center h3 mb-4">Block & Reset Requests</h1>
            <?php if (empty($pendingRequests)): ?>
                <p class="text-center text-dim">No pending requests.</p>
            <?php else: ?>
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="request-item">
                        <p class="mb-1"><strong>User:</strong> <?php echo htmlspecialchars($request['username']); ?></p>
                        <p class="mb-1"><strong>Type:</strong> <?php echo htmlspecialchars($request['request_type']); ?></p>
                        <p class="mb-1"><strong>Mod:</strong> <?php echo htmlspecialchars($request['mod_name']); ?></p>
                        <form method="POST" class="d-flex gap-2 mt-3">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        }
    </script>
</body>
</html>