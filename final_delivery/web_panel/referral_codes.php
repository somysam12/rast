<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Completely silent table setup
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `referral_codes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` timestamp NULL DEFAULT NULL,
        `bonus_amount` decimal(10,2) DEFAULT '0.00',
        `usage_limit` int(11) DEFAULT '1',
        `usage_count` int(11) DEFAULT '0',
        `status` enum('active','inactive') DEFAULT 'active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Safely add missing columns one by one - Old style for older PHP/MySQL
    try { $pdo->exec("ALTER TABLE `referral_codes` ADD COLUMN `bonus_amount` decimal(10,2) DEFAULT '0.00'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `referral_codes` ADD COLUMN `usage_limit` int(11) DEFAULT '1'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `referral_codes` ADD COLUMN `usage_count` int(11) DEFAULT '0'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `referral_codes` ADD COLUMN `expires_at` timestamp NULL DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `referral_codes` ADD COLUMN `status` enum('active','inactive') DEFAULT 'active'"); } catch (Exception $e) {}
} catch (Exception $e) {}

$success = '';
$error = '';

// Handle generate referral code
if ($_POST && isset($_POST['generate_code'])) {
    try {
        $expiryOption = $_POST['expiry_option'] ?? '30d';
        $bonusAmount = (float)($_POST['bonus_amount'] ?? 50.00);
        $usageLimit = (int)($_POST['usage_limit'] ?? 1);
        
        $duration = '+30 days';
        if ($expiryOption === '1h') {
            $duration = '+1 hour';
        } elseif ($expiryOption === '1d') {
            $duration = '+1 day';
        } elseif ($expiryOption === '1w') {
            $duration = '+7 days';
        } elseif ($expiryOption === '1m') {
            $duration = '+30 days';
        }

        if ($bonusAmount < 0) {
            $error = 'Bonus amount cannot be negative';
        } elseif ($usageLimit <= 0) {
            $error = 'Usage limit must be at least 1';
        } else {
            $code = generateReferralCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime($duration));
            
            $stmt = $pdo->prepare("INSERT INTO referral_codes (code, created_by, expires_at, bonus_amount, usage_limit, usage_count) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$code, $_SESSION['user_id'], $expiresAt, $bonusAmount, $usageLimit])) {
                $success = "Referral code generated successfully!";
                $generatedCode = $code;
            } else {
                $error = 'Failed to generate referral code';
            }
        }
    } catch (Exception $e) {
        $error = 'Error generating referral code: ' . $e->getMessage();
    }
}

// Handle bulk deletion
if (isset($_POST['bulk_delete']) && isset($_POST['selected_codes'])) {
    try {
        $ids = $_POST['selected_codes'];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $success = "Selected referral codes deleted successfully.";
        }
    } catch (Exception $e) {
        $error = "Error during bulk deletion: " . $e->getMessage();
    }
}

// Handle deactivate/delete
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: referral_codes.php");
    exit();
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: referral_codes.php");
    exit();
}

