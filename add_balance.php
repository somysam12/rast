<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

$pdo = getDBConnection();

// Get user statistics for dashboard
$userStats = [
    'total_users' => 0,
    'total_balance' => 0,
    'users_with_balance' => 0,
    'avg_balance' => 0
];

try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        COALESCE(SUM(balance), 0) as total_balance,
        COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance,
        COALESCE(AVG(balance), 0) as avg_balance
        FROM users WHERE role = 'user'");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $userStats;
} catch (Exception $e) {
    // Use defaults
}

// Pre-select user if provided in URL
$selectedUserId = $_GET['user_id'] ?? '';

// Get all users for dropdown or search
$allUsers = [];
$search = $_GET['search'] ?? '';
try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT id, username, email, balance FROM users WHERE role = 'user' AND (username LIKE ? OR email LIKE ?) ORDER BY username");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    } else {
        $stmt = $pdo->query("SELECT id, username, email, balance FROM users WHERE role = 'user' ORDER BY username LIMIT 100");
    }
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allUsers = [];
}

if ($_POST) {
    try {
        $userId = $_POST['user_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($userId) || $amount <= 0) {
            $error = 'Please select a user and enter a valid amount';
        } else {
            if (updateBalance($userId, $amount, 'balance_add', $reference)) {
                $success = 'Balance added successfully!';
                $selectedUserId = $userId;
            } else {
                $error = 'Failed to add balance';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Balance - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --border-glow: rgba(139, 92, 246, 0.2);
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
            padding: 40px 20px;
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

        .wrapper {
            width: 100%;
            max-width: 500px;
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 1;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 2px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 0 60px rgba(139, 92, 246, 0.15);
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1); }
            50% { box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2); }
        }

        .brand-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            color: white;
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.4);
        }

        h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #f8fafc, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-field, select.input-field {
            width: 100%;
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid var(--border-light);
            border-radius: 14px;
            padding: 12px 16px 12px 48px;
            color: white;
            font-size: 15px;
            transition: all 0.3s;
        }

        select.input-field option {
            background: #1e1b4b;
            color: white;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(139, 92, 246, 0.05);
        }

        .field-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 18px;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 14px;
            padding: 14px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.5);
        }

        .alert-custom {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success-custom {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .alert-error-custom {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link:hover { color: var(--primary); }

        .search-container {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            background: rgba(15, 23, 42, 0.3);
            border: 1.5px solid var(--border-light);
            border-radius: 12px;
            padding: 10px 15px;
            color: white;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="glass-card">
            <div class="brand-section">
                <div class="brand-icon">
                    <i class="fas fa-wallet"></i>
                </div>
                <h1>Add User Balance</h1>
                <p>Manage user wallets with ease</p>
            </div>

            <?php if ($success): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-custom alert-error-custom">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Search Section -->
            <form method="GET" class="search-container">
                <input type="text" name="search" class="search-input" placeholder="Search username/email..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-sm btn-primary" style="border-radius: 10px; background: var(--primary); border: none;">Search</button>
                <?php if($search): ?>
                    <a href="add_balance.php" class="btn btn-sm btn-secondary" style="border-radius: 10px;">Clear</a>
                <?php endif; ?>
            </form>

            <form method="POST">
                <div class="form-group">
                    <i class="fas fa-user field-icon"></i>
                    <select name="user_id" class="input-field" required>
                        <option value="">-- <?php echo empty($allUsers) ? 'No users found' : 'Select User'; ?> --</option>
                        <?php foreach ($allUsers as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id']); ?>" <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo formatCurrency($user['balance']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <i class="fas fa-indian-rupee-sign field-icon"></i>
                    <input type="number" name="amount" step="0.01" min="0" class="input-field" placeholder="Amount (0.00)" required>
                </div>

                <div class="form-group">
                    <i class="fas fa-tag field-icon"></i>
                    <input type="text" name="reference" class="input-field" placeholder="Reference (Optional)">
                </div>

                <button type="submit" class="btn-submit">Add Balance</button>
            </form>

            <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
        </div>
    </div>
    <script src="assets/js/scroll-restore.js"></script>
    <script src="assets/js/menu-logic.js"></script>
</body>
</html>