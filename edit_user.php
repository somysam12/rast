<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

$user = null;
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user
try {
    if ($pdo && $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'User not found';
        }
    }
} catch (Exception $e) {
    $error = 'Failed to fetch user: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && $user) {
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $logout_limit = isset($_POST['logout_limit']) ? (int)$_POST['logout_limit'] : 0;
    
    try {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
        }
        
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $user_id]);
        
        // Update logout limit
        $stmt = $pdo->prepare("UPDATE force_logouts SET logout_limit = ? WHERE user_id = ? LIMIT 1");
        $stmt->execute([$logout_limit, $user_id]);
        
        // If no record exists, insert one
        $stmt = $pdo->prepare("SELECT id FROM force_logouts WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by, logout_limit) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $_SESSION['user_id'], $logout_limit]);
        }
        
        $success = 'User updated successfully!';
        // Refresh user data
        $stmt = $pdo->prepare("SELECT u.*, COALESCE(fl.logout_limit, 0) as logout_limit FROM users u LEFT JOIN force_logouts fl ON u.id = fl.user_id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}

// Get user statistics
$stats = [
    'purchases' => 0,
    'total_spent' => 0,
    'member_since' => ''
];

try {
    if ($pdo && $user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = ? AND type = 'purchase'");
        $stmt->execute([$user_id]);
        $trans = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['purchases'] = $trans['count'] ?? 0;
        $stats['total_spent'] = $trans['total'] ?? 0;
        $stats['member_since'] = $user['created_at'] ?? '';
    }
} catch (Exception $e) {
    // Use defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --border-light: #e2e8f0;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
        }
        
        .sidebar {
            background: var(--card-bg);
            border-right: 1px solid var(--border-light);
            width: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .form-control {
            border: 1px solid var(--border-light);
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background-color: var(--purple);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--purple-dark);
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background-color: #cbd5e1;
        }
        
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #7f1d1d;
        }
        
        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            text-align: center;
            margin-bottom: 15px;
        }
        
        .stat-box h3 {
            color: var(--purple);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-box p {
            color: #888;
            font-size: 12px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div style="display: flex; min-height: 100vh;">
        <div class="sidebar">
            <h5 style="margin-bottom: 30px;"><i class="fas fa-crown me-2"></i>SilentMultiPanel</h5>
            <nav class="nav flex-column gap-2">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
                <a class="nav-link" href="manage_users.php"><i class="fas fa-users me-2"></i>Manage Users</a>
                <a class="nav-link" href="add_balance.php"><i class="fas fa-plus-circle me-2"></i>Add Balance</a>
                <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            </nav>
        </div>

        <div class="main-content">
            <a href="manage_users.php" style="color: var(--purple); text-decoration: none; margin-bottom: 20px; display: inline-block;">
                <i class="fas fa-arrow-left me-2"></i>Back to Users
            </a>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($user): ?>
                <div class="card">
                    <h2 style="margin-bottom: 30px;">Edit User: <?php echo htmlspecialchars($user['username']); ?></h2>
                    
                    <form method="POST" style="margin-bottom: 30px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label class="form-label">New Password <span style="color: #888; font-weight: 400;">(Leave blank to keep current)</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Enter new password (minimum 8 characters)">
                                <small style="color: #888;">Minimum 8 characters</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select name="role" class="form-control">
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="reseller" <?php echo $user['role'] === 'reseller' ? 'selected' : ''; ?>>Reseller</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Force Logout Limit (24h)</label>
                            <input type="number" name="logout_limit" class="form-control" value="<?php echo htmlspecialchars($user['logout_limit'] ?? 0); ?>" min="0" placeholder="0 = Unlimited">
                            <small style="color: #888;">0 means unlimited force logouts</small>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update User
                            </button>
                            <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <h3 style="margin-top: 30px; margin-bottom: 20px;">User Statistics</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="stat-box">
                        <h3><?php echo (int)$stats['purchases']; ?></h3>
                        <p>Purchases</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo formatCurrency($stats['total_spent']); ?></h3>
                        <p>Total Spent</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo formatDate($stats['member_since']); ?></h3>
                        <p>Member Since</p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo formatCurrency($user['balance'] ?? 0); ?></h3>
                        <p>Current Balance</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-error">User not found or failed to load.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
