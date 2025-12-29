<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Handle delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_users.php");
    exit();
}

// Get all users
$stmt = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    COALESCE(SUM(balance), 0) as total_balance,
    COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance
    FROM users WHERE role != 'admin'");
$userStats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            padding: 2rem 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar.active { transform: translateX(0); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 280px; padding: 2.5rem; transition: margin-left 0.3s ease; }

        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 1px solid var(--border-light); border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); margin-bottom: 2rem; }

        .header-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 2rem; border-radius: 24px; margin-bottom: 2rem; }

        .stat-card { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); border-radius: 18px; padding: 20px; text-align: center; }
        .stat-card h3 { color: var(--primary); font-weight: 800; margin-bottom: 5px; }
        .stat-card p { color: var(--text-dim); font-size: 0.9rem; margin-bottom: 0; }

        .table { color: var(--text-main); border-color: var(--border-light); }
        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border: none; padding: 15px; }
        .table tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid var(--border-light); }

        .btn-action { padding: 8px 12px; border-radius: 10px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 250px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 5rem 1.5rem 2rem; }
            .hamburger { display: block; }
        }
    </style>
</head>
<body>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link active" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <h2 class="text-white mb-1">User Management</h2>
            <p class="text-white opacity-75 mb-0">Manage all registered accounts and their balances.</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?php echo (int)$userStats['total_users']; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3>₹<?php echo number_format($userStats['total_balance'], 2); ?></h3>
                    <p>Total Balance</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h3><?php echo (int)$userStats['users_with_balance']; ?></h3>
                    <p>Active Wallets</p>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Balance</th>
                            <th>Role</th>
                            <th>Join Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="text-success fw-bold">₹<?php echo number_format($user['balance'], 2); ?></span></td>
                            <td><span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm btn-action"><i class="fas fa-edit"></i>Edit</a>
                                    <button onclick="confirmDelete(<?php echo $user['id']; ?>)" class="btn btn-danger btn-sm btn-action"><i class="fas fa-trash"></i>Delete</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); sidebar.classList.toggle('active'); });
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== hamburgerBtn) {
                sidebar.classList.remove('active');
            }
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete User?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            })
        }
    </script>
</body>
</html>