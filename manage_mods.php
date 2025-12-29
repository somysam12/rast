<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM mods WHERE id = ?");
    if ($stmt->execute([$_GET['delete']])) {
        $success = 'Mod deleted successfully!';
    } else {
        $error = 'Failed to delete mod.';
    }
}

// Handle status toggle
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $stmt = $pdo->prepare("UPDATE mods SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
    if ($stmt->execute([$_GET['toggle_status']])) {
        $success = 'Mod status updated successfully!';
    } else {
        $error = 'Failed to update mod status.';
    }
}

// Get all mods
$stmt = $pdo->query("SELECT * FROM mods ORDER BY created_at DESC");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Mods - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
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

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar h4 {
            font-weight: 800;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #f8fafc, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            padding: 0 20px;
        }

        .sidebar .nav-link {
            color: var(--text-dim);
            padding: 12px 20px;
            margin: 4px 16px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar .nav-link:hover {
            color: var(--text-main);
            background: rgba(139, 92, 246, 0.1);
        }

        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 20px rgba(139, 92, 246, 0.2);
        }

        .main-content {
            margin-left: 280px;
            padding: 2.5rem;
            position: relative;
            z-index: 1;
            transition: margin-left 0.3s ease;
        }

        .hamburger {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }

        .header-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 2.5rem;
            border-radius: 24px;
            margin-bottom: 2.5rem;
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-card h2 {
            font-weight: 800;
            letter-spacing: -0.03em;
            color: white;
        }

        .table {
            color: var(--text-main);
            border-color: var(--border-light);
        }

        .table thead th {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary);
            border-bottom: 2px solid var(--border-light);
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-light);
        }

        .table-hover tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.3s;
            border: none;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 700;
            color: white;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
            color: white;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 5rem 1.5rem 1.5rem;
            }
            .hamburger {
                display: block;
            }
        }
    </style>
</head>
<body>
    <button class="hamburger" id="hamburgerBtn">
        <i class="fas fa-bars"></i>
    </button>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar" id="sidebar">
                <h4>
                    <i class="fas fa-bolt me-2"></i>Multi Panel
                </h4>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
                    <a class="nav-link active" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
                    <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License Key List</a>
                    <a class="nav-link" href="available_keys.php"><i class="fas fa-check-circle"></i>Available Keys</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
                    <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transactions</a>
                    <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
                    <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
                    <hr style="border-color: var(--border-light); margin: 1rem 16px;">
                    <a class="nav-link" href="logout.php" style="color: #fca5a5;"><i class="fas fa-sign-out"></i>Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 main-content">
                <div class="header-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2><i class="fas fa-edit me-3"></i>Manage Mods</h2>
                            <p class="mb-0 opacity-75 text-white">View and manage all mod entries in the system</p>
                        </div>
                        <div>
                            <a href="add_mod.php" class="btn-primary-custom">
                                <i class="fas fa-plus me-2"></i>Add New Mod
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($success)): ?>
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?php echo $success; ?>',
                            background: '#1e293b',
                            color: '#fff',
                            confirmButtonColor: '#8b5cf6'
                        });
                    </script>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: '<?php echo $error; ?>',
                            background: '#1e293b',
                            color: '#fff',
                            confirmButtonColor: '#8b5cf6'
                        });
                    </script>
                <?php endif; ?>
                
                <div class="glass-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-list me-2" style="color: var(--primary);"></i>Mod List</h5>
                        <span class="badge bg-primary"><?php echo count($mods); ?> Total Mods</span>
                    </div>
                    
                    <?php if (empty($mods)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box fa-3x mb-3 opacity-25"></i>
                            <h6 class="text-dim">No mods found</h6>
                            <a href="add_mod.php" class="btn-primary-custom mt-3">
                                <i class="fas fa-plus me-2"></i>Add First Mod
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mods as $mod): ?>
                                    <tr>
                                        <td><span class="fw-bold text-primary">#<?php echo $mod['id']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($mod['name']); ?></strong></td>
                                        <td class="text-dim"><?php echo htmlspecialchars($mod['description'] ?: 'No description'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $mod['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <i class="fas fa-<?php echo $mod['status'] === 'active' ? 'check' : 'pause'; ?> me-1"></i>
                                                <?php echo ucfirst($mod['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="?toggle_status=<?php echo $mod['id']; ?>" 
                                                   class="btn-action bg-<?php echo $mod['status'] === 'active' ? 'warning' : 'success'; ?>"
                                                   title="<?php echo $mod['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $mod['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="#" class="btn-action bg-danger" 
                                                   onclick="confirmDelete(<?php echo $mod['id']; ?>)"
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
        function confirmDelete(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                background: '#1e293b',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            })
        }

        document.getElementById('hamburgerBtn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.getElementById('hamburgerBtn');
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !hamburger.contains(event.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>