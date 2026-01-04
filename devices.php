<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';

if (isset($_POST['action'])) {
    $keyId = $_POST['key_id'] ?? 0;
    $action = $_POST['action'];

    if ($action === 'reset_device') {
        $pdo->prepare("UPDATE key_devices SET is_active = 0 WHERE key_id = ?")->execute([$keyId]);
        $message = "Device reset successful for Key ID: " . $keyId;
    } elseif ($action === 'change_limit') {
        $limit = $_POST['max_devices'] ?? 1;
        $pdo->prepare("UPDATE user_keys SET max_devices = ? WHERE id = ?")->execute([$limit, $keyId]);
        $message = "Device limit updated to " . $limit;
    } elseif ($action === 'reset_counter') {
        $pdo->prepare("UPDATE key_resets SET reset_count = 0 WHERE key_id = ?")->execute([$keyId]);
        $message = "Reset counter cleared.";
    } elseif ($action === 'send_command') {
        $command = $_POST['command'];
        $target = $_POST['target'];
        $targetId = $_POST['target_id'] ?: null;
        $stmt = $pdo->prepare("INSERT INTO admin_commands (command, target, target_id, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$command, $target, $targetId, date('Y-m-d H:i:s')]);
        $message = "Command " . $command . " queued.";
    }
}

$keys = $pdo->query("SELECT uk.*, u.username FROM user_keys uk JOIN users u ON uk.user_id = u.id ORDER BY uk.created_at DESC")->fetchAll();
$activity = $pdo->query("SELECT da.*, uk.key_value FROM device_activity da JOIN user_keys uk ON da.key_id = uk.id ORDER BY da.last_seen DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #8b5cf6; --bg: #0a0e27; --card-bg: rgba(15, 23, 42, 0.7); --text-main: #f8fafc; }
        body { background: var(--bg); color: var(--text-main); font-family: 'Plus Jakarta Sans', sans-serif; padding: 2rem; }
        .glass-card { background: var(--card-bg); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); padding: 1.5rem; margin-bottom: 2rem; }
        .table { color: white; }
        .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-offline { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-mobile-alt me-2"></i> Device Management</h2>
            <a href="admin_dashboard.php" class="btn btn-outline-light"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="glass-card">
            <h4><i class="fas fa-terminal me-2"></i> Quick Commands</h4>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="send_command">
                <div class="col-md-3">
                    <select name="command" class="form-select bg-dark text-white border-secondary">
                        <option value="CLEAR_COOKIES">Clear Cookies</option>
                        <option value="CLEAR_SESSION">Clear Session</option>
                        <option value="FORCE_LOGOUT">Force Logout</option>
                        <option value="DISABLE_APP">Disable App</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="target" class="form-select bg-dark text-white border-secondary">
                        <option value="ALL">All Users</option>
                        <option value="KEY">Specific Key</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" name="target_id" class="form-control bg-dark text-white border-secondary" placeholder="Key ID (optional)">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Send Command</button>
                </div>
            </form>
        </div>

        <div class="glass-card">
            <h4><i class="fas fa-key me-2"></i> User Keys & Limits</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Key</th>
                            <th>Max Devices</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($keys as $k): ?>
                        <tr>
                            <td><?php echo $k['id']; ?></td>
                            <td><?php echo $k['username']; ?></td>
                            <td><code><?php echo $k['key_value']; ?></code></td>
                            <td>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="change_limit">
                                    <input type="hidden" name="key_id" value="<?php echo $k['id']; ?>">
                                    <input type="number" name="max_devices" value="<?php echo $k['max_devices']; ?>" class="form-control form-control-sm w-50 bg-dark text-white border-secondary">
                                    <button type="submit" class="btn btn-sm btn-info">Set</button>
                                </form>
                            </td>
                            <td><span class="status-pill <?php echo $k['is_active'] ? 'status-active' : 'status-offline'; ?>"><?php echo $k['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reset_device">
                                    <input type="hidden" name="key_id" value="<?php echo $k['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">Reset HWID</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="reset_counter">
                                    <input type="hidden" name="key_id" value="<?php echo $k['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Clear Counter</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card">
            <h4><i class="fas fa-satellite-dish me-2"></i> Live Activity (Anti-Abuse)</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>IP Address</th>
                            <th>Location</th>
                            <th>Device</th>
                            <th>Last Seen</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $a): ?>
                        <tr>
                            <td><?php echo $a['key_value']; ?></td>
                            <td><?php echo $a['ip_address']; ?></td>
                            <td><?php echo $a['city'] . ', ' . $a['country']; ?></td>
                            <td><small><?php echo substr($a['device_fingerprint'], 0, 16); ?>...</small></td>
                            <td><?php echo $a['last_seen']; ?></td>
                            <td>
                                <?php 
                                $is_active = (strtotime($a['last_seen']) >= time() - 120);
                                ?>
                                <span class="status-pill <?php echo $is_active ? 'status-active' : 'status-offline'; ?>">
                                    <?php echo $is_active ? '🟢 Online' : '🔴 Offline'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>