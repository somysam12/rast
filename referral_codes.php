<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

// Debug: Check if we reach this point
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect instead of showing white screen
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
        
        if ($expiryDays <= 0) {
            $error = 'Please enter a valid expiry period';
        } else {
            $code = generateReferralCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));
            
            $stmt = $pdo->prepare("INSERT INTO referral_codes (code, created_by, expires_at) VALUES (?, ?, ?)");
            if ($stmt->execute([$code, $_SESSION['user_id'], $expiresAt])) {
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
        
        /* Theme toggle button */
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-light);
        }
        
            transform: scale(1.1);
        }
        
        /* Force mobile header visibility on mobile devices */
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
        
        /* Mobile Responsiveness */
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
                padding-top: 80px; /* Add padding for fixed mobile header */
            }
            
            .mobile-header {
                display: flex !important;
                justify-content: space-between;
                align-items: center;
                backdrop-filter: blur(20px);
            }
            
            .mobile-toggle {
                background: var(--gradient-primary);
                border: none;
                color: white;
                padding: 0.75rem;
                border-radius: 12px;
                box-shadow: var(--shadow-light);
                transition: all 0.3s ease;
                font-size: 1.1rem;
                min-width: 48px;
                min-height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .mobile-toggle:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-medium);
            }
            
            .mobile-toggle:active {
                transform: translateY(0) scale(0.95);
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1001;
                pointer-events: none;
            }
            
            .mobile-overlay.show {
                display: block;
                pointer-events: auto;
            }
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        .mobile-overlay {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Theme Toggle Button -->
        <i class="fas fa-sun" id="theme-icon"></i>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar()">
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
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="position-sticky">
                    <h4 class="text-center py-3 border-bottom" style="border-color: var(--border-light) !important; color: var(--purple); font-weight: 600;">
                        <i class="fas fa-shield-alt me-2"></i>Multi Panel
                    </h4>
                <nav class="nav flex-column p-3">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="add_mod.php">
                        <i class="fas fa-plus"></i>Add Mod Name
                    </a>
                    <a class="nav-link" href="manage_mods.php">
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
                    <a class="nav-link active" href="referral_codes.php">
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
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-tag me-2" style="color: var(--purple);"></i>Referral Codes</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Generate and manage referral codes for user acquisition</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                <small style="color: var(--text-secondary);">Admin Account</small>
                            </div>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-codes">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Codes</h6>
                                    <h3 class="mb-0"><?php echo $codeStats['total_codes']; ?></h3>
                                </div>
                                <div style="color: var(--purple);">
                                    <i class="fas fa-tag fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card active-codes">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Active Codes</h6>
                                    <h3 class="mb-0"><?php echo $codeStats['active_codes']; ?></h3>
                                </div>
                                <div style="color: var(--success);">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card valid-codes">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Valid Codes</h6>
                                    <h3 class="mb-0"><?php echo $codeStats['valid_codes']; ?></h3>
                                </div>
                                <div style="color: var(--info);">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card expired-codes">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Expired Codes</h6>
                                    <h3 class="mb-0"><?php echo $codeStats['expired_codes']; ?></h3>
                                </div>
                                <div style="color: var(--danger);">
                                    <i class="fas fa-times-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Generate Referral Code -->
                <div class="form-card">
                    <div class="form-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Generate New Referral Code</h5>
                        <small>Create time-limited referral codes for user acquisition campaigns</small>
                    </div>
                    
                    <form method="POST" class="row g-3" id="generateForm">
                        <div class="col-md-6">
                            <label for="expiry_days" class="form-label">
                                <i class="fas fa-calendar me-2"></i>Expiry Period
                            </label>
                            <select class="form-control" id="expiry_days" name="expiry_days" required>
                                <option value="7">7 Days</option>
                                <option value="15">15 Days</option>
                                <option value="30" selected>30 Days (Recommended)</option>
                                <option value="60">60 Days</option>
                                <option value="90">90 Days</option>
                                <option value="180">180 Days</option>
                            </select>
                            <small class="text-muted mt-1 d-block">
                                <i class="fas fa-info-circle me-1"></i>
                                Choose how long the referral code will remain valid
                            </small>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="d-grid w-100">
                                <button type="submit" name="generate_code" class="btn btn-primary">
                                    <i class="fas fa-magic me-2"></i>Generate Code
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Existing Referral Codes -->
                <div class="codes-card">
                    <div class="codes-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Existing Referral Codes</h5>
                                <small>Manage all generated referral codes and their status</small>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark"><?php echo count($referralCodes); ?> Codes</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($referralCodes)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tag fa-4x mb-3" style="color: var(--text-secondary);"></i>
                            <h5>No Referral Codes Found</h5>
                            <p>Generate your first referral code using the form above.</p>
                            <button type="button" class="btn btn-primary" onclick="focusGenerateForm()">
                                <i class="fas fa-plus me-2"></i>Generate First Code
                            </button>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tag me-2"></i>Code</th>
                                    <th><i class="fas fa-user me-2"></i>Created By</th>
                                    <th><i class="fas fa-calendar-plus me-2"></i>Created At</th>
                                    <th><i class="fas fa-calendar-times me-2"></i>Expires At</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                    <th><i class="fas fa-cogs me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referralCodes as $code): ?>
                                <?php 
                                $isExpired = strtotime($code['expires_at']) <= time();
                                $statusClass = $isExpired ? 'status-expired' : ($code['status'] === 'active' ? 'status-active' : 'status-inactive');
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="code-display" onclick="copyToClipboard('<?php echo htmlspecialchars($code['code']); ?>')" title="Click to copy">
                                                <?php echo htmlspecialchars($code['code']); ?>
                                            </span>
                                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?php echo htmlspecialchars($code['code']); ?>')" title="Copy code">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2" style="width: 32px; height: 32px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8em; font-weight: 600;">
                                                <?php echo strtoupper(substr($code['created_by_name'] ?? 'A', 0, 2)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($code['created_by_name'] ?? 'Admin'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo formatDate($code['created_at']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-secondary);" class="<?php echo $isExpired ? 'text-danger' : ''; ?>">
                                            <?php echo formatDate($code['expires_at']); ?>
                                            <?php if ($isExpired): ?>
                                                <br><small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Expired</small>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isExpired): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i>Expired
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-<?php echo $code['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <i class="fas fa-<?php echo $code['status'] === 'active' ? 'check-circle' : 'pause-circle'; ?> me-1"></i>
                                                <?php echo ucfirst($code['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($code['status'] === 'active' && !$isExpired): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-warning"
                                                        onclick="confirmDeactivate(<?php echo $code['id']; ?>, '<?php echo htmlspecialchars($code['code']); ?>')" 
                                                        title="Deactivate Code">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="confirmDelete(<?php echo $code['id']; ?>, '<?php echo htmlspecialchars($code['code']); ?>')" 
                                                    title="Delete Code">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
        // Theme Management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
        }
        
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        }
        
        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Mobile Sidebar Management
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.querySelector('.main-content');
            const body = document.body;
            
            // Toggle sidebar visibility
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            // Prevent body scroll when sidebar is open on mobile
            if (window.innerWidth <= 768) {
                if (sidebar.classList.contains('show')) {
                    body.style.overflow = 'hidden';
                    // Ensure sidebar is clickable
                    sidebar.style.pointerEvents = 'auto';
                    sidebar.style.zIndex = '1002';
                } else {
                    body.style.overflow = '';
                    sidebar.style.pointerEvents = 'none';
                }
            }
            
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('full-width');
            }
            
            // Add smooth transition
            sidebar.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        }
        
        // Copy to Clipboard Functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast(`Referral code "${text}" copied to clipboard!`, 'success');
            }, function(err) {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    showToast(`Referral code "${text}" copied to clipboard!`, 'success');
                } catch (err) {
                    showToast('Failed to copy referral code', 'error');
                }
                document.body.removeChild(textArea);
            });
        }
        
        // Enhanced Confirmation Dialogs
        function confirmDeactivate(codeId, codeText) {
            if (confirm(`Are you sure you want to deactivate referral code "${codeText}"? This action will prevent new users from using this code.`)) {
                showToast('Deactivating referral code...', 'warning');
                window.location.href = `?deactivate=${codeId}`;
            }
        }
        
        function confirmDelete(codeId, codeText) {
            if (confirm(`Are you sure you want to permanently delete referral code "${codeText}"? This action cannot be undone.`)) {
                showToast('Deleting referral code...', 'warning');
                window.location.href = `?delete=${codeId}`;
            }
        }
        
        // Focus Generate Form
        function focusGenerateForm() {
            document.getElementById('expiry_days').focus();
            showToast('Select expiry period and generate your first code', 'info');
        }
        
        // Toast Notification System
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${getToastIcon(type)}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            const style = document.createElement('style');
            if (!document.getElementById('toast-styles')) {
                style.id = 'toast-styles';
                style.textContent = `
                    .toast-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 12px 20px;
                        border-radius: 8px;
                        box-shadow: var(--shadow-medium);
                        z-index: 10001;
                        transform: translateX(100%);
                        transition: all 0.3s ease;
                        max-width: 300px;
                    }
                    
                    .toast-notification.show {
                        transform: translateX(0);
                    }
                    
                    .toast-success {
                        background: #10b981;
                        color: white;
                    }
                    
                    .toast-error {
                        background: #ef4444;
                        color: white;
                    }
                    
                    .toast-warning {
                        background: #f59e0b;
                        color: white;
                    }
                    
                    .toast-info {
                        background: var(--purple);
                        color: white;
                    }
                    
                    .toast-content {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function getToastIcon(type) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            return icons[type] || icons.info;
        }
        
        // Enhanced Statistics Animation
        function animateStats() {
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // Enhanced Form Handling
        function enhanceForm() {
            const form = document.getElementById('generateForm');
            const expirySelect = document.getElementById('expiry_days');
            
            form.addEventListener('submit', function(e) {
                const expiryDays = parseInt(expirySelect.value);
                
                if (expiryDays <= 0) {
                    e.preventDefault();
                    showToast('Please select a valid expiry period', 'error');
                    return;
                }
                
                showToast(`Generating referral code with ${expiryDays} days validity...`, 'info');
            });
            
            // Visual feedback for selection
            expirySelect.addEventListener('change', function() {
                const days = this.value;
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + parseInt(days));
                
                showToast(`Code will expire on ${expiryDate.toLocaleDateString()}`, 'info');
            });
        }
        
        // Code Display Enhancements
        function enhanceCodeDisplay() {
            const codeDisplays = document.querySelectorAll('.code-display');
            codeDisplays.forEach(display => {
                display.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });
                
                display.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
        
        // Responsive Behavior Enhancement
        function handleResize() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mainContent = document.querySelector('.main-content');
            const body = document.body;
            
            if (window.innerWidth > 768) {
                // Desktop view
                overlay.classList.remove('show');
                sidebar.classList.remove('show');
                body.style.overflow = '';
                if (!sidebar.classList.contains('hidden')) {
                    mainContent.classList.remove('full-width');
                }
            } else {
                // Mobile view
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                mainContent.classList.remove('full-width');
                body.style.overflow = '';
            }
        }
        
        // Ensure mobile header is visible on mobile devices
        function checkMobileView() {
            const mobileHeader = document.querySelector('.mobile-header');
            const isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                mobileHeader.style.display = 'flex';
                mobileHeader.style.position = 'fixed';
                mobileHeader.style.top = '0';
                mobileHeader.style.left = '0';
                mobileHeader.style.right = '0';
                mobileHeader.style.width = '100%';
                mobileHeader.style.zIndex = '1001';
            } else {
                mobileHeader.style.display = 'none';
            }
        }
        
        // Check on load and resize
        document.addEventListener('DOMContentLoaded', checkMobileView);
        window.addEventListener('resize', checkMobileView);
        
        // Force mobile header visibility on small screens
        setTimeout(() => {
            if (window.innerWidth <= 768) {
                const mobileHeader = document.querySelector('.mobile-header');
                mobileHeader.style.display = 'flex';
                mobileHeader.style.position = 'fixed';
                mobileHeader.style.top = '0';
                mobileHeader.style.left = '0';
                mobileHeader.style.right = '0';
                mobileHeader.style.width = '100%';
                mobileHeader.style.zIndex = '1001';
            }
        }, 100);
        
        // Emergency mobile header fix
        function forceMobileHeader() {
            const mobileHeader = document.querySelector('.mobile-header');
            if (mobileHeader && window.innerWidth <= 768) {
                mobileHeader.style.cssText = `
                    display: flex !important;
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
                    visibility: visible !important;
                    opacity: 1 !important;
                `;
            }
        }
        
        // Run on load and after a delay
        document.addEventListener('DOMContentLoaded', forceMobileHeader);
        setTimeout(forceMobileHeader, 500);
        window.addEventListener('resize', forceMobileHeader);
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            enhanceForm();
            enhanceCodeDisplay();
            
            setTimeout(() => {
                animateStats();
            }, 500);
            
            // Add resize event listener
            window.addEventListener('resize', handleResize);
            
            // Close sidebar when clicking on a nav link (mobile)
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    // Ensure the click event works
                    e.stopPropagation();
                    
                    if (window.innerWidth <= 768) {
                        // Add small delay to ensure click registers
                        setTimeout(() => {
                            toggleSidebar();
                        }, 100);
                    }
                });
                
                // Add touch event support for mobile
                link.addEventListener('touchend', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (window.innerWidth <= 768) {
                        // Trigger click event
                        link.click();
                    }
                });
            });
            
            // Welcome message
            setTimeout(() => {
                showToast('Referral Code Management Panel Ready', 'info');
            }, 1000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + D for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
            }
            
            // Ctrl/Cmd + G for generate focus
            if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
                e.preventDefault();
                focusGenerateForm();
            }
            
            // Escape to close mobile sidebar
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('show')) {
                    toggleSidebar();
                }
            }
        });
        
        // Enhanced Error Handling Display
        <?php if (isset($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($error); ?>', 'error');
        });
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($success); ?>', 'success');
        });
        <?php endif; ?>
    </script>
</body>
</html>