<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

$success = '';
$error = '';

// Handle generate referral code
if ($_POST && isset($_POST['generate_code'])) {
    try {
        $expiryOption = $_POST['expiry_option'] ?? '30d';
        $bonusAmount = (float)($_POST['bonus_amount'] ?? 50.00);
        $usageLimit = (int)($_POST['usage_limit'] ?? 1);
        
        $duration = match($expiryOption) {
            '1h' => '+1 hour',
            '1d' => '+1 day',
            '1w' => '+7 days',
            '1m' => '+30 days',
            default => '+30 days'
        };

        if ($bonusAmount < 0) {
            $error = 'Bonus amount cannot be negative';
        } elseif ($usageLimit <= 0) {
            $error = 'Usage limit must be at least 1';
        } else {
            $code = generateReferralCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime($duration));
            
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

// Handle bulk deletion
if (isset($_POST['bulk_delete']) && isset($_POST['selected_codes'])) {
    try {
        $ids = $_POST['selected_codes'];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $success = "Selected referral codes deleted successfully.";
        }
    } catch (Exception $e) {
        $error = "Error during bulk deletion: " . $e->getMessage();
    }
}

// Handle deactivate/delete
if (isset($_GET['deactivate']) && is_numeric($_GET['deactivate'])) {
    $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$_GET['deactivate']]);
    header("Location: referral_codes.php");
    exit();
}
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM referral_codes WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: referral_codes.php");
    exit();
}

