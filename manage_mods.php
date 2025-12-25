<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM mods WHERE id = ?");
    if ($stmt->execute([$_GET['delete']])) {
        $success = 'Mod deleted successfully!';
    } else {
        $error = 'Failed to delete mod.';
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $stmt = $pdo->prepare("UPDATE mods SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
    if ($stmt->execute([$_GET['toggle_status']])) {
        $success = 'Mod status updated successfully!';
    } else {
        $error = 'Failed to update mod status.';
    }
}

// Get all mods
$stmt = $pdo->query("SELECT * FROM mods ORDER BY created_at DESC");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Mods - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: #8b5cf6;"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="add_mod.php">
                        <i class="fas fa-plus"></i>Add Mod Name
                    </a>
                    <a class="nav-link active" href="manage_mods.php">
                        <i class="fas fa-edit"></i>Manage Mods
                    </a>
                    <a class="nav-link" href="upload_mod.php">
                        <i class="fas fa-upload"></i>Upload Mod APK
                    </a>
                    <a class="nav-link" href="mod_list.php">
                        <i class="fas fa-list"></i>Mod APK List
                    </a>
                    <a class="nav-link" href="add_license.php">
                        <i class="fas fa-key"></i>Add License Key
                    </a>
                    <a class="nav-link" href="licence_key_list.php">
                        <i class="fas fa-key"></i>License Key List
                    </a>
                    <a class="nav-link" href="available_keys.php">
                        <i class="fas fa-key"></i>Available Keys
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i>Manage Users
                    </a>
                    <a class="nav-link" href="add_balance.php">
                        <i class="fas fa-wallet"></i>Add Balance
                    </a>
                    <a class="nav-link" href="transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="referral_codes.php">
                        <i class="fas fa-tag"></i>Referral Code
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content" id="mainContent">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-edit me-2"></i>Manage Mods</h2>
                            <p class="text-muted mb-0">View and manage all mod entries in the system</p>
                        </div>
                        <div class="d-none d-md-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                <small class="text-muted">Administrator</small>
                            </div>
                            <div class="user-avatar" style="width: 48px; height: 48px; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="text-muted mb-0">Total Mods: <span class="text-primary fw-bold"><?php echo count($mods); ?></span></h5>
                    </div>
                    <a href="add_mod.php" class="btn btn-primary" style="background: #8b5cf6; border: none;">
                        <i class="fas fa-plus me-2"></i>Add New Mod
                    </a>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="table-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h5><i class="fas fa-list me-2"></i>Mod List</h5>
                    <?php if (empty($mods)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted">No mods found</h6>
                            <p class="text-muted mb-3">Get started by adding your first mod</p>
                            <a href="add_mod.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add First Mod
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th>Name</th>
                                        <th class="d-none d-md-table-cell">Description</th>
                                        <th style="width: 120px;">Status</th>
                                        <th style="width: 140px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mods as $mod): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-primary">#<?php echo $mod['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($mod['name']); ?></div>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <span class="text-muted">
                                                <?php echo htmlspecialchars($mod['description'] ?: 'No description provided'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $mod['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($mod['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="?toggle_status=<?php echo $mod['id']; ?>" 
                                                   class="btn btn-<?php echo $mod['status'] === 'active' ? 'warning' : 'success'; ?>"
                                                   onclick="return confirm('Are you sure you want to change the status?')">
                                                    <i class="fas fa-<?php echo $mod['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="?delete=<?php echo $mod['id']; ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Are you sure you want to delete this mod?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    </script>
</body>
</html>