<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Initial data load (limit to 100 for smoothness)
$transactions = getAllTransactions($filters);

// Statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as income,
    COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as expenses
    FROM transactions");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Silent Panel</title>
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

        .form-control, .form-select { background: rgba(15, 23, 42, 0.5); border: 1.5px solid var(--border-light); border-radius: 12px; padding: 10px; color: white; font-size: 0.9rem; }
        
        .table { color: var(--text-main); vertical-align: middle; }
        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border: none; padding: 12px; font-size: 0.9rem; }
        .table tbody td { padding: 12px; border-bottom: 1px solid var(--border-light); font-size: 0.85rem; }
        
        .type-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .type-credit { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .type-debit { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .overlay.active { display: block; }
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
            <a class="nav-link" href="referral_codes.php"><i class="fas fa-tag"></i>Referral Codes</a>
            <a class="nav-link active" href="transactions.php"><i class="fas fa-exchange-alt"></i>Transactions</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h2 class="text-white mb-1" style="font-weight: 800;">Transaction Logs</h2>
                    <p class="text-white opacity-75 mb-0">Track all financial activities across the system</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="stat-card">
                                <h3>₹<?php echo number_format($stats['income'], 0); ?></h3>
                                <p>Income</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card">
                                <h3>₹<?php echo number_format($stats['expenses'], 0); ?></h3>
                                <p>Outflow</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card mb-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by username or reference..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo $filters['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $filters['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $filters['status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100" style="border-radius:12px; padding:10px; background:var(--primary); border:none; font-weight:700;">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['username'] ?? 'System'); ?></strong></td>
                            <td class="fw-bold <?php echo $t['amount'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                ₹<?php echo number_format(abs($t['amount']), 2); ?>
                            </td>
                            <td><span class="text-dim small"><?php echo htmlspecialchars($t['description']); ?></span></td>
                            <td>
                                <span class="type-badge type-<?php echo $t['type']; ?>">
                                    <?php echo $t['type']; ?>
                                </span>
                            </td>
                            <td><span class="text-dim small"><?php echo date('M d, H:i', strtotime($t['created_at'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-dim">No transactions found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };
    </script>
</body>
</html>