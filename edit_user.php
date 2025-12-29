<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $logout_limit = isset($_POST['logout_limit']) ? (int)$_POST['logout_limit'] : 1;
    $balance_amount = isset($_POST['balance_amount']) ? (float)$_POST['balance_amount'] : 0;
    $balance_type = $_POST['balance_type'] ?? 'add';

    try {
        $pdo->beginTransaction();
        
        // Update basic info
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $email, $role, $user_id]);

        // Update password if provided
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $user_id]);
        }

        // Check if user exists in force_logouts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM force_logouts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE force_logouts SET logged_out_by = ?, logout_limit = ? WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id'], $logout_limit, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by, logout_limit) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $_SESSION['user_id'], $logout_limit]);
        }

        // Update balance
        if ($balance_amount > 0) {
            $op = ($balance_type === 'add') ? '+' : '-';
            // Securely apply balance update using parameters for the amount
            $stmt = $pdo->prepare("UPDATE users SET balance = balance $op ? WHERE id = ?");
            $stmt->execute([$balance_amount, $user_id]);
            
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, 'admin_adj', ($balance_type === 'add' ? $balance_amount : -$balance_amount), "Admin adjusted balance ($balance_type)"]);
        }

        $pdo->commit();
        $success = 'User updated successfully!';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT u.*, COALESCE(fl.logout_limit, 1) as logout_limit 
                      FROM users u 
                      LEFT JOIN force_logouts fl ON u.id = fl.user_id 
                      WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: manage_users.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Silent Panel</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            left: 0; top: 0;
            padding: 2rem 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar.active { transform: translateX(0); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .edit-wrapper { width: 100%; max-width: 600px; padding: 20px; position: relative; z-index: 1; margin-left: 280px; transition: margin-left 0.3s ease; }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.15);
        }

        .form-group { margin-bottom: 20px; position: relative; }
        .input-field { width: 100%; background: rgba(15, 23, 42, 0.5); border: 1.5px solid var(--border-light); border-radius: 14px; padding: 14px 16px 14px 48px; color: white; font-size: 14px; transition: all 0.4s; }
        .input-field:focus { outline: none; border-color: var(--primary); background: rgba(139, 92, 246, 0.05); }
        .field-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-dim); font-size: 18px; }

        .btn-submit { width: 100%; background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 14px; padding: 14px; color: white; font-weight: 700; margin-top: 20px; cursor: pointer; transition: all 0.4s; }
        .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(139, 92, 246, 0.4); }

        .hamburger { display: none; position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 250px; }
            .sidebar.active { transform: translateX(0); }
            .edit-wrapper { margin-left: 0; }
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

    <div class="edit-wrapper">
        <div class="glass-card">
            <h2 class="text-center mb-4" style="font-weight: 800;">Edit User</h2>
            <form method="POST">
                <div class="form-group">
                    <i class="fas fa-user field-icon"></i>
                    <input type="text" name="username" class="input-field" value="<?php echo htmlspecialchars($user['username']); ?>" placeholder="Username" required>
                </div>
                <div class="form-group">
                    <i class="fas fa-envelope field-icon"></i>
                    <input type="email" name="email" class="input-field" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <i class="fas fa-lock field-icon"></i>
                    <input type="password" name="password" class="input-field" placeholder="New Password (Leave blank to keep current)">
                </div>
                <div class="form-group">
                    <i class="fas fa-user-shield field-icon"></i>
                    <select name="role" class="input-field" style="padding-left: 48px; -webkit-appearance: none;">
                        <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="reseller" <?php echo $user['role'] === 'reseller' ? 'selected' : ''; ?>>Reseller</option>
                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <i class="fas fa-history field-icon"></i>
                    <input type="number" name="logout_limit" class="input-field" value="<?php echo htmlspecialchars($user['logout_limit']); ?>" placeholder="Force Logout Limit (24h)" min="1">
                </div>
                
                <hr style="border-color: var(--border-light); margin: 2rem 0;">
                <h5 class="mb-3">Balance Management</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-wallet field-icon"></i>
                            <input type="number" step="0.01" name="balance_amount" class="input-field" placeholder="Amount (₹)">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <i class="fas fa-tasks field-icon"></i>
                            <select name="balance_type" class="input-field" style="padding-left: 48px; -webkit-appearance: none;">
                                <option value="add">Add</option>
                                <option value="deduct">Deduct</option>
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Update User Profile</button>
            </form>
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

        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Success', text: '<?php echo $success; ?>', background: '#111827', color: '#fff' });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $error; ?>', background: '#111827', color: '#fff' });
        <?php endif; ?>
    </script>
</body>
</html>