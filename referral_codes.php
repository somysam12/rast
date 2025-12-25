<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Handle generate referral code
if ($_POST && isset($_POST['generate_code']) && $pdo) {
    try {
        $expiryDays = (int)$_POST['expiry_days'];
        $bonusAmount = (float)($_POST['bonus_amount'] ?? 50.00);
        $usageLimit = (int)($_POST['usage_limit'] ?? 1);
        
        if ($expiryDays <= 0) {
            $error = 'Please enter a valid expiry period';
        } elseif ($bonusAmount < 0) {
            $error = 'Bonus amount cannot be negative';
        } elseif ($usageLimit <= 0) {
            $error = 'Usage limit must be at least 1';
        } else {
            $code = generateReferralCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));
            
            $stmt = $pdo->prepare("INSERT INTO referral_codes (code, created_by, expires_at, bonus_amount, usage_limit, usage_count) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$code, $_SESSION['user_id'], $expiresAt, $bonusAmount, $usageLimit])) {
                $success = "Referral code generated successfully: $code";
            } else {
                $error = 'Failed to generate referral code';
            }
        }
    } catch (Exception $e) {
        $error = 'Error generating referral code: ' . $e->getMessage();
    }
}

// Handle deactivate code
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate']) && $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE id = ?");
        if ($stmt->execute([$_GET['deactivate']])) {
            $success = 'Referral code deactivated successfully!';
        } else {
            $error = 'Failed to deactivate referral code';
        }
    } catch (Exception $e) {
        $error = 'Error deactivating referral code: ' . $e->getMessage();
    }
}

// Handle delete code
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id = ?");
        if ($stmt->execute([$_GET['delete']])) {
            $success = 'Referral code deleted successfully!';
        } else {
            $error = 'Failed to delete referral code';
        }
    } catch (Exception $e) {
        $error = 'Error deleting referral code: ' . $e->getMessage();
    }
}

// Get all referral codes
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT rc.*, u.username as created_by_name 
                            FROM referral_codes rc 
                            LEFT JOIN users u ON rc.created_by = u.id 
                            ORDER BY rc.created_at DESC");
        $referralCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $referralCodes = [];
    }
} catch (Exception $e) {
    $referralCodes = [];
    $error = 'Failed to fetch referral codes: ' . $e->getMessage();
}

