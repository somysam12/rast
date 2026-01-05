<?php
require_once "includes/optimization.php";
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get all mods with APK information
$stmt = $pdo->query("SELECT m.*, ma.file_name, ma.file_path, ma.uploaded_at as apk_uploaded_at 
                    FROM mods m 
                    LEFT JOIN (
                        SELECT * FROM mod_apks WHERE id IN (SELECT MAX(id) FROM mod_apks GROUP BY mod_id)
                    ) ma ON m.id = ma.mod_id 
                    ORDER BY m.created_at DESC");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mod APK List - Silent Panel</title>
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
        }

        body {
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
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
            -webkit-backdrop-filter: blur(30px);
            border-right: 1.5px solid var(--border-light);
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s ease;
            padding: 1.5rem 0;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 1.4rem;
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
            padding: 1.5rem;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .top-bar {
            display: none;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
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
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 24px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: var(--primary);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
            transform: translateY(-5px);
        }

        .header-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(6, 182, 212, 0.25));
            border: 2px solid var(--border-light);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            backdrop-filter: blur(20px);
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
        }

        .btn-primary-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
            color: white;
        }

        .table {
            color: var(--text-main);
            vertical-align: middle;
        }

        .table thead th {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            border: none;
            padding: 1.25rem;
            font-size: 0.9rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .table tbody td {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.95rem;
        }

        .mod-icon-placeholder {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-inactive {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .download-link {
            color: var(--secondary);
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .download-link:hover {
            color: var(--primary);
            transform: scale(1.05);
        }

        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 999;
            display: none;
        }

        .mobile-overlay.show {
            display: block;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .top-bar {
                display: flex;
            }

            .header-card {
                padding: 1.5rem;
                text-align: center;
            }

            .header-card .d-flex {
                flex-direction: column;
                gap: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-shield-alt"></i> SILENT PANEL</h4>
        </div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link active" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
            <a class="nav-link" href="admin_block_reset_requests.php"><i class="fas fa-ban"></i>Requests</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1rem 0;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <button class="hamburger-btn" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
            <h4 style="margin: 0; font-weight: 800; background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">MOD LIST</h4>
            <div style="width: 44px;"></div>
        </div>

        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1" style="font-weight: 800; color: white;">Mod Repository</h2>
                    <p class="mb-0 text-white opacity-75">Manage and monitor all mod APK releases</p>
                </div>
                <a href="add_mod.php" class="btn-action btn-primary-gradient">
                    <i class="fas fa-plus"></i> Create New Mod
                </a>
            </div>
        </div>

        <div class="glass-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-bold"><i class="fas fa-box-open me-2 text-primary"></i>Installed Modules</h5>
                <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo count($mods); ?> Total</span>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Description</th>
                            <th>Release Data</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mods)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                <h6 class="text-dim">No modules detected in system</h6>
                                <a href="add_mod.php" class="btn btn-primary btn-sm mt-3">Initialize First Module</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($mods as $mod): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="mod-icon-placeholder">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($mod['name']); ?></div>
                                        <small class="text-dim">ID: #<?php echo $mod['id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="text-dim small" style="max-width: 250px;">
                                    <?php echo htmlspecialchars($mod['description'] ?: 'No functional documentation provided.'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($mod['file_name']): ?>
                                    <a href="<?php echo htmlspecialchars($mod['file_path']); ?>" class="download-link">
                                        <i class="fas fa-download"></i> Latest APK
                                    </a>
                                    <small class="text-dim d-block mt-1">
                                        <i class="far fa-clock me-1"></i> <?php echo date('d M Y', strtotime($mod['apk_uploaded_at'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-warning small fw-bold">
                                        <i class="fas fa-exclamation-triangle me-1"></i> No APK Uploaded
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?php echo $mod['status'] === 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $mod['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="upload_mod.php?mod_id=<?php echo $mod['id']; ?>" class="btn btn-sm btn-outline-primary border-2 rounded-pill px-3" title="Update Binary">
                                        <i class="fas fa-upload me-1"></i> Update
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

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        if (hamburgerBtn) hamburgerBtn.onclick = toggleSidebar;
        if (overlay) overlay.onclick = toggleSidebar;
    </script>
</body>
</html>