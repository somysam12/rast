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
    // Get mod count
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $stats['total_mods'] = $stmt->fetchColumn();
    
    // Get key counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys");
    $stats['total_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'available'");
    $stats['available_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'sold'");
    $stats['sold_keys'] = $stmt->fetchColumn();
    
    // Get user count
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
    // Ignore errors for recent data
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
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: #8b5cf6;"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="admin_dashboard.php">
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
                    <a class="nav-link" href="referral_codes.php">
                        <i class="fas fa-tag"></i>Referral Code
                    </a>
                    <a class="nav-link" href="admin_block_reset_requests.php">
                        <i class="fas fa-shield-alt"></i>Block & Reset Requests
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content" id="mainContent">
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.5rem;"><i class="fas fa-crown me-2" style="color: #8b5cf6; -webkit-text-fill-color: #8b5cf6;"></i>SilentMultiPanel</h2>
                            <p class="text-muted mb-0">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
                        </div>
                        <div class="d-none d-md-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                <small class="text-muted">Administrator</small>
                            </div>
                            <div class="user-avatar" style="width: 48px; height: 48px; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4 fade-in">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card total-mods">
                            <div class="text-center">
                                <div class="stats-icon total-mods mx-auto" style="background: #8b5cf6; color: white; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                                    <i class="fas fa-box"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Mods</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $stats['total_mods']; ?></h3>
                                <small style="color: #8b5cf6;">
                                    <i class="fas fa-chart-line me-1"></i>Active mods
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card license-keys">
                            <div class="text-center">
                                <div class="stats-icon license-keys mx-auto" style="background: #059669; color: white; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                                    <i class="fas fa-key"></i>
                                </div>
                                <h6 class="text-muted mb-2">License Keys</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $stats['total_keys']; ?></h3>
                                <small style="color: #059669;">
                                    <i class="fas fa-key me-1"></i>Total generated
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card total-users">
                            <div class="text-center">
                                <div class="stats-icon total-users mx-auto" style="background: #0ea5e9; color: white; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Users</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $stats['total_users']; ?></h3>
                                <small style="color: #0ea5e9;">
                                    <i class="fas fa-user-plus me-1"></i>Registered users
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stats-card sold-licenses">
                            <div class="text-center">
                                <div class="stats-icon sold-licenses mx-auto" style="background: #f59e0b; color: white; width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h6 class="text-muted mb-2">Sold Licenses</h6>
                                <h3 class="mb-0 fw-bold"><?php echo $stats['sold_keys']; ?></h3>
                                <small style="color: #f59e0b;">
                                    <i class="fas fa-check-circle me-1"></i>Keys sold
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row fade-in">
                    <div class="col-md-6 mb-4">
                        <div class="table-card">
                            <h5><i class="fas fa-box me-2"></i>Recent Mods</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentMods as $mod): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mod['name']); ?></td>
                                            <td><span class="badge bg-<?php echo $mod['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($mod['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="table-card">
                            <h5><i class="fas fa-users me-2"></i>Recent Users</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td>$<?php echo number_format($user['balance'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
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
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }

        // Close sidebar when clicking overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('overlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    const sidebar = document.getElementById('sidebar');
                    sidebar.classList.remove('show');
                    this.classList.remove('show');
                });
            }
            
            // Fade in animations
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('visible');
                }, index * 100);
            });
        });
    </script>
</body>
</html>