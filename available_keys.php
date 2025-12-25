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

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Get statistics
try {
    $stats = getModStats();
} catch (Exception $e) {
    $stats = [
        'total_mods' => 0,
        'total_keys' => 0,
        'available_keys' => 0,
        'sold_keys' => 0
    ];
}

// Get available keys grouped by mod (ONLY AVAILABLE KEYS)
try {
    $stmt = $pdo->query("SELECT lk.*, m.name as mod_name, 
                        COUNT(*) as total_keys,
                        SUM(CASE WHEN lk.status = 'available' THEN 1 ELSE 0 END) as available_keys,
                        SUM(CASE WHEN lk.status = 'sold' THEN 1 ELSE 0 END) as sold_keys,
                        MIN(lk.price) as min_price,
                        MAX(lk.price) as max_price
                        FROM license_keys lk 
                        LEFT JOIN mods m ON lk.mod_id = m.id 
                        WHERE lk.status = 'available'
                        GROUP BY lk.mod_id, m.name
                        ORDER BY m.name");
    $modStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $modStats = [];
}


// Get detailed available keys
try {
    $availableKeys = getAvailableKeys();
} catch (Exception $e) {
    $availableKeys = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Available License Keys - Multi Panel</title>
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
        
        .stats-card.total-mods { border-left-color: var(--purple); }
        .stats-card.total-keys { border-left-color: #10b981; }
        .stats-card.available-keys { border-left-color: #06b6d4; }
        .stats-card.sold-keys { border-left-color: #f59e0b; }
        
        .mod-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 1.5rem;
        }
        
        .mod-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .mod-section {
            background-color: rgba(139, 92, 246, 0.05);
            border: 1px solid rgba(139, 92, 246, 0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
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
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
        }
        
        .price-range {
            color: #10b981;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        
        /* Theme toggle button */
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1050;
            background: var(--card) !important;
            border: 2px solid var(--line) !important;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            font-size: 20px;
            color: var(--text);
        }
        .theme-toggle:hover {
            transform: scale(1.1);
            color: var(--accent);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }
        .theme-toggle:active {
            transform: scale(0.95);
        }
        @media (max-width: 768px) {
            .theme-toggle {
                width: 50px !important;
                height: 50px !important;
                font-size: 18px !important;
                top: 12px !important;
                right: 12px !important;
                z-index: 1050 !important;
            }
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
            
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .stats-card, .mod-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
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
            
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .stats-card, .mod-card {
                padding: 0.5rem;
            }
            
            .table {
                font-size: 0.8rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.3rem;
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
    <div class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
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
                    <a class="nav-link active" href="available_keys.php">
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
                    <a class="nav-link" href="referral_codes.php">
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
                            <h2 class="mb-2"><i class="fas fa-key me-2" style="color: var(--purple);"></i>Available License Keys</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Overview of all available license keys by mod</p>
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
                        <div class="stats-card total-mods">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Mods</h6>
                                    <h3 class="mb-0"><?php echo $stats['total_mods']; ?></h3>
                                </div>
                                <div style="color: var(--purple);">
                                    <i class="fas fa-box fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-keys">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Keys</h6>
                                    <h3 class="mb-0"><?php echo $stats['total_keys']; ?></h3>
                                </div>
                                <div style="color: #10b981;">
                                    <i class="fas fa-key fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card available-keys">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Available Keys</h6>
                                    <h3 class="mb-0"><?php echo $stats['available_keys']; ?></h3>
                                </div>
                                <div style="color: #06b6d4;">
                                    <i class="fas fa-unlock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card sold-keys">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Sold Keys</h6>
                                    <h3 class="mb-0"><?php echo $stats['sold_keys']; ?></h3>
                                </div>
                                <div style="color: #f59e0b;">
                                    <i class="fas fa-shopping-cart fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Mod Statistics -->
                <div class="mod-card">
                    <div class="mod-header">
                        <h5><i class="fas fa-list me-2"></i>Available License Keys</h5>
                        <span><?php echo count($modStats); ?> Mods Available</span>
                    </div>
                    
                    <?php if (empty($modStats)): ?>
                        <div class="empty-state">
                            <i class="fas fa-key fa-4x mb-3" style="color: var(--text-secondary);"></i>
                            <h5>No License Keys Available</h5>
                            <p>Start by adding some mods and license keys to see them here.</p>
                            <a href="add_mod.php" class="btn btn-primary me-2">
                                <i class="fas fa-plus me-2"></i>Add Mod
                            </a>
                            <a href="add_license.php" class="btn btn-outline-primary">
                                <i class="fas fa-key me-2"></i>Add License Key
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($modStats as $mod): ?>
                        <div class="mod-section">
                            <h6 style="color: var(--purple); margin-bottom: 1rem; font-weight: 600;">
                                <i class="fas fa-box me-2"></i><?php echo htmlspecialchars($mod['mod_name']); ?>
                            </h6>
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <span class="badge bg-primary">Total: <?php echo $mod['total_keys']; ?></span>
                                </div>
                                <div class="col-md-2">
                                    <span class="badge bg-success">Available: <?php echo $mod['available_keys']; ?></span>
                                </div>
                                <div class="col-md-2">
                                    <span class="badge bg-danger">Sold: <?php echo $mod['sold_keys']; ?></span>
                                </div>
                                <div class="col-md-6">
                                    <span class="price-range">
                                        Price Range: <?php echo formatCurrency($mod['min_price']); ?> - <?php echo formatCurrency($mod['max_price']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Available keys for this mod -->
                            <?php
                            $modKeys = array_filter($availableKeys, function($key) use ($mod) {
                                return $key['mod_id'] == $mod['mod_id'];
                            });
                            ?>
                            
                            <?php if (!empty($modKeys)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-clock me-2"></i>Duration</th>
                                            <th><i class="fas fa-rupee-sign me-2"></i>Price</th>
                                            <th><i class="fas fa-key me-2"></i>Available Keys</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $groupedKeys = [];
                                        foreach ($modKeys as $key) {
                                            $durationKey = $key['duration'] . ' ' . ucfirst($key['duration_type']);
                                            if (!isset($groupedKeys[$durationKey])) {
                                                $groupedKeys[$durationKey] = [
                                                    'duration' => $durationKey,
                                                    'price' => $key['price'],
                                                    'count' => 0
                                                ];
                                            }
                                            $groupedKeys[$durationKey]['count']++;
                                        }
                                        ?>
                                        <?php foreach ($groupedKeys as $group): ?>
                                        <tr>
                                            <td><?php echo $group['duration']; ?></td>
                                            <td><?php echo formatCurrency($group['price']); ?></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo $group['count']; ?> Available
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
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
        
        function toggleTheme() {
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
        });
        
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
        
        // Progress Bar for Loading
        function showLoadingProgress() {
            const progressBar = document.createElement('div');
            progressBar.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 3px;
                background: linear-gradient(90deg, var(--purple), var(--purple-light));
                z-index: 10000;
                animation: loadingProgress 2s ease-in-out;
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes loadingProgress {
                    0% { transform: translateX(-100%); }
                    50% { transform: translateX(0%); }
                    100% { transform: translateX(100%); }
                }
            `;
            
            document.head.appendChild(style);
            document.body.appendChild(progressBar);
            
            setTimeout(() => {
                progressBar.remove();
                style.remove();
            }, 2000);
        }
        
        // Copy Key Functionality (if needed in future)
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Key copied to clipboard!', 'success');
            }, function(err) {
                showToast('Failed to copy key', 'error');
            });
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
        
        // Smooth Scroll for Better UX
        function smoothScrollTo(element) {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Enhanced Table Interactions
        function enhanceTableInteractions() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    row.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.01)';
                    });
                    
                    row.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
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
            showLoadingProgress();
            
            setTimeout(() => {
                animateStats();
                enhanceTableInteractions();
            }, 500);
            
            // Add resize event listener
            window.addEventListener('resize', handleResize);
            
            // Welcome message
            setTimeout(() => {
                showToast('Welcome to Multi Panel! Available Keys Overview', 'info');
            }, 1000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + D for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                toggleTheme();
            }
            
            // Escape to close mobile sidebar
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('show')) {
                    toggleSidebar();
                }
            }
        });
        
        // Add loading state management
        function setLoadingState(element, loading) {
            if (loading) {
                element.style.opacity = '0.6';
                element.style.pointerEvents = 'none';
            } else {
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            }
        }
        
        // Enhanced Error Handling Display
        <?php if (isset($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($error); ?>', 'error');
        });
        <?php endif; ?>
    </script>
</body>
</html>