// Get referral code statistics
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_codes,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_codes,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_codes,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_codes,
            COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_codes
            FROM referral_codes");
        $codeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $codeStats = [
            'total_codes' => 0,
            'active_codes' => 0,
            'inactive_codes' => 0,
            'valid_codes' => 0,
            'expired_codes' => 0
        ];
    }
} catch (Exception $e) {
    $codeStats = [
        'total_codes' => 0,
        'active_codes' => 0,
        'inactive_codes' => 0,
        'valid_codes' => 0,
        'expired_codes' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Referral Codes - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            transition: all 0.3s ease;
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
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 16px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1em;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        .mobile-header {
            display: none;
            background: var(--card-bg);
            padding: 1rem;
            box-shadow: var(--shadow-light);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            border-bottom: 1px solid var(--border-light);
            backdrop-filter: blur(20px);
            width: 100%;
            box-sizing: border-box;
        }
        
        .mobile-toggle {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: var(--shadow-light);
            transition: all 0.2s ease;
        }
        
        .mobile-toggle:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }
        
        .page-header {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-light);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--purple);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .stats-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border-left: 4px solid;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.total-codes { border-left-color: var(--purple); }
        .stats-card.active-codes { border-left-color: var(--success); }
        .stats-card.valid-codes { border-left-color: var(--info); }
        .stats-card.expired-codes { border-left-color: var(--danger); }
        
        .form-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 1.5rem;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .codes-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
        }
        
        .codes-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }
        
        .table thead th {
            background-color: var(--purple);
            color: white;
            border: none;
            font-weight: 600;
            padding: 12px;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .table tbody td {
            padding: 12px;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-light);
            padding: 0.75rem;
            transition: all 0.2s ease;
            color: var(--text-primary);
            background-color: var(--card-bg);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25);
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.75rem 1.5rem;
        }
        
        .btn-primary {
            background-color: var(--purple);
            border-color: var(--purple);
        }
        
        .btn-primary:hover {
            background-color: var(--purple-dark);
            border-color: var(--purple-dark);
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background-color: var(--warning);
            border-color: var(--warning);
        }
        
        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 1rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        
        .code-display {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-weight: 700;
            font-size: 1.1em;
            color: var(--purple);
            background-color: rgba(139, 92, 246, 0.1);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .code-display:hover {
            background-color: rgba(139, 92, 246, 0.15);
            transform: scale(1.05);
        }
        
        .status-active {
            color: var(--success);
        }
        
        .status-inactive {
            color: var(--danger);
        }
        
        .status-expired {
            color: var(--warning);
        }
        
        @media screen and (max-width: 768px) {
            .mobile-header {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1001 !important;
                background: var(--card-bg) !important;
                padding: 1rem !important;
                box-shadow: var(--shadow-light) !important;
                border-bottom: 1px solid var(--border-light) !important;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                background: var(--card-bg);
                border-right: none;
                box-shadow: var(--shadow-medium);
                backdrop-filter: blur(20px);
                z-index: 1002;
                pointer-events: none;
            }
            
            .sidebar.show {
                transform: translateX(0);
                pointer-events: auto;
            }
            
            .sidebar .nav-link {
                pointer-events: auto;
                position: relative;
                z-index: 1003;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 80px;
            }
            
            .mobile-header {
                display: flex !important;
                justify-content: space-between;
                align-items: center;
                backdrop-filter: blur(20px);
            }
        }
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
    <script src="assets/js/menu-logic.js"></script>
</head>
<body>
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: var(--purple);"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="position-sticky">
                    <h4 class="text-center py-3 border-bottom" style="border-color: var(--border-light) !important; color: var(--purple); font-weight: 600;">
                        <i class="fas fa-shield-alt me-2"></i>Multi Panel
                    </h4>
                <nav class="nav flex-column p-3">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod Name</a>
                    <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload Mod APK</a>
                    <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod APK List</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License Key</a>
                    <a class="nav-link" href="licence_key_list.php"><i class="fas fa-key"></i>License Key List</a>
                    <a class="nav-link" href="available_keys.php"><i class="fas fa-key"></i>Available Keys</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
                    <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link active" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Code</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
                </div>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Referral Code Management</h2>
                            <p class="text-muted mb-0">Create and manage customized referral codes</p>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                        </div>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card total-codes">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Total Codes</div>
                            <div class="h3 mb-0"><?php echo $codeStats['total_codes']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card active-codes">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Active</div>
                            <div class="h3 mb-0"><?php echo $codeStats['active_codes']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card valid-codes">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Valid</div>
                            <div class="h3 mb-0"><?php echo $codeStats['valid_codes']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card expired-codes">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Expired</div>
                            <div class="h3 mb-0"><?php echo $codeStats['expired_codes']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Generate New Referral Code</h5>
                    </div>
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Expiry Period</label>
                            <select class="form-control" name="expiry_days" required>
                                <option value="7">7 Days</option>
                                <option value="15">15 Days</option>
                                <option value="30" selected>30 Days (Recommended)</option>
                                <option value="90">90 Days</option>
                                <option value="365">1 Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonus Balance</label>
                            <input type="number" class="form-control" name="bonus_amount" value="50" min="0" step="1" required>
                            <small class="text-muted">Balance user gets</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" class="form-control" name="usage_limit" value="1" min="1" step="1" required>
                            <small class="text-muted">Max users allowed</small>
                        </div>
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" name="generate_code" class="btn btn-primary px-5">
                                <i class="fas fa-magic me-2"></i>Generate Code
                            </button>
                        </div>
                    </form>
                </div>

                <div class="codes-card">
                    <div class="codes-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list-ul me-2"></i>Existing Referral Codes</h5>
                        <span class="badge bg-white text-primary"><?php echo count($referralCodes); ?> Codes</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Bonus</th>
                                    <th>Usage</th>
                                    <th>Created By</th>
                                    <th>Expires At</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($referralCodes)): ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">No referral codes found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($referralCodes as $code): 
                                        $isExpired = strtotime($code['expires_at']) < time();
                                        $isLimitReached = ($code['usage_limit'] > 0 && $code['usage_count'] >= $code['usage_limit']);
                                    ?>
                                        <tr>
                                            <td><span class="code-display" onclick="copyToClipboard('<?php echo $code['code']; ?>')"><?php echo $code['code']; ?></span></td>
                                            <td><span class="badge bg-purple"><?php echo (int)($code['bonus_amount'] ?? 50); ?> Balance</span></td>
                                            <td>
                                                <div class="small fw-bold"><?php echo $code['usage_count']; ?> / <?php echo $code['usage_limit']; ?></div>
                                                <div class="progress" style="height: 4px; width: 60px;">
                                                    <?php $percent = min(100, ($code['usage_count'] / $code['usage_limit']) * 100); ?>
                                                    <div class="progress-bar bg-purple" style="width: <?php echo $percent; ?>%"></div>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($code['created_by_name']); ?></span></td>
                                            <td><small class="text-muted"><?php echo date('d M Y H:i', strtotime($code['expires_at'])); ?></small></td>
                                            <td>
                                                <?php if ($code['status'] === 'inactive' || $isLimitReached): ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php elseif ($isExpired): ?>
                                                    <span class="badge bg-warning">Expired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($code['status'] === 'active' && !$isExpired && !$isLimitReached): ?>
                                                        <a href="?deactivate=<?php echo $code['id']; ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate this code?')">
                                                            <i class="fas fa-ban"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?delete=<?php echo $code['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this code permanently?')">
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
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar(e) {
            e.stopPropagation();
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('overlay').classList.toggle('show');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Copied to clipboard!'
                });
            });
        }

        // Show success animation if code was generated
        <?php if (isset($success) && strpos($success, 'Referral code generated successfully') !== false): 
            $generatedCode = substr($success, strrpos($success, ': ') + 2);
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            const code = '<?php echo $generatedCode; ?>';
            
            // Auto copy to clipboard
            navigator.clipboard.writeText(code).then(() => {
                Swal.fire({
                    title: 'Generated!',
                    html: `Referral Code: <b style="color:#8b5cf6; font-size:1.5rem; letter-spacing:2px;">${code}</b><br><br>Code has been auto-copied to clipboard.`,
                    icon: 'success',
                    confirmButtonColor: '#8b5cf6',
                    timer: 5000,
                    timerProgressBar: true,
                    showClass: {
                        popup: 'animate__animated animate__fadeInDown'
                    },
                    hideClass: {
                        popup: 'animate__animated animate__fadeOutUp'
                    }
                });
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
