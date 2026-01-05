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
} catch (Exception $e) {
    // Handle error
}
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
            --accent: #ec4899;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.1);
            --border-glow: rgba(139, 92, 246, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
            padding: 20px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .requests-wrapper {
            width: 100%;
            max-width: 700px;
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(40px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
            }
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 32px;
            padding: 45px;
            box-shadow: 
                0 0 60px rgba(139, 92, 246, 0.15),
                0 0 20px rgba(6, 182, 212, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .glass-card > * {
            position: relative;
            z-index: 2;
        }

        .brand-section {
            text-align: center;
            margin-bottom: 36px;
        }

        .brand-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: white;
            box-shadow: 
                0 15px 35px rgba(139, 92, 246, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset -2px -2px 5px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .brand-section h1 {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.03em;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #f8fafc, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-section p {
            color: var(--text-dim);
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
            border: 1.5px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .requests-list {
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }

        .requests-list::-webkit-scrollbar {
            width: 6px;
        }

        .requests-list::-webkit-scrollbar-track {
            background: rgba(139, 92, 246, 0.1);
            border-radius: 10px;
        }

        .requests-list::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.3);
            border-radius: 10px;
        }

        .request-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }

        .request-item:hover {
            background: rgba(139, 92, 246, 0.1);
            border-color: var(--primary);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .request-user {
            font-weight: 700;
            color: var(--text-main);
            font-size: 14px;
        }

        .request-type {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            background: rgba(139, 92, 246, 0.2);
            color: var(--secondary);
        }

        .request-type.block {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .request-details {
            font-size: 12px;
            color: var(--text-dim);
            margin-bottom: 10px;
        }

        .request-actions {
            display: flex;
            gap: 8px;
        }

        .btn-approve, .btn-reject {
            flex: 1;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #06b6d4);
            color: white;
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.2);
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #ec4899);
            color: white;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.2);
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
            opacity: 0.5;
            display: block;
        }

        .empty-state p {
            color: var(--text-dim);
            font-size: 14px;
        }

        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-dim);
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .pending-count {
            text-align: center;
            font-size: 12px;
            color: var(--text-dim);
            margin-bottom: 15px;
        }

        .pending-count strong {
            color: var(--secondary);
            font-weight: 700;
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

        .sidebar.active { transform: translateX(0); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        @media (min-width: 993px) {
            .sidebar { transform: translateX(0); }
            .requests-wrapper { margin-left: 280px; }
            .hamburger { display: none !important; }
        }

        .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; display: none; }
        @media (max-width: 992px) { .hamburger { display: block; } }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <?php include 'includes/admin_header.php'; ?>

    <div class="overlay" id="overlay"></div>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
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
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h1>Requests</h1>
                <p>Block & Reset Management</p>
            </div>

            <?php if ($message): ?>
                <div class=\"alert-<?php echo $messageType; ?>\">
                    <i class=\"fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>\"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="pending-count">
                Total Pending: <strong><?php echo count($pendingRequests); ?></strong>
            </div>

            <?php if (empty($pendingRequests)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending requests at this moment.</p>
                </div>
            <?php else: ?>
                <div class="requests-list">
                    <?php foreach ($pendingRequests as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <span class="request-user"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($request['username']); ?></span>
                                <span class="request-type <?php echo strtolower($request['request_type']); ?>">
                                    <?php echo htmlspecialchars($request['request_type']); ?>
                                </span>
                            </div>
                            <div class="request-details">
                                <p class="mb-1"><strong>Mod:</strong> <?php echo htmlspecialchars($request['mod_name']); ?></p>
                                <p class="mb-1"><strong>Key:</strong> <?php echo htmlspecialchars($request['license_key'] ?? 'N/A'); ?></p>
                                <p class="mb-1"><strong>Duration:</strong> <?php echo htmlspecialchars($request['duration'] . ' ' . $request['duration_type']); ?></p>
                                <p class="mb-0"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></p>
                                <p class="mb-0 mt-1 small"><strong>Requested:</strong> <?php echo formatDate($request['created_at']); ?></p>
                            </div>
                            <div class="request-actions">
                                <form method="POST" style="display: flex; gap: 8px; width: 100%;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="footer-text">
                Back to <a href="admin_dashboard.php">Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');

        hamburgerBtn.onclick = () => { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };
    </script>
</body>
</html>

    <div class="requests-wrapper">
        <div class="glass-card">
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h1>Requests</h1>
                <p>Block & Reset Management</p>
            </div>

            <?php if ($message): ?>
                <div class="alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="pending-count">
                Total Pending: <strong><?php echo count($pendingRequests); ?></strong>
            </div>

            <?php if (empty($pendingRequests)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending requests at this moment.</p>
                </div>
            <?php else: ?>
                <div class="requests-list">
                    <?php foreach ($pendingRequests as $request): ?>
                        <div class="request-item">
                            <div class="request-header">
                                <span class="request-user">
                                    <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($request['username']); ?>
                                </span>
                                <span class="request-type <?php echo htmlspecialchars($request['request_type']); ?>">
                                    <?php echo strtoupper($request['request_type']); ?>
                                </span>
                            </div>
                            
                            <div class="request-details">
                                <div><strong><?php echo htmlspecialchars($request['mod_name']); ?></strong></div>
                                <div style="font-size: 11px; opacity: 0.7;">
                                    <?php echo htmlspecialchars($request['duration'] . ' ' . ucfirst($request['duration_type'])); ?> • 
                                    <?php echo formatDate($request['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="request-actions">
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="btn-approve" style="width: 100%;">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </form>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" name="action" value="reject" class="btn-reject" style="width: 100%;">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="footer-text">
                <a href="admin_dashboard.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            const navMenu = document.getElementById('navMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            navMenu.classList.toggle('show');
            menuOverlay.classList.toggle('show');
        }

        function closeMenu() {
            const navMenu = document.getElementById('navMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            navMenu.classList.remove('show');
            menuOverlay.classList.remove('show');
        }

        // Close menu when clicking on a menu item
        document.querySelectorAll('.nav-menu-item').forEach(item => {
            item.addEventListener('click', closeMenu);
        });

        // Close menu when pressing Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    </script>
</body>
</html>
