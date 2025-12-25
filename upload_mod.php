<?php require_once "includes/optimization.php"; ?>
<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$success = $error = '';

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM mod_apks WHERE id = ?");
        $stmt->execute([$delete_id]);
        $apk = $stmt->fetch();
        
        if ($apk && file_exists($apk['file_path'])) {
            @unlink($apk['file_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM mod_apks WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = '✓ APK deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting APK: ' . $e->getMessage();
    }
}

// Handle upload
if ($_POST && isset($_FILES['apk_file'])) {
    $mod_id = $_POST['mod_id'] ?? '';
    $file = $_FILES['apk_file'];
    
    if (!$mod_id) {
        $error = 'Please select a mod';
    } elseif ($file['error']) {
        $error = 'Upload error: ' . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'apk') {
            $error = 'Only .apk files allowed';
        } else {
            @mkdir('uploads/apks', 0777, true);
            $name = uniqid() . '_' . $file['name'];
            $path = 'uploads/apks/' . $name;
            
            if (move_uploaded_file($file['tmp_name'], $path)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO mod_apks (mod_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$mod_id, $file['name'], $path, $file['size']]);
                    $success = '✓ Upload successful!';
                } catch (Exception $e) {
                    @unlink($path);
                    $error = 'Database error';
                }
            } else {
                $error = 'File move failed';
            }
        }
    }
}

// Get mods and uploads
$mods = $pdo->query("SELECT id, name FROM mods WHERE status='active' ORDER BY name")->fetchAll();
$uploads = $pdo->query("SELECT ma.*, m.name FROM mod_apks ma LEFT JOIN mods m ON ma.mod_id=m.id ORDER BY ma.uploaded_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upload Mod APK - SilentMultiPanel</title>
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
                    <a class="nav-link active" href="upload_mod.php"><i class="fas fa-upload me-2"></i>Upload APK</a>
                    <a class="nav-link" href="mod_list.php"><i class="fas fa-list me-2"></i>Mod List</a>
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
                        <h2>Upload Mod APK</h2>
                        <p class="mb-0 text-muted">Upload your APK files without any size restrictions</p>
                    </div>
                </div>

                <div class="card p-4 mb-4" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <strong><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></strong>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label fw-bold">Select Mod</label>
                                <select name="mod_id" class="form-control" required>
                                    <option value="">-- Choose Mod --</option>
                                    <?php foreach ($mods as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Choose APK File</label>
                                <input type="file" name="apk_file" class="form-control" accept=".apk" required>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary" style="background: #8b5cf6; border: none; padding: 10px 30px; border-radius: 8px;">
                                <i class="fas fa-upload me-2"></i>Upload APK
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card p-4" style="border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h5 class="mb-4"><i class="fas fa-list me-2" style="color: #8b5cf6;"></i>Uploaded APKs</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mod Name</th>
                                    <th>File Name</th>
                                    <th>Size</th>
                                    <th>Upload Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($uploads)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No APKs uploaded yet</td></tr>
                                <?php else: ?>
                                    <?php foreach ($uploads as $u): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($u['name'] ?? 'Unknown'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($u['file_name']); ?></td>
                                            <td><?php echo round($u['file_size']/1024/1024, 2) . ' MB'; ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($u['uploaded_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (file_exists($u['file_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars($u['file_path']); ?>" class="btn btn-success" download>
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?delete_id=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this APK?');">
                                                        <i class="fas fa-trash"></i>
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