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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: #111827;
            --input-bg: #1f2937;
            --text-main: #ffffff;
            --text-dim: #9ca3af;
            --accent: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            background: #0f172a;
            min-height: 100vh;
            color: var(--text-main);
            overflow-x: hidden;
        }

        .sidebar {
            background: #111827;
            border-right: 1px solid #1f2937;
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

        .sidebar .nav-link:hover { color: var(--text-main); background: #1f2937; }

        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 280px; padding: 2.5rem; }

        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; }

        .header-section { margin-bottom: 2.5rem; }
        .header-section h2 { font-weight: 800; color: white; font-size: 2.2rem; }
        .header-section p { color: var(--text-dim); font-size: 1.1rem; }

        .glass-card { 
            background: var(--card-bg); 
            border: 2px solid #374151;
            border-radius: 28px; 
            padding: 40px; 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }

        .tab-btn-group {
            display: flex;
            background: #1f2937;
            padding: 8px;
            border-radius: 20px;
            margin-bottom: 3rem;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid #374151;
        }

        .tab-btn {
            padding: 14px 35px;
            border: none;
            background: transparent;
            color: var(--text-dim);
            font-weight: 800;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(139, 92, 246, 0.4);
            transform: scale(1.05);
        }

        .form-group { margin-bottom: 2rem; }
        .form-label { display: block; font-weight: 800; color: var(--primary); margin-bottom: 0.8rem; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px; }

        .form-control, .form-select { 
            background: #111827; 
            border: 2px solid #374151; 
            color: #ffffff; 
            border-radius: 16px; 
            padding: 16px 20px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus { 
            border-color: var(--primary); 
            outline: none;
            background: #1f2937;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }

        option {
            background: #111827;
            color: white;
            padding: 15px;
            font-weight: 600;
        }

        .input-group { position: relative; display: flex; align-items: stretch; width: 100%; }
        .input-group-text {
            display: flex;
            align-items: center;
            padding: 0 25px;
            font-size: 1.5rem;
            font-weight: 900;
            color: #ffffff;
            background-color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 16px 0 0 16px;
        }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: none; color: #34d399 !important; font-size: 1.4rem; font-weight: 800; }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 18px;
            padding: 20px;
            font-weight: 800;
            width: 100%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        .submit-btn:hover {
            filter: brightness(1.2);
            transform: translateY(-4px);
            box-shadow: 0 15px 30px -5px rgba(139, 92, 246, 0.5);
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 6rem 1.5rem; }
            .hamburger { display: block; }
        }

        ::placeholder { color: #4b5563; font-weight: 500; }
        textarea { resize: none; line-height: 1.6; }
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
            <a class="nav-link active" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: #374151; margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-section">
            <h2>Add License Keys</h2>
            <p>Generate secure access keys for your mods instantly</p>
        </div>

        <div class="glass-card">
            <div class="tab-btn-group">
                <button class="tab-btn active" onclick="showTab('single', this)">Single Key</button>
                <button class="tab-btn" onclick="showTab('bulk', this)">Bulk Keys</button>
            </div>

            <div id="singleTab">
                <form method="POST">
                    <input type="hidden" name="key_type" value="single">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Target Mod</label>
                            <select name="mod_id" class="form-select" required>
                                <option value="" disabled selected>Select Product</option>
                                <?php foreach ($mods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">License Key</label>
                            <input type="text" name="license_key" class="form-control" placeholder="Type access key...">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Time Value</label>
                            <input type="number" name="duration" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Time Type</label>
                            <select name="duration_type" class="form-select">
                                <option value="days">DAYS</option>
                                <option value="months">MONTHS</option>
                                <option value="hours">HOURS</option>
                                <option value="seasons">SEASONS</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Add Single Key</button>
                </form>
            </div>

            <div id="bulkTab" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="key_type" value="bulk">
                    <div class="row">
                        <div class="col-12 form-group">
                            <label class="form-label">Target Mod</label>
                            <select name="mod_id" class="form-select" required>
                                <option value="" disabled selected>Select Product</option>
                                <?php foreach ($mods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>"><?php echo htmlspecialchars($mod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label class="form-label">Batch Input (one key per line)</label>
                            <textarea name="bulk_keys" class="form-control" rows="8" placeholder="Paste your keys list here..."></textarea>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Time Value</label>
                            <input type="number" name="duration" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Time Type</label>
                            <select name="duration_type" class="form-select">
                                <option value="days">DAYS</option>
                                <option value="months">MONTHS</option>
                                <option value="hours">HOURS</option>
                                <option value="seasons">SEASONS</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Price</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" name="price" class="form-control" value="0.00">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Add Bulk Keys</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');

        hamburgerBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        function showTab(type, btn) {
            document.getElementById('singleTab').style.display = type === 'single' ? 'block' : 'none';
            document.getElementById('bulkTab').style.display = type === 'bulk' ? 'block' : 'none';
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== hamburgerBtn) {
                sidebar.classList.remove('active');
            }
        });

        <?php if ($success): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo $success; ?>',
            timer: 2500,
            showConfirmButton: false,
            background: '#111827',
            color: '#ffffff'
        });
        <?php endif; ?>

        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: '<?php echo $error; ?>',
            background: '#111827',
            color: '#ffffff'
        });
        <?php endif; ?>
    </script>
</body>
</html>