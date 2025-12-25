<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get all mods with APK information
$stmt = $pdo->query("SELECT m.*, ma.file_name, ma.uploaded_at as apk_uploaded_at 
                    FROM mods m 
                    LEFT JOIN mod_apks ma ON m.id = ma.mod_id 
                    ORDER BY m.created_at DESC");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mod APK List - Multi Panel</title>
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
        
        .table-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
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
        
        .btn-primary {
            background-color: var(--purple);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: background-color 0.2s ease;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--purple-dark);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
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
            
            .page-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .table-card {
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
            
            .table-card {
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
                    <a class="nav-link active" href="mod_list.php">
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
                            <h2 class="mb-2"><i class="fas fa-list me-2" style="color: var(--purple);"></i>All Mod APKs</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Manage and view all mod APK files</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <a href="add_mod.php" class="btn btn-primary me-3">
                                <i class="fas fa-plus me-2"></i>Add New Mod
                            </a>
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
                
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-table me-2" style="color: var(--purple);"></i>Mod APK Overview</h5>
                        <span class="badge bg-secondary"><?php echo count($mods); ?> Total Mods</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-tag me-2"></i>Name</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Description</th>
                                    <th><i class="fas fa-file me-2"></i>APK File</th>
                                    <th><i class="fas fa-calendar me-2"></i>Upload Date</th>
                                    <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                                    <th><i class="fas fa-cog me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mods)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5" style="color: var(--text-secondary);">
                                        <i class="fas fa-box fa-3x mb-3"></i><br>
                                        <h6>No mods found</h6>
                                        <p class="mb-3">Get started by adding your first mod</p>
                                        <a href="add_mod.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add First Mod
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($mods as $mod): ?>
                                <tr>
                                    <td><strong>#<?php echo $mod['id']; ?></strong></td>
                                    <td><strong><?php echo htmlspecialchars($mod['name']); ?></strong></td>
                                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($mod['description'] ?: 'No description'); ?></td>
                                    <td>
                                        <?php if ($mod['file_name']): ?>
                                            <span class="badge bg-success">Uploaded</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Not uploaded</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-secondary);"><?php echo $mod['apk_uploaded_at'] ? formatDate($mod['apk_uploaded_at']) : 'Not uploaded'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $mod['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($mod['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="upload_mod.php?mod_id=<?php echo $mod['id']; ?>" 
                                           class="btn btn-sm btn-primary me-1" title="Upload APK">
                                            <i class="fas fa-upload"></i>
                                        </a>
                                        <a href="manage_mods.php" 
                                           class="btn btn-sm btn-outline-secondary" title="Manage">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
    
    <script>
        // Theme functionality
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
            const themeIcon = document.getElementById('theme-icon');
            themeIcon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }
        
        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', initTheme);
        
        // Mobile menu functionality
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
        
        // Close sidebar when clicking on nav links on mobile
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
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
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
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
        });
        
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
        
        // Add loading state for action buttons
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.href) {
                        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
                    }
                });
            });
        });
        
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
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>