// Initial data load
$referralCodes = [];
try {
    $stmt = $pdo->query("SELECT rc.*, u.username as created_by_name FROM referral_codes rc LEFT JOIN users u ON rc.created_by = u.id ORDER BY rc.created_at DESC");
    if ($stmt) {
        $referralCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Silent catch to prevent 500
}

// Statistics - Compatible with both SQLite and MySQL
$stats = ['total' => 0, 'active' => 0];
try {
    $isSQLite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $timeFunc = $isSQLite ? "datetime('now')" : "NOW()";
    
    // Completely safe count queries
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM referral_codes");
        if ($stmt) $stats['total'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM referral_codes WHERE expires_at > $timeFunc");
        if ($stmt) $stats['active'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
} catch (Exception $e) {
    // Silent catch
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Management - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .header-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(6, 182, 212, 0.25));
            border: 2px solid var(--border-light);
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-light);
            border-radius: 18px;
            padding: 1.25rem;
            text-align: center;
        }

        .stat-card h3 {
            color: var(--secondary);
            font-weight: 800;
            margin-bottom: 0.25rem;
            font-size: 1.5rem;
        }

        .stat-card p {
            color: var(--text-dim);
            font-size: 0.75rem;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control, .form-select {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: 12px;
            color: white;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.7);
            color: white;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            color: white;
            font-weight: 700;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3);
        }

        .table {
            color: var(--text-main);
            vertical-align: middle;
        }

        .table thead th {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            border: none;
            padding: 12px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .table tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.85rem;
        }

        .code-badge {
            font-family: 'Courier New', monospace;
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 800;
            letter-spacing: 1px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            cursor: pointer;
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
        }
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobile-overlay"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><div class="logo-icon"><i class="fas fa-shield-alt"></i></div> SILENT PANEL</h4>
        </div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link active" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
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
            <h4 style="margin: 0; font-weight: 800; background: linear-gradient(135deg, var(--secondary), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">REFERRALS</h4>
            <div style="width: 44px;"></div>
        </div>

        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h2 class="text-white mb-1" style="font-weight: 800;">Referral Management</h2>
                    <p class="text-white opacity-75 mb-0">Bulk manage bonus referral codes</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="stat-card">
                                <h3><?php echo (int)$stats['total']; ?></h3>
                                <p>Total Codes</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h3><?php echo (int)$stats['active']; ?></h3>
                                <p>Active Now</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="glass-card">
                    <h5 class="mb-4 fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>New Referral</h5>
                    <form method="POST">
                        <input type="hidden" name="generate_code" value="1">
                        <div class="mb-3">
                            <label class="form-label small text-dim">Bonus Amount (₹)</label>
                            <input type="number" name="bonus_amount" class="form-control" value="50" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-dim">Expiry Duration</label>
                            <select name="expiry_option" class="form-select">
                                <option value="1h">1 Hour</option>
                                <option value="1d">1 Day</option>
                                <option value="1w">1 Week</option>
                                <option value="1m" selected>1 Month</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-dim">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" value="1" min="1" required>
                        </div>
                        <button type="submit" class="btn-submit">Generate Code</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="glass-card">
                    <form method="POST" id="bulkDeleteForm">
                        <input type="hidden" name="bulk_delete" value="1">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <input type="checkbox" id="selectAll" class="form-check-input" style="background-color: transparent; border: 2px solid var(--primary);">
                                <label for="selectAll" class="text-dim small mb-0 cursor-pointer">Select All</label>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirmBulkDelete(event)">
                                <i class="fas fa-trash-alt me-2"></i>Delete Selected
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Code</th>
                                        <th>Bonus</th>
                                        <th>Usage</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referralCodes as $code): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_codes[]" value="<?php echo isset($code['id']) ? $code['id'] : ''; ?>" class="form-check-input code-checkbox" style="background-color: transparent; border: 2px solid var(--primary);">
                                        </td>
                                        <td>
                                            <div class="code-badge" onclick="copyCode('<?php echo isset($code['code']) ? $code['code'] : ''; ?>', this)"><?php echo isset($code['code']) ? $code['code'] : 'N/A'; ?></div>
                                        </td>
                                        <td><span class="text-success fw-bold">₹<?php echo isset($code['bonus_amount']) ? number_format($code['bonus_amount'], 2) : '0.00'; ?></span></td>
                                        <td><span class="text-dim"><?php echo isset($code['usage_count']) ? $code['usage_count'] : '0'; ?> / <?php echo isset($code['usage_limit']) ? $code['usage_limit'] : '1'; ?></span></td>
                                        <td>
                                            <?php 
                                            $expiresAt = isset($code['expires_at']) ? $code['expires_at'] : null;
                                            $isExpired = $expiresAt ? strtotime($expiresAt) < time() : false;
                                            ?>
                                            <span class="small <?php echo $isExpired ? 'text-danger fw-bold' : 'text-dim'; ?>">
                                                <?php echo $expiresAt ? date('M d, H:i', strtotime($expiresAt)) : 'Never'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button type="button" onclick="confirmDelete(<?php echo isset($code['id']) ? $code['id'] : 0; ?>)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');

        <?php if (isset($generatedCode)): ?>
        window.addEventListener('DOMContentLoaded', (event) => {
            const code = '<?php echo $generatedCode; ?>';
            navigator.clipboard.writeText(code).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Code Generated & Copied!',
                    text: code,
                    timer: 2000,
                    background: '#111827',
                    color: '#ffffff',
                    showConfirmButton: false
                });
            });
        });
        <?php endif; ?>

        function toggleSidebar() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        if (hamburgerBtn) hamburgerBtn.onclick = toggleSidebar;
        if (overlay) overlay.onclick = toggleSidebar;

        const selectAll = document.getElementById('selectAll');
        const codeCheckboxes = document.querySelectorAll('.code-checkbox');
        if (selectAll) {
            selectAll.onchange = (e) => {
                codeCheckboxes.forEach(cb => cb.checked = e.target.checked);
            };
        }

        async function copyCode(text, el) {
            try {
                await navigator.clipboard.writeText(text);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Code Copied!',
                    showConfirmButton: false,
                    timer: 1500,
                    background: '#1e293b',
                    color: '#f8fafc'
                });
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Code?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => { if (result.isConfirmed) window.location.href = '?delete=' + id; })
        }

        function confirmBulkDelete(e) {
            e.preventDefault();
            const selected = document.querySelectorAll('.code-checkbox:checked').length;
            if (selected === 0) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No codes selected!', background: '#111827', color: '#ffffff' });
                return false;
            }
            Swal.fire({
                title: 'Bulk Delete?',
                text: `Are you sure you want to delete ${selected} codes?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete all!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => { if (result.isConfirmed) document.getElementById('bulkDeleteForm').submit(); })
        }
    </script>
</body>
</html>