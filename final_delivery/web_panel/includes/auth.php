<?php
session_start();
require_once 'config/database.php';

function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    if (isset($_SESSION['session_check']) && time() - $_SESSION['session_check'] < 300) {
        return true;
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id'], session_id()]);
    $sessionExists = $stmt->fetchColumn();
    
    if (!$sessionExists) {
        logout();
        return false;
    }
    
    $_SESSION['session_check'] = time();
    return true;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: user_dashboard.php');
        exit();
    }
}

function requireUser() {
    requireLogin();
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
        exit();
    }
}

function login($username, $password, $forceLogout = false) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_id != ? LIMIT 1");
        $stmt->execute([$user['id'], session_id()]);
        $activeSessions = $stmt->fetchColumn();
        
        if ($activeSessions > 0 && !$forceLogout) {
            return 'already_logged_in';
        }
        
        if ($forceLogout && $activeSessions > 0) {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
            $stmt->execute([$user['id'], session_id()]);
        }
        
        // Delete old session first (works on both SQLite and MySQL)
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([session_id()]);
        
        // Insert new session
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute([$user['id'], session_id(), $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['balance'] = $user['balance'];
        $_SESSION['session_check'] = time();
        return true;
    }
    return false;
}

function register($username, $email, $password, $referralCode = null) {
    $pdo = getDBConnection();
    
    $referredBy = null;
    $referralType = null;
    
    if ($referralCode) {
        $stmt = $pdo->prepare("SELECT created_by FROM referral_codes WHERE code = ? AND status = 'active' AND expires_at > CURRENT_TIMESTAMP LIMIT 1");
        $stmt->execute([$referralCode]);
        $adminReferral = $stmt->fetchColumn();
        
        if ($adminReferral) {
            $referredBy = $adminReferral;
            $referralType = 'admin';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND role = 'user' LIMIT 1");
            $stmt->execute([$referralCode]);
            $referredBy = $stmt->fetchColumn();
            if ($referredBy) {
                $referralType = 'user';
            }
        }
    }
    
    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
    
    try {
        $pdo->beginTransaction();
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
        
        $userId = $pdo->lastInsertId();
        
        if ($referralType === 'admin') {
            $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE code = ?");
            $stmt->execute([$referralCode]);
        } else if ($referralType === 'user') {
            $newUserReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$newUserReferralCode, $referredBy]);
        }
        
        if ($referredBy) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + 50 WHERE id = ?");
            $stmt->execute([$referredBy]);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, 'balance_add', 50, 'Referral bonus', CURRENT_TIMESTAMP)");
                $stmt->execute([$referredBy]);
            } catch (Exception $e) {}
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function logout() {
    if (isset($_SESSION['user_id'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$_SESSION['user_id'], session_id()]);
    }
    
    session_destroy();
    header('Location: login.php');
    exit();
}

function forceLogoutAllDevices($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);
}

function cleanupExpiredSessions() {
    $pdo = getDBConnection();
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE created_at < ?");
    $stmt->execute([$cutoffTime]);
}

function hasActiveSession($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_id != ? LIMIT 1");
    $stmt->execute([$userId, session_id()]);
    return $stmt->fetchColumn() > 0;
}

function resetDevice($username, $password) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return 'user_not_found';
    }
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $hashedPassword = $stmt->fetchColumn();
    
    if (!password_verify($password, $hashedPassword)) {
        return 'invalid_password';
    }
    
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    return 'success';
}

function getUserData($userId = null) {
    if (!$userId) {
        $userId = $_SESSION['user_id'];
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateBalance($userId, $amount, $type = 'balance_add', $reference = null) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        $description = ($type === 'balance_add') ? "Balance added by admin" : "Transaction";
        if ($type === 'debit' || $type === 'purchase') {
            $description = "License key purchase";
        }
        
        // Ensure negative amount for purchases/debits
        if (($type === 'debit' || $type === 'purchase') && $amount > 0) {
            $amount = -$amount;
        }

        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, reference, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP)");
        $stmt->execute([$userId, $amount, $type, $description, $reference]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}
?>
