<?php require_once "includes/optimization.php"; ?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

function formatCurrency($amount){
    return '₹' . number_format((float)$amount, 2, '.', ',');
}
function formatDate($dt){
    if(!$dt){ return '-'; }
    // Set Indian Timezone for formatting
    $date = new DateTime($dt, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
    return $date->format('d M Y, h:i A');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    die("Database connection failed");
}

$stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if(!$user){
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get filter parameters
$modFilter = $_GET['mod_id'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$keyIdFilter = $_GET['key_id'] ?? '';

// Get user's purchased mods for filter
$purchasedMods = [];
try {
    $stmt = $pdo->prepare('SELECT DISTINCT m.id, m.name 
                           FROM license_keys lk
                           LEFT JOIN mods m ON m.id = lk.mod_id
                           WHERE lk.sold_to = ?
                           ORDER BY m.name');
    $stmt->execute([$user['id']]);
    $purchasedMods = $stmt->fetchAll();
} catch (Throwable $e) {
    $purchasedMods = [];
}

// Get user's purchased keys with filter and search
$purchasedKeys = [];
try {
    $sql = 'SELECT lk.*, m.name AS mod_name
            FROM license_keys lk
            LEFT JOIN mods m ON m.id = lk.mod_id
            WHERE lk.sold_to = ?';
    $params = [$user['id']];

    if ($keyIdFilter !== '' && ctype_digit((string)$keyIdFilter)) {
        $sql .= ' AND lk.id = ?';
        $params[] = $keyIdFilter;
    }

    if ($modFilter !== '' && ctype_digit((string)$modFilter)) {
        $sql .= ' AND lk.mod_id = ?';
        $params[] = $modFilter;
    }

    if ($searchQuery !== '') {
        $sql .= ' AND (m.name LIKE ? OR lk.license_key LIKE ?)';
        $params[] = '%' . $searchQuery . '%';
        $params[] = '%' . $searchQuery . '%';
    }

    $sql .= ' ORDER BY lk.sold_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $purchasedKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $purchasedKeys = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Keys - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { padding-top: 60px; }
        .sidebar { width: 260px; position: fixed; top: 60px; bottom: 0; left: 0; z-index: 1000; transition: transform 0.3s ease; }
        .main-content { margin-left: 260px; padding: 2rem; transition: margin-left 0.3s ease; }
        .header { height: 60px; position: fixed; top: 0; left: 0; right: 0; z-index: 1001; background: rgba(5,7,10,0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .search-input {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid rgba(148, 163, 184, 0.1);
            color: white;
            border-radius: 12px;
            padding: 10px 15px 10px 45px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #8b5cf6;
            color: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }

        .search-wrapper { position: relative; }
        .search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: rgba(148, 163, 184, 0.5); }

        .license-key-box {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: rgba(139, 92, 246, 0.1);
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            color: #a78bfa;
            font-size: 0.95rem;
            word-break: break-all;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .license-key-box:hover {
            background: rgba(139, 92, 246, 0.2);
            border-color: #8b5cf6;
            transform: scale(1.02);
            color: white;
        }

        .license-key-box:active {
            transform: scale(0.98);
        }

        .license-key-box::after {
            content: '\f0c5';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.8rem;
            opacity: 0.5;
        }

        .status-purchased {
            background: rgba(16, 185, 129, 0.1) !important;
            color: #10b981 !important;
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table-responsive {
            border-radius: 15px;
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(10, 15, 25, 0.5);
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            min-width: 900px;
            margin-bottom: 0;
        }

        /* Custom scrollbar for better UI */
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.3);
            border-radius: 10px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.5);
        }
        
        .user-nav-wrapper { position: relative; }
        .user-avatar-header { cursor:pointer; transition:all 0.3s ease; }
        .user-avatar-header:hover { transform:scale(1.05); box-shadow:0 0 15px rgba(139, 92, 246, 0.4); }
        .avatar-dropdown { position:absolute; top:calc(100% + 15px); right:0; width:220px; background:rgba(10, 15, 25, 0.95); backdrop-filter:blur(20px); border:1px solid rgba(139, 92, 246, 0.3); border-radius:16px; padding:10px; z-index:1002; display:none; box-shadow:0 10px 30px rgba(0,0,0,0.5); animation:dropdownFade 0.3s ease; }
        .avatar-dropdown.show { display:block; }
        @keyframes dropdownFade { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .dropdown-item-cyber { display:flex; align-items:center; gap:12px; padding:10px 15px; color:rgba(255, 255, 255, 0.7); text-decoration:none; border-radius:10px; transition:all 0.2s ease; font-size:0.9rem; }
        .dropdown-item-cyber:hover { background:rgba(139, 92, 246, 0.1); color:#fff; transform:translateX(5px); }
        .dropdown-item-cyber i { width:20px; text-align:center; color:var(--primary); }
        .dropdown-divider { height:1px; background:rgba(255, 255, 255, 0.05); margin:8px 0; }
    </style>
</head>
<body>
    <header class="header">
        <div class="d-flex align-items-center gap-3">
            <button class="btn text-white p-0 d-lg-none" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h4 class="m-0 text-neon fw-bold">SilentMultiPanel</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <div class="small fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                <div class="text-secondary small">Balance: <?php echo formatCurrency($user['balance']); ?></div>
            </div>
            <div class="user-nav-wrapper">
                <div class="user-avatar-header" onclick="toggleAvatarDropdown()" style="width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                </div>
                <div class="avatar-dropdown" id="avatarDropdown">
                    <div class="px-3 py-2">
                        <div class="fw-bold text-white"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="text-secondary small">ID: #<?php echo $user['id']; ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="user_transactions.php" class="dropdown-item-cyber">
                        <i class="fas fa-history"></i> Transactions
                    </a>
                    <a href="user_generate.php" class="dropdown-item-cyber">
                        <i class="fas fa-plus"></i> Generate Key
                    </a>
                    <a href="user_settings.php" class="dropdown-item-cyber">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item-cyber text-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar p-3" id="sidebar">
        <nav class="nav flex-column gap-2">
            <a class="nav-link" href="user_dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
            <a class="nav-link" href="user_generate.php"><i class="fas fa-plus me-2"></i> Generate Key</a>
            <a class="nav-link active" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="text-neon mb-1">Manage Keys</h2>
                    <p class="text-secondary mb-0">View and manage your purchased license keys.</p>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="cyber-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="small fw-bold text-secondary mb-2">FILTER BY MOD</label>
                            <select name="mod_id" class="form-select bg-dark border-secondary text-white rounded-3 py-2" onchange="this.form.submit()">
                                <option value="">All Applications</option>
                                <?php foreach ($purchasedMods as $mod): ?>
                                    <option value="<?php echo $mod['id']; ?>" <?php echo $modFilter == $mod['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mod['name'] ?? 'Unknown'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="small fw-bold text-secondary mb-2">SEARCH KEYS</label>
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" class="search-input" placeholder="Search by key or mod name..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                            </div>
                        </div>
                        <div class="col-12 col-md-3 d-flex gap-2">
                            <button type="submit" class="cyber-btn w-100 py-2">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($modFilter || $searchQuery || $keyIdFilter): ?>
                                <a href="user_manage_keys.php" class="btn btn-outline-secondary rounded-3 px-3">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="cyber-card">
            <h5 class="mb-4 text-white"><i class="fas fa-list text-primary me-2"></i> Your Purchased Keys</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Mod Name</th>
                            <th>License Key</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchasedKeys)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
                                    <i class="fas fa-key d-block mb-3" style="font-size: 2rem; opacity: 0.2;"></i>
                                    No keys found matching your criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($purchasedKeys as $key): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?php echo htmlspecialchars($key['mod_name'] ?? 'Unknown'); ?></div>
                                        <div class="small text-secondary">₹<?php echo number_format($key['price'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="license-key-box" onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>', this)">
                                            <?php echo htmlspecialchars($key['license_key']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-25 text-white">
                                            <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-purchased">
                                            PURCHASED
                                        </span>
                                    </td>
                                    <td class="small text-secondary"><?php echo formatDate($key['sold_at']); ?></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn btn-sm btn-outline-primary rounded-2" onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')" title="Copy Key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <a href="user_block_request.php?key=<?php echo urlencode($key['license_key']); ?>&action=reset" class="btn btn-sm btn-outline-info rounded-2" title="Reset HWID">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                            <a href="user_block_request.php?key=<?php echo urlencode($key['license_key']); ?>&action=block" class="btn btn-sm btn-outline-danger rounded-2" title="Block Key">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); }
        
        function toggleAvatarDropdown() {
            document.getElementById('avatarDropdown').classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.matches('.user-avatar-header')) {
                var dropdowns = document.getElementsByClassName("avatar-dropdown");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
        
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(() => {
                // Flash animation on the element
                if (element) {
                    const originalColor = element.style.borderColor;
                    element.style.borderColor = '#8b5cf6';
                    element.style.background = 'rgba(139, 92, 246, 0.3)';
                    setTimeout(() => {
                        element.style.borderColor = originalColor;
                        element.style.background = '';
                    }, 300);
                }

                Swal.fire({
                    title: 'Copied!',
                    text: 'Key copied to clipboard',
                    icon: 'success',
                    background: 'rgba(15, 23, 42, 0.95)',
                    color: '#fff',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end',
                    customClass: {
                        popup: 'cyber-swal'
                    }
                });
            });
        }
    </script>
</body>
</html>