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

// Handle bulk delete for license keys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_keys'])) {
    // Get the raw array from POST
    $rawKeyIds = isset($_POST['key_ids']) ? $_POST['key_ids'] : [];
    
    // Ensure it's an array and convert to integers
    if (!is_array($rawKeyIds)) {
        $rawKeyIds = [$rawKeyIds];
    }
    
    $keyIds = array_filter(array_map('intval', $rawKeyIds));
    
    if (!empty($keyIds)) {
        try {
            $placeholders = implode(',', array_fill(0, count($keyIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id IN ($placeholders)");
            if ($stmt->execute(array_values($keyIds))) {
                $deletedCount = $stmt->rowCount();
                $success = 'Successfully deleted ' . $deletedCount . ' license key(s)!';
            } else {
                $error = 'Failed to delete selected license keys';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$filters = [
    'mod_id' => $_GET['mod_id'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get all mods for filter dropdown
$stmt = $pdo->query("SELECT id, name FROM mods ORDER BY name");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query with filters
$where = ["1=1"];
$params = [];

if (!empty($filters['mod_id'])) {
    $where[] = "lk.mod_id = ?";
    $params[] = $filters['mod_id'];
}

if (!empty($filters['status'])) {
    $where[] = "lk.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['search'])) {
    $where[] = "(lk.license_key LIKE ? OR m.name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql = "SELECT lk.*, m.name as mod_name 
        FROM license_keys lk 
        LEFT JOIN mods m ON lk.mod_id = m.id 
        WHERE " . implode(' AND ', $where) . " 
        ORDER BY lk.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$licenseKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>License Key List - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .sidebar {
            background-color: var(--card-bg);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-light);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 16px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1em;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        .mobile-header {
            display: none;
            background: var(--card-bg);
            padding: 1rem;
            box-shadow: var(--shadow-light);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-light);
        }
        
        .mobile-toggle {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .mobile-toggle:hover {
            transform: translateY(-1px);
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .filter-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-light);
        }
        
        .table-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-light);
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
        }
        
        .table thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--border-light);
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .btn-sm {
            padding: 0.4rem 0.6rem;
            font-size: 0.85rem;
        }
        
        .badge {
            font-weight: 600;
            padding: 0.4rem 0.8rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .checkbox-column {
            width: 40px;
            text-align: center;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-light);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .form-check-input:checked {
            background-color: var(--purple);
            border-color: var(--purple);
        }
        
        .form-check-input:hover {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        #bulkDeleteBtn {
            animation: slideInBtn 0.3s ease-out;
        }
        
        @keyframes slideInBtn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes slideInDelete {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .swal-delete-popup {
            border-radius: 12px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
        }
        
        .swal-delete-title {
            font-size: 1.3rem !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }
        
        .swal-delete-confirm, .swal-delete-cancel {
            border-radius: 6px !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
            transition: all 0.2s ease !important;
        }
        
        .swal-delete-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
        }
        
        .swal-delete-cancel {
            background-color: #e5e7eb !important;
            color: #374151 !important;
        }
        
        .swal-delete-cancel:hover {
            background-color: #d1d5db !important;
            transform: translateY(-2px) !important;
        }
        
        .license-key {
            font-family: 'Courier New', monospace;
            background: rgba(139, 92, 246, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 500;
            color: var(--purple);
        }
        
        .success-alert {
            animation: slideIn 0.4s ease-out;
        }
        
        .error-alert {
            animation: slideIn 0.4s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-top: 60px;
            }
            
            .main-content {
                padding-top: 5rem;
            }
            
            .table {
                font-size: 0.85rem;
            }
            
            .table thead th {
                padding: 0.75rem 0.5rem;
            }
            
            .table tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .btn-sm {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-header">
        <button class="mobile-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <span>License Keys</span>
        <div style="width: 40px;"></div>
    </div>
    
    <div class="sidebar" id="sidebar">
        <div style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
                <div class="user-avatar" style="width: 50px; height: 50px; font-size: 1.2rem;">MP</div>
                <div>
                    <div style="font-weight: 700; color: var(--text-primary);">Multi Panel</div>
                    <small style="color: var(--text-secondary);">License Manager</small>
                </div>
            </div>
        </div>
        
        <nav style="padding: 1rem 0;">
            <a href="admin_dashboard.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Dashboard
            </a>
            <a href="add_mod.php" class="nav-link">
                <i class="fas fa-plus"></i>Add Mod Name
            </a>
            <a href="upload_mod.php" class="nav-link">
                <i class="fas fa-upload"></i>Upload Mod APK
            </a>
            <a href="manage_mods.php" class="nav-link">
                <i class="fas fa-cog"></i>Manage Mods
            </a>
            <a href="add_license.php" class="nav-link">
                <i class="fas fa-key"></i>Add License Key
            </a>
            <a href="licence_key_list.php" class="nav-link active">
                <i class="fas fa-list"></i>License Key List
            </a>
            <a href="available_keys.php" class="nav-link">
                <i class="fas fa-unlock"></i>Available Keys
            </a>
            <a href="manage_users.php" class="nav-link">
                <i class="fas fa-users"></i>Manage Users
            </a>
            <a href="add_balance.php" class="nav-link">
                <i class="fas fa-plus-circle"></i>Add Balance
            </a>
            <a href="transactions.php" class="nav-link">
                <i class="fas fa-exchange-alt"></i>Transaction
            </a>
            <a href="referral_codes.php" class="nav-link">
                <i class="fas fa-link"></i>Referral Code
            </a>
            <a href="logout.php" class="nav-link" style="color: #ef4444; margin-top: 2rem;">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </nav>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-9 col-lg-10 main-content">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show success-alert" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show error-alert" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-key me-2" style="color: var(--purple);"></i>License Key List</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Manage and view all license keys with advanced filtering</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="add_license.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add License Key
                            </a>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-filter me-2" style="color: var(--purple);"></i>Filter Options</h5>
                        <span class="badge bg-secondary"><?php echo count($licenseKeys); ?> Total Keys</span>
                    </div>
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="mod_id" class="form-label fw-bold">
                                <i class="fas fa-list me-2" style="color: var(--purple);"></i>Filter by Mod:
                            </label>
                            <select class="form-control" id="mod_id" name="mod_id">
                                <option value="">All Mods</option>
                                <?php foreach ($mods as $mod): ?>
                                <option value="<?php echo $mod['id']; ?>" 
                                        <?php echo $filters['mod_id'] == $mod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mod['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label fw-bold">
                                <i class="fas fa-toggle-on me-2" style="color: var(--purple);"></i>Filter by Status:
                            </label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="available" <?php echo $filters['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="sold" <?php echo $filters['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label fw-bold">
                                <i class="fas fa-search me-2" style="color: var(--purple);"></i>Search License Key or Mod
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search...">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Apply
                            </button>
                            <a href="licence_key_list.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Bulk Delete Button -->
                <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="button" id="bulkDeleteBtn" class="btn btn-danger" onclick="confirmBulkDelete()" style="display: none;">
                        <i class="fas fa-trash me-2"></i><span id="bulkBtnText">Delete Selected</span>
                    </button>
                </div>
                
                <!-- License Keys Table -->
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-table me-2" style="color: var(--purple);"></i>License Key Overview</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-success">Available: <?php echo count(array_filter($licenseKeys, function($k) { return $k['status'] === 'available'; })); ?></span>
                            <span class="badge bg-danger">Sold: <?php echo count(array_filter($licenseKeys, function($k) { return $k['status'] === 'sold'; })); ?></span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="checkbox-column"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll(this)"></th>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-tag me-2"></i>Mod Name</th>
                                    <th><i class="fas fa-key me-2"></i>License Key</th>
                                    <th><i class="fas fa-clock me-2"></i>Duration</th>
                                    <th><i class="fas fa-rupee-sign me-2"></i>Price (INR)</th>
                                    <th><i class="fas fa-toggle-on me-2"></i>Status</th>
                                    <th><i class="fas fa-calendar me-2"></i>Created At</th>
                                    <th><i class="fas fa-cog me-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($licenseKeys)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5" style="color: var(--text-secondary);">
                                        <i class="fas fa-key fa-3x mb-3"></i><br>
                                        <h6>No license keys found</h6>
                                        <p class="mb-3">Try adjusting your filters or add some license keys</p>
                                        <a href="add_license.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Add First License Key
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($licenseKeys as $key): ?>
                                    <tr>
                                        <td class="checkbox-column"><input type="checkbox" class="form-check-input key-checkbox" value="<?php echo $key['id']; ?>" onchange="updateBulkDelete()"></td>
                                        <td><strong>#<?php echo $key['id']; ?></strong></td>
                                        <td><strong><?php echo htmlspecialchars($key['mod_name']); ?></strong></td>
                                        <td>
                                            <span class="license-key"><?php echo htmlspecialchars($key['license_key']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($key['price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $key['status'] === 'available' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($key['status']); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-secondary);"><?php echo formatDate($key['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1" 
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')" 
                                                    title="Copy Key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteKey(<?php echo $key['id']; ?>)" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scroll-restore.js"></script>
    <style>
        /* Smooth Modal Animations */
        .modal.fade .modal-dialog {
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
            transform: translateY(50px) scale(0.95);
            opacity: 0;
        }
        
        .modal.show .modal-dialog {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        
        .modal-backdrop.fade {
            transition: opacity 0.4s ease !important;
        }
        
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        /* SweetAlert2 Animations */
        .swal2-popup {
            animation: smoothPopup 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        }
        
        @keyframes smoothPopup {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('hidden');
            document.body.style.overflow = sidebar.classList.contains('hidden') ? '' : 'hidden';
        }
        
        // Close sidebar when link is clicked on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    toggleSidebar();
                }
            });
        });
        
        // Multi-select delete functionality
        let selectedKeys = [];
        
        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.key-checkbox').forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkDelete();
        }
        
        function updateBulkDelete() {
            selectedKeys = Array.from(document.querySelectorAll('.key-checkbox:checked')).map(cb => cb.value);
            const bulkBtn = document.getElementById('bulkDeleteBtn');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            if (selectedKeys.length > 0) {
                bulkBtn.style.display = 'inline-block';
                bulkBtn.innerHTML = `<i class="fas fa-trash me-2"></i><span>Delete Selected (${selectedKeys.length})</span>`;
                selectAllCheckbox.indeterminate = selectedKeys.length > 0 && selectedKeys.length < document.querySelectorAll('.key-checkbox').length;
            } else {
                bulkBtn.style.display = 'none';
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            }
        }
        
        function confirmBulkDelete() {
            const currentSelected = Array.from(document.querySelectorAll('.key-checkbox:checked')).map(cb => cb.value);
            
            if (currentSelected.length === 0) {
                Swal.fire({
                    title: 'No Selection',
                    html: 'Please select at least one key to delete',
                    icon: 'info',
                    confirmButtonColor: '#8b5cf6',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'swal-delete-popup',
                        confirmButton: 'swal-delete-confirm'
                    }
                });
                return;
            }
            
            Swal.fire({
                title: `Delete ${currentSelected.length} Key(s)?`,
                html: `<div style="text-align: left; color: var(--text-secondary);">
                    <p style="font-size: 0.95rem; margin-bottom: 1rem;">You are about to permanently delete <strong style="color: var(--text-primary);">${currentSelected.length}</strong> license key(s).</p>
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong style="color: #991b1b;"><i class="fas fa-exclamation-circle me-2"></i>This action cannot be undone.</strong>
                    </div>
                    <div style="background: rgba(139, 92, 246, 0.1); border-left: 4px solid #8b5cf6; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem;">
                        Keys to delete: <strong>${currentSelected.join(', ')}</strong>
                    </div>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, Delete All',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'swal-delete-popup',
                    title: 'swal-delete-title',
                    confirmButton: 'swal-delete-confirm',
                    cancelButton: 'swal-delete-cancel'
                },
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    performDelete(currentSelected);
                }
            });
        }
        
        function deleteKey(keyId) {
            Swal.fire({
                title: 'Delete License Key?',
                html: `<div style="text-align: left; color: var(--text-secondary);">
                    <p style="font-size: 0.95rem; margin-bottom: 1rem;">This license key will be permanently deleted.</p>
                    <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 1rem; border-radius: 8px;">
                        <strong style="color: #991b1b;"><i class="fas fa-exclamation-circle me-2"></i>This action cannot be undone.</strong>
                    </div>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'swal-delete-popup',
                    title: 'swal-delete-title',
                    confirmButton: 'swal-delete-confirm',
                    cancelButton: 'swal-delete-cancel'
                },
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    performDelete([keyId]);
                }
            });
        }
        
        function performDelete(keyIds) {
            console.log('Attempting to delete keys:', keyIds);
            
            Swal.fire({
                title: `Deleting ${keyIds.length} key(s)...`,
                html: `<div style="display: flex; align-items: center; justify-content: center; gap: 15px; padding: 20px;">
                    <div style="width: 30px; height: 30px; border: 3px solid rgba(139, 92, 246, 0.2); border-top: 3px solid #8b5cf6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="font-size: 1rem; font-weight: 500;">Processing deletion...</span>
                </div>`,
                icon: undefined,
                customClass: {
                    popup: 'swal-delete-popup',
                    htmlContainer: 'swal-html-container'
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    const formData = new FormData();
                    formData.append('delete_keys', '1');
                    
                    keyIds.forEach((id, idx) => {
                        console.log(`Adding key ${idx}: ${id}`);
                        formData.append('key_ids[]', id);
                    });
                    
                    // Create a temporary form and submit it the traditional way
                    const tempForm = document.createElement('form');
                    tempForm.method = 'POST';
                    tempForm.style.display = 'none';
                    
                    const deleteKeyInput = document.createElement('input');
                    deleteKeyInput.type = 'hidden';
                    deleteKeyInput.name = 'delete_keys';
                    deleteKeyInput.value = '1';
                    tempForm.appendChild(deleteKeyInput);
                    
                    keyIds.forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'key_ids[]';
                        input.value = id;
                        tempForm.appendChild(input);
                    });
                    
                    document.body.appendChild(tempForm);
                    console.log('Form ready, submitting with', keyIds.length, 'keys');
                    
                    // Add a small delay to ensure form is in DOM
                    setTimeout(() => {
                        tempForm.submit();
                    }, 100);
                }
            });
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Copied to clipboard!'
                });
            });
        }

        // Handle browser form resubmission warning with custom modal
        let isFormResubmission = false;
        
        window.addEventListener('beforeunload', function(e) {
            if (document.querySelector('form[method="POST"]') && !isFormResubmission) {
                // Don't show browser warning - we'll handle it with custom modal
                e.preventDefault();
                e.returnValue = '';
                isFormResubmission = true;
            }
        });
        
        // Handle popstate (back button) with custom modal
        window.addEventListener('popstate', function(e) {
            if (isFormResubmission) {
                showResubmissionModal();
            }
        });
        
        function showResubmissionModal() {
            Swal.fire({
                title: 'Confirm Form Resubmission',
                html: `<div style="text-align: left; color: var(--text-secondary);">
                    <div style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
                        <i class="fas fa-sync-alt" style="font-size: 2.5rem; color: #8b5cf6;"></i>
                    </div>
                    <p style="margin-bottom: 1rem;">
                        The page you're trying to access used information that you entered. Returning to that page might cause any action you took to be repeated.
                    </p>
                    <p style="color: #667eea; font-weight: 600;">
                        Do you want to continue?
                    </p>
                </div>`,
                icon: undefined,
                showCancelButton: true,
                confirmButtonColor: '#667eea',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-arrow-right me-2"></i>Continue',
                cancelButtonText: 'Go Back',
                customClass: {
                    popup: 'swal-delete-popup',
                    title: 'swal-delete-title',
                    confirmButton: 'swal-delete-confirm',
                    cancelButton: 'swal-delete-cancel'
                },
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (!result.isConfirmed) {
                    window.history.forward();
                }
            });
        }
        
        // Monitor for form submissions
        document.addEventListener('submit', function(e) {
            if (e.target.method && e.target.method.toUpperCase() === 'POST') {
                isFormResubmission = true;
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            updateBulkDelete();
        });
    </script>
</body>
</html>
