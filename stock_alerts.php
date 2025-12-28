<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$success = '';
$error = '';

// Mark alert as resolved
if (isset($_GET['resolve']) && is_numeric($_GET['resolve'])) {
    $stmt = $pdo->prepare("UPDATE stock_alerts SET status = 'resolved' WHERE id = ?");
    if ($stmt->execute([$_GET['resolve']])) {
        $success = 'Alert marked as resolved!';
    }
}

// Delete alert
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM stock_alerts WHERE id = ?");
    if ($stmt->execute([$_GET['delete']])) {
        $success = 'Alert deleted!';
    }
}

// Get all pending alerts
$stmt = $pdo->query("SELECT * FROM stock_alerts ORDER BY created_at DESC");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count pending vs resolved
$stmt = $pdo->query("SELECT COUNT(*) as total FROM stock_alerts WHERE status = 'pending'");
$pendingCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="assets/css/global.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Alerts - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.8);
            --purple: #8b5cf6;
            --purple-dark: #7c3aed;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.15);
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
        }
        .sidebar {
            background-color: var(--card-bg);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-light);
            transform: translateX(0);
        }
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 16px;
            transition: none !important;
            text-decoration: none;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: white;
        }
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        .table-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
        }
        .table thead th {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        .badge-pending {
            background-color: #fbbf24;
            color: #78350f;
        }
        .badge-resolved {
            background-color: #86efac;
            color: #15803d;
        }
    </style>
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="stock_alerts.php">
                        <i class="fas fa-bell"></i>Stock Alerts
                    </a>
                    <a class="nav-link" href="add_license.php">
                        <i class="fas fa-key"></i>Add License Key
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i>Manage Users
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <div class="col-md-9 col-lg-10 main-content">
                <div style="background: var(--card-bg); border-radius: 16px; padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-medium);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-bell me-2" style="color: var(--purple);"></i>Stock Alerts</h2>
                            <p class="text-muted mb-0">Manage product stock alerts from users</p>
                        </div>
                        <div>
                            <span class="badge badge-pending" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                <i class="fas fa-clock me-2"></i>Pending: <?php echo $pendingCount; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Requester</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($alerts)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fas fa-inbox fa-3x mb-3"></i><br>
                                            No stock alerts yet
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($alerts as $alert): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($alert['mod_name']); ?></div>
                                            <small class="text-muted">ID: #<?php echo $alert['mod_id']; ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($alert['username']); ?></div>
                                            <small class="text-muted">User ID: #<?php echo $alert['user_id']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $alert['status'] === 'pending' ? 'badge-pending' : 'badge-resolved'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($alert['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($alert['created_at'])); ?></td>
                                        <td>
                                            <?php if ($alert['status'] === 'pending'): ?>
                                                <a href="?resolve=<?php echo $alert['id']; ?>" class="btn btn-sm btn-success" title="Mark as resolved">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $alert['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this alert?')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
