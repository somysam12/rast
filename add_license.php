<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM mods WHERE status = 'active' ORDER BY name");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_POST) {
    try {
        $modId = $_POST['mod_id'];
        $keyType = $_POST['key_type'];
        $duration = (int)$_POST['duration'];
        $durationType = $_POST['duration_type'];
        $price = (float)$_POST['price'];
    
        if ($keyType === 'single') {
            $licenseKey = trim($_POST['license_key']);
            
            if (empty($modId) || empty($licenseKey) || $duration <= 0 || $price <= 0) {
                $error = 'Please fill in all required fields';
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM license_keys WHERE license_key = ?");
                $stmt->execute([$licenseKey]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'License key already exists';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO license_keys (mod_id, license_key, duration, duration_type, price) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$modId, $licenseKey, $duration, $durationType, $price])) {
                        $success = 'License key added successfully!';
                    } else {
                        $error = 'Failed to add license key';
                    }
                }
            }
        } else {
            $licenseKeys = trim($_POST['bulk_keys']);
            
            if (empty($modId) || empty($licenseKeys) || $duration <= 0 || $price <= 0) {
                $error = 'Please fill in all required fields';
            } else {
                $keys = array_filter(array_map('trim', explode("\n", $licenseKeys)));
                $addedCount = 0;
                $duplicateCount = 0;
                
                $pdo->beginTransaction();
                
                foreach ($keys as $key) {
                    if (empty($key)) continue;
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM license_keys WHERE license_key = ?");
                    $stmt->execute([$key]);
                    
                    if ($stmt->fetchColumn() > 0) {
                        $duplicateCount++;
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO license_keys (mod_id, license_key, duration, duration_type, price) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt->execute([$modId, $key, $duration, $durationType, $price])) {
                        $addedCount++;
                    }
                }
                
                $pdo->commit();
                $success = "Added $addedCount license keys successfully!";
                if ($duplicateCount > 0) $success .= " $duplicateCount duplicate keys were skipped.";
            }
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add License Key - SilentMultiPanel</title>
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

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 2px solid; border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1; border-radius: 24px; padding: 30px; box-shadow: 0 0 40px rgba(0, 0, 0, 0.2); }

        .header-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 2.5rem; border-radius: 24px; margin-bottom: 2.5rem; }

        .form-control, .form-select { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); color: white; border-radius: 12px; padding: 12px; }

        .form-control:focus, .form-select:focus { background: rgba(255, 255, 255, 0.1); border-color: var(--primary); color: white; box-shadow: none; }

        .nav-tabs { border: none; margin-bottom: 2rem; }

        .nav-tabs .nav-link { color: var(--text-dim); border: none; font-weight: 700; padding: 12px 24px; border-radius: 12px; margin-right: 10px; }

        .nav-tabs .nav-link.active { background: var(--primary); color: white; }

        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 12px; padding: 12px 24px; font-weight: 700; color: white; transition: all 0.3s; width: 100%; }

        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4); }

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
            <a class="nav-link active" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #fca5a5;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>
    <div class="main-content">
        <div class="header-card">
            <h2 class="text-white"><i class="fas fa-key me-3"></i>Add License Key</h2>
            <p class="text-white opacity-75 mb-0">Generate single or bulk license keys</p>
        </div>
        <?php if ($success): ?><div class="alert alert-success bg-success text-white border-0 mb-4"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 mb-4"><?php echo $error; ?></div><?php endif; ?>
        <div class="glass-card">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#single">Single Key</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bulk">Bulk Keys</button></li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="single">
                    <form method="POST">
                        <input type="hidden" name="key_type" value="single">
                        <div class="row g-4">
                            <div class="col-md-6"><label class="form-label">Select Mod</label><select name="mod_id" class="form-select" required><option value="">Choose Mod...</option><?php foreach ($mods as $mod): ?><option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label">License Key</label><input type="text" name="license_key" class="form-control" placeholder="Enter Key"></div>
                            <div class="col-md-4"><label class="form-label">Duration</label><input type="number" name="duration" class="form-control" value="1" min="1"></div>
                            <div class="col-md-4"><label class="form-label">Type</label><select name="duration_type" class="form-select"><option value="days">Days</option><option value="hours">Hours</option></select></div>
                            <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" value="0.00"></div>
                            <div class="col-12"><button type="submit" class="btn-primary-custom">Add Single Key</button></div>
                        </div>
                    </form>
                </div>
                <div class="tab-pane fade" id="bulk">
                    <form method="POST">
                        <input type="hidden" name="key_type" value="bulk">
                        <div class="row g-4">
                            <div class="col-md-12"><label class="form-label">Select Mod</label><select name="mod_id" class="form-select" required><option value="">Choose Mod...</option><?php foreach ($mods as $mod): ?><option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option><?php endforeach; ?></select></div>
                            <div class="col-12"><label class="form-label">Bulk Keys (one per line)</label><textarea name="bulk_keys" class="form-control" rows="8" placeholder="Key1\nKey2..."></textarea></div>
                            <div class="col-md-4"><label class="form-label">Duration</label><input type="number" name="duration" class="form-control" value="1" min="1"></div>
                            <div class="col-md-4"><label class="form-label">Type</label><select name="duration_type" class="form-select"><option value="days">Days</option><option value="hours">Hours</option></select></div>
                            <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" value="0.00"></div>
                            <div class="col-12"><button type="submit" class="btn-primary-custom">Add Bulk Keys</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('hamburgerBtn').addEventListener('click', () => document.getElementById('sidebar').classList.toggle('active'));
    </script>
</body>
</html>