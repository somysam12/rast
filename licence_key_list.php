<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

$success_msg = '';
$error_msg = '';

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_keys'])) {
    $ids = $_POST['selected_keys'];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM keys_table WHERE id IN ($placeholders)");
        if ($stmt->execute($ids)) {
            $success_msg = count($ids) . ' keys deleted successfully!';
        }
    }
}

// Handle single delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM keys_table WHERE id = ?");
    if ($stmt->execute([$_GET['delete_id']])) {
        $success_msg = 'Key deleted successfully!';
    }
}

// Initial data load
$stmt = $pdo->query("SELECT * FROM keys_table ORDER BY created_at DESC LIMIT 100");
$keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Key Statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_keys,
    COUNT(CASE WHEN is_used = 0 THEN 1 END) as unused_keys,
    COUNT(CASE WHEN is_used = 1 THEN 1 END) as used_keys
    FROM keys_table");
$keyStats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Keys - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            padding: 2rem 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            left: -280px;
        }

        .sidebar.active { transform: translateX(280px); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 0; padding: 1.5rem; transition: margin-left 0.3s ease; max-width: 1400px; margin: 0 auto; }

        @media (min-width: 993px) {
            .sidebar { left: 0; }
            .main-content { margin-left: 280px; }
        }

        .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; display: none; }
        @media (max-width: 992px) { .hamburger { display: block; } }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 1px solid var(--border-light); border-radius: 24px; padding: 25px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); margin-bottom: 2rem; }

        .header-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 1.5rem; border-radius: 24px; margin-bottom: 2rem; position: relative; overflow: hidden; }
        .header-card::after { content: ''; position: absolute; top: -50%; right: -10%; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; }

        .stat-card { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); border-radius: 18px; padding: 12px 8px; text-align: center; height: 100%; transition: transform 0.3s; display: flex; flex-direction: column; justify-content: center; min-height: 80px; }
        .stat-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.08); }
        .stat-card h3 { color: var(--secondary); font-weight: 800; margin-bottom: 2px; font-size: 1.2rem; }
        .stat-card p { color: var(--text-dim); font-size: 0.7rem; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px; }

        .table { color: var(--text-main); border-color: var(--border-light); vertical-align: middle; }
        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border: none; padding: 12px; font-size: 0.9rem; }
        .table tbody td { padding: 12px; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; }
        
        .status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-unused { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-used { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .key-code { font-family: 'Courier New', Courier, monospace; background: rgba(255,255,255,0.05); padding: 4px 8px; border-radius: 6px; color: var(--secondary); font-weight: 600; letter-spacing: 1px; }

        .btn-copy { background: transparent; border: none; color: var(--primary); cursor: pointer; transition: all 0.2s; padding: 0 5px; }
        .btn-copy:hover { color: var(--text-main); transform: scale(1.2); }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }
        
        .bulk-actions { margin-bottom: 1rem; display: none; }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }

        @media (max-width: 576px) {
            .header-card { padding: 1rem; }
            .stat-card h3 { font-size: 1rem; }
            .stat-card p { font-size: 0.6rem; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link active" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h2 class="text-white mb-1" style="font-weight: 800;">License Keys</h2>
                    <p class="text-white opacity-75 mb-0">Manage and track all system licenses</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="stat-card">
                                <h3><?php echo (int)$keyStats['total_keys']; ?></h3>
                                <p>Total</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <h3><?php echo (int)$keyStats['unused_keys']; ?></h3>
                                <p>Unused</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <h3><?php echo (int)$keyStats['used_keys']; ?></h3>
                                <p>Used</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <form method="POST" id="bulkDeleteForm">
                <input type="hidden" name="bulk_delete" value="1">
                <div class="bulk-actions" id="bulkActions">
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmBulkDelete()">
                        <i class="fas fa-trash me-2"></i>Delete Selected (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="form-check-input" id="selectAll"></th>
                                <th>License Key</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keys as $key): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_keys[]" value="<?php echo $key['id']; ?>" class="form-check-input key-checkbox"></td>
                                <td>
                                    <span class="key-code"><?php echo htmlspecialchars($key['license_key']); ?></span>
                                    <button type="button" class="btn-copy" onclick="copyToClipboard('<?php echo $key['license_key']; ?>')" title="Copy Key">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </td>
                                <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($key['duration']); ?> Days</span></td>
                                <td>
                                    <span class="status-badge <?php echo $key['is_used'] ? 'status-used' : 'status-unused'; ?>">
                                        <?php echo $key['is_used'] ? 'Used' : 'Available'; ?>
                                    </span>
                                </td>
                                <td><span class="text-dim"><?php echo htmlspecialchars($key['created_by'] ?? 'System'); ?></span></td>
                                <td>
                                    <button type="button" onclick="confirmDelete(<?php echo $key['id']; ?>)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($keys)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-dim">No license keys found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.key-checkbox');
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');

        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        function updateBulkActions() {
            const checkedCount = document.querySelectorAll('.key-checkbox:checked').length;
            bulkActions.style.display = checkedCount > 0 ? 'block' : 'none';
            selectedCount.textContent = checkedCount;
        }
        
        selectAll.addEventListener('change', () => {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        });
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Key copied to clipboard',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500,
                    background: '#111827',
                    color: '#ffffff'
                });
            });
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Key?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_id=' + id;
                }
            })
        }

        function confirmBulkDelete() {
            Swal.fire({
                title: 'Bulk Delete?',
                text: "All selected keys will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete them!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkDeleteForm').submit();
                }
            })
        }

        <?php if ($success_msg): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo $success_msg; ?>',
            timer: 2000,
            showConfirmButton: false,
            background: '#111827',
            color: '#ffffff'
        });
        <?php endif; ?>
    </script>
</body>
</html>