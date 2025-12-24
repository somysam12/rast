<?php
require_once 'config/database.php';

function generateLicenseKey($modName, $duration, $durationType) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $modName), 0, 8));
    $suffix = strtoupper(substr(md5(uniqid()), 0, 10));
    return $prefix . '-' . $duration . $durationType[0] . '-' . $suffix;
}

function generateReferralCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function formatCurrency($amount) {
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        $amount = 0;
    }
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('d M Y H:i', strtotime($date));
}

function getModStats() {
    $pdo = getDBConnection();
    static $cached_stats = null;
    
    if ($cached_stats !== null) {
        return $cached_stats;
    }
    
    $stats = ['total_mods' => 0, 'total_keys' => 0, 'available_keys' => 0, 'sold_keys' => 0, 'total_users' => 0];
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM mods");
        $stats['total_mods'] = (int)($stmt->fetchColumn() ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM license_keys");
        $stats['total_keys'] = (int)($stmt->fetchColumn() ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM license_keys WHERE status = 'available'");
        $stats['available_keys'] = (int)($stmt->fetchColumn() ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM license_keys WHERE status = 'sold'");
        $stats['sold_keys'] = (int)($stmt->fetchColumn() ?? 0);
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
        $stats['total_users'] = (int)($stmt->fetchColumn() ?? 0);
    } catch (Exception $e) {}
    
    $cached_stats = $stats;
    return $cached_stats;
}

function getRecentMods($limit = 5) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM mods ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getRecentUsers($limit = 5) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getUserTransactions($userId, $limit = 50) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getAllTransactions($filters = []) {
    try {
        $pdo = getDBConnection();
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = "t.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(u.username LIKE ? OR t.reference LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql = "SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE " . implode(' AND ', $where) . " ORDER BY t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function getAvailableKeys($modId = null) {
    try {
        $pdo = getDBConnection();
        $where = "lk.status = 'available'";
        $params = [];
        
        if ($modId) {
            $where .= " AND lk.mod_id = ?";
            $params[] = $modId;
        }
        
        $sql = "SELECT lk.*, m.name as mod_name FROM license_keys lk LEFT JOIN mods m ON lk.mod_id = m.id WHERE $where ORDER BY lk.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function purchaseLicenseKey($userId, $keyId) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name FROM license_keys lk LEFT JOIN mods m ON lk.mod_id = m.id WHERE lk.id = ? AND lk.status = 'available' LIMIT 1");
        $stmt->execute([$keyId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) throw new Exception("Key not available");
        
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || $user['balance'] < $key['price']) throw new Exception("Insufficient balance");
        
        $stmt = $pdo->prepare("UPDATE license_keys SET status = 'sold', sold_to = ?, sold_at = datetime('now') WHERE id = ?");
        $stmt->execute([$userId, $keyId]);
        
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$key['price'], $userId]);
        
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) VALUES (?, ?, 'purchase', ?, 'completed')");
        $stmt->execute([$userId, -$key['price'], "License purchase #" . $keyId]);
        
        $pdo->commit();
        return $key;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function uploadFile($file, $uploadDir = 'uploads/') {
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'file_name' => $fileName, 'file_path' => $targetPath, 'file_size' => $file['size']];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}
?>
