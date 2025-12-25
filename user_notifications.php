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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Mod APK Manager</title>
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
        .notification-card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
        .notification-card.unread { background: #f3f1ff; border: 2px solid var(--purple); }
        .notification-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .notification-type { font-weight: 600; font-size: 1.1em; }
        .approved { color: #10b981; }
        .blocked { color: #ef4444; }
        .badge { padding: 0.35rem 0.75rem; border-radius: 6px; }
        .empty-state { text-align: center; padding: 3rem; color: var(--muted); }
        .alert { border-radius: 8px; border: none; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; }
        .unread-badge { background: var(--purple); color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.8em; font-weight: 700; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
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
                    <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban"></i>Block & Reset</a>
                    <a class="nav-link active" href="user_notifications.php">
                        <i class="fas fa-bell"></i>Notifications
                        <?php if ($unreadCount > 0): ?>
                        <span class="unread-badge ms-2"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link" href="user_settings.php"><i class="fas fa-cog"></i>Settings</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-bell me-2"></i>Notifications</h2>
                            <p class="text-muted mb-0">Admin responses to your block and reset requests</p>
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
                
                <?php if (empty($notifications)): ?>
                <div class="empty-state" style="background: white; border-radius: 12px; border: 1px solid var(--border); padding: 3rem;">
                    <i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--purple); opacity: 0.5; margin-bottom: 1rem;"></i>
                    <h5>No Notifications</h5>
                    <p>You don't have any notifications yet. Submit a block or reset request to get started.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>">
                        <div class="notification-header">
                            <div>
                                <div class="notification-type <?php echo strpos($notif['action_type'], 'approved') !== false ? 'approved' : 'blocked'; ?>">
                                    <i class="fas fa-<?php echo strpos($notif['action_type'], 'approved') !== false ? 'check-circle' : 'times-circle'; ?> me-2"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $notif['action_type'])); ?>
                                </div>
                                <p class="text-muted small mt-2 mb-0">Request Type: <strong><?php echo ucfirst($notif['request_type']); ?></strong> for <strong><?php echo htmlspecialchars($notif['mod_name']); ?></strong></p>
                            </div>
                            <?php if ($notif['status'] === 'unread'): ?>
                            <span class="badge bg-primary">New</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="alert alert-info mb-3" style="margin-top: 1rem;">
                            <strong>Your Reason:</strong><br>
                            <?php echo htmlspecialchars($notif['reason']); ?>
                        </div>
                        
                        <div class="alert" style="background: #f0fdf4; border: 1px solid #86efac; color: #15803d; margin-bottom: 1rem;">
                            <strong><i class="fas fa-comment-dots me-2"></i>Admin Response:</strong><br>
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i><?php echo formatDate($notif['created_at']); ?>
                            </small>
                            <?php if ($notif['status'] === 'unread'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-check me-1"></i>Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
