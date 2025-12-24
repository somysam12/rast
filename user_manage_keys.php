<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

// Simple helpers
function formatCurrency($amount){
    return '₹' . number_format((float)$amount, 2, '.', ',');
}
function formatDate($dt){
    if(!$dt){ return '-'; }
    return date('d M Y, h:i A', strtotime($dt));
}

// Require user login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// PDO connection
try {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('Database connection failed');
}

// Load current user
$stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if(!$user){
    session_destroy();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle key purchase with transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_key'])) {
    $modId = (int)($_POST['mod_id'] ?? 0);
    $duration = (int)($_POST['duration'] ?? 0);
    $durationType = $_POST['duration_type'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    
    if ($modId <= 0 || $duration <= 0 || empty($durationType) || $price <= 0) {
        $error = 'Invalid key selection.';
    } else {
        try {
            $pdo->beginTransaction();

            // Find an available key for this mod and duration
            $stmt = $pdo->prepare('SELECT id FROM license_keys 
                                   WHERE mod_id = ? AND duration = ? AND duration_type = ? AND price = ? AND sold_to IS NULL 
                                   LIMIT 1 FOR UPDATE');
            $stmt->execute([$modId, $duration, $durationType, $price]);
            $key = $stmt->fetch();
            
            if(!$key){
                throw new Exception('No keys available for this selection.');
            }

            // Refresh user balance with lock
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            $currentBalance = (float)$row['balance'];
            
            if ($currentBalance < $price) {
                throw new Exception('Insufficient balance.');
            }

            // Deduct and mark sold
            $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$price, $user['id']]);

            $stmt = $pdo->prepare('UPDATE license_keys SET sold_to = ?, sold_at = NOW() WHERE id = ?');
            $stmt->execute([$user['id'], $key['id']]);

            // Optional: record transaction if table exists
            try {
                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, "debit", ?, "License key purchase", NOW())');
                $stmt->execute([$user['id'], $price]);
            } catch (Throwable $ignored) {}

            $pdo->commit();
            
            // Get the purchased key details
            $stmt = $pdo->prepare('SELECT lk.license_key, lk.duration, lk.duration_type, lk.price, m.name as mod_name 
                                   FROM license_keys lk 
                                   LEFT JOIN mods m ON m.id = lk.mod_id 
                                   WHERE lk.id = ?');
            $stmt->execute([$key['id']]);
            $purchasedKey = $stmt->fetch();
            
            $success = 'License key purchased successfully!';
            $purchasedKeyData = $purchasedKey; // Store for display

            // Refresh user data to update balance
            $stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$modId = $_GET['mod_id'] ?? '';

// Get all active mods
$mods = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get available keys grouped by mod and duration
try {
    if ($modId !== '' && ctype_digit((string)$modId)) {
        $stmt = $pdo->prepare('SELECT 
                               m.name AS mod_name,
                               lk.mod_id,
                               lk.duration,
                               lk.duration_type,
                               lk.price,
                               COUNT(*) as key_count,
                               MIN(lk.id) as min_id
                               FROM license_keys lk
                               LEFT JOIN mods m ON m.id = lk.mod_id
                               WHERE lk.sold_to IS NULL AND lk.mod_id = ?
                               GROUP BY lk.mod_id, lk.duration, lk.duration_type, lk.price
                               ORDER BY m.name, lk.duration, lk.duration_type');
        $stmt->execute([$modId]);
    } else {
        $stmt = $pdo->query('SELECT 
                             m.name AS mod_name,
                             lk.mod_id,
                             lk.duration,
                             lk.duration_type,
                             lk.price,
                             COUNT(*) as key_count,
                             MIN(lk.id) as min_id
                              FROM license_keys lk
                              LEFT JOIN mods m ON m.id = lk.mod_id
                              WHERE lk.sold_to IS NULL
                             GROUP BY lk.mod_id, lk.duration, lk.duration_type, lk.price
                             ORDER BY m.name, lk.duration, lk.duration_type');
    }
    $availableKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $availableKeys = [];
}

// Get user's purchased keys
try {
    $stmt = $pdo->prepare('SELECT lk.*, m.name AS mod_name
                           FROM license_keys lk
                           LEFT JOIN mods m ON m.id = lk.mod_id
                           WHERE lk.sold_to = ?
                           ORDER BY lk.sold_at DESC');
    $stmt->execute([$user['id']]);
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
    <title>Manage Keys - Mod APK Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Enhanced theme with modern colors */
        :root{
            --bg:#f8fafc; --card:#ffffff; --text:#1e293b; --muted:#64748b; --line:#e2e8f0;
            --accent:#8b5cf6; --accent-600:#7c3aed; --accent-100:#f3e8ff;
            --gradient-primary: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-warning: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-info: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --line: #334155;
        }
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f9fafb;
            overflow-x: hidden;
            color: #374151;
            transition: all 0.3s ease;
        }
        
        .page-header {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .page-header h2 {
            color: var(--text);
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: var(--muted);
            font-size: 1rem;
        }
        
        .sidebar {
            background: #ffffff;
            color: #374151;
            border-right: 1px solid #e5e7eb;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: #6b7280;
            padding: 12px 20px;
            margin: 4px 16px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1003;
            pointer-events: auto;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 500;
            border-radius: 8px;
        }
        
        .sidebar .nav-link i {
            color: #6b7280;
            width: 20px;
            margin-right: 12px;
            font-size: 1.1em;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .sidebar .nav-link:hover i {
            color: #374151;
        }
        
        .sidebar .nav-link.active {
            background: #7c3aed;
            color: white;
        }
        
        .sidebar .nav-link.active i {
            color: white;
        } 

        .filter-card, .table-card, .key-card {
            background: var(--card);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .filter-card::before, .table-card::before, .key-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .filter-card:hover, .table-card:hover, .key-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-large);
        }
        
        .table-card h5 {
            color: var(--text);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }
        
        .table-card h5 i {
            margin-right: 10px;
            color: var(--accent);
        }
        
        .btn-primary {
            background: var(--gradient-primary);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            color: white;
        }
        
        .btn-success {
            background: var(--gradient-success);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--accent);
            color: var(--accent);
            border-radius: 12px;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--accent);
            color: white;
            transform: translateY(-1px);
        }
        
        .btn-outline-secondary {
            border: 2px solid var(--line);
            color: var(--muted);
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--card);
        }
        
        .btn-outline-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-100);
            transform: translateY(-1px);
        }
        
        .badge {
            border-radius: 20px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid var(--line);
            padding: 12px 16px;
            transition: all 0.3s ease;
            background: var(--card);
            color: var(--text);
        }
        
        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            background: var(--card);
            color: var(--text);
        }
        
        .license-key {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            font-size: 0.9em;
            background: var(--accent-100);
            padding: 12px 16px;
            border-radius: 12px;
            border: 2px solid var(--line);
            color: var(--accent);
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .license-key:hover {
            background: var(--accent);
            color: white;
            transform: scale(1.02);
        }
        
        .key-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .key-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: var(--shadow-large);
        } 

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content-with-header {
            margin-top: 80px;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        .mobile-header {
            display: none !important;
        }
        
        .mobile-toggle {
            background: var(--gradient-primary);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            transition: all 0.3s ease;
            font-size: 1.1rem;
            min-width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-toggle:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-large);
        }
        
        .mobile-toggle:active {
            transform: translateY(0) scale(0.95);
        }
        
        /* Enhanced Table Styling */
        .table {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--line);
            box-shadow: var(--shadow-light);
        }
        
        .table thead th {
            background: var(--gradient-primary);
            color: white;
            border: none;
            font-weight: 700;
            padding: 20px 16px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--line);
        }
        
        .table tbody tr:hover {
            background: var(--accent-100);
            transform: scale(1.01);
        }
        
        .table tbody td {
            padding: 20px 16px;
            border: none;
            color: var(--text);
            vertical-align: middle;
            font-weight: 500;
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Enhanced Form Styling */
        .form-label {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-select {
            border-radius: 12px;
            border: 2px solid var(--line);
            padding: 12px 16px;
            transition: all 0.3s ease;
            background: var(--card);
            color: var(--text);
        }
        
        .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        /* Enhanced Alert Styling */
        .alert {
            border-radius: 16px;
            border: none;
            padding: 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: var(--shadow-light);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .btn-outline-success {
            border: 2px solid #10b981;
            color: #10b981;
            border-radius: 8px;
            padding: 6px 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
        }
        
        .btn-outline-success:hover {
            background: #10b981;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }
        
        /* Purchased Key Display */
        .purchased-key-display {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .license-key-display .license-key:hover {
            background: #0ea5e9 !important;
            color: white !important;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        
        .license-key-display .license-key:hover i {
            opacity: 1 !important;
        }
        
        /* Enhanced Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--accent);
            opacity: 0.7;
        }
        
        .empty-state h5 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text);
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        /* Enhanced User Avatar */
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2em;
            box-shadow: var(--shadow-medium);
            transition: all 0.3s ease;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-large);
        }
        
        /* Modern Header Styling - Exact Match */
        .modern-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1002;
            height: 60px;
            box-sizing: border-box;
        }
        
        .hamburger-menu {
            background: none;
            border: none;
            color: #7c3aed;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hamburger-menu:hover {
            color: #6d28d9;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-avatar-header {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #7c3aed;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .user-avatar-header:hover {
            background: #6d28d9;
        }
        
        .dropdown-arrow {
            color: #7c3aed;
            font-size: 0.7rem;
            transition: transform 0.3s ease;
            margin-left: 4px;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow-large);
            min-width: 200px;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1003;
        }
        
        .user-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            padding: 0.75rem 1rem;
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        
        .dropdown-item:hover {
            background: var(--accent-100);
            color: var(--accent);
        }
        
        .dropdown-item i {
            width: 16px;
            text-align: center;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-right: 0.5rem;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: var(--muted);
            margin: 0;
        }
        
        /* Adjust main content for new header */
        .main-content-with-header {
            margin-top: 60px;
        }
        
        /* Theme Toggle Button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--muted);
            box-shadow: var(--shadow-medium);
            backdrop-filter: blur(20px);
        }
        
        .theme-toggle:hover {
            color: var(--accent);
            box-shadow: var(--shadow-large);
            transform: scale(1.1);
        }
        
        /* Enhanced Page Header */
        .page-header {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }
        
        .page-header h2 {
            color: #374151;
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .page-header h2 i {
            margin-right: 12px;
            color: #7c3aed;
            font-size: 1.5rem;
        }
        
        .page-header p {
            color: #6b7280;
            font-size: 1.1rem;
            margin-bottom: 0;
        }
        
        .balance-info {
            background: var(--accent-100);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid var(--line);
        }
        
        .balance-info .balance-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .balance-info .balance-label {
            font-size: 0.9rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Enhanced Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        /* Enhanced Key Card Hover Effects */
        .key-card {
            position: relative;
            overflow: hidden;
        }
        
        .key-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        
        .key-card:hover::before {
            left: 100%;
        }
        
        /* Enhanced Button Ripple Effect */
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:active::before {
            width: 300px;
            height: 300px;
        }
        
        /* Enhanced Table Row Hover */
        .table tbody tr {
            position: relative;
        }
        
        .table tbody tr::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--gradient-primary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .table tbody tr:hover::before {
            transform: scaleY(1);
        }
        
        /* Duration Option Styling */
        .duration-option {
            transition: all 0.3s ease;
            background: var(--card);
            padding: 1rem !important;
        }
        
        .duration-option:hover {
            border-color: var(--accent) !important;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .duration-option.selected {
            border-color: var(--accent) !important;
            background: var(--accent-100);
            box-shadow: var(--shadow-medium);
        }
        
        .duration-option .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }
        
        .duration-option .form-check-input:checked + .form-check-label {
            color: var(--accent);
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .key-card {
                padding: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            .duration-option {
                padding: 0.75rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .duration-option .form-check {
                margin-bottom: 0.5rem !important;
            }
            
            .duration-option .badge {
                font-size: 0.7rem !important;
                padding: 4px 8px !important;
            }
            
            .duration-option div[style*="font-size: 1.25rem"] {
                font-size: 1rem !important;
            }
            
            .duration-option div[style*="font-size: 0.9rem"] {
                font-size: 0.8rem !important;
            }
            
            .btn-lg {
                padding: 0.75rem 1.5rem !important;
                font-size: 0.9rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .key-card {
                padding: 0.75rem !important;
            }
            
            .duration-option {
                padding: 0.5rem !important;
            }
            
            .duration-option .form-check {
                margin-bottom: 0.25rem !important;
            }
            
            .duration-option .badge {
                font-size: 0.65rem !important;
                padding: 3px 6px !important;
            }
            
            .duration-option div[style*="font-size: 1.25rem"] {
                font-size: 0.9rem !important;
            }
            
            .duration-option div[style*="font-size: 0.9rem"] {
                font-size: 0.75rem !important;
            }
            
            .btn-lg {
                padding: 0.5rem 1rem !important;
                font-size: 0.8rem !important;
            }
        }
        
        /* Force mobile header visibility on mobile devices */
        @media screen and (max-width: 991.98px) {
            .mobile-header {
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1001 !important;
                background: var(--card) !important;
                padding: 1rem !important;
                box-shadow: 0 1px 0 rgba(0,0,0,.02) !important;
                border-bottom: 1px solid var(--line) !important;
            }
        }

        /* Responsive + mobile sidebar */
        .container-fluid,.row{width:100%;margin:0;}
        @media (max-width: 991.98px){
            .main-content{margin-left:0;width:100%;padding:0.5rem;}
            .main-content-with-header{margin-top:60px;}
            .sidebar{width:100%;left:0;right:0;transform:translateX(-100%);background:#ffffff;border-right:none;border-bottom:1px solid #e5e7eb;z-index:1002;pointer-events:none;} 
            .sidebar.show{transform:translateX(0);pointer-events:auto;} 
            .mobile-overlay{z-index:1001;}
            .mobile-overlay.show{display:block;pointer-events:auto;}
            .modern-header{display:flex !important;}
        }
        @media (max-width: 480px){
            .page-header {
                padding: 1rem !important;
                margin-bottom: 1rem !important;
            }
            .page-header .d-flex{flex-direction:column;align-items:flex-start;gap:.35rem;}
            .col-md-4,.col-md-6,.col-lg-4{flex:0 0 100%;max-width:100%;}
            .balance-info {
                padding: 0.5rem !important;
                margin-top: 0.5rem !important;
            }
            .balance-info .balance-amount {
                font-size: 1.2rem !important;
            }
        }
        /* Overlay base (hidden by default) */
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1001;pointer-events:none;}
        .mobile-overlay.show{pointer-events:auto;}
        .user-avatar{width:50px;height:50px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:1.1em;} 
        
        /* Add Key Button Styling */
        .add-key-button {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
            background: var(--gradient-success);
            border: none;
            border-radius: 50px;
            padding: 12px 24px;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: var(--shadow-large);
            cursor: pointer;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-20px);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-key-button.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .add-key-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 15px 25px rgba(16, 185, 129, 0.4);
        }
        
        .add-key-button i {
            font-size: 1rem;
        }
        
        /* Mobile positioning */
        @media (max-width: 768px) {
            .add-key-button {
                top: 70px;
                right: 15px;
                padding: 10px 20px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Header -->
    <div class="modern-header">
        <button class="hamburger-menu" onclick="toggleSidebar()" title="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
        
        <div class="user-section">
            <div class="user-avatar-header" onclick="toggleUserDropdown()" title="User Menu">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
            <i class="fas fa-chevron-down dropdown-arrow" onclick="toggleUserDropdown()" style="cursor: pointer;"></i>
                
                <!-- User Dropdown -->
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-item" style="background: var(--accent-100); color: var(--accent); font-weight: 600; cursor: default;">
                        <i class="fas fa-user"></i>Profile
                    </div>
                    <a href="user_dashboard.php" class="dropdown-item">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a href="user_manage_keys.php" class="dropdown-item">
                        <i class="fas fa-key"></i>Manage Keys
                    </a>
                    <a href="user_generate.php" class="dropdown-item">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a href="user_balance.php" class="dropdown-item">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a href="user_transactions.php" class="dropdown-item">
                        <i class="fas fa-exchange-alt"></i>Transactions
                    </a>
                    <a href="user_applications.php" class="dropdown-item">
                        <i class="fas fa-mobile-alt"></i>Applications
                    </a>
                    <hr style="margin: 0.5rem 0; border-color: var(--line);">
                    <a href="user_settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a href="javascript:history.back()" class="dropdown-item">
                        <i class="fas fa-arrow-left"></i>Back
                    </a>
                    <hr style="margin: 0.5rem 0; border-color: var(--line);">
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Theme Toggle -->
    <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <!-- Add Key Button (appears after copying) -->
    <button class="add-key-button" id="addKeyButton" onclick="goToGeneratePage()" title="Add New Key">
        <i class="fas fa-plus"></i>
        <span>Add Key</span>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar()"></div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-4">
                    <h4 style="color: #374151; font-weight: 600; margin: 0; display: flex; align-items: center;">
                        <i class="fas fa-user me-2" style="color: #6b7280;"></i>User Panel
                    </h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="user_manage_keys.php">
                        <i class="fas fa-key"></i>Manage Keys
                    </a>
                    <a class="nav-link" href="user_generate.php">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a class="nav-link" href="user_balance.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="user_applications.php">
                        <i class="fas fa-mobile-alt"></i>Applications
                    </a>
                    <a class="nav-link" href="user_settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content main-content-with-header">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center mb-2">
                                <button class="btn btn-outline-secondary me-3" onclick="history.back()" title="Go Back">
                                    <i class="fas fa-arrow-left me-1"></i>Back
                                </button>
                                <h2 class="mb-0"><i class="fas fa-key me-2"></i>Manage Keys</h2>
                            </div>
                            <p>Browse available keys and manage your purchases</p>
                            <div class="balance-info">
                                <div class="balance-amount"><?php echo formatCurrency($user['balance']); ?></div>
                                <div class="balance-label">Current Balance</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold" style="color: var(--text); font-size: 1.1rem;"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small style="color: var(--muted);">User Account</small>
                            </div>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span><?php echo htmlspecialchars($success); ?></span>
                                </div>
                                
                                <?php if (isset($purchasedKeyData) && $purchasedKeyData): ?>
                                <div class="purchased-key-display">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="license-key-display">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-key me-2" style="color: #10b981;"></i>
                                                    <strong>Your New License Key:</strong>
                                                </div>
                                                <div class="license-key" onclick="copyToClipboard('<?php echo htmlspecialchars($purchasedKeyData['license_key']); ?>')" 
                                                     style="cursor: pointer; font-family: 'JetBrains Mono', 'Courier New', monospace; font-size: 1.1em; background: #f0f9ff; border: 2px solid #0ea5e9; color: #0369a1; padding: 12px 16px; border-radius: 8px; margin-bottom: 10px; transition: all 0.3s ease;">
                                                    <?php echo htmlspecialchars($purchasedKeyData['license_key']); ?>
                                                    <i class="fas fa-copy ms-2" style="opacity: 0.7;"></i>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Click to copy • <?php echo $purchasedKeyData['mod_name']; ?> • <?php echo $purchasedKeyData['duration'] . ' ' . ucfirst($purchasedKeyData['duration_type']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-center justify-content-end">
                                            <button class="btn btn-outline-success" onclick="copyToClipboard('<?php echo htmlspecialchars($purchasedKeyData['license_key']); ?>')" title="Copy Key">
                                                <i class="fas fa-copy me-1"></i>Copy Key
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-3">
                                <button class="btn btn-sm btn-outline-success" onclick="showAddKeyButton()" title="Add More Keys">
                                    <i class="fas fa-plus me-1"></i>Add Key
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    </div>
                    <script>
                        // Show Add Key button after successful purchase
                        document.addEventListener('DOMContentLoaded', function() {
                            setTimeout(() => {
                                showAddKeyButton();
                            }, 1500);
                        });
                    </script>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="filter-card fade-in">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="mod_id" class="form-label">Filter by Mod:</label>
                            <select class="form-control" id="mod_id" name="mod_id">
                                <option value="">All Mods</option>
                                <?php foreach ($mods as $mod): ?>
                                <option value="<?php echo $mod['id']; ?>" 
                                        <?php echo $modId == $mod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mod['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="user_manage_keys.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Available Keys -->
                <div class="table-card fade-in">
                    <h5><i class="fas fa-unlock me-2"></i>Available Keys</h5>
                    <div class="row">
                        <?php if (empty($availableKeys)): ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="fas fa-key"></i>
                                <h5>No Keys Available</h5>
                                <p>No keys are currently available for the selected mod. Please try a different mod or check back later.</p>
                            </div>
                        </div>
                        <?php else: ?>
                            <?php 
                            // Group keys by mod name
                            $groupedKeys = [];
                            foreach ($availableKeys as $key) {
                                $groupedKeys[$key['mod_name']][] = $key;
                            }
                            ?>
                            
                            <?php foreach ($groupedKeys as $modName => $keys): ?>
                            <div class="col-12 mb-4">
                                <div class="key-card">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 style="color: var(--accent); font-weight: 700; margin: 0;">
                                            <i class="fas fa-mobile-alt me-2"></i><?php echo htmlspecialchars($modName); ?>
                                        </h5>
                                        <span class="badge" style="background: var(--gradient-primary); color: white; font-size: 0.9rem;">
                                            <?php echo count($keys); ?> Duration Options
                                        </span>
                                    </div>
                                    
                                    <div class="row">
                                        <?php foreach ($keys as $key): ?>
                                        <div class="col-6 col-md-4 col-lg-3 mb-2">
                                            <div class="duration-option" style="border: 2px solid var(--line); border-radius: 8px; padding: 1rem; text-align: center; transition: all 0.3s ease; cursor: pointer;" 
                                                 onclick="selectDuration(this, <?php echo $key['mod_id']; ?>, <?php echo $key['duration']; ?>, '<?php echo $key['duration_type']; ?>', <?php echo $key['price']; ?>)">
                                                
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" name="duration_<?php echo $key['mod_id']; ?>" 
                                                           id="duration_<?php echo $key['mod_id']; ?>_<?php echo $key['duration']; ?>_<?php echo $key['duration_type']; ?>">
                                                </div>
                                                
                                    <div class="mb-2">
                                                    <span class="badge" style="background: var(--gradient-success); color: white; font-size: 0.7rem; padding: 4px 8px;">
                                                        <?php echo $key['duration']; ?> <?php echo ucfirst($key['duration_type']); ?>
                                        </span>
                                    </div>
                                                
                                    <div class="mb-2">
                                                    <div style="color: var(--muted); font-size: 0.8rem;">Price</div>
                                                    <div style="color: var(--text); font-size: 1.1rem; font-weight: 700;"><?php echo formatCurrency($key['price']); ?></div>
                                    </div>
                                                
                                                <div class="mb-1">
                                                    <span class="badge" style="background: var(--gradient-info); color: white; font-size: 0.7rem; padding: 4px 8px;">
                                                        Available: <?php echo $key['key_count']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="text-center mt-4">
                                        <form method="POST" id="purchaseForm_<?php echo $keys[0]['mod_id']; ?>" style="display: none;">
                                            <input type="hidden" name="purchase_key" value="1">
                                            <input type="hidden" name="mod_id" id="selected_mod_id_<?php echo $keys[0]['mod_id']; ?>">
                                            <input type="hidden" name="duration" id="selected_duration_<?php echo $keys[0]['mod_id']; ?>">
                                            <input type="hidden" name="duration_type" id="selected_duration_type_<?php echo $keys[0]['mod_id']; ?>">
                                            <input type="hidden" name="price" id="selected_price_<?php echo $keys[0]['mod_id']; ?>">
                                            <button type="submit" class="btn btn-success btn-lg" 
                                                    onclick="return confirmPurchase('<?php echo htmlspecialchars($modName); ?>')">
                                                <i class="fas fa-shopping-cart me-2"></i>Purchase Selected License
                                        </button>
                                    </form>
                                        <div id="selectMessage_<?php echo $keys[0]['mod_id']; ?>" class="text-muted">
                                            <i class="fas fa-hand-pointer me-2"></i>Select a duration option above to purchase
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- My Purchased Keys -->
                <div class="table-card fade-in">
                    <h5><i class="fas fa-shopping-bag me-2"></i>My Purchased Keys</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mod Name</th>
                                    <th>License Key</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Purchased Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($purchasedKeys)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 4rem 2rem;">
                                        <div class="empty-state">
                                            <i class="fas fa-shopping-bag"></i>
                                            <h5>No Purchased Keys</h5>
                                            <p>You haven't purchased any keys yet. Browse available keys above to get started!</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($purchasedKeys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['mod_name']); ?></td>
                                        <td>
                                            <div class="license-key"><?php echo htmlspecialchars($key['license_key']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($key['price']); ?></td>
                                        <td><?php echo formatDate($key['sold_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')" 
                                                    title="Copy Key">
                                                <i class="fas fa-copy"></i>
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
    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
                document.getElementById('darkModeIcon').className = 'fas fa-sun';
            }
        });
        
        // Duration selection functionality
        function selectDuration(element, modId, duration, durationType, price) {
            // Remove selected class from all options in this mod group
            const modGroup = element.closest('.key-card');
            const allOptions = modGroup.querySelectorAll('.duration-option');
            allOptions.forEach(option => {
                option.classList.remove('selected');
                const radio = option.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            const radio = element.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            
            // Update form values
            document.getElementById('selected_mod_id_' + modId).value = modId;
            document.getElementById('selected_duration_' + modId).value = duration;
            document.getElementById('selected_duration_type_' + modId).value = durationType;
            document.getElementById('selected_price_' + modId).value = price;
            
            // Show purchase form and hide message
            document.getElementById('purchaseForm_' + modId).style.display = 'block';
            document.getElementById('selectMessage_' + modId).style.display = 'none';
        }
        
        // Confirm purchase
        function confirmPurchase(modName) {
            return confirm('Are you sure you want to purchase a license key for ' + modName + '?');
        }
        
        // Toggle user dropdown
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const arrow = document.querySelector('.dropdown-arrow');
            
            dropdown.classList.toggle('show');
            arrow.style.transform = dropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const userSection = document.querySelector('.user-section');
            
            if (!userSection.contains(event.target)) {
                dropdown.classList.remove('show');
                document.querySelector('.dropdown-arrow').style.transform = 'rotate(0deg)';
            }
        });
        
        function toggleSidebar(){
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.getElementById('overlay');
            var mainContent = document.querySelector('.main-content');
            var body = document.body;
            
            // Toggle sidebar visibility
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            
            // Prevent body scroll when sidebar is open on mobile
            if (window.innerWidth <= 991) {
                if (sidebar.classList.contains('show')) {
                    body.style.overflow = 'hidden';
                    // Ensure sidebar is clickable
                    sidebar.style.pointerEvents = 'auto';
                    sidebar.style.zIndex = '1002';
                } else {
                    body.style.overflow = '';
                    sidebar.style.pointerEvents = 'none';
                }
            }
            
            if (window.innerWidth > 991) {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('full-width');
            }
            
            // Add smooth transition
            sidebar.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Create a temporary toast notification
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: var(--gradient-success);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 12px;
                    box-shadow: var(--shadow-large);
                    z-index: 9999;
                    font-weight: 600;
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                `;
                toast.innerHTML = '<i class="fas fa-check me-2"></i>License key copied to clipboard!';
                document.body.appendChild(toast);
                
                // Animate in
                setTimeout(() => {
                    toast.style.transform = 'translateX(0)';
                }, 100);
                
                // Show Add Key button after copying
                showAddKeyButton();
                
                // Remove toast after 3 seconds
                setTimeout(() => {
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        document.body.removeChild(toast);
                    }, 300);
                }, 3000);
            }, function(err) {
                console.error('Could not copy text: ', err);
                alert('Failed to copy license key. Please try again.');
            });
        }
        
        // Show Add Key button
        function showAddKeyButton() {
            const addKeyButton = document.getElementById('addKeyButton');
            addKeyButton.classList.add('show');
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                addKeyButton.classList.remove('show');
            }, 10000);
        }
        
        // Navigate to generate page
        function goToGeneratePage() {
            window.location.href = 'user_generate.php';
        }
        
        // Hide Add Key button when clicking outside
        document.addEventListener('click', function(event) {
            const addKeyButton = document.getElementById('addKeyButton');
            if (!addKeyButton.contains(event.target) && addKeyButton.classList.contains('show')) {
                addKeyButton.classList.remove('show');
            }
        });
        // Ensure mobile header is visible on mobile devices
        function checkMobileView() {
            const mobileHeader = document.querySelector('.mobile-header');
            const isMobile = window.innerWidth <= 991 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                mobileHeader.style.display = 'flex';
                mobileHeader.style.position = 'fixed';
                mobileHeader.style.top = '0';
                mobileHeader.style.left = '0';
                mobileHeader.style.right = '0';
                mobileHeader.style.width = '100%';
                mobileHeader.style.zIndex = '1001';
            } else {
                mobileHeader.style.display = 'none';
            }
        }
        
        // Check on load and resize
        document.addEventListener('DOMContentLoaded', checkMobileView);
        window.addEventListener('resize', checkMobileView);
        
        // Force mobile header visibility on small screens
        setTimeout(() => {
            if (window.innerWidth <= 991) {
                const mobileHeader = document.querySelector('.mobile-header');
                mobileHeader.style.display = 'flex';
                mobileHeader.style.position = 'fixed';
                mobileHeader.style.top = '0';
                mobileHeader.style.left = '0';
                mobileHeader.style.right = '0';
                mobileHeader.style.width = '100%';
                mobileHeader.style.zIndex = '1001';
            }
        }, 100);
        
        // Emergency mobile header fix
        function forceMobileHeader() {
            const mobileHeader = document.querySelector('.mobile-header');
            if (mobileHeader && window.innerWidth <= 991) {
                mobileHeader.style.cssText = `
                    display: flex !important;
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    width: 100% !important;
                    z-index: 1001 !important;
                    background: var(--card) !important;
                    padding: 1rem !important;
                    box-shadow: 0 1px 0 rgba(0,0,0,.02) !important;
                    border-bottom: 1px solid var(--line) !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                `;
            }
        }
        
        // Run on load and after a delay
        document.addEventListener('DOMContentLoaded', forceMobileHeader);
        setTimeout(forceMobileHeader, 500);
        window.addEventListener('resize', forceMobileHeader);
        
        // Auto-close sidebar on link click (mobile)
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link){
            link.addEventListener('click', function(e){
                // Ensure the click event works
                e.stopPropagation();
                
                if (window.innerWidth <= 991) {
                    // Add small delay to ensure click registers
                    setTimeout(() => {
                        toggleSidebar();
                    }, 100);
                }
            });
            
            // Add touch event support for mobile
            link.addEventListener('touchend', function(e){
                e.preventDefault();
                e.stopPropagation();
                
                if (window.innerWidth <= 991) {
                    // Trigger click event
                    link.click();
                }
            });
        });
    </script>
</body>
</html>