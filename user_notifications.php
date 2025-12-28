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

// Mark notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notifId = (int)($_POST['notification_id'] ?? 0);
    if ($notifId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE key_confirmations SET status = "read" WHERE id = ? AND user_id = ?');
            $stmt->execute([$notifId, $user['id']]);
        } catch (Throwable $e) {}
    }
}

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare('UPDATE key_confirmations SET status = "read" WHERE user_id = ? AND status = "unread"');
        $stmt->execute([$user['id']]);
        header('Location: user_notifications.php');
        exit;
    } catch (Throwable $e) {}
}

// Get all notifications
$notifications = [];
try {
    $stmt = $pdo->prepare('SELECT kc.id, kc.action_type, kc.message, kc.status, kc.created_at, 
                                  kr.request_type, kr.mod_name, kr.reason
                           FROM key_confirmations kc
                           JOIN key_requests kr ON kr.id = kc.request_id
                           WHERE kc.user_id = ?
                           ORDER BY kc.created_at DESC');
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
} catch (Throwable $e) {
    $notifications = [];
}

// Count unread
$unreadCount = 0;
foreach ($notifications as $notif) {
    if ($notif['status'] === 'unread') {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications - SilentMultiPanel</title>
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
        
        .notification-item {
            background: rgba(10, 15, 25, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .notification-item.unread {
            border-left: 4px solid #8b5cf6;
            background: rgba(139, 92, 246, 0.05);
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
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
            <a class="nav-link active" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="text-neon mb-1">Notifications</h2>
                <p class="text-secondary mb-0">Track admin responses to your requests.</p>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="cyber-btn py-2">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="cyber-card text-center py-5">
                <i class="fas fa-bell-slash text-secondary mb-3" style="font-size: 3rem; opacity: 0.2;"></i>
                <h5 class="text-secondary">No notifications yet.</h5>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="status-badge <?php echo strpos($notif['action_type'], 'approved') !== false ? 'bg-success text-success' : 'bg-danger text-danger'; ?> bg-opacity-10 mb-2 d-inline-block">
                                <?php echo ucfirst(str_replace('_', ' ', $notif['action_type'])); ?>
                            </span>
                            <h5 class="text-white mb-1"><?php echo htmlspecialchars($notif['mod_name']); ?></h5>
                            <p class="text-secondary small mb-0">Type: <?php echo ucfirst($notif['request_type']); ?></p>
                        </div>
                        <div class="text-end">
                            <div class="text-secondary small mb-2"><?php echo formatDate($notif['created_at']); ?></div>
                            <?php if ($notif['status'] === 'unread'): ?>
                                <form method="POST">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill">
                                        Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary border-opacity-10 mb-3">
                        <div class="small text-secondary fw-bold mb-1">YOUR REASON:</div>
                        <div class="text-white small"><?php echo htmlspecialchars($notif['reason']); ?></div>
                    </div>
                    
                    <div class="bg-primary bg-opacity-10 p-3 rounded-3 border border-primary border-opacity-20">
                        <div class="small text-primary fw-bold mb-1">ADMIN RESPONSE:</div>
                        <div class="text-white"><?php echo htmlspecialchars($notif['message']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
    </script>
</body>
</html>