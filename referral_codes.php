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

$success = '';
$error = '';

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Handle generate referral code
if ($_POST && isset($_POST['generate_code']) && $pdo) {
    try {
        $expiryDays = (int)$_POST['expiry_days'];
        $bonusAmount = (float)($_POST['bonus_amount'] ?? 50.00);
        $usageLimit = (int)($_POST['usage_limit'] ?? 1);
        
        if ($expiryDays <= 0) {
            $error = 'Please enter a valid expiry period';
        } elseif ($bonusAmount < 0) {
            $error = 'Bonus amount cannot be negative';
        } elseif ($usageLimit <= 0) {
            $error = 'Usage limit must be at least 1';
        } else {
            $code = generateReferralCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));
            
            $stmt = $pdo->prepare("INSERT INTO referral_codes (code, created_by, expires_at, bonus_amount, usage_limit, usage_count) VALUES (?, ?, ?, ?, ?, 0)");
            if ($stmt->execute([$code, $_SESSION['user_id'], $expiresAt, $bonusAmount, $usageLimit])) {
                $success = "Referral code generated successfully: $code";
            } else {
                $error = 'Failed to generate referral code';
            }
        }
    } catch (Exception $e) {
        $error = 'Error generating referral code: ' . $e->getMessage();
    }
}

// Handle deactivate code
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate']) && $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE id = ?");
        if ($stmt->execute([$_GET['deactivate']])) {
            $success = 'Referral code deactivated successfully!';
        } else {
            $error = 'Failed to deactivate referral code';
        }
    } catch (Exception $e) {
        $error = 'Error deactivating referral code: ' . $e->getMessage();
    }
}

// Handle delete code
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id = ?");
        if ($stmt->execute([$_GET['delete']])) {
            $success = 'Referral code deleted successfully!';
        } else {
            $error = 'Failed to delete referral code';
        }
    } catch (Exception $e) {
        $error = 'Error deleting referral code: ' . $e->getMessage();
    }
}

// Get all referral codes
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT rc.*, u.username as created_by_name 
                            FROM referral_codes rc 
                            LEFT JOIN users u ON rc.created_by = u.id 
                            ORDER BY rc.created_at DESC");
        $referralCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $referralCodes = [];
    }
} catch (Exception $e) {
    $referralCodes = [];
    $error = 'Failed to fetch referral codes: ' . $e->getMessage();
}

// Get referral code statistics
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_codes,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_codes,
            COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as valid_codes,
            COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_codes
            FROM referral_codes");
        $codeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $codeStats = [
            'total_codes' => 0,
            'active_codes' => 0,
            'valid_codes' => 0,
            'expired_codes' => 0
        ];
    }
} catch (Exception $e) {
    $codeStats = [
        'total_codes' => 0,
        'active_codes' => 0,
        'valid_codes' => 0,
        'expired_codes' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Referral Codes - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: #8b5cf6;"></i>Multi Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-2 d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                    <p class="small mb-0" style="opacity: 0.7;">Admin Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
                    <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod Name</a>
                    <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
                    <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload Mod APK</a>
                    <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod APK List</a>
                    <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License Key</a>
                    <a class="nav-link" href="licence_key_list.php"><i class="fas fa-key"></i>License Key List</a>
                    <a class="nav-link" href="available_keys.php"><i class="fas fa-key"></i>Available Keys</a>
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
                    <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
                    <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transaction</a>
                    <a class="nav-link active" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Code</a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a>
                </nav>
            </div>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Referral Code Management</h2>
                            <p class="text-muted mb-0">Create and manage customized referral codes</p>
                        </div>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Total Codes</div>
                            <div class="h3 mb-0"><?php echo $codeStats['total_codes']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <div class="text-secondary small fw-bold text-uppercase mb-1">Active</div>
                            <div class="h3 mb-0"><?php echo $codeStats['active_codes']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="form-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i>Generate New Referral Code</h5>
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Expiry Period</label>
                            <select class="form-control" name="expiry_days" required>
                                <option value="7">7 Days</option>
                                <option value="30">30 Days</option>
                                <option value="365">1 Year</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonus Amount</label>
                            <input type="number" step="0.01" class="form-control" name="bonus_amount" value="50.00" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="generate_code" class="btn btn-primary w-100" style="background: #8b5cf6; border: none;">Generate Code</button>
                        </div>
                    </form>
                </div>

                <div class="codes-card" style="background: white; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Bonus</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($referralCodes as $code): ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($code['code']); ?></code></td>
                                    <td>$<?php echo number_format($code['bonus_amount'], 2); ?></td>
                                    <td><span class="badge bg-<?php echo $code['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($code['status']); ?></span></td>
                                    <td><?php echo $code['created_at']; ?></td>
                                    <td>
                                        <a href="?delete=<?php echo $code['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this code?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar(e) {
            if (e) e.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar) sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        }
    </script>
</body>
</html>