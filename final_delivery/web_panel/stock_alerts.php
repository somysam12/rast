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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Alerts - SilentMultiPanel</title>
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

        html, body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%) !important;
            background-attachment: fixed !important;
            width: 100%;
            height: 100%;
            color: var(--text-main);
            overflow-x: hidden;
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

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border-right: 1px solid var(--border-light);
            z-index: 1000;
            overflow-y: auto;
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            text-align: center;
        }

        .sidebar-brand h4 {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .sidebar-brand p {
            color: var(--text-dim);
            font-size: 0.8rem;
            margin: 0;
        }

        .sidebar .nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 1rem;
        }

        .sidebar .nav-link {
            color: var(--text-dim);
            padding: 12px 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            transform: translateX(4px);
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .mobile-header {
            display: none;
            margin-bottom: 1.5rem;
        }

        .hamburger-btn {
            background: linear-gradient(135deg, #06b6d4, #0891b2) !important;
            border: 2px solid rgba(6, 182, 212, 0.4) !important;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 10px 12px !important;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.3);
            outline: none;
            flex-shrink: 0;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.5);
            border-color: rgba(6, 182, 212, 0.7);
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: var(--text-main);
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-dim);
            font-size: 1rem;
        }

        .alert-banner {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.15));
            border: 1.5px solid rgba(139, 92, 246, 0.2);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .alert-banner .alert-count {
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 20px;
            padding: 2rem;
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
            z-index: 1;
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
            }
        }

        .table {
            color: var(--text-main);
            margin-bottom: 0;
        }

        .table thead {
            border-bottom: 1px solid var(--border-light);
        }

        .table thead th {
            color: var(--secondary);
            font-weight: 700;
            border: none;
            padding: 1.2rem;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 1.2rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-main);
        }

        .table tbody tr:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .badge {
            font-weight: 700;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.3);
        }

        .badge-resolved {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }

        .btn-action {
            padding: 0.6rem 1rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-resolve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }

        .btn-resolve:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.5);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.3);
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.5);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-dim);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .success-alert {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 1.5px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .success-alert i {
            color: #10b981;
            margin-right: 0.75rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .mobile-header {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .alert-banner {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .glass-card {
                padding: 1.5rem;
            }

            .table {
                font-size: 0.9rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.75rem;
            }

            .btn-action {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .page-header h2 {
                font-size: 1.6rem;
            }

            .glass-card {
                padding: 1rem;
                border-radius: 16px;
            }

            .table {
                font-size: 0.8rem;
            }

            .table thead th,
            .table tbody td {
                padding: 0.5rem;
            }

            .badge {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
            }

            .btn-action {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-bell"></i> SilentMultiPanel</h4>
            <p>Stock Alerts System</p>
        </div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a class="nav-link active" href="stock_alerts.php"><i class="fas fa-bell"></i><span>Stock Alerts</span></a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i><span>Add Mod</span></a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i><span>Manage Mods</span></a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i><span>Manage Users</span></a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i><span>Add License</span></a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>
            <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-bell"></i> Stock Alerts</h2>
            <p>Manage product stock notifications from users</p>
        </div>

        <!-- Alert Banner -->
        <div class="alert-banner">
            <div>
                <p style="color: var(--text-dim); margin: 0; font-weight: 600;">
                    Pending alerts awaiting your action
                </p>
            </div>
            <div class="alert-count">
                <i class="fas fa-clock"></i> <?php echo $pendingCount; ?> Pending
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="success-alert">
                <div>
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; color: var(--text-main); cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Alerts Table -->
        <div class="glass-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-box"></i> Product</th>
                            <th><i class="fas fa-user"></i> Requester</th>
                            <th><i class="fas fa-hourglass-end"></i> Status</th>
                            <th><i class="fas fa-calendar"></i> Date</th>
                            <th><i class="fas fa-sliders-h"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alerts)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p style="font-weight: 600; margin-top: 1rem;">No stock alerts yet</p>
                                        <p style="font-size: 0.9rem;">All stock alerts will appear here</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: var(--secondary);"><?php echo htmlspecialchars($alert['mod_name']); ?></div>
                                        <small style="color: var(--text-dim);">ID: #<?php echo $alert['mod_id']; ?></small>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700;"><?php echo htmlspecialchars($alert['username']); ?></div>
                                        <small style="color: var(--text-dim);">User #<?php echo $alert['user_id']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $alert['status'] === 'pending' ? 'badge-pending' : 'badge-resolved'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($alert['status'])); ?>
                                        </span>
                                    </td>
                                    <td style="color: var(--text-dim);">
                                        <?php echo date('M d, Y H:i', strtotime($alert['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($alert['status'] === 'pending'): ?>
                                            <a href="?resolve=<?php echo $alert['id']; ?>" class="btn-action btn-resolve" title="Mark as resolved">
                                                <i class="fas fa-check"></i> Resolve
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $alert['id']; ?>" class="btn-action btn-delete" onclick="return confirm('Delete this alert?')" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
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

    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sidebar') && !e.target.closest('[onclick="toggleSidebar()"]')) {
                document.getElementById('sidebar').classList.remove('show');
            }
        });
    </script>
</body>
</html>