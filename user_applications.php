<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireUser();

$pdo = getDBConnection();
$user = getUserData();

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount){
        return '₹' . number_format((float)$amount, 2, '.', ',');
    }
}

if (!function_exists('formatDate')) {
    function formatDate($dt){
        if(!$dt){ return '-'; }
        $date = new DateTime($dt, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $date->format('d M Y, h:i A');
    }
}

// Get only mods that have an APK uploaded
$stmt = $pdo->prepare("SELECT m.*, ma.file_name, ma.file_path, ma.file_size, ma.uploaded_at 
                      FROM mods m 
                      INNER JOIN mod_apks ma ON m.id = ma.mod_id 
                      WHERE m.status = 'active'
                      ORDER BY m.name ASC");
$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Applications - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <style>
        body { padding-top: 60px; }
        .sidebar { width: 260px; position: fixed; top: 60px; bottom: 0; left: 0; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: 260px; padding: 2rem; transition: margin-left 0.3s ease; }
        .header { height: 60px; position: fixed; top: 0; left: 0; right: 0; z-index: 1001; background: rgba(5,7,10,0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .cyber-app-card {
            background: rgba(10, 15, 25, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .cyber-app-card:hover {
            transform: translateY(-5px);
            border-color: rgba(139, 92, 246, 0.3);
            background: rgba(10, 15, 25, 0.9);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .app-icon-wrapper {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #8b5cf6, #06b6d4);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }

        .download-btn {
            width: 100%;
            margin-top: 1rem;
            padding: 0.8rem;
            font-size: 0.9rem;
            border-radius: 12px;
        }

        .file-info {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: rgba(148, 163, 184, 0.6);
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn text-white p-0 d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 text-neon fw-bold">SilentMultiPanel</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div class="user-avatar-header" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link active" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Available Applications</h2>
            <p class="text-secondary mb-0">Download the latest mod APKs for your purchased keys.</p>
        </div>

        <?php if (empty($applications)): ?>
            <div class="cyber-card text-center py-5">
                <i class="fas fa-cloud-download-alt text-secondary mb-3" style="font-size: 3rem; opacity: 0.2;"></i>
                <h5 class="text-secondary">No applications are currently available for download.</h5>
                <p class="small text-secondary">Check back later for new uploads.</p>
            </div>
        <?php else: ?>
            <div class="app-grid">
                <?php foreach ($applications as $app): ?>
                    <div class="cyber-app-card">
                        <div class="app-icon-wrapper">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h5 class="text-white mb-2"><?php echo htmlspecialchars($app['name']); ?></h5>
                        <p class="text-secondary small mb-3"><?php echo htmlspecialchars($app['description'] ?: 'No description available'); ?></p>
                        
                        <div class="file-info">
                            <span><i class="fas fa-hdd me-1"></i> <?php echo round($app['file_size'] / (1024 * 1024), 2); ?> MB</span>
                            <span><i class="fas fa-clock me-1"></i> <?php echo date('d M Y', strtotime($app['uploaded_at'])); ?></span>
                        </div>

                        <a href="<?php echo htmlspecialchars($app['file_path']); ?>" class="cyber-btn download-btn" download>
                            <i class="fas fa-download"></i> Download APK
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
    </script>
</body>
</html>