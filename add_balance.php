<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Simple AJAX Search Handler
if (isset($_GET['ajax_search'])) {
    $search = $_GET['ajax_search'] ?? '';
    $results = [];
    try {
        $stmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE role = 'user' AND username LIKE ? ORDER BY username LIMIT 10");
        $stmt->execute(['%' . $search . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

$success = '';
$error = '';

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
    <title>Add Balance - Silent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --secondary: #06b6d4;
            --bg: #0a0e27;
            --card-bg: rgba(15, 23, 42, 0.8);
            --text-main: #f8fafc;
            --text-dim: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1e1b4b 50%, #0a0e27 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            z-index: 1000;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-right: 1px solid var(--border);
            padding: 2rem 0;
        }

        .sidebar.active { left: 0; }

        .nav-link {
            color: var(--text-dim);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: 0.3s;
            font-weight: 600;
        }

        .nav-link:hover, .nav-link.active {
            color: white;
            background: rgba(139, 92, 246, 0.2);
        }

        .nav-link.active { border-left: 4px solid var(--primary); }

        /* Mobile Header */
        .mobile-header {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(10, 14, 39, 0.5);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 900;
            border-bottom: 1px solid var(--border);
        }

        .hamburger {
            font-size: 24px;
            cursor: pointer;
            color: var(--primary);
        }

        /* Form Container */
        .main-container {
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .glass-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            margin-top: 10px;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 15px;
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
        }

        .form-label { color: var(--text-dim); font-size: 14px; margin-bottom: 8px; font-weight: 600; }

        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
        }

        .form-input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1.5px solid var(--border);
            border-radius: 14px;
            padding: 12px 15px 12px 45px;
            color: white;
            outline: none;
            transition: 0.3s;
        }

        .form-input:focus { border-color: var(--primary); background: rgba(255,255,255,0.08); }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e1b4b;
            border-radius: 14px;
            margin-top: 5px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1001;
            display: none;
            border: 1px solid var(--border);
        }

        .search-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
        }

        .search-item:hover { background: var(--primary); }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 14px;
            padding: 14px;
            color: white;
            font-weight: 700;
            font-size: 16px;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.3);
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            z-index: 999;
        }

        .overlay.active { display: block; }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="px-4 mb-4">
            <h4 class="text-primary fw-bold">SILENT PANEL</h4>
        </div>
        <nav>
            <a href="admin_dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="manage_users.php" class="nav-link"><i class="fas fa-users"></i> Manage Users</a>
            <a href="add_balance.php" class="nav-link active"><i class="fas fa-wallet"></i> Add Balance</a>
            <a href="transactions.php" class="nav-link"><i class="fas fa-history"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <header class="mobile-header">
        <div class="hamburger" id="hamBtn"><i class="fas fa-bars"></i></div>
        <div class="fw-bold">SILENT PANEL</div>
        <div style="width: 24px;"></div>
    </header>

    <div class="main-container">
        <div class="glass-card">
            <div class="brand-icon"><i class="fas fa-coins"></i></div>
            <h4 class="text-center mb-1">Add Balance</h4>
            <p class="text-center text-dim small mb-4">Search user by typing initials</p>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3 mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3 mb-4"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" id="balanceForm">
                <label class="form-label">Search User</label>
                <div class="input-group-custom">
                    <i class="fas fa-search input-icon"></i>
                    <input type="text" id="userSearch" class="form-input" placeholder="Type username..." autocomplete="off">
                    <div id="searchResults" class="search-results"></div>
                </div>

                <input type="hidden" name="user_id" id="userIdInput" required>

                <div id="selectedUserBox" class="mb-3 d-none">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-dim">Selected User</div>
                            <div id="selectedUsername" class="fw-bold text-primary"></div>
                        </div>
                        <div class="text-end">
                            <div class="small text-dim">Current Balance</div>
                            <div id="selectedBalance" class="fw-bold text-success"></div>
                        </div>
                    </div>
                </div>

                <label class="form-label">Amount (₹)</label>
                <div class="input-group-custom">
                    <i class="fas fa-plus-circle input-icon"></i>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-input" placeholder="Enter amount" required>
                </div>

                <label class="form-label">Reference (Optional)</label>
                <div class="input-group-custom">
                    <i class="fas fa-tag input-icon"></i>
                    <input type="text" name="reference" class="form-input" placeholder="e.g. UPI ID / Purpose">
                </div>

                <button type="submit" class="btn-submit">CONFIRM ADDITION</button>
            </form>
        </div>
    </div>

    <script>
        const hamBtn = document.getElementById('hamBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const userSearch = document.getElementById('userSearch');
        const searchResults = document.getElementById('searchResults');
        const userIdInput = document.getElementById('userIdInput');
        const selectedUserBox = document.getElementById('selectedUserBox');
        const selectedUsername = document.getElementById('selectedUsername');
        const selectedBalance = document.getElementById('selectedBalance');

        // Sidebar Toggle
        hamBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        // Advanced AJAX Search
        userSearch.oninput = async (e) => {
            const val = e.target.value.trim();
            if (val.length < 1) { searchResults.style.display = 'none'; return; }

            const res = await fetch(`add_balance.php?ajax_search=${val}`);
            const data = await res.json();

            searchResults.innerHTML = '';
            if (data.length > 0) {
                searchResults.style.display = 'block';
                data.forEach(user => {
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<div class="fw-bold">${user.username}</div><div class="small text-dim">Balance: ₹${user.balance}</div>`;
                    div.onclick = () => {
                        userIdInput.value = user.id;
                        selectedUsername.innerText = user.username;
                        selectedBalance.innerText = '₹' + parseFloat(user.balance).toFixed(2);
                        selectedUserBox.classList.remove('d-none');
                        searchResults.style.display = 'none';
                        userSearch.value = user.username;
                    };
                    searchResults.appendChild(div);
                });
            } else {
                searchResults.style.display = 'none';
            }
        };

        // Close search results when clicking outside
        document.onclick = (e) => { if (!userSearch.contains(e.target)) searchResults.style.display = 'none'; };
    </script>
</body>
</html>