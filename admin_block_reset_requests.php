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

        .hamburger-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            color: white;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
            transition: all 0.3s;
        }

        .hamburger-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
        }

        .hamburger-btn:active {
            transform: scale(0.95);
        }

        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: none;
            z-index: 99;
            animation: fadeIn 0.3s ease;
        }

        .menu-overlay.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .nav-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 280px;
            height: 100vh;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 2px solid rgba(139, 92, 246, 0.3);
            z-index: 100;
            padding: 20px;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        .nav-menu.show {
            left: 0;
        }

        .nav-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(139, 92, 246, 0.2);
        }

        .nav-menu-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .close-btn {
            background: transparent;
            border: none;
            color: var(--text-main);
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .close-btn:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-dim);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 14px;
        }

        .nav-menu-item:hover {
            background: rgba(139, 92, 246, 0.2);
            color: var(--primary);
            transform: translateX(5px);
        }

        .nav-menu-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-divider {
            height: 1px;
            background: rgba(139, 92, 246, 0.2);
            margin: 15px 0;
        }

        .nav-menu-item.logout {
            color: #fca5a5;
        }

        .nav-menu-item.logout:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #ff6b6b;
        }

        @media (max-width: 480px) {
            .glass-card {
                padding: 30px 20px;
                border-radius: 20px;
                border: 1.5px solid var(--border-light);
            }
            
            .brand-section h1 {
                font-size: 24px;
            }

            .request-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .hamburger-btn {
                display: flex;
            }
        }

        @media (min-width: 769px) {
            .hamburger-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Hamburger Button -->
    <button class="hamburger-btn" onclick="toggleMenu()" id="hamburgerBtn">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Menu Overlay -->
    <div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

    <!-- Navigation Menu -->
    <div class="nav-menu" id="navMenu">
        <div class="nav-menu-header">
            <span class="nav-menu-title">Menu</span>
            <button class="close-btn" onclick="closeMenu()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="nav-menu-items">
            <a href="admin_dashboard.php" class="nav-menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="add_mod.php" class="nav-menu-item">
                <i class="fas fa-plus"></i>
                <span>Add Mod Name</span>
            </a>
            <a href="manage_mods.php" class="nav-menu-item">
                <i class="fas fa-edit"></i>
                <span>Manage Mods</span>
            </a>
            <a href="upload_mod.php" class="nav-menu-item">
                <i class="fas fa-upload"></i>
                <span>Upload Mod APK</span>
            </a>
            <a href="mod_list.php" class="nav-menu-item">
                <i class="fas fa-list"></i>
                <span>Mod APK List</span>
            </a>
            <a href="add_license.php" class="nav-menu-item">
                <i class="fas fa-key"></i>
                <span>Add License Key</span>
            </a>
            <a href="licence_key_list.php" class="nav-menu-item">
                <i class="fas fa-key"></i>
                <span>License Key List</span>
            </a>
            <a href="available_keys.php" class="nav-menu-item">
                <i class="fas fa-key"></i>
                <span>Available Keys</span>
            </a>
            <a href="manage_users.php" class="nav-menu-item">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="add_balance.php" class="nav-menu-item">
                <i class="fas fa-wallet"></i>
                <span>Add Balance</span>
            </a>
            <a href="transactions.php" class="nav-menu-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Transactions</span>
            </a>
            <a href="referral_codes.php" class="nav-menu-item">
                <i class="fas fa-tag"></i>
                <span>Referral Code</span>
            </a>
            <a href="settings.php" class="nav-menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>

            <div class="nav-divider"></div>

            <a href="logout.php" class="nav-menu-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
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
