<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get stats directly
$stats = [
    'total_mods' => 0,
    'total_keys' => 0,
    'available_keys' => 0,
    'sold_keys' => 0,
    'total_users' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $stats['total_mods'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys");
    $stats['total_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'available'");
    $stats['available_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'sold'");
    $stats['sold_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $stats = [
        'total_mods' => 0,
        'total_keys' => 0,
        'available_keys' => 0,
        'sold_keys' => 0,
        'total_users' => 0
    ];
}

// Get recent data
$recentMods = [];
$recentUsers = [];

try {
    $stmt = $pdo->query("SELECT * FROM mods ORDER BY created_at DESC LIMIT 5");
    $recentMods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SilentMultiPanel</title>
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

        .sidebar.hidden {
            transform: translateX(-100%);
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

        .sidebar-brand .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .sidebar-brand p {
            color: var(--text-dim);
            font-size: 0.8rem;
            margin: 0;
            font-weight: 500;
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

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.5);
            border-color: rgba(6, 182, 212, 0.7);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            justify-content: center;
            flex-wrap: wrap;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 1002;
        }

        .user-avatar:hover {
            transform: scale(1.1);
        }

        .user-dropdown {
            position: fixed;
            top: 60px;
            right: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 16px;
            min-width: 200px;
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.3);
            z-index: 1003;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s ease;
            padding: 0.5rem 0;
        }

        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-dim);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .user-dropdown a:hover {
            background: rgba(139, 92, 246, 0.2);
            color: var(--secondary);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
            margin-bottom: 1.5rem;
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .glass-card > * {
            position: relative;
            z-index: 1;
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
            }
        }

        .welcome-banner {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.25), rgba(6, 182, 212, 0.25), rgba(236, 72, 153, 0.15));
            border: 2px solid;
            border-image: linear-gradient(135deg, #8b5cf6, #06b6d4, #ec4899) 1;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            margin-bottom: 2.5rem;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.2) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .welcome-banner h2 {
            color: var(--text-main);
            font-weight: 800;
            margin-bottom: 0.75rem;
            font-size: 1.9rem;
            letter-spacing: -0.8px;
            position: relative;
            z-index: 2;
            background: linear-gradient(135deg, #06b6d4, #8b5cf6, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-banner p {
            color: var(--text-dim);
            font-size: 1rem;
            margin: 0;
            line-height: 1.6;
            position: relative;
            z-index: 2;
            font-weight: 500;
        }

        .welcome-banner p strong {
            color: #ec4899;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(30, 27, 75, 0.6));
            backdrop-filter: blur(30px);
            border: 2px solid rgba(139, 92, 246, 0.2);
            border-radius: 24px;
            padding: 1.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), transparent, rgba(6, 182, 212, 0.1));
            pointer-events: none;
        }

        .stat-card > * {
            position: relative;
            z-index: 1;
        }

        .stat-card:hover {
            border-color: rgba(6, 182, 212, 0.5);
            box-shadow: 0 15px 50px rgba(6, 182, 212, 0.2);
            transform: translateY(-8px);
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 27, 75, 0.8));
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 12px 30px rgba(6, 182, 212, 0.5);
        }

        .stat-icon.icon-mods {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3) !important;
        }

        .stat-card:hover .stat-icon.icon-mods {
            box-shadow: 0 12px 30px rgba(139, 92, 246, 0.5) !important;
        }

        .stat-icon.icon-keys {
            background: linear-gradient(135deg, #ec4899, #db2777) !important;
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.3) !important;
        }

        .stat-card:hover .stat-icon.icon-keys {
            box-shadow: 0 12px 30px rgba(236, 72, 153, 0.5) !important;
        }

        .stat-icon.icon-users {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3) !important;
        }

        .stat-card:hover .stat-icon.icon-users {
            box-shadow: 0 12px 30px rgba(245, 158, 11, 0.5) !important;
        }

        .stat-icon.icon-sold {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3) !important;
        }

        .stat-card:hover .stat-icon.icon-sold {
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.5) !important;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-top: 1rem;
            display: block;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card h3 {
            color: var(--text-main);
            font-weight: 800;
            font-size: 2.5rem;
            margin: 0.5rem 0 0;
            letter-spacing: -1px;
        }

        .stat-card h6 {
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0;
            text-transform: capitalize;
            letter-spacing: 0px;
        }

        .table-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .table-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .table-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05), transparent);
            pointer-events: none;
        }

        .table-card > * {
            position: relative;
            z-index: 1;
        }

        .table-card h5 {
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table {
            color: var(--text-main);
            margin-bottom: 0;
        }

        .table thead {
            border-bottom: 1px solid var(--border-light);
        }

        .table thead th {
            color: var(--secondary);
            font-weight: 700;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-main);
        }

        .table tbody tr:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .user-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.1));
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            border-color: var(--secondary);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.2);
        }

        .user-avatar-lg {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0 auto 1rem;
        }

        .user-card h6 {
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-card p {
            color: var(--text-dim);
            font-size: 0.85rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                padding: 1rem 0;
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
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }

            .welcome-banner {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .welcome-banner h2 {
                font-size: 1.4rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .table-section {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }

            .stat-card h6 {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .welcome-banner {
                padding: 1rem;
                margin-bottom: 1rem;
                border-radius: 16px;
            }

            .welcome-banner h2 {
                font-size: 1.2rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .stat-card h3 {
                font-size: 1.3rem;
            }

            .users-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Mobile Overlay -->
    <div id="overlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fas fa-bolt"></i></div>
            <h4>SilentMultiPanel</h4>
            <p>Admin Control Panel</p>
        </div>
        <nav class="nav">
            <a class="nav-link active" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a class="nav-link" href="stock_alerts.php"><i class="fas fa-bell"></i><span>Stock Alerts</span></a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i><span>Add Mod</span></a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i><span>Manage Mods</span></a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i><span>Upload APK</span></a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i><span>Mod List</span></a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i><span>Add License</span></a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-key"></i><span>License List</span></a>
            <a class="nav-link" href="available_keys.php"><i class="fas fa-key"></i><span>Available Keys</span></a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i><span>Manage Users</span></a>
            <a class="nav-link" href="edit_user.php"><i class="fas fa-wallet"></i><span>Add Balance</span></a>
            <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i><span>Transactions</span></a>
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i><span>Referral Codes</span></a>
            <a class="nav-link" href="admin_block_reset_requests.php"><i class="fas fa-shield-alt"></i><span>Block & Reset</span></a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>
            <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Bar (Mobile) -->
        <div class="top-bar">
            <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="user-info">
                <span style="color: var(--secondary); font-weight: 700;">Welcome back, <span style="color: var(--primary);"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!</span>
                <div class="user-avatar" onclick="toggleUserDropdown()">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <a href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </div>
            </div>
        </div>


        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-mods"><i class="fas fa-gamepad"></i></div>
                <h6>Total Mods</h6>
                <h3><?php echo $stats['total_mods']; ?></h3>
                <span class="stat-label">⚡ Active</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-keys"><i class="fas fa-shield-alt"></i></div>
                <h6>License Keys</h6>
                <h3><?php echo $stats['total_keys']; ?></h3>
                <span class="stat-label">🔐 Generated</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-users"><i class="fas fa-crown"></i></div>
                <h6>Total Users</h6>
                <h3><?php echo $stats['total_users']; ?></h3>
                <span class="stat-label">👥 Community</span>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-sold"><i class="fas fa-rocket"></i></div>
                <h6>Sold Licenses</h6>
                <h3><?php echo $stats['sold_keys']; ?></h3>
                <span class="stat-label">💰 Revenue</span>
            </div>
        </div>

        <!-- All Users Section -->
        <div class="glass-card">
            <h5><i class="fas fa-users"></i>All Users</h5>
            <div class="users-grid">
<?php
try {
    $stmt = $pdo->query("SELECT username, role FROM users WHERE role = 'user' ORDER BY username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo '<div style="grid-column: 1/-1; text-align: center; color: var(--text-dim); padding: 2rem 0;">No users registered yet</div>';
    } else {
        foreach ($users as $user):
            $initials = strtoupper(substr($user['username'], 0, 2));
?>
                <div class="user-card">
                    <div class="user-avatar-lg"><?php echo $initials; ?></div>
                    <h6><?php echo htmlspecialchars($user['username']); ?></h6>
                    <p>User Account</p>
                </div>
<?php
        endforeach;
    }
} catch (Exception $e) {
    echo '<div style="color: var(--accent); text-align: center; padding: 2rem 0;">Unable to load users</div>';
}
?>
            </div>
        </div>

        <!-- Recent Data Tables -->
        <div class="table-section">
            <div class="table-card">
                <h5><i class="fas fa-box"></i>Recent Mods</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
<?php
if (empty($recentMods)):
    echo '<tr><td colspan="2" style="text-align: center; padding: 2rem 0;">No mods uploaded yet</td></tr>';
else:
    foreach ($recentMods as $mod):
?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; color: white;">
                                            <i class="fas fa-mobile-alt"></i>
                                        </div>
                                        <span style="color: var(--secondary); font-weight: 600;"><?php echo htmlspecialchars($mod['name']); ?></span>
                                    </div>
                                </td>
                                <td style="color: var(--secondary); font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($mod['created_at'])); ?></td>
                            </tr>
<?php
    endforeach;
endif;
?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-card">
                <h5><i class="fas fa-users"></i>Recent Users</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Join Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
<?php
if (empty($recentUsers)):
    echo '<tr><td colspan="2" style="text-align: center; padding: 2rem 0;">No users registered yet</td></tr>';
else:
    foreach ($recentUsers as $user):
?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                        </div>
                                        <span style="color: var(--secondary); font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </td>
                                <td style="color: var(--secondary); font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                            </tr>
<?php
    endforeach;
endif;
?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('active');
        }

        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const avatar = e.target.closest('.user-avatar');
            if (!avatar && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });

        // Close sidebar when clicking overlay
        document.getElementById('overlay').addEventListener('click', toggleSidebar);
    </script>
</body>
</html>