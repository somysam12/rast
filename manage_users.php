<?php
require_once "includes/optimization.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Handle AJAX Search
if (isset($_GET['ajax_search'])) {
    $search = $_GET['ajax_search'] ?? '';
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 50");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        exit;
    }
}

// Handle delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_users.php");
    exit();
}

// Initial data load
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 100");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    COALESCE(SUM(balance), 0) as total_balance,
    COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance
    FROM users");
$userStats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Silent Panel</title>
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
        .header-card::after { content: ''; position: absolute; top: -50%; right: -10%; width: 200px; height: 200px; background: rgba(255,255,255,0.1); border-radius: 50%; }

        .stat-card { background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-light); border-radius: 18px; padding: 12px 8px; text-align: center; height: 100%; transition: transform 0.3s; display: flex; flex-direction: column; justify-content: center; min-height: 80px; }
        .stat-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.08); }
        .stat-card h3 { color: var(--secondary); font-weight: 800; margin-bottom: 2px; font-size: 1.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stat-card p { color: var(--text-dim); font-size: 0.7rem; margin-bottom: 0; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }

        @media (max-width: 576px) {
            .header-card { padding: 1rem; }
            .stat-card h3 { font-size: 1rem; }
            .stat-card p { font-size: 0.6rem; }
            .row.g-2 { --bs-gutter-x: 0.5rem; }
        }

        .search-container { margin-bottom: 20px; position: relative; }
        .search-input { width: 100%; background: rgba(15, 23, 42, 0.5); border: 1.5px solid var(--border-light); border-radius: 14px; padding: 12px 15px 12px 45px; color: white; font-size: 15px; transition: all 0.3s; }
        .search-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 15px rgba(139, 92, 246, 0.2); }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--primary); }

        .table { color: var(--text-main); border-color: var(--border-light); vertical-align: middle; }
        .table thead th { background: rgba(139, 92, 246, 0.1); color: var(--primary); border: none; padding: 12px; font-size: 0.9rem; }
        .table tbody td { padding: 12px; border-bottom: 1px solid var(--border-light); font-size: 0.9rem; }
        .table tbody tr { transition: all 0.2s; }
        .table tbody tr:hover { background: rgba(139, 92, 246, 0.05); transform: scale(1.002); }

        .btn-action { padding: 6px 10px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s; }
        .btn-action:hover { transform: translateY(-2px); }
        
        .role-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .role-user { background: rgba(6, 182, 212, 0.2); color: var(--secondary); }
        .role-reseller { background: rgba(139, 92, 246, 0.2); color: var(--primary); }
        .role-admin { background: rgba(236, 72, 153, 0.2); color: #ec4899; }

        .loader { width: 24px; height: 24px; border: 3px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: var(--primary); animation: spin 1s ease-in-out infinite; display: none; position: absolute; right: 15px; top: 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }

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
            <a class="nav-link" href="add_mod.php"><i class="fas fa-plus"></i>Add Mod</a>
            <a class="nav-link" href="manage_mods.php"><i class="fas fa-edit"></i>Manage Mods</a>
            <a class="nav-link" href="upload_mod.php"><i class="fas fa-upload"></i>Upload APK</a>
            <a class="nav-link" href="mod_list.php"><i class="fas fa-list"></i>Mod List</a>
            <a class="nav-link" href="add_license.php"><i class="fas fa-key"></i>Add License</a>
            <a class="nav-link" href="licence_key_list.php"><i class="fas fa-list"></i>License List</a>
            <a class="nav-link active" href="manage_users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a class="nav-link" href="add_balance.php"><i class="fas fa-wallet"></i>Add Balance</a>
            <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i>Settings</a>
            <hr style="border-color: var(--border-light); margin: 1.5rem 16px;">
            <a class="nav-link" href="logout.php" style="color: #ef4444;"><i class="fas fa-sign-out"></i>Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h2 class="text-white mb-1" style="font-weight: 800;">User Management</h2>
                    <p class="text-white opacity-75 mb-0">Monitor and manage all user accounts</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="stat-card">
                                <h3><?php echo (int)$userStats['total_users']; ?></h3>
                                <p>Users</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <h3>₹<?php echo number_format($userStats['total_balance'], 0); ?></h3>
                                <p>Funds</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-card">
                                <h3><?php echo (int)$userStats['users_with_balance']; ?></h3>
                                <p>Active</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="userSearch" class="search-input" placeholder="Search by username or email initials..." autocomplete="off">
                <div class="loader" id="searchLoader"></div>
            </div>

            <div class="table-responsive">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Role</th>
                            <th>Balance</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersList">
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="text-dim small"><?php echo htmlspecialchars($user['email']); ?></div>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><span class="text-success fw-bold">₹<?php echo number_format($user['balance'], 2); ?></span></td>
                            <td><span class="text-dim small"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm btn-action"><i class="fas fa-edit"></i></a>
                                    <a href="add_balance.php?user_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm btn-action"><i class="fas fa-wallet"></i></a>
                                    <button onclick="confirmDelete(<?php echo $user['id']; ?>)" class="btn btn-danger btn-sm btn-action"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const searchInput = document.getElementById('userSearch');
        const searchLoader = document.getElementById('searchLoader');
        const usersList = document.getElementById('usersList');

        // Sidebar logic
        hamburgerBtn.onclick = () => { sidebar.classList.add('active'); overlay.classList.add('active'); };
        overlay.onclick = () => { sidebar.classList.remove('active'); overlay.classList.remove('active'); };

        // Advanced AJAX Search
        let searchTimeout;
        searchInput.oninput = (e) => {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();
            
            searchLoader.style.display = 'block';
            
            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`manage_users.php?ajax_search=${encodeURIComponent(val)}`);
                    const users = await res.json();
                    
                    usersList.innerHTML = '';
                    users.forEach(user => {
                        const row = `
                            <tr>
                                <td>
                                    <div class="fw-bold">${escapeHtml(user.username)}</div>
                                    <div class="text-dim small">${escapeHtml(user.email)}</div>
                                </td>
                                <td><span class="role-badge role-${user.role}">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</span></td>
                                <td><span class="text-success fw-bold">₹${parseFloat(user.balance).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span></td>
                                <td><span class="text-dim small">${new Date(user.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}</span></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="edit_user.php?id=${user.id}" class="btn btn-primary btn-sm btn-action"><i class="fas fa-edit"></i></a>
                                        <a href="add_balance.php?user_id=${user.id}" class="btn btn-success btn-sm btn-action"><i class="fas fa-wallet"></i></a>
                                        <button onclick="confirmDelete(${user.id})" class="btn btn-danger btn-sm btn-action"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        usersList.insertAdjacentHTML('beforeend', row);
                    });
                } catch (err) {
                    console.error('Search failed', err);
                } finally {
                    searchLoader.style.display = 'none';
                }
            }, 300);
        };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete User?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#7c3aed',
                cancelButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete!',
                background: '#111827',
                color: '#ffffff'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete=' + id;
                }
            })
        }
    </script>
</body>
</html>