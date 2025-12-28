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
            color: var(--text-main);
            overflow-x: hidden;
            position: relative;
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

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-right: 1.5px solid var(--border-light);
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.3s ease;
            padding: 1.5rem 0;
        }

        .sidebar.hidden {
            transform: translateX(-100%);
        }

        .sidebar-brand {
            padding: 0 1.5rem 2rem;
            border-bottom: 2px solid rgba(139, 92, 246, 0.4);
            margin-bottom: 1.5rem;
        }

        .sidebar-brand h4 {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .sidebar-brand h4 i {
            color: var(--primary);
        }

        .sidebar-brand p {
            color: var(--text-dim);
            font-size: 0.85rem;
            margin: 0;
        }

        .sidebar .nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            padding: 0 1rem;
        }

        .sidebar .nav-link {
            color: var(--text-dim);
            padding: 12px 16px;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            font-size: 0.9rem;
            border: 1px solid transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            transform: translateX(4px);
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .main-content.full-width {
            margin-left: 0;
        }

        .mobile-header {
            display: none;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-light);
        }

        .mobile-toggle {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: #06b6d4;
            padding: 0.75rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.3);
            font-weight: 700;
        }

        .mobile-toggle:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(139, 92, 246, 0.5);
            filter: brightness(1.15);
        }

        .glass-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1.5px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 
                0 0 60px rgba(139, 92, 246, 0.15),
                0 0 20px rgba(6, 182, 212, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            animation: borderGlow 4s ease-in-out infinite;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .glass-card > * {
            position: relative;
            z-index: 2;
        }

        .glass-card:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 0 80px rgba(139, 92, 246, 0.25),
                0 0 40px rgba(6, 182, 212, 0.1);
        }

        @keyframes borderGlow {
            0%, 100% {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.3), 0 0 40px rgba(139, 92, 246, 0.1);
            }
            50% {
                box-shadow: 0 0 30px rgba(139, 92, 246, 0.5), 0 0 60px rgba(139, 92, 246, 0.2);
            }
        }

        .page-header {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(6, 182, 212, 0.15));
            border: 2px solid;
            border-image: linear-gradient(135deg, #8b5cf6, #06b6d4) 1;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 0 50px rgba(139, 92, 246, 0.2), 0 0 30px rgba(6, 182, 212, 0.1);
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            color: #06b6d4;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-shadow: 0 0 20px rgba(6, 182, 212, 0.3);
        }

        .page-header h1 i {
            color: #8b5cf6;
            font-size: 2.2rem;
            text-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
        }

        .page-header p {
            color: #8b5cf6;
            font-size: 1.1rem;
            margin: 0.75rem 0 0 0;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .page-header p strong {
            color: #06b6d4;
            font-weight: 700;
            text-shadow: 0 0 15px rgba(6, 182, 212, 0.2);
            margin-left: 0.25rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s backwards;
        }

        .stats-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1.5px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: 
                0 0 60px rgba(139, 92, 246, 0.15),
                0 0 20px rgba(6, 182, 212, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .stats-card > * {
            position: relative;
            z-index: 2;
        }

        .stats-card:hover {
            transform: translateY(-6px);
            box-shadow: 
                0 0 80px rgba(139, 92, 246, 0.25),
                0 0 40px rgba(6, 182, 212, 0.1);
        }

        .stats-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            margin: 0 auto 1rem;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stats-card h6 {
            color: var(--text-dim);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stats-card h3 {
            color: var(--secondary);
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stats-card .stat-label {
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .table-card {
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1.5px solid;
            border-image: linear-gradient(135deg, rgba(139, 92, 246, 0.5), rgba(6, 182, 212, 0.3)) 1;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 
                0 0 60px rgba(139, 92, 246, 0.15),
                0 0 20px rgba(6, 182, 212, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s backwards;
        }

        .table-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, transparent 50%, rgba(6, 182, 212, 0.05) 100%);
            pointer-events: none;
        }

        .table-card > * {
            position: relative;
            z-index: 2;
        }

        .table-card:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 0 80px rgba(139, 92, 246, 0.25),
                0 0 40px rgba(6, 182, 212, 0.1);
        }

        .table-card h5 {
            color: var(--secondary);
            margin-bottom: 1.5rem;
            font-weight: 700;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-card h5 i {
            color: var(--primary);
        }

        .table {
            background: transparent;
            border: none;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
            border-radius: 12px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }

        .table tbody tr {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: transparent;
            border: none;
        }

        .table tbody tr:hover {
            background: rgba(139, 92, 246, 0.1);
            border-radius: 12px;
        }

        .table tbody td {
            padding: 1rem;
            border: none;
            color: var(--text-main);
            border-bottom: 1px solid var(--border-light);
            font-weight: 500;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .user-card {
            background: rgba(139, 92, 246, 0.08);
            border: 1.5px solid var(--border-light);
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .user-card:hover {
            background: rgba(139, 92, 246, 0.12);
            border-color: rgba(139, 92, 246, 0.3);
            transform: translateY(-3px);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.2);
        }

        .user-card .user-avatar {
            margin: 0 auto 1rem;
        }

        .user-card h6 {
            color: var(--secondary);
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .user-card p {
            color: var(--text-dim);
            font-size: 0.9rem;
            margin: 0;
        }

        .admin-menu-container {
            position: relative;
        }

        .admin-menu-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 20px rgba(139, 92, 246, 0.3);
            position: relative;
            z-index: 1002;
        }

        .admin-menu-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 30px rgba(139, 92, 246, 0.5);
        }

        .admin-menu-btn:active {
            transform: scale(0.95);
        }

        .admin-dropdown {
            position: fixed;
            top: 60px;
            right: 20px;
            background: var(--card-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1.5px solid var(--border-light);
            border-radius: 16px;
            min-width: 200px;
            box-shadow: 0 10px 40px rgba(139, 92, 246, 0.3);
            z-index: 1003;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0.5rem 0;
        }

        .admin-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        .admin-dropdown a,
        .admin-dropdown button {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 12px 16px;
            background: none;
            border: none;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .admin-dropdown a:hover,
        .admin-dropdown button:hover {
            background: rgba(139, 92, 246, 0.1);
            color: var(--text-main);
            padding-left: 20px;
        }

        .admin-dropdown a i,
        .admin-dropdown button i {
            width: 18px;
            text-align: center;
            color: var(--primary);
        }

        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 999;
        }

        .mobile-overlay.show {
            display: block;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Tablet and iPad */
        @media (max-width: 1024px) {
            .sidebar {
                width: 260px;
            }

            .main-content {
                margin-left: 260px;
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .stats-card h3 {
                font-size: 2.2rem;
            }

            .stats-card h6 {
                font-size: 0.85rem;
            }

            .table-card h5 {
                font-size: 1.1rem;
            }
        }

        /* Large mobile and small tablet */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                border-right: none;
                padding: 1rem 0;
            }

            .sidebar.show {
                transform: translateX(0);
                z-index: 1001;
            }

            .sidebar-brand {
                padding: 1rem 1.5rem;
                margin-bottom: 1rem;
            }

            .sidebar-brand h4 {
                font-size: 1.1rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar-brand p {
                font-size: 0.75rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar .nav {
                padding: 0 0.5rem;
                gap: 0.25rem;
            }

            .sidebar .nav-link {
                padding: 10px 12px;
                font-size: 0.85rem;
                gap: 10px;
                border-radius: 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar .nav-link i {
                width: 18px;
                min-width: 18px;
                font-size: 0.95rem;
            }

            .sidebar .nav-link span {
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem 0.75rem;
            }

            .main-content.full-width {
                margin-left: 0;
            }

            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem 1rem;
            }

            .mobile-header h5 {
                font-size: 1rem;
                margin-bottom: 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }

            .page-header {
                margin-bottom: 1.5rem;
                padding: 1.5rem;
                border-radius: 16px;
            }

            .page-header h1 {
                font-size: 1.4rem;
                margin-bottom: 0.5rem;
                gap: 0.5rem;
            }

            .page-header h1 i {
                font-size: 1.4rem;
            }

            .page-header p {
                font-size: 0.9rem;
                margin: 0.5rem 0 0 0;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }

            .stats-card {
                padding: 1.25rem 1rem;
                border-radius: 16px;
            }

            .stats-card h6 {
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .stats-card h3 {
                font-size: 1.8rem;
                margin-bottom: 0.3rem;
            }

            .stats-card .stat-label {
                font-size: 0.75rem;
            }

            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
                margin: 0 auto 0.75rem;
            }

            .glass-card,
            .table-card {
                padding: 1.25rem;
                border-radius: 16px;
                margin-bottom: 1rem;
            }

            .table-card h5 {
                font-size: 1rem;
                margin-bottom: 1rem;
            }

            .table {
                font-size: 0.9rem;
            }

            .table thead th {
                padding: 0.75rem 0.5rem;
                font-size: 0.75rem;
            }

            .table tbody td {
                padding: 0.75rem 0.5rem;
            }

            .user-card {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }

            .user-card .user-avatar {
                width: 48px;
                height: 48px;
                font-size: 1rem;
                margin: 0 auto 0.75rem;
            }

            .user-card h6 {
                font-size: 0.9rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-card p {
                font-size: 0.8rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .d-flex > div {
                min-width: 0;
            }

            .table tbody td .d-flex {
                gap: 0.5rem;
            }

            .table tbody td .d-flex > div {
                overflow: hidden;
            }

            .table tbody td .d-flex div div {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-size: 0.9rem;
            }

            .table tbody td .d-flex small {
                font-size: 0.7rem;
            }
        }

        /* Small mobile devices */
        @media (max-width: 480px) {
            .sidebar {
                width: 100%;
            }

            .sidebar-brand {
                padding: 0.75rem 1rem;
                margin-bottom: 0.75rem;
            }

            .sidebar-brand h4 {
                font-size: 1rem;
                margin-bottom: 0.15rem;
            }

            .sidebar-brand p {
                font-size: 0.7rem;
            }

            .sidebar .nav {
                padding: 0 0.25rem;
                gap: 0.15rem;
            }

            .sidebar .nav-link {
                padding: 8px 10px;
                font-size: 0.8rem;
                gap: 8px;
                margin: 0;
            }

            .sidebar .nav-link i {
                width: 16px;
                min-width: 16px;
                font-size: 0.85rem;
            }

            .main-content {
                padding: 0.5rem 0.5rem;
            }

            .mobile-header {
                padding: 0.5rem 0.75rem;
                gap: 0.5rem;
            }

            .mobile-header h5 {
                font-size: 0.9rem;
                max-width: 150px;
            }

            .mobile-header .d-flex {
                gap: 0.5rem;
            }

            .mobile-toggle {
                padding: 0.5rem;
                font-size: 1rem;
            }

            .page-header {
                margin-bottom: 1rem;
                padding: 1.2rem;
                border-radius: 12px;
            }

            .page-header h1 {
                font-size: 1.2rem;
                margin-bottom: 0.4rem;
                gap: 0.4rem;
            }

            .page-header h1 i {
                font-size: 1.2rem;
            }

            .page-header p {
                font-size: 0.85rem;
                margin: 0.4rem 0 0 0;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
                margin-bottom: 1rem;
            }

            .stats-card {
                padding: 1rem 0.75rem;
                border-radius: 12px;
            }

            .stats-card h6 {
                font-size: 0.65rem;
                margin-bottom: 0.35rem;
                text-transform: capitalize;
                letter-spacing: 0.02em;
            }

            .stats-card h3 {
                font-size: 1.5rem;
                margin-bottom: 0.25rem;
            }

            .stats-card .stat-label {
                font-size: 0.65rem;
            }

            .stats-card .stat-label i {
                font-size: 0.6rem;
            }

            .stats-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
                margin: 0 auto 0.5rem;
            }

            .glass-card,
            .table-card {
                padding: 1rem 0.75rem;
                border-radius: 12px;
                margin-bottom: 0.75rem;
            }

            .table-card h5 {
                font-size: 0.9rem;
                margin-bottom: 0.75rem;
                gap: 0.4rem;
            }

            .table-card h5 i {
                font-size: 0.85rem;
            }

            .table {
                font-size: 0.8rem;
                margin-bottom: 0;
            }

            .table thead th {
                padding: 0.5rem 0.4rem;
                font-size: 0.65rem;
                font-weight: 600;
                white-space: nowrap;
            }

            .table tbody tr {
                border: none;
            }

            .table tbody td {
                padding: 0.6rem 0.4rem;
                font-size: 0.8rem;
            }

            .table tbody td .d-flex {
                gap: 0.4rem;
                flex-wrap: nowrap;
            }

            .table tbody td .d-flex > div:first-child {
                width: 28px;
                height: 28px;
                min-width: 28px;
                border-radius: 6px;
                font-size: 0.65rem;
                flex-shrink: 0;
            }

            .table tbody td .d-flex > div:last-child {
                min-width: 0;
                flex: 1;
                overflow: hidden;
            }

            .table tbody td .d-flex div div {
                font-size: 0.8rem;
                font-weight: 600;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .table tbody td .d-flex small {
                font-size: 0.65rem;
                color: var(--text-dim);
                display: block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-card {
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                border-radius: 12px;
            }

            .user-card .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
                margin: 0 auto 0.5rem;
            }

            .user-card h6 {
                font-size: 0.8rem;
                margin-bottom: 0.15rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-card p {
                font-size: 0.7rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .row {
                margin-right: -0.25rem;
                margin-left: -0.25rem;
            }

            .col-md-4,
            .col-lg-3 {
                padding-right: 0.25rem;
                padding-left: 0.25rem;
            }

            .col-lg-6 {
                padding-right: 0.25rem;
                padding-left: 0.25rem;
            }
        }

        /* Extra small devices (very small phones) */
        @media (max-width: 360px) {
            .sidebar .nav-link span {
                display: none;
            }

            .sidebar .nav-link {
                justify-content: center;
                padding: 8px;
            }

            .page-header h1 {
                font-size: 1.1rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .stats-card h3 {
                font-size: 1.3rem;
            }

            .table-card h5 span {
                display: none;
            }
        }
    </style>
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
    <script src="assets/js/menu-logic.js"></script>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar(event)"></div>
    
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar(event)">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="admin-menu-container">
            <div class="admin-menu-btn" onclick="toggleAdminMenu(event)">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
            </div>
            <div class="admin-dropdown" id="adminDropdown">
                <a href="manage_users.php">
                    <i class="fas fa-users"></i>
                    <span>Manage Users</span>
                </a>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h4><i class="fas fa-crown me-2"></i>SilentMultiPanel</h4>
            <p>Admin Control Panel</p>
        </div>
        <nav class="nav">
            <a class="nav-link active" href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
            <a class="nav-link" href="stock_alerts.php">
                <i class="fas fa-bell"></i><span>Stock Alerts</span>
            </a>
            <a class="nav-link" href="add_mod.php">
                <i class="fas fa-plus"></i><span>Add Mod Name</span>
            </a>
            <a class="nav-link" href="manage_mods.php">
                <i class="fas fa-edit"></i><span>Manage Mods</span>
            </a>
            <a class="nav-link" href="upload_mod.php">
                <i class="fas fa-upload"></i><span>Upload Mod APK</span>
            </a>
            <a class="nav-link" href="mod_list.php">
                <i class="fas fa-list"></i><span>Mod APK List</span>
            </a>
            <a class="nav-link" href="add_license.php">
                <i class="fas fa-key"></i><span>Add License Key</span>
            </a>
            <a class="nav-link" href="licence_key_list.php">
                <i class="fas fa-key"></i><span>License Key List</span>
            </a>
            <a class="nav-link" href="available_keys.php">
                <i class="fas fa-key"></i><span>Available Keys</span>
            </a>
            <a class="nav-link" href="manage_users.php">
                <i class="fas fa-users"></i><span>Manage Users</span>
            </a>
            <a class="nav-link" href="edit_user.php">
                <i class="fas fa-wallet"></i><span>Add Balance</span>
            </a>
            <a class="nav-link" href="transactions.php">
                <i class="fas fa-exchange-alt"></i><span>Transactions</span>
            </a>
            <a class="nav-link" href="referral_codes.php">
                <i class="fas fa-tag"></i><span>Referral Codes</span>
            </a>
            <a class="nav-link" href="admin_block_reset_requests.php">
                <i class="fas fa-shield-alt"></i><span>Block & Reset</span>
            </a>
            <a class="nav-link" href="settings.php">
                <i class="fas fa-cog"></i><span>Settings</span>
            </a>
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-crown me-2"></i>SilentMultiPanel</h1>
            <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-box"></i>
                </div>
                <h6>Total Mods</h6>
                <h3><?php echo $stats['total_mods']; ?></h3>
                <span class="stat-label"><i class="fas fa-chart-line me-1"></i>Active mods</span>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h6>License Keys</h6>
                <h3><?php echo $stats['total_keys']; ?></h3>
                <span class="stat-label"><i class="fas fa-key me-1"></i>Total generated</span>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h6>Total Users</h6>
                <h3><?php echo $stats['total_users']; ?></h3>
                <span class="stat-label"><i class="fas fa-user-plus me-1"></i>Registered users</span>
            </div>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6>Sold Licenses</h6>
                <h3><?php echo $stats['sold_keys']; ?></h3>
                <span class="stat-label"><i class="fas fa-coins me-1"></i>Revenue generated</span>
            </div>
        </div>

        <!-- All Users Section -->
        <div class="table-card">
            <h5><i class="fas fa-users"></i>All Users</h5>
            <div class="row">
<?php
try {
    $stmt = $pdo->query("SELECT username, role FROM users WHERE role = 'user' ORDER BY username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo '<div class="col-12"><p style="text-align: center; color: var(--text-dim); padding: 2rem 0;">No users registered yet</p></div>';
    } else {
        foreach ($users as $userItem):
            $initials = strtoupper(substr($userItem['username'], 0, 2));
            $roleDisplay = $userItem['role'] === 'user' ? 'User Account' : 'Administrator';
?>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="user-card">
                        <div class="user-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <h6><?php echo htmlspecialchars($userItem['username']); ?></h6>
                        <p><?php echo $roleDisplay; ?></p>
                    </div>
                </div>
<?php
        endforeach;
    }
} catch (Exception $e) {
    echo '<div class="col-12"><p style="color: var(--accent); text-align: center; padding: 2rem 0;">Unable to load users</p></div>';
}
?>
            </div>
        </div>

        <!-- Recent Data Tables -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="table-card">
                    <h5><i class="fas fa-box"></i>Recent Mods</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th class="d-none d-sm-table-cell">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentMods)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim); padding: 2rem 0;">
                                        <i class="fas fa-box fa-2x mb-2 d-block" style="opacity: 0.5;"></i>
                                        No mods uploaded yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentMods as $mod): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 35px; height: 35px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                                                    <i class="fas fa-mobile-alt text-white"></i>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-weight: 600; color: #06b6d4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($mod['name']); ?></div>
                                                    <small style="color: #8b5cf6; font-weight: 500; display: none;" class="d-sm-none"><?php echo date('M d, Y H:i', strtotime($mod['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-sm-table-cell" style="color: #06b6d4; font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($mod['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="table-card">
                    <h5><i class="fas fa-users"></i>Recent Users</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th class="d-none d-sm-table-cell">Join Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUsers)): ?>
                                <tr>
                                    <td colspan="2" style="text-align: center; color: var(--text-dim); padding: 2rem 0;">
                                        <i class="fas fa-users fa-2x mb-2 d-block" style="opacity: 0.5;"></i>
                                        No users registered yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="width: 35px; height: 35px; background: linear-gradient(135deg, var(--primary), var(--secondary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.8rem; flex-shrink: 0;">
                                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                                </div>
                                                <div style="min-width: 0;">
                                                    <div style="font-weight: 600; color: #06b6d4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($user['username']); ?></div>
                                                    <small style="color: #8b5cf6; font-weight: 500; display: none;" class="d-sm-none"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="d-none d-sm-table-cell" style="color: #06b6d4; font-weight: 600;"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
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
        function toggleAdminMenu(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('adminDropdown');
            dropdown.classList.toggle('show');
        }

        function closeAdminMenu() {
            const dropdown = document.getElementById('adminDropdown');
            dropdown.classList.remove('show');
        }

        function toggleSidebar(event) {
            if (event) event.preventDefault();
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            
            if (sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('show');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            const adminDropdown = document.getElementById('adminDropdown');

            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                    if (this.href === window.location.href) {
                        this.classList.add('active');
                    }
                });
            });

            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }

            // Close admin dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (adminDropdown && !e.target.closest('.admin-menu-container')) {
                    adminDropdown.classList.remove('show');
                }
            });

            // Close admin dropdown when clicking a link
            if (adminDropdown) {
                adminDropdown.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', closeAdminMenu);
                });
            }
        });

        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Animate stats numbers on page load
        document.addEventListener('DOMContentLoaded', function() {
            const statsCards = document.querySelectorAll('.stats-card h3');
            statsCards.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                if (finalValue > 0) {
                    let currentValue = 0;
                    const increment = Math.max(1, Math.floor(finalValue / 20));
                    
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = currentValue;
                        }
                    }, 30);
                }
            });
        });
    </script>
</body>
</html>
