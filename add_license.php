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
            --border-light: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.05);
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

        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4); }

        .glass-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(30px); 
            -webkit-backdrop-filter: blur(30px); 
            border: 1px solid var(--border-light);
            border-radius: 24px; 
            padding: 40px; 
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header-card { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            padding: 3rem; 
            border-radius: 24px; 
            margin-bottom: 3rem; 
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-label { font-weight: 600; color: var(--text-main); margin-bottom: 0.75rem; font-size: 0.95rem; }

        .form-control, .form-select { 
            background: var(--glass-bg); 
            border: 1px solid var(--border-light); 
            color: white; 
            border-radius: 14px; 
            padding: 14px 18px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus { 
            background: rgba(255, 255, 255, 0.1); 
            border-color: var(--primary); 
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15); 
        }

        .nav-tabs-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2.5rem;
        }

        .nav-tabs-custom { 
            background: var(--glass-bg);
            padding: 8px;
            border-radius: 18px;
            display: inline-flex;
            border: 1px solid var(--border-light);
            gap: 10px;
        }

        .nav-tabs-custom .nav-link { 
            color: var(--text-dim); 
            border: none; 
            font-weight: 700; 
            padding: 12px 32px; 
            border-radius: 12px; 
            transition: all 0.4s ease;
            background: transparent;
        }

        .nav-tabs-custom .nav-link.active { 
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)); 
            color: white; 
            box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3);
        }

        .btn-primary-custom { 
            background: linear-gradient(135deg, var(--primary), var(--secondary)); 
            border: none; 
            border-radius: 16px; 
            padding: 16px; 
            font-weight: 800; 
            color: white; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            width: 100%; 
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.95rem;
            margin-top: 1rem;
        }

        .btn-primary-custom:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 12px 25px rgba(139, 92, 246, 0.4); 
        }

        .input-group-text {
            background: var(--glass-bg);
            border: 1px solid var(--border-light);
            color: var(--text-main);
            border-radius: 14px 0 0 14px;
            font-weight: bold;
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 250px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 6rem 1.5rem 2rem; }
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
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #f87171;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <h2 class="text-white mb-2"><i class="fas fa-plus-circle me-3"></i>Add New Keys</h2>
            <p class="text-white opacity-75 mb-0">Quickly create single or bulk license keys for your users.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success bg-success text-white border-0 mb-4"><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger bg-danger text-white border-0 mb-4"><?php echo $error; ?></div><?php endif; ?>

        <div class="glass-card">
            <div class="nav-tabs-container">
                <div class="nav nav-tabs-custom" id="keyTabs" role="tablist">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#single" type="button">Add Single Key</button>
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bulk" type="button">Add Bulk Keys</button>
                </div>
            </div>

            <div class="tab-content" id="keyTabsContent">
                <!-- Single Key Pane -->
                <div class="tab-pane fade show active" id="single">
                    <form method="POST">
                        <input type="hidden" name="key_type" value="single">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Select Mod</label>
                                <select name="mod_id" class="form-select" required>
                                    <option value="">Choose a mod...</option>
                                    <?php foreach ($mods as $mod): ?>
                                        <option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">License Key</label>
                                <input type="text" name="license_key" class="form-control" placeholder="Enter key here">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Value</label>
                                <input type="number" name="duration" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Type</label>
                                <select name="duration_type" class="form-select">
                                    <option value="days">Days</option>
                                    <option value="months">Months</option>
                                    <option value="hours">Hours</option>
                                    <option value="seasons">Seasons</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Price (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-primary-custom">Add Single Key</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Key Pane -->
                <div class="tab-pane fade" id="bulk">
                    <form method="POST">
                        <input type="hidden" name="key_type" value="bulk">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label">Select Mod</label>
                                <select name="mod_id" class="form-select" required>
                                    <option value="">Choose a mod...</option>
                                    <?php foreach ($mods as $mod): ?>
                                        <option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Paste Keys (one per line)</label>
                                <textarea name="bulk_keys" class="form-control" rows="8" placeholder="Key 1&#10;Key 2&#10;Key 3..."></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Value</label>
                                <input type="number" name="duration" class="form-control" value="1" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time Type</label>
                                <select name="duration_type" class="form-select">
                                    <option value="days">Days</option>
                                    <option value="months">Months</option>
                                    <option value="hours">Hours</option>
                                    <option value="seasons">Seasons</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Price (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn-primary-custom">Add Bulk Keys</button>
                            </div>
                        </div>
                    </form>
                </div>
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
    </script>
</body>
</html>