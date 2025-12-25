<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();

// Get stats directly
$stats = [
    'total_mods' => 0,
    'total_keys' => 0,
    'available_keys' => 0,
    'sold_keys' => 0,
    'total_users' => 0
];

try {
    // Get mod count
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $stats['total_mods'] = $stmt->fetchColumn();
    
    // Get key counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys");
    $stats['total_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'available'");
    $stats['available_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'sold'");
    $stats['sold_keys'] = $stmt->fetchColumn();
    
    // Get user count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetchColumn();
    
} catch (Exception $e) {
    $stats = [
        'total_mods' => 0,
        'total_keys' => 0,
        'available_keys' => 0,
        'sold_keys' => 0,
        'total_users' => 0
    ];
}

// Get recent data
$recentMods = [];
$recentUsers = [];

try {
    $stmt = $pdo->query("SELECT * FROM mods ORDER BY created_at DESC LIMIT 5");
    $recentMods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors for recent data
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-light: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --sidebar-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-light: #334155;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }
        
        .sidebar {
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-light);
            height: 100vh;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateX(0);
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 12px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: white;
            transform: translateX(2px);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1rem;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            overflow-y: auto;
            
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        .mobile-header {
            display: none;
            background: var(--card-bg);
            padding: 1rem;
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-light);
        }
        
        .mobile-toggle {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            box-shadow: var(--shadow-medium);
            transition: all 0.2s ease;
        }
        
        .mobile-toggle:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-large);
        }
        
        .stats-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stats-card.total-mods::before { background: var(--purple); }
        .stats-card.license-keys::before { background: #059669; }
        .stats-card.total-users::before { background: #0ea5e9; }
        .stats-card.sold-licenses::before { background: #f59e0b; }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-large);
        }
        
        .stats-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }
        
        .stats-icon.total-mods { background: var(--purple); }
        .stats-icon.license-keys { background: #059669; }
        .stats-icon.total-users { background: #0ea5e9; }
        .stats-icon.sold-licenses { background: #f59e0b; }
        
        .table-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-medium);
            margin-top: 2rem;
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }
        
        .table-card:hover {
            box-shadow: var(--shadow-large);
        }
        
        .table-card h5 {
            color: var(--purple);
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .page-header {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-light);
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            box-shadow: var(--shadow-medium);
        }
        
        .table {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-light);
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
            background: var(--card-bg);
        }
        
        .table tbody tr:hover {
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .table tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 1050;
            background: var(--card) !important;
            border: 2px solid var(--line) !important;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex !important;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 0;
            font-size: 20px;
            color: var(--text);
        }
        .theme-toggle:hover {
            transform: scale(1.1);
            color: var(--accent);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }
        .theme-toggle:active {
            transform: scale(0.95);
        }
        @media (max-width: 768px) {
            .theme-toggle {
                width: 50px !important;
                height: 50px !important;
                font-size: 18px !important;
                top: 12px !important;
                right: 12px !important;
                z-index: 1050 !important;
            }
        }
            color: var(--purple);
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.show {
            display: block;
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Mobile Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                background: var(--sidebar-bg);
                border-right: none;
                box-shadow: var(--shadow-large);
            }
            
            .sidebar.show {
                transform: translateX(0);
            overflow-y: auto;
            overflow-x: hidden;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .stats-card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .stats-icon {
                width: 48px;
                height: 48px;
                font-size: 1.3rem;
            }
            
            .table-card {
                padding: 1rem;
                margin-top: 1rem;
            }
            
            .table-card h5 {
                font-size: 1rem;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
