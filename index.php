<?php
require_once 'includes/auth.php';

$isLoggedIn = isLoggedIn();
$isAdmin = $isLoggedIn && isAdmin();

$dashboardUrl = '';
if ($isLoggedIn) {
    $dashboardUrl = $isAdmin ? 'admin_dashboard.php' : 'user_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SilentMultiPanel - Best Multipanel And Instant Support</title>
    <meta name="description" content="SilentMultiPanel - Best Multipanel And Instant Support">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="20" fill="url(%23dots)"/></svg>');
            opacity: 0.5;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }
        
        .hero-image {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-large);
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar-custom.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-section {
            background: var(--card-bg);
            padding: 5rem 0;
            border-top: 1px solid var(--border-light);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-cta {
            background: white;
            color: var(--purple);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-cta:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-large);
            color: var(--purple-dark);
        }
        
        .btn-outline-cta {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .btn-outline-cta:hover {
            background: white;
            color: var(--purple);
            transform: translateY(-1px);
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
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        [data-theme="dark"] .navbar-custom {
            background: rgba(30, 41, 59, 0.95);
        }
        
        [data-theme="dark"] .feature-card {
            background: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .stats-section {
            background: var(--card-bg);
            border-color: var(--border-light);
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
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .feature-card {
                padding: 1.5rem;
            }
            
            .feature-icon {
                width: 56px;
                height: 56px;
                font-size: 1.3rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
