<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE key_confirmations SET status = 'read' WHERE user_id = ? AND status = 'unread'");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Handle error
    }
}

// Get all confirmations for user
$confirmations = [];
try {
    $stmt = $pdo->prepare("SELECT kc.*, kr.mod_name FROM key_confirmations kc 
                          JOIN key_requests kr ON kc.request_id = kr.id 
                          WHERE kc.user_id = ? 
                          ORDER BY kc.created_at DESC");
    $stmt->execute([$userId]);
    $confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}

// Count unread
$unreadCount = 0;
foreach ($confirmations as $conf) {
    if ($conf['status'] === 'unread') {
        $unreadCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block/Reset Request Confirmations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f9fafb;
            --card: #ffffff;
            --text: #374151;
            --muted: #6b7280;
            --line: #e5e7eb;
            --accent: #7c3aed;
            --accent-600: #6d28d9;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --line: #334155;
        }
        
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        .navbar-custom {
            background: var(--card);
            border-bottom: 1px solid var(--line);
            padding: 1rem 1.5rem;
        }
        
        .container-custom {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card-custom {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
        }
        
        .confirmation-item {
            background: var(--bg);
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .confirmation-item.unread {
            background: rgba(124, 58, 237, 0.05);
            border-left-color: var(--accent);
        }
        
        .confirmation-item:hover {
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2);
        }
        
        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-approved {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .badge-block {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .badge-reset {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .btn-primary-custom {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            border: none;
        }
        
        .btn-primary-custom:hover {
            background: var(--accent-600);
            color: white;
            text-decoration: none;
        }
        
        .unread-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <div class="navbar-custom">
        <div class="container-custom">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="margin: 0; color: var(--accent); font-weight: 700;">📬 Block/Reset Request Confirmations</h2>
                    <?php if ($unreadCount > 0): ?>
                        <p style="margin: 0.5rem 0 0; color: var(--muted); font-size: 0.9rem;">
                            <?php echo $unreadCount; ?> new notifications
                        </p>
                    <?php endif; ?>
                </div>
                <a href="user_dashboard.php" class="btn-primary-custom">← Back</a>
            </div>
        </div>
    </div>
    
    <div class="container-custom">
        <div class="card-custom">
            <?php if (empty($confirmations)): ?>
                <p style="color: var(--muted); text-align: center; font-size: 1.1rem;">No notifications at this time.</p>
            <?php else: ?>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" style="margin-bottom: 1.5rem;">
                        <button type="submit" name="mark_as_read" value="1" class="btn-primary-custom">
                            ✓ Mark All As Read
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php foreach ($confirmations as $conf): ?>
                    <div class="confirmation-item <?php echo $conf['status'] === 'unread' ? 'unread' : ''; ?>">
                        <?php if ($conf['status'] === 'unread'): ?>
                            <span class="unread-badge">NEW</span>
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 1rem;">
                            <h4 style="margin: 0; color: var(--text);">
                                <?php echo $conf['action_type'] === 'block' ? '🚫 Block' : '↻ Reset'; ?> Request - 
                                <span class="badge-custom badge-<?php echo $conf['action_type'] === 'block' ? 'block' : 'reset'; ?>">
                                    <?php echo htmlspecialchars($conf['mod_name']); ?>
                                </span>
                            </h4>
                            <p style="margin: 0.5rem 0 0; color: var(--muted); font-size: 0.85rem;">
                                📅 <?php echo date('d M Y, H:i', strtotime($conf['created_at'])); ?>
                            </p>
                        </div>
                        
                        <div style="background: var(--line); padding: 1rem; border-radius: 6px; color: var(--text);">
                            <p style="margin: 0;">
                                <strong>Notification:</strong><br>
                                <?php echo htmlspecialchars($conf['message']); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