// Initial data load
$stmt = $pdo->query("SELECT rc.*, u.username as created_by_name FROM referral_codes rc LEFT JOIN users u ON rc.created_by = u.id ORDER BY rc.created_at DESC");
$referralCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(CASE WHEN status='active' AND (expires_at > datetime('now')) THEN 1 END) as active FROM referral_codes");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Management - Silent Panel</title>
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
            left: -280px;
        }

        .sidebar.active { transform: translateX(280px); }
        .sidebar h4 { font-weight: 800; color: var(--primary); margin-bottom: 2rem; padding: 0 20px; }
        .sidebar .nav-link { color: var(--text-dim); padding: 12px 20px; margin: 4px 16px; border-radius: 12px; font-weight: 600; transition: all 0.3s; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .sidebar .nav-link:hover { color: var(--text-main); background: rgba(139, 92, 246, 0.1); }
        .sidebar .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 0; padding: 1.5rem; transition: margin-left 0.3s ease; max-width: 1400px; margin: 0 auto; }

        @media (min-width: 993px) {
            .sidebar { left: 0; }
            .main-content { margin-left: 280px; }
        }

        .hamburger { position: fixed; top: 20px; left: 20px; z-index: 1100; background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 10px; cursor: pointer; display: none; }
        @media (max-width: 992px) { .hamburger { display: block; } }

        .glass-card { background: var(--card-bg); backdrop-filter: blur(30px); -webkit-backdrop-filter: blur(30px); border: 1px solid var(--border-light); border-radius: 24px; padding: 25px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); margin-bottom: 2rem; }

        .header-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); padding: 1.5rem; border-radius: 24px; margin-bottom: 2rem; position: relative; overflow: hidden; }

        .stat-card { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); border-radius: 18px; padding: 12px 8px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; min-height: 80px; }
        .stat-card h3 { color: var(--secondary); font-weight: 800; margin-bottom: 2px; font-size: 1.2rem; }
        .stat-card p { color: var(--text-dim); font-size: 0.7rem; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px; }

        .form-control, .form-select { background: rgba(15, 23, 42, 0.5); border: 1.5px solid var(--border-light); border-radius: 12px; padding: 12px; color: white; }
        .form-control:focus, .form-select:focus { outline: none; border-color: var(--primary); background: rgba(15, 23, 42, 0.7); color: white; box-shadow: 0 0 15px rgba(139, 92, 246, 0.2); }

        .btn-submit { background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none; border-radius: 12px; padding: 12px 24px; color: white; font-weight: 700; transition: all 0.3s; width: 100%; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(139, 92, 246, 0.3); }

        .table { color: var(--text-main); vertical-align: middle; }
        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border: none; padding: 12px; font-size: 0.9rem; }
        .table tbody td { padding: 12px; border-bottom: 1px solid var(--border-light); font-size: 0.85rem; }
        
        .code-badge-wrapper { position: relative; display: inline-flex; align-items: center; gap: 8px; }
        .code-badge { font-family: 'Courier New', monospace; background: rgba(139, 92, 246, 0.1); color: var(--primary); padding: 4px 10px; border-radius: 8px; font-weight: 800; letter-spacing: 1px; border: 1px solid rgba(139, 92, 246, 0.2); cursor: pointer; position: relative; }
        
        .copy-btn { color: var(--primary); cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
        .copy-btn:hover { color: var(--secondary); transform: scale(1.2); }
        .copy-btn.copied { color: #10b981; transform: scale(1.3); }

        .status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .status-active { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-inactive { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }

        .checkbox-custom { width: 18px; height: 18px; border: 2px solid var(--primary); border-radius: 4px; appearance: none; cursor: pointer; position: relative; transition: all 0.2s; }
        .checkbox-custom:checked { background: var(--primary); }
        .checkbox-custom:checked::after { content: '\f00c'; font-family: 'Font Awesome 5 Free'; font-weight: 900; color: white; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 10px; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    <div class="sidebar" id="sidebar">
        <h4>SILENT PANEL</h4>
        <nav class="nav flex-column">
            <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i>Dashboard</a>
            <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
            <a class="nav-link active" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transactions</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h2 class="text-white mb-1" style="font-weight: 800;">Referral Management</h2>
                    <p class="text-white opacity-75 mb-0">Bulk manage bonus referral codes</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="stat-card">
                                <h3><?php echo (int)$stats['total']; ?></h3>
                                <p>Total Codes</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h3><?php echo (int)$stats['active']; ?></h3>
                                <p>Active Now</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="glass-card">
                    <h5 class="mb-4 fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>New Referral</h5>
                    <form method="POST">
                        <input type="hidden" name="generate_code" value="1">
                        <div class="mb-3">
                            <label class="form-label small text-dim">Bonus Amount (₹)</label>
                            <input type="number" name="bonus_amount" class="form-control" value="50" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-dim">Expiry Duration</label>
                            <select name="expiry_option" class="form-select">
                                <option value="1h">1 Hour</option>
                                <option value="1d">1 Day</option>
                                <option value="1w">1 Week</option>
                                <option value="1m" selected>1 Month</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-dim">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" value="1" min="1" required>
                        </div>
                        <button type="submit" class="btn-submit">Generate Code</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="glass-card">
                    <form method="POST" id="bulkDeleteForm">
                        <input type="hidden" name="bulk_delete" value="1">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <input type="checkbox" id="selectAll" class="checkbox-custom">
                                <label for="selectAll" class="text-dim small mb-0 cursor-pointer">Select All</label>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirmBulkDelete(event)">
                                <i class="fas fa-trash-alt me-2"></i>Delete Selected
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Code</th>
                                        <th>Bonus</th>
                                        <th>Usage</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referralCodes as $code): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_codes[]" value="<?php echo $code['id']; ?>" class="checkbox-custom code-checkbox">
                                        </td>
                                        <td>
                                            <div class="code-badge-wrapper">
                                                <span class="code-badge" onclick="copyCode('<?php echo $code['code']; ?>', this)"><?php echo $code['code']; ?></span>
                                                <i class="fas fa-copy copy-btn" onclick="copyCode('<?php echo $code['code']; ?>', this)"></i>
                                            </div>
                                        </td>
                                        <td><span class="text-success fw-bold">₹<?php echo number_format($code['bonus_amount'], 2); ?></span></td>
                                        <td><span class="text-dim"><?php echo $code['usage_count']; ?> / <?php echo $code['usage_limit']; ?></span></td>
                                        <td>
                                            <?php 
                                            $isExpired = strtotime($code['expires_at']) < time();
                                            ?>
                                            <span class="small <?php echo $isExpired ? 'text-danger fw-bold' : 'text-dim'; ?>">
                                                <?php echo date('M d, H:i', strtotime($code['expires_at'])); ?>
                                                <?php if($isExpired): ?> <i class="fas fa-exclamation-circle"></i> <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <?php if ($code['status'] === 'active' && !$isExpired): ?>
                                                    <a href="?deactivate=<?php echo $code['id']; ?>" class="btn btn-warning btn-sm" title="Deactivate"><i class="fas fa-ban"></i></a>
                                                <?php endif; ?>
                                                <button type="button" onclick="confirmDelete(<?php echo $code['id']; ?>)" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const selectAll = document.getElementById('selectAll');
        const codeCheckboxes = document.querySelectorAll('.code-checkbox');

        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        selectAll.onchange = (e) => {
            codeCheckboxes.forEach(cb => cb.checked = e.target.checked);
        };

        async function copyCode(text, el) {
            try {
                await navigator.clipboard.writeText(text);
                const btn = el.parentElement.querySelector('.copy-btn') || el;
                btn.classList.add('copied');
                btn.classList.replace('fa-copy', 'fa-check-circle');
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Code Copied!',
                    showConfirmButton: false,
                    timer: 1500,
                    background: '#1e293b',
                    color: '#f8fafc'
                });

                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.classList.replace('fa-check-circle', 'fa-copy');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy: ', err);
            }
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete Code?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => { if (result.isConfirmed) window.location.href = '?delete=' + id; })
        }

        function confirmBulkDelete(e) {
            e.preventDefault();
            const selected = document.querySelectorAll('.code-checkbox:checked').length;
            if (selected === 0) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No codes selected!', background: '#111827', color: '#ffffff' });
                return false;
            }

            Swal.fire({
                title: 'Bulk Delete?',
                text: `Are you sure you want to delete ${selected} codes?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete all!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => { if (result.isConfirmed) document.getElementById('bulkDeleteForm').submit(); })
        }

        <?php if ($success): ?>
        Swal.fire({ icon: 'success', title: 'Success!', text: '<?php echo $success; ?>', background: '#111827', color: '#ffffff' });
        <?php endif; ?>
        <?php if ($error): ?>
        Swal.fire({ icon: 'error', title: 'Error!', text: '<?php echo $error; ?>', background: '#111827', color: '#ffffff' });
        <?php endif; ?>
    </script>
</body>
</html>