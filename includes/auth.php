<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = :user_id AND session_id = :session_id");
        $stmt->execute([':user_id' => $_SESSION['user_id'], ':session_id' => session_id()]);
        $sessionExists = $stmt->fetchColumn();
        
        if (!$sessionExists) {
            logout();
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        return isset($_SESSION['user_id']);
    }
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
    
    cleanupExpiredSessions();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = :user_id AND session_id != :session_id");
        $stmt->execute([':user_id' => $user['id'], ':session_id' => session_id()]);
        $activeSessions = $stmt->fetchColumn();
        
        if ($activeSessions > 0 && !$forceLogout) {
            return 'already_logged_in';
        }
        
        if ($forceLogout && $activeSessions > 0) {
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id AND session_id != :session_id");
            $stmt->execute([':user_id' => $user['id'], ':session_id' => session_id()]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id AND session_id = :session_id");
        $stmt->execute([':user_id' => $user['id'], ':session_id' => session_id()]);
        
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, created_at) VALUES (:user_id, :session_id, :ip_address, :user_agent, NOW())");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':session_id' => session_id(),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['balance'] = $user['balance'];
        return true;
    }
    return false;
}

function register($username, $email, $password, $referralCode = null) {
    $pdo = getDBConnection();
    
    $referredBy = null;
    $referralType = null;
    
    if ($referralCode) {
        $stmt = $pdo->prepare("SELECT created_by FROM referral_codes WHERE code = :code AND status = 'active' AND expires_at > NOW()");
        $stmt->execute([':code' => $referralCode]);
        $adminReferral = $stmt->fetchColumn();
        
        if ($adminReferral) {
            $referredBy = $adminReferral;
            $referralType = 'admin';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = :code AND role = 'user'");
            $stmt->execute([':code' => $referralCode]);
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
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (:username, :email, :password, :referral_code, :referred_by)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashedPassword,
            ':referral_code' => $userReferralCode,
            ':referred_by' => $referredBy
        ]);
        
        if ($referralType === 'admin' && $referralCode) {
            $stmt = $pdo->prepare("UPDATE referral_codes SET status = 'inactive' WHERE code = :code");
            $stmt->execute([':code' => $referralCode]);
        } else if ($referralType === 'user' && $referredBy) {
            $newUserReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
            $stmt = $pdo->prepare("UPDATE users SET referral_code = :referral_code WHERE id = :id");
            $stmt->execute([':referral_code' => $newUserReferralCode, ':id' => $referredBy]);
        }
        
        if ($referredBy) {
            $stmt = $pdo->prepare("UPDATE users SET balance = balance + 50 WHERE id = :id");
            $stmt->execute([':id' => $referredBy]);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (:user_id, 'balance_add', 50, 'Referral bonus for referring new user', NOW())");
                $stmt->execute([':user_id' => $referredBy]);
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
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id AND session_id = :session_id");
            $stmt->execute([':user_id' => $_SESSION['user_id'], ':session_id' => session_id()]);
        } catch (Exception $e) {}
    }
    
    session_destroy();
    header('Location: login.php');
    exit();
}

function forceLogoutAllDevices($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
}

function cleanupExpiredSessions() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE created_at < NOW() - INTERVAL '24 hours'");
        $stmt->execute();
    } catch (Exception $e) {}
}

function hasActiveSession($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = :user_id AND session_id != :session_id");
    $stmt->execute([':user_id' => $userId, ':session_id' => session_id()]);
    return $stmt->fetchColumn() > 0;
}

function resetDevice($username, $password) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = :username OR email = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return 'user_not_found';
    }
    
    if (!password_verify($password, $user['password'])) {
        return 'invalid_password';
    }
    
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user['id']]);
    
    return 'success';
}

function getUserData($userId = null) {
    if (!$userId) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    if (!$userId) return null;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateBalance($userId, $amount, $type = 'balance_add', $reference = null) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + :amount WHERE id = :id");
        $stmt->execute([':amount' => $amount, ':id' => $userId]);
        
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) VALUES (:user_id, :amount, :type, :reference, 'completed')");
        $stmt->execute([':user_id' => $userId, ':amount' => $amount, ':type' => $type, ':reference' => $reference]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
?>
