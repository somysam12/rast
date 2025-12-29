<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Handle single delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
}

// Get filter parameters
$mod_id = $_GET['mod_id'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$sql = "SELECT lk.*, m.name as mod_name FROM license_keys lk LEFT JOIN mods m ON lk.mod_id = m.id WHERE 1=1";
$params = [];
if ($mod_id) { $sql .= " AND lk.mod_id = ?"; $params[] = $mod_id; }
if ($status) { $sql .= " AND lk.status = ?"; $params[] = $status; }
$sql .= " ORDER BY lk.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licenseKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mods = $pdo->query("SELECT id, name FROM mods ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Key List - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.7);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border-light: rgba(148, 163, 184, 0.1);
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

        .sidebar h4 { font-weight: 800; background: linear-gradient(135deg, #f8fafc, var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 2rem; padding: 0 20px; }

        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }

        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }

        .sidebar .nav-link.active { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }

        .main-content { margin-left: 280px; padding: 2.5rem; transition: margin-left 0.3s ease; }

        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 2px solid; border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1; border-radius: 24px; padding: 30px; box-shadow: 0 0 40px rgba(0, 0, 0, 0.2); margin-bottom: 2rem; }

        .header-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 2.5rem; border-radius: 24px; margin-bottom: 2.5rem; }

        .table { color: var(--text-main); border-color: var(--border-light); }

        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom: 2px solid var(--border-light); padding: 15px; }

        .table tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid var(--border-light); }

        .form-select, .form-control { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); color: white; border-radius: 12px; }

        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 12px; padding: 10px 20px; font-weight: 700; color: white; text-decoration: none; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 250px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 5rem 1.5rem 1.5rem; }
            .hamburger { display: block; }
        }
    </style>
</head>
<body>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4><i class="fas fa-bolt me-2"></i>Multi Panel</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link active" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #fca5a5;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>
    <div class="main-content">
        <div class="header-card">
            <h2 class="text-white"><i class="fas fa-list me-3"></i>License Key List</h2>
            <p class="text-white opacity-75 mb-0">Manage and filter all license keys</p>
        </div>
        <div class="glass-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4"><label class="form-label">Filter by Mod</label><select name="mod_id" class="form-select"><option value="">All Mods</option><?php foreach ($mods as $mod): ?><option value="<?php echo $mod['id']; ?>" <?php echo $mod_id == $mod['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mod['name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Filter by Status</label><select name="status" class="form-select"><option value="">All Statuses</option><option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option><option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option></select></div>
                <div class="col-md-4"><button type="submit" class="btn-primary-custom w-100"><i class="fas fa-filter me-2"></i>Apply Filters</button></div>
            </form>
        </div>
        <div class="glass-card">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Mod Name</th><th>License Key</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($licenseKeys as $key): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($key['mod_name']); ?></strong></td>
                            <td><code><?php echo htmlspecialchars($key['license_key']); ?></code></td>
                            <td><?php echo $key['duration'] . ' ' . $key['duration_type']; ?></td>
                            <td>$<?php echo number_format($key['price'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $key['status'] === 'available' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($key['status']); ?></span></td>
                            <td><a href="?delete_id=<?php echo $key['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this key?')"><i class="fas fa-trash"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('hamburgerBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    </script>
</body>
</html>