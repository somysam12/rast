<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get all mods with APK information
$stmt = $pdo->query("SELECT m.*, ma.file_name, ma.uploaded_at as apk_uploaded_at 
                    FROM mods m 
                    LEFT JOIN mod_apks ma ON m.id = ma.mod_id 
                    ORDER BY m.created_at DESC");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mod APK List - SilentMultiPanel</title>
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
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'AD', 0, 2)); ?>
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
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus me-2"></i>Add Mod</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload me-2"></i>Upload APK</a>
                    <a class="nav-link active" href="mod_list.php"><i class="fas fa-list me-2"></i>Mod List</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key me-2"></i>Add License</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                    <a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
                    <hr>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out me-2"></i>Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>Mod APK List</h2>
                        <p class="mb-0 text-muted">Manage and view all mod APK files</p>
                    </div>
                    <a href="add_mod.php" class="btn btn-primary" style="background: #8b5cf6; border: none; padding: 10px 20px; border-radius: 8px;">
                        <i class="fas fa-plus me-2"></i>Add New Mod
                    </a>
                </div>

                <div class="table-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>APK Status</th>
                                    <th>Upload Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mods)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No mods found</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($mods as $mod): ?>
                                <tr>
                                    <td>#<?php echo $mod['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($mod['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($mod['description'] ?: 'No description'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $mod['file_name'] ? 'success' : 'warning'; ?>">
                                            <?php echo $mod['file_name'] ? 'Uploaded' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $mod['apk_uploaded_at'] ? date('d M Y', strtotime($mod['apk_uploaded_at'])) : 'N/A'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $mod['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($mod['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="upload_mod.php?mod_id=<?php echo $mod['id']; ?>" class="btn btn-primary" title="Upload APK">
                                                <i class="fas fa-upload"></i>
                                            </a>
                                            <a href="manage_mods.php" class="btn btn-outline-secondary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
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