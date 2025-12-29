<?php
// Get current user info
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error silently
    }
}
?>

<style>
    .admin-header {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 100;
    }

    .logo-button {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        border: 2px solid rgba(139, 92, 246, 0.3);
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(6, 182, 212, 0.1));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        color: #f8fafc;
        font-size: 28px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        box-shadow: 0 5px 20px rgba(139, 92, 246, 0.2);
        position: relative;
    }

    .logo-button:hover {
        border-color: rgba(139, 92, 246, 0.6);
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(6, 182, 212, 0.15));
        transform: scale(1.1);
        box-shadow: 0 8px 30px rgba(139, 92, 246, 0.4);
    }

    .logo-button:active {
        transform: scale(0.95);
    }

    .dropdown-menu-custom {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 10px;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 2px solid rgba(139, 92, 246, 0.3);
        border-radius: 16px;
        min-width: 260px;
        box-shadow: 0 20px 60px rgba(139, 92, 246, 0.3);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        pointer-events: none;
        z-index: 1000;
    }

    .dropdown-menu-custom.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        pointer-events: auto;
    }

    .dropdown-header {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(139, 92, 246, 0.2);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: linear-gradient(135deg, #8b5cf6, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 16px;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        font-size: 13px;
        font-weight: 700;
        color: #f8fafc;
        display: block;
    }

    .user-email {
        font-size: 11px;
        color: #94a3b8;
    }

    .dropdown-items {
        padding: 8px 0;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        color: #94a3b8;
        text-decoration: none;
        transition: all 0.3s;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .dropdown-item i {
        width: 18px;
        text-align: center;
        font-size: 14px;
    }

    .dropdown-item:hover {
        background: rgba(139, 92, 246, 0.2);
        color: #8b5cf6;
        padding-left: 24px;
    }

    .dropdown-divider {
        height: 1px;
        background: rgba(139, 92, 246, 0.2);
        margin: 8px 0;
    }

    .dropdown-item.logout {
        color: #fca5a5;
    }

    .dropdown-item.logout:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ff6b6b;
    }

    /* Mobile responsive */
    @media (max-width: 480px) {
        .admin-header {
            top: 15px;
            right: 15px;
        }

        .logo-button {
            width: 50px;
            height: 50px;
            font-size: 24px;
        }

        .dropdown-menu-custom {
            min-width: 240px;
            right: -20px;
        }
    }
</style>

<div class="admin-header">
    <button class="logo-button" onclick="toggleDropdown(event)" id="logoBtn" title="Click for menu">
        <i class="fas fa-bolt"></i>
    </button>

    <div class="dropdown-menu-custom" id="dropdownMenu">
        <div class="dropdown-header">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['username'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($currentUser['username'] ?? 'Admin'); ?></span>
                    <span class="user-email"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></span>
                </div>
            </div>
        </div>

        <div class="dropdown-items">
            <a href="admin_dashboard.php" class="dropdown-item" onclick="closeDropdown()">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_users.php" class="dropdown-item" onclick="closeDropdown()">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="settings.php" class="dropdown-item" onclick="closeDropdown()">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="admin_block_reset_requests.php" class="dropdown-item" onclick="closeDropdown()">
                <i class="fas fa-ban"></i>
                <span>Block & Reset</span>
            </a>

            <div class="dropdown-divider"></div>

            <a href="logout.php" class="dropdown-item logout" onclick="closeDropdown()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<script>
    function toggleDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('dropdownMenu');
        dropdown.classList.toggle('show');
    }

    function closeDropdown() {
        const dropdown = document.getElementById('dropdownMenu');
        dropdown.classList.remove('show');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const header = document.querySelector('.admin-header');
        if (header && !header.contains(event.target)) {
            closeDropdown();
        }
    });

    // Close dropdown with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDropdown();
        }
    });
</script>
