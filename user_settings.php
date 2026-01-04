<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireUser();

$success = '';
$error = '';

$pdo = getDBConnection();
    $user = getUserData();

    // 2FA Logic
    if (file_exists('includes/GoogleAuthenticator.php')) {
        require_once 'includes/GoogleAuthenticator.php';
        $ga = new PHPGangsta_GoogleAuthenticator();
    } else {
        $ga = null;
    }
    
    $stmt = $pdo->prepare("SELECT two_factor_secret, two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $user_2fa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (isset($_POST['setup_2fa'])) {
        $secret = $ga->createSecret();
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
        $stmt->execute([$secret, $user['id']]);
        $success = '2FA Secret generated. Scan the QR code to finish setup.';
        $user_2fa['two_factor_secret'] = $secret;
    }

    if (isset($_POST['verify_2fa'])) {
        $code = $_POST['otp_code'] ?? '';
        $secret = $user_2fa['two_factor_secret'];
        if ($ga->verifyCode($secret, $code, 2)) {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id = ?");
            $stmt->execute([$user['id']]);
            $success = '2FA enabled successfully!';
            $user_2fa['two_factor_enabled'] = 1;
        } else {
            $error = 'Invalid OTP code.';
        }
    }

    if (isset($_POST['disable_2fa'])) {
        $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        $success = '2FA disabled.';
        $user_2fa['two_factor_enabled'] = 0;
        $user_2fa['two_factor_secret'] = null;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && $user) {
    if (isset($_POST['reset_device'])) {
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            $error = 'Please enter your password to reset device';
        } else {
            $resetResult = resetDevice($user['username'], $password);
            if ($resetResult === 'success') {
                $success = 'Successfully logged out from all devices. You will be redirected to login page.';
                header('refresh:3;url=logout.php');
            } else if ($resetResult === 'invalid_password') {
                $error = 'Invalid password';
            } else {
                $error = 'Failed to reset device';
            }
        }
    } elseif (isset($_POST['update_profile'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($username) || empty($email)) {
            $error = 'Please fill in all fields';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user['id']]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username or email already exists';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $user['id']]);
                    $_SESSION['username'] = $username;
                    $success = 'Profile updated successfully!';
                    $user = getUserData();
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in all password fields';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $user['id']]);
                $success = 'Password changed successfully!';
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

function formatCurrencyLocal($amount){
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

if (!function_exists('formatDateLocal')) {
    function formatDateLocal($dt){
        if(!$dt){ return '-'; }
        $date = new DateTime($dt, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $date->format('d M Y');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="assets/css/cyber-ui.css" rel="stylesheet">
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

        .settings-card {
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .form-control {
            background: rgba(15, 23, 42, 0.5);
            border: 1.5px solid rgba(148, 163, 184, 0.1);
            color: white;
            border-radius: 12px;
            padding: 12px 15px;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #8b5cf6;
            color: white;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.2);
        }
        .form-control[readonly] {
            background: rgba(0, 0, 0, 0.2);
            border-color: transparent;
            color: #94a3b8;
        }
        .settings-btn {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border: none;
            border-radius: 14px;
            padding: 12px 25px;
            color: white;
            font-weight: 700;
            transition: all 0.3s ease;
        }
        .settings-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 92, 246, 0.4);
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
                <div class="text-secondary small">Balance: <?php echo formatCurrencyLocal($user['balance']); ?></div>
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
            <a class="nav-link" href="user_manage_keys.php"><i class="fas fa-key me-2"></i> Manage Keys</a>
            <a class="nav-link" href="user_applications.php"><i class="fas fa-mobile-alt me-2"></i> Applications</a>
            <a class="nav-link" href="user_notifications.php"><i class="fas fa-bell me-2"></i> Notifications</a>
            <a class="nav-link" href="user_block_request.php"><i class="fas fa-ban me-2"></i> Block & Reset</a>
            <a class="nav-link" href="user_stock_alert.php"><i class="fas fa-warehouse me-2"></i> Stock Alert</a>
            <a class="nav-link active" href="user_settings.php"><i class="fas fa-cog me-2"></i> Settings</a>
            <a class="nav-link" href="user_transactions.php"><i class="fas fa-history me-2"></i> Transactions</a>
            <hr class="border-secondary opacity-25">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="cyber-card mb-4">
            <h2 class="text-neon mb-1">Account Settings</h2>
            <p class="text-secondary mb-0">Update your profile and manage security options.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="settings-card">
                    <h5 class="text-white mb-4"><i class="fas fa-user-circle text-primary me-2"></i> Profile Details</h5>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">USERNAME</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">EMAIL ADDRESS</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">JOIN DATE</label>
                            <input type="text" class="form-control" value="<?php echo formatDateLocal($user['created_at']); ?>" readonly>
                        </div>
                        <button type="submit" class="settings-btn w-100">Update Profile</button>
                    </form>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="settings-card">
                    <h5 class="text-white mb-4"><i class="fas fa-lock text-primary me-2"></i> Security</h5>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">CURRENT PASSWORD</label>
                            <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-bold">NEW PASSWORD</label>
                            <input type="password" name="new_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-bold">CONFIRM NEW PASSWORD</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="submit" class="settings-btn w-100">Change Password</button>
                    </form>
                </div>

                <div class="settings-card">
                    <h5 class="text-white mb-4"><i class="fas fa-shield-alt text-primary me-2"></i> Two-Factor Authentication</h5>
                    <?php if (!$user_2fa['two_factor_enabled']): ?>
                        <?php if (!$user_2fa['two_factor_secret']): ?>
                            <p class="text-secondary small">Add an extra layer of security to your account.</p>
                            <form method="POST">
                                <button type="submit" name="setup_2fa" class="settings-btn w-100">Setup 2FA</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center mb-3">
                                <p class="text-white small">Scan this QR code with your TOTP app:</p>
                                <img src="<?php echo $ga->getQRCodeGoogleUrl('SilentPanel_'.urlencode($user['username']), $user_2fa['two_factor_secret'], 'SilentPanel'); ?>" alt="QR Code" class="img-fluid rounded border border-secondary border-opacity-25 mb-3" style="max-width: 150px;">
                                <p class="text-secondary" style="font-size: 0.7rem;">Manual Secret: <code><?php echo $user_2fa['two_factor_secret']; ?></code></p>
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-bold">ENTER 6-DIGIT CODE</label>
                                    <input type="text" name="otp_code" class="form-control text-center" placeholder="000000" maxlength="6" required>
                                </div>
                                <button type="submit" name="verify_2fa" class="settings-btn w-100">Verify & Enable</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-3 bg-success bg-opacity-10 border border-success border-opacity-20 rounded-3 mb-3 text-center">
                            <i class="fas fa-check-circle text-success mb-2" style="font-size: 1.5rem;"></i>
                            <div class="text-success small fw-bold">2FA is currently active</div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA?');">
                            <button type="submit" name="disable_2fa" class="btn btn-outline-danger w-100 rounded-pill">Disable 2FA</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12">
                <div class="settings-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="text-white mb-2"><i class="fas fa-gift text-primary me-2"></i> Referral Program</h5>
                            <p class="text-secondary small mb-md-0">Share your code with others to earn rewards when they join.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-dark bg-opacity-50 border border-secondary border-opacity-10 rounded-3 text-center">
                                <div class="small text-secondary mb-1">YOUR CODE</div>
                                <div class="h4 text-neon mb-0"><?php echo htmlspecialchars($user['referral_code'] ?: 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="settings-card border-danger border-opacity-10">
                    <h5 class="text-danger mb-4"><i class="fas fa-exclamation-triangle me-2"></i> Danger Zone</h5>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <div class="fw-bold text-white">Reset Device Binding</div>
                            <div class="small text-secondary">Force logout from all devices and reset your active session.</div>
                        </div>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="reset_device" value="1">
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Verify Password" style="max-width: 150px;">
                            <button type="submit" class="btn btn-outline-danger btn-sm px-3 rounded-pill">Reset Now</button>
                        </form>
                    </div>
                </div>
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
    </script>
</body>
</html>