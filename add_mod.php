<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    if (empty($name)) {
        $error = 'Mod name is required';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO mods (name, description) VALUES (?, ?)");
        
        if ($stmt->execute([$name, $description])) {
            $success = 'Mod added successfully!';
            $name = $description = ''; // Clear form
        } else {
            $error = 'Failed to add mod. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Mod - SilentMultiPanel</title>
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
            color: var(--text-main);
            overflow-x: hidden;
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
            border-right: 1px solid var(--border-light);
            z-index: 1000;
            overflow-y: auto;
            padding: 1.5rem 0;
            transition: transform 0.3s ease;
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
            font-size: 1.3rem;
        }

        .sidebar-brand p {
            color: var(--text-dim);
            font-size: 0.8rem;
            margin: 0;
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
            padding: 2rem;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        .mobile-header {
            display: none;
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
            outline: none;
        }

        .hamburger-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.5);
            border-color: rgba(6, 182, 212, 0.7);
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: var(--text-main);
            font-weight: 800;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-header p {
            color: var(--text-dim);
            font-size: 1rem;
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
            max-width: 600px;
            margin: 0 auto;
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

        .form-label {
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1.5px solid rgba(139, 92, 246, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: var(--text-dim);
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(6, 182, 212, 0.5);
            color: var(--text-main);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.2);
            outline: none;
        }

        .form-group {
            margin-bottom: 1.75rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 14px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .success-alert {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            border: 1.5px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #10b981;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .success-alert i {
            margin-right: 0.75rem;
        }

        .error-alert {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #ef4444;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .error-alert i {
            margin-right: 0.75rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .mobile-header {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .page-header h2 {
                font-size: 1.8rem;
            }

            .glass-card {
                padding: 1.75rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }

            .page-header h2 {
                font-size: 1.4rem;
            }

            .glass-card {
                padding: 1.5rem;
                border-radius: 16px;
            }

            .form-label {
                font-size: 0.85rem;
            }

            .form-control {
                padding: 10px 12px;
                font-size: 0.9rem;
            }

            .btn-submit {
                padding: 12px 24px;
                font-size: 0.9rem;
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-plus"></i> SilentMultiPanel</h4>
            <p>Add New Mod</p>
        </div>
        <nav class="nav">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a class="nav-link active" href="add_mod.php"><i class="fas fa-plus"></i><span>Add Mod</span></a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i><span>Manage Mods</span></a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i><span>Upload APK</span></a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i><span>Mod List</span></a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i><span>Add License</span></a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-key"></i><span>License List</span></a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i><span>Manage Users</span></a>
            <a class="nav-link" href="stock_alerts.php"><i class="fas fa-bell"></i><span>Stock Alerts</span></a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>
            <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Mobile Header -->
        <div class="mobile-header">
            <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fas fa-plus"></i> Add New Mod</h2>
            <p>Create a new mod entry in the SilentMultiPanel system</p>
        </div>

        <!-- Form Card -->
        <div class="glass-card">
            <?php if ($success): ?>
                <div class="success-alert">
                    <div>
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                    <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; color: #10b981; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-alert">
                    <div>
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; color: #ef4444; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="name" class="form-label">
                        <i class="fas fa-gamepad"></i> Mod Name
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="name" 
                        name="name" 
                        value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                        placeholder="Enter mod name" 
                        required>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">
                        <i class="fas fa-align-left"></i> Description
                    </label>
                    <textarea 
                        class="form-control" 
                        id="description" 
                        name="description" 
                        rows="5" 
                        placeholder="Enter mod description (optional)"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>

                <div style="text-align: center;">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus"></i> Add Mod
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking a nav link
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                    }
                });
            });

            // Close sidebar when clicking outside
            if (!e.target.closest('.sidebar') && !e.target.closest('.hamburger-btn')) {
                sidebar.classList.remove('show');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.success-alert, .error-alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>