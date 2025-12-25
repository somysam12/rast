<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user is admin (redirect to admin panel)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$pdo = getDBConnection();

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
        $pdo = getDBConnection();        $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(balance), 0) as total_balance, COALESCE(AVG(balance), 0) as avg_balance FROM users WHERE role = 'user'");        $userStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0, 'total_balance' => 0, 'avg_balance' => 0];

$stats = ['total_purchases' => 0, 'total_spent' => 0];
try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_purchases,
        SUM(ABS(amount)) as total_spent
        FROM transactions 
        WHERE user_id = ? AND type = 'purchase' AND status = 'completed'");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Use defaults
}
        $pdo = getDBConnection();        $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(balance), 0) as total_balance, COALESCE(AVG(balance), 0) as avg_balance FROM users WHERE role = 'user'");        $userStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0, 'total_balance' => 0, 'avg_balance' => 0];
try {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors
}

// Get available mods
$mods = [];
try {
    $stmt = $pdo->query("SELECT * FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors
}

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2, '.', ',');
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid Date';
    return date('d M Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>User Dashboard - Mod APK Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Dashboard Design */
        :root {
            --bg: #f9fafb;
            --card: #ffffff;
            --text: #374151;
            --muted: #6b7280;
            --line: #e5e7eb;
            --accent: #7c3aed;
            --accent-600: #6d28d9;
            --accent-100: #f3e8ff;
            --gradient-primary: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --line: #334155;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: var(--bg);
            overflow-x: hidden;
            color: var(--text);
            transition: all 0.3s ease;
        }
        
        /* Modern Header */
        .modern-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1002;
            height: 60px;
            box-sizing: border-box;
        }
        
        .hamburger-menu {
            background: none;
            border: none;
            color: #7c3aed;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hamburger-menu:hover {
            color: #6d28d9;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-avatar-header {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #7c3aed;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .user-avatar-header:hover {
            background: #6d28d9;
        }
        
        .dropdown-arrow {
            color: #7c3aed;
            font-size: 0.7rem;
            transition: transform 0.3s ease;
            margin-left: 4px;
            cursor: pointer;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow-large);
            min-width: 200px;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1003;
        }
        
        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            padding: 0.75rem 1rem;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .dropdown-item:hover {
            background: var(--accent-100);
            color: var(--accent);
        }
        
        .dropdown-item i {
            width: 16px;
            text-align: center;
        }
        
        /* Sidebar */
        .sidebar {
            background: #ffffff;
            color: #374151;
            border-right: 1px solid #e5e7eb;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        overflow-y: auto;
        overflow-x: hidden;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: #6b7280;
            padding: 12px 20px;
            margin: 4px 16px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1003;
            pointer-events: auto;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 500;
            border-radius: 8px;
        }
        
        .sidebar .nav-link i {
            color: #6b7280;
            width: 20px;
            margin-right: 12px;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .sidebar .nav-link:hover i {
            color: #374151;
        }
        
        .sidebar .nav-link.active {
            background: #7c3aed;
            color: white;
        }
        
        .sidebar .nav-link.active i {
            color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        overflow-y: auto;
        
        }
        
        .main-content-with-header {
            margin-top: 60px;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        /* Page Header */
        .page-header {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .page-header h2 {
            color: var(--text);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .page-header h2 i {
            margin-right: 12px;
            color: var(--accent);
            font-size: 1.5rem;
        }
        
        .page-header p {
            color: var(--muted);
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2em;
            box-shadow: var(--shadow-medium);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-large);
        }
        
        /* Stats Cards */
        .stats-card {
            background: var(--card);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-large);
        }
        
        .stats-card.balance::before { background: var(--gradient-success); }
        .stats-card.purchases::before { background: var(--gradient-info); }
        .stats-card.spent::before { background: var(--gradient-warning); }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            margin-bottom: 1rem;
            color: white;
        }
        
        .stats-icon.balance { background: var(--gradient-success); }
        .stats-icon.purchases { background: var(--gradient-info); }
        .stats-icon.spent { background: var(--gradient-warning); }
        
        /* Table Cards */
        .table-card {
            background: var(--card);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .table-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .table-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .table-card h5 {
            color: var(--text);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }
        
        .table-card h5 i {
            margin-right: 10px;
            color: var(--accent);
        }
        
        /* Mod Cards */
        .mod-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .mod-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }
        
        .mod-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.875rem;
        }
        
        /* Tables */
        .table {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
        }
        
        .table thead th {
            background: var(--gradient-primary);
            color: white;
            border: none;
            font-weight: 700;
            padding: 20px 16px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--line);
        }
        
        .table tbody tr:hover {
            background: var(--accent-100);
            transform: scale(1.01);
        }
        
        .table tbody td {
            padding: 20px 16px;
            border: none;
            color: var(--text);
            vertical-align: middle;
            font-weight: 500;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges */
        .badge {
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        /* Animations */
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--muted);
            box-shadow: var(--shadow-medium);
            backdrop-filter: blur(20px);
        }
        
        .theme-toggle:hover {
            color: var(--accent);
            box-shadow: var(--shadow-large);
            transform: scale(1.1);
        }
        
        /* Mobile Responsive */
        .mobile-header {
            display: none !important;
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 1001;
            pointer-events: none;
        }
        
        .mobile-overlay.show {
            pointer-events: auto;
        }
        
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0.5rem;
            }
            
            .main-content-with-header {
                margin-top: 60px;
            }
            
            .sidebar {
                width: 100%;
                left: 0;
                right: 0;
                transform: translateX(-100%);
                background: #ffffff;
                border-right: none;
                border-bottom: 1px solid #e5e7eb;
                z-index: 1002;
                pointer-events: none;
            }
            
            .sidebar.show {
                transform: translateX(0);
        overflow-y: auto;
        overflow-x: hidden;
                pointer-events: auto;
            }
            
            .mobile-overlay.show {
                display: block;
                pointer-events: auto;
            }
            
            .modern-header {
                display: flex !important;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                padding: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            .page-header .d-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
            }
            
            .col-md-4, .col-md-8, .col-md-6, .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .stats-card {
                padding: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            .table-card {
                padding: 1rem !important;
            }
            
            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2em;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
            }
        }
    </style>
    <link href="assets/css/dark-mode-button.css" rel="stylesheet">
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
</head>
<body>
    <!-- Modern Header -->
    <div class="modern-header">
        <button class="hamburger-menu" onclick="toggleSidebar()" title="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        
        <div class="user-section">
            <div class="user-avatar-header" onclick="toggleUserDropdown()" title="User Menu">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
            <i class="fas fa-chevron-down dropdown-arrow" onclick="toggleUserDropdown()" style="cursor: pointer;"></i>
                
                <!-- User Dropdown -->
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-item" style="background: var(--accent-100); color: var(--accent); font-weight: 600; cursor: default;">
                        <i class="fas fa-user"></i>Profile
                    </div>
                    <a href="user_settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a href="javascript:history.back()" class="dropdown-item">
                        <i class="fas fa-arrow-left"></i>Back
                    </a>
                    <hr style="margin: 0.5rem 0; border-color: var(--line);">
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </div>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>
    
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fas fa-moon"></i>
    </button>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 style="color: #374151; font-weight: 700; margin-bottom: 0;">
                        <i class="fas fa-user" style="color: #6b7280; margin-right: 8px;"></i>User Panel
                    </h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="user_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="user_manage_keys.php">
                        <i class="fas fa-key"></i>Manage Keys
                    </a>
                    <a class="nav-link" href="user_generate.php">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a class="nav-link" href="user_balance.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="user_applications.php">
                        <i class="fas fa-mobile-alt"></i>Applications
                    </a>
                    <a class="nav-link" href="block_reset_key.php">
                        <i class="fas fa-lock"></i>Block Or Reset Key
                    </a>
                    <a class="nav-link" href="user_request_confirmations.php">
                        <i class="fas fa-bell"></i>Key Notifications
                    </a>
                    <a class="nav-link" href="user_settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content main-content-with-header">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.5rem;"><i class="fas fa-crown me-2" style="color: #7c3aed; -webkit-text-fill-color: #7c3aed;"></i>SilentMultiPanel</h2>
                            <p class="text-muted mb-0">Welcome, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted">User Account</small>
                            </div>
                            <div class="user-avatar floating-animation">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card balance">
                            <div class="text-center">
                                <div class="stats-icon balance mx-auto">
                                    <i class="fas fa-wallet text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Current Balance</h6>
                                <h2 class="mb-0 fw-bold"><?php echo formatCurrency($user['balance']); ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>Available for purchases
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card purchases">
                            <div class="text-center">
                                <div class="stats-icon purchases mx-auto">
                                    <i class="fas fa-shopping-cart text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Purchases</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $stats['total_purchases'] ?: 0; ?></h2>
                                <small class="text-info">
                                    <i class="fas fa-shopping-bag me-1"></i>License keys bought
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card spent">
                            <div class="text-center">
                                <div class="stats-icon spent mx-auto">
                                    <i class="fas fa-rupee-sign text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Spent</h6>
                                <h2 class="mb-0 fw-bold"><?php echo formatCurrency($stats['total_spent'] ?: 0); ?></h2>
                                <small class="text-warning">
                                    <i class="fas fa-chart-line me-1"></i>All time spending
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Available Mods -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="table-card">
                            <h5><i class="fas fa-mobile-alt me-2"></i>Available Mods</h5>
                            <div class="row">
                                <?php foreach ($mods as $mod): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="mod-card">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($mod['name']); ?></h6>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($mod['description'] ?: 'No description available'); ?></p>
                                        <a href="user_manage_keys.php?mod_id=<?php echo $mod['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-key me-1"></i>View Keys
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="table-card">
                            <h5><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">Type</th>
                                            <th style="width: 30%;">Amount</th>
                                            <th style="width: 40%;">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentTransactions)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No transactions yet</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php echo $transaction['type'] === 'purchase' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                                    </span>
                                                </td>
                                                <td class="<?php echo $transaction['amount'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo $transaction['amount'] < 0 ? formatCurrency(abs($transaction['amount'])) : formatCurrency($transaction['amount']); ?>
                                                </td>
                                                <td><?php echo formatDate($transaction['created_at']); ?></td>
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile Navigation (optimized)
        function toggleSidebar() {
            const sidebar = document.querySelector(".sidebar");
            const overlay = document.querySelector(".mobile-overlay");
            if (!sidebar || !overlay) return;
            sidebar.classList.toggle("show");
            overlay.classList.toggle("show");
            if (window.innerWidth <= 991) {
                if (sidebar.classList.contains("show")) {
                    document.body.style.overflow = "hidden";
                } else {
                    document.body.style.overflow = "";
                }
            }
        }
        // Mobile nav links - close sidebar and allow navigation
        document.addEventListener("DOMContentLoaded", function() {
            const links = document.querySelectorAll(".sidebar .nav-link");
            const sidebar = document.querySelector(".sidebar");
            const overlay = document.querySelector(".mobile-overlay");
            links.forEach(link => {
                link.addEventListener("click", function() {
                    if (window.innerWidth <= 991) {
                        sidebar.classList.remove("show");
                        overlay.classList.remove("show");
                        document.body.style.overflow = "";
                    }
                });
            });
            if (overlay) {
                overlay.addEventListener("click", toggleSidebar);
            }
        });
        window.addEventListener("resize", function() {
            if (window.innerWidth > 991) {
                const sidebar = document.querySelector(".sidebar");
                const overlay = document.querySelector(".mobile-overlay");
                sidebar.classList.remove("show");
                overlay.classList.remove("show");
                document.body.style.overflow = "";
            }
        });
        
        // Close sidebar when clicking on nav links on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.stopPropagation();
                if (window.innerWidth <= 991) {
                    toggleSidebar();
                }
            });
            
            // Add touch event for better mobile experience
            link.addEventListener('touchend', (e) => {
                e.stopPropagation();
                if (window.innerWidth <= 991) {
                    toggleSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 991) {
                document.querySelector('.sidebar').classList.remove('show');
                document.querySelector('.mobile-overlay').classList.remove('show');
                document.body.style.overflow = '';
            }
        });
        
        // Theme toggle functionality
        function toggleDarkMode() {
            const body = document.body;
            const currentTheme = body.getAttribute('data-theme');
            
            if (currentTheme === 'dark') {
                body.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            }
        }
        
        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
            }
        });
    </script>
</body>
</html>