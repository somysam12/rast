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
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Mod APK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --purple: #8b5cf6;
            --dark: #0f172a;
            --light: #f8fafc;
        }
        body { background: var(--light); font-family: 'Inter', sans-serif; }
        .sidebar { background: white; border-right: 1px solid #e2e8f0; min-height: 100vh; position: fixed; width: 280px; padding: 2rem 0; }
        .sidebar .nav-link { color: #64748b; padding: 12px 20px; margin: 4px 16px; border-radius: 8px; }
        .sidebar .nav-link.active { background: var(--purple); color: white; }
        .sidebar .nav-link:hover { background: var(--purple); color: white; }
        .main { margin-left: 280px; padding: 2rem; }
        .card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; }
        .file-upload { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; }
        .file-upload:hover { border-color: var(--purple); }
        .file-upload.active { border-color: var(--purple); background: rgba(139,92,246,0.05); }
        .alert { border-radius: 8px; border: none; }
        .btn-primary { background: var(--purple); border: none; }
        .btn-primary:hover { background: #7c3aed; }
        .table { border-radius: 8px; overflow: hidden; }
        .table thead { background: var(--purple); color: white; }
        @media (max-width: 768px) { .sidebar { width: 100%; position: relative; min-height: auto; } .main { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 sidebar">
                <h4 style="color: var(--purple); font-weight: 700; padding: 0 20px; margin-bottom: 2rem;">
                    <i class="fas fa-shield me-2"></i>Multi Panel
                </h4>
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
            
            <div class="col-md-9 main">
                <div class="header">
                    <h2><i class="fas fa-upload me-2"></i>Upload Mod APK</h2>
                    <p>Upload your APK files without any size restrictions</p>
                </div>
                
                <div class="card p-4 mb-4">
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
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Mod</label>
                                <select name="mod_id" class="form-control" id="modSelect" required>
                                    <option value="">-- Choose Mod --</option>
                                    <?php foreach ($mods as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Choose APK File</label>
                                <input type="file" name="apk_file" class="form-control" id="apkFile" accept=".apk" required>
                            </div>
                        </div>
                        
                        <!-- Progress Indicator -->
                        <div id="uploadProgressContainer" style="display: none; margin-bottom: 2rem;">
                            <div class="progress" style="height: 24px;">
                                <div id="uploadProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                            <div style="margin-top: 1rem; text-align: center;">
                                <p style="margin: 0; font-size: 0.95rem; color: #64748b;">
                                    <span id="uploadedMB">0</span> MB / <span id="totalMB">0</span> MB
                                    <br>
                                    <small id="uploadSpeed" style="color: #94a3b8;">Calculating speed...</small>
                                </p>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>Upload APK
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="card p-4">
                    <h5 class="mb-4"><i class="fas fa-list me-2" style="color: var(--purple);"></i>Uploaded APKs</h5>
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
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <?php if (file_exists($u['file_path'])): ?>
                                                        <a href="<?php echo htmlspecialchars($u['file_path']); ?>" class="btn btn-success" download>
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="btn btn-outline-danger disabled">
                                                            <i class="fas fa-exclamation"></i> Not Found
                                                        </span>
                                                    <?php endif; ?>
                                                    <a href="?delete_id=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this APK?');">
                                                        <i class="fas fa-trash"></i> Delete
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/menu-logic.js"></script>
    
    <script>
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const apkFile = document.getElementById('apkFile');
        const modSelect = document.getElementById('modSelect');
        const uploadBtn = document.getElementById('uploadBtn');
        
        // Validate selections
        if (!modSelect.value) {
            e.preventDefault();
            alert('Please select a mod');
            return;
        }
        
        if (!apkFile.files || apkFile.files.length === 0) {
            e.preventDefault();
            alert('Please select an APK file');
            return;
        }
        
        const file = apkFile.files[0];
        const totalBytes = file.size;
        const totalMB = (totalBytes / 1024 / 1024).toFixed(2);
        
        // Show progress indicator
        document.getElementById('uploadProgressContainer').style.display = 'block';
        document.getElementById('totalMB').textContent = totalMB;
        uploadBtn.disabled = true;
        
        e.preventDefault();
        
        const formData = new FormData(this);
        const xhr = new XMLHttpRequest();
        
        // Track upload progress
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const uploadedMB = (e.loaded / 1024 / 1024).toFixed(2);
                
                document.getElementById('uploadProgressBar').style.width = percentComplete + '%';
                document.getElementById('uploadProgressBar').textContent = Math.round(percentComplete) + '%';
                document.getElementById('uploadedMB').textContent = uploadedMB;
                
                // Calculate speed
                if (!window.uploadStartTime) {
                    window.uploadStartTime = Date.now();
                }
                const elapsedSeconds = (Date.now() - window.uploadStartTime) / 1000;
                const speedMBps = (uploadedMB / elapsedSeconds).toFixed(2);
                const remainingBytes = e.total - e.loaded;
                const remainingSeconds = remainingBytes / (e.loaded / elapsedSeconds);
                const remainingTime = formatTime(remainingSeconds);
                
                document.getElementById('uploadSpeed').textContent = speedMBps + ' MB/s | Time left: ' + remainingTime;
            }
        });
        
        xhr.addEventListener('loadstart', function() {
            window.uploadStartTime = Date.now();
        });
        
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                // Show success animation
                document.getElementById('uploadProgressBar').className = 'progress-bar bg-success';
                document.getElementById('uploadProgressBar').style.width = '100%';
                document.getElementById('uploadProgressBar').textContent = '100%';
                
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert('Upload failed. Please try again.');
                uploadBtn.disabled = false;
                document.getElementById('uploadProgressContainer').style.display = 'none';
                delete window.uploadStartTime;
            }
        });
        
        xhr.addEventListener('error', function() {
            alert('Upload error. Please check your connection and try again.');
            uploadBtn.disabled = false;
            document.getElementById('uploadProgressContainer').style.display = 'none';
            delete window.uploadStartTime;
        });
        
        xhr.open('POST', 'upload_mod.php');
        xhr.send(formData);
    });
    
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds < 0) return '-- ';
        if (seconds < 60) return Math.round(seconds) + 's';
        if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            return minutes + 'm ' + secs + 's';
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + minutes + 'm';
    }
    </script>
</body>
</html>
