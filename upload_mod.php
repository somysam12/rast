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
    <link href="assets/css/theme.css" rel="stylesheet">
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
                        
                        <!-- Smooth 120 FPS Progress Indicator -->
                        <div id="uploadProgressContainer" style="display: none; margin-bottom: 2rem;">
                            <style>
                                @keyframes slideDown {
                                    from { opacity: 0; transform: translateY(-20px); }
                                    to { opacity: 1; transform: translateY(0); }
                                }
                                
                                @keyframes wavFlow {
                                    0% { transform: translateX(-100%); }
                                    100% { transform: translateX(100%); }
                                }
                                
                                @keyframes glow {
                                    0%, 100% { box-shadow: 0 0 10px rgba(139, 92, 246, 0.4), inset 0 0 10px rgba(139, 92, 246, 0.1); }
                                    50% { box-shadow: 0 0 25px rgba(139, 92, 246, 0.8), inset 0 0 20px rgba(139, 92, 246, 0.2); }
                                }
                                
                                @keyframes float {
                                    0%, 100% { transform: translateY(0px); }
                                    50% { transform: translateY(-3px); }
                                }
                                
                                .upload-progress-wrapper {
                                    animation: slideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
                                    will-change: transform, opacity;
                                    transform: translateZ(0);
                                    backface-visibility: hidden;
                                }
                                
                                .progress-section {
                                    margin-bottom: 1.5rem;
                                }
                                
                                .progress-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 0.8rem;
                                    padding: 0 0.5rem;
                                }
                                
                                .progress-label {
                                    font-size: 0.9rem;
                                    font-weight: 600;
                                    color: #1e293b;
                                    display: flex;
                                    align-items: center;
                                    gap: 0.5rem;
                                }
                                
                                .progress-label i {
                                    color: #8b5cf6;
                                    font-size: 1.1rem;
                                }
                                
                                .progress-percent {
                                    font-size: 1.3rem;
                                    font-weight: 700;
                                    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                                    -webkit-background-clip: text;
                                    -webkit-text-fill-color: transparent;
                                    background-clip: text;
                                }
                                
                                .progress-bar-smooth {
                                    width: 100%;
                                    height: 8px;
                                    background: #f0f0f0;
                                    border-radius: 20px;
                                    overflow: hidden;
                                    box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
                                    position: relative;
                                }
                                
                                .progress-fill {
                                    height: 100%;
                                    background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 50%, #8b5cf6 100%);
                                    background-size: 200% 100%;
                                    border-radius: 20px;
                                    width: 0%;
                                    transition: width 0.08s linear;
                                    box-shadow: 0 0 15px rgba(139, 92, 246, 0.5), inset 0 0 5px rgba(255,255,255,0.5);
                                    position: relative;
                                    animation: glow 2s ease-in-out infinite;
                                    will-change: width;
                                    transform: translateZ(0);
                                    backface-visibility: hidden;
                                    -webkit-font-smoothing: antialiased;
                                }
                                
                                .progress-fill::before {
                                    content: '';
                                    position: absolute;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
                                    animation: wavFlow 1.5s infinite;
                                }
                                
                                .stats-grid {
                                    display: grid;
                                    grid-template-columns: 1fr 1fr;
                                    gap: 1rem;
                                    margin-top: 1rem;
                                }
                                
                                .stat-card {
                                    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                                    padding: 1rem;
                                    border-radius: 12px;
                                    border: 1px solid #e2e8f0;
                                    text-align: center;
                                    transition: all 0.3s ease;
                                    animation: float 3s ease-in-out infinite;
                                    will-change: transform;
                                    transform: translateZ(0);
                                    backface-visibility: hidden;
                                    -webkit-font-smoothing: antialiased;
                                }
                                
                                .stat-card:hover {
                                    border-color: #8b5cf6;
                                    box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
                                }
                                
                                .stat-label {
                                    font-size: 0.8rem;
                                    color: #94a3b8;
                                    font-weight: 500;
                                    margin-bottom: 0.4rem;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                }
                                
                                .stat-value {
                                    font-size: 1.3rem;
                                    font-weight: 700;
                                    color: #1e293b;
                                }
                                
                                .stat-icon {
                                    display: inline-block;
                                    width: 28px;
                                    height: 28px;
                                    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
                                    border-radius: 8px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    color: white;
                                    font-size: 0.9rem;
                                    margin-bottom: 0.5rem;
                                }
                                
                                .status-text {
                                    font-size: 0.9rem;
                                    color: #64748b;
                                    margin-top: 1rem;
                                    font-weight: 500;
                                    letter-spacing: 0.3px;
                                }
                                
                                .status-text .pulse {
                                    display: inline-block;
                                    width: 8px;
                                    height: 8px;
                                    background: #10b981;
                                    border-radius: 50%;
                                    margin-right: 0.5rem;
                                    animation: pulse 1.5s ease-in-out infinite;
                                }
                                
                                @keyframes pulse {
                                    0%, 100% { opacity: 1; }
                                    50% { opacity: 0.5; }
                                }
                                
                                @media (max-width: 1024px) {
                                    .stats-grid {
                                        grid-template-columns: 1fr 1fr;
                                    }
                                }
                                
                                @media (max-width: 600px) {
                                    .progress-container, .progress-fill, .stat-card {
                                        will-change: auto;
                                    }
                                    .stats-grid {
                                        grid-template-columns: 1fr;
                                    }
                                }
                            </style>
                            
                            <div class="upload-progress-wrapper">
                                <div class="progress-section">
                                    <div class="progress-header">
                                        <div class="progress-label">
                                            <i class="fas fa-arrow-up"></i>
                                            Uploading File
                                        </div>
                                        <div class="progress-percent" id="percentageText">0%</div>
                                    </div>
                                    <div class="progress-bar-smooth">
                                        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                                    </div>
                                </div>
                                
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="stat-label">File Size</div>
                                        <div class="stat-value">
                                            <span id="uploadedMB">0</span><span style="font-size: 0.8rem; color: #94a3b8;"> / </span><span id="totalMB">0</span> MB
                                        </div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-tachometer-alt"></i>
                                        </div>
                                        <div class="stat-label">Speed</div>
                                        <div class="stat-value" id="uploadSpeed" style="font-size: 1.1rem;">--</div>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-hourglass-end"></i>
                                        </div>
                                        <div class="stat-label">Time Left</div>
                                        <div class="stat-value" id="timeLeft" style="font-size: 1.1rem;">--</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-info-circle"></i>
                                        </div>
                                        <div class="stat-label">Status</div>
                                        <div class="stat-value" style="font-size: 0.9rem;" id="uploadStatus">Preparing...</div>
                                    </div>
                                </div>
                                
                                <div class="status-text">
                                    <span class="pulse"></span>
                                    <span id="statusMessage">Ready to upload</span>
                                </div>
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
        
        // Track upload progress with smooth 120 FPS animation
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const uploadedMB = (e.loaded / 1024 / 1024).toFixed(2);
                const totalMB = (e.total / 1024 / 1024).toFixed(2);
                
                // Update progress bar with smooth animation
                document.getElementById('progressFill').style.width = percentComplete + '%';
                document.getElementById('percentageText').textContent = Math.round(percentComplete) + '%';
                document.getElementById('uploadedMB').textContent = uploadedMB;
                document.getElementById('totalMB').textContent = totalMB;
                
                // Calculate speed and time
                if (!window.uploadStartTime) {
                    window.uploadStartTime = Date.now();
                }
                const elapsedSeconds = (Date.now() - window.uploadStartTime) / 1000;
                const speedMBps = (uploadedMB / elapsedSeconds).toFixed(2);
                const remainingBytes = e.total - e.loaded;
                const remainingSeconds = remainingBytes / (e.loaded / elapsedSeconds);
                const remainingTime = formatTime(remainingSeconds);
                
                document.getElementById('uploadSpeed').textContent = speedMBps + ' MB/s';
                document.getElementById('timeLeft').textContent = remainingTime;
                
                // Dynamic status message
                let status = 'Initializing...';
                let message = 'Starting upload...';
                
                if (percentComplete < 25) {
                    status = 'Starting...';
                    message = 'Initiating transfer...';
                } else if (percentComplete < 50) {
                    status = 'Uploading...';
                    message = 'Transfer in progress...';
                } else if (percentComplete < 75) {
                    status = 'Uploading...';
                    message = 'Halfway done...';
                } else if (percentComplete < 95) {
                    status = 'Almost done...';
                    message = 'Final stretch...';
                } else {
                    status = 'Finalizing...';
                    message = 'Writing to server...';
                }
                
                document.getElementById('uploadStatus').textContent = status;
                document.getElementById('statusMessage').textContent = message;
            }
        });
        
        xhr.addEventListener('loadstart', function() {
            window.uploadStartTime = Date.now();
        });
        
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                // Success animation
                const progressFill = document.getElementById('progressFill');
                progressFill.style.background = 'linear-gradient(90deg, #10b981 0%, #059669 50%, #10b981 100%)';
                progressFill.style.boxShadow = '0 0 20px rgba(16, 185, 129, 0.6)';
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('percentageText').textContent = '✓';
                document.getElementById('percentageText').style.color = '#10b981';
                document.getElementById('uploadStatus').textContent = 'Complete!';
                document.getElementById('statusMessage').innerHTML = '<i class="fas fa-check-circle me-1"></i>Upload successful!';
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
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
    <script src="assets/js/scroll-restore.js"></script>
</body>
</html>
