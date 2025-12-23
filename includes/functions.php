<?php
require_once __DIR__ . '/../config/database.php';

function generateLicenseKey($modName, $duration, $durationType) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $modName), 0, 8));
    $suffix = strtoupper(substr(md5(uniqid()), 0, 10));
    return $prefix . '-' . $duration . $durationType[0] . '-' . $suffix;
}

function generateReferralCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function formatCurrency($amount) {
    return '₹' . number_format((float)($amount ?? 0), 2);
}

function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

function getModStats() {
    $pdo = getDBConnection();
    
    $stats = [];
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $stats['total_mods'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys");
    $stats['total_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'available'");
    $stats['available_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'sold'");
    $stats['sold_keys'] = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetchColumn();
    
    return $stats;
}

function getRecentMods($limit = 5) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM mods ORDER BY created_at DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentUsers($limit = 5) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserTransactions($userId, $limit = 50) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllTransactions($filters = []) {
    $pdo = getDBConnection();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $where[] = "t.user_id = :user_id";
        $params[':user_id'] = $filters['user_id'];
    }
    
    if (!empty($filters['type'])) {
        $where[] = "t.type = :type";
        $params[':type'] = $filters['type'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "t.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(u.username LIKE :search1 OR t.reference LIKE :search2)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[':search1'] = $searchTerm;
        $params[':search2'] = $searchTerm;
    }
    
    $sql = "SELECT t.*, u.username FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAvailableKeys($modId = null) {
    $pdo = getDBConnection();
    
    $params = [];
    $where = "lk.status = 'available'";
    
    if ($modId) {
        $where .= " AND lk.mod_id = :mod_id";
        $params[':mod_id'] = $modId;
    }
    
    $sql = "SELECT lk.*, m.name as mod_name 
            FROM license_keys lk 
            LEFT JOIN mods m ON lk.mod_id = m.id 
            WHERE $where 
            ORDER BY lk.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function purchaseLicenseKey($userId, $keyId) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name FROM license_keys lk 
                              LEFT JOIN mods m ON lk.mod_id = m.id 
                              WHERE lk.id = :id AND lk.status = 'available'");
        $stmt->execute([':id' => $keyId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            throw new Exception("Key not available");
        }
        
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user['balance'] < $key['price']) {
            throw new Exception("Insufficient balance");
        }
        
        $stmt = $pdo->prepare("UPDATE license_keys SET status = 'sold', sold_to = :sold_to, sold_at = NOW() WHERE id = :id");
        $stmt->execute([':sold_to' => $userId, ':id' => $keyId]);
        
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - :price WHERE id = :id");
        $stmt->execute([':price' => $key['price'], ':id' => $userId]);
        
        $reference = "License purchase #" . $keyId;
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) VALUES (:user_id, :amount, 'purchase', :reference, 'completed')");
        $stmt->execute([':user_id' => $userId, ':amount' => -$key['price'], ':reference' => $reference]);
        
        $pdo->commit();
        return $key;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function uploadFile($file, $uploadDir = 'uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'file_name' => $fileName,
            'file_path' => $targetPath,
            'file_size' => $file['size']
        ];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}

// Deletion Functions
function deleteMod($modId) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Get mod details for logging
        $stmt = $pdo->prepare("SELECT * FROM mods WHERE id = ?");
        $stmt->execute([$modId]);
        $mod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$mod) {
            throw new Exception("Mod not found");
        }
        
        // Delete all associated license keys (cascade)
        $stmt = $pdo->prepare("DELETE FROM license_keys WHERE mod_id = ?");
        $stmt->execute([$modId]);
        
        // Delete all associated APK files
        $stmt = $pdo->prepare("DELETE FROM mod_apks WHERE mod_id = ?");
        $stmt->execute([$modId]);
        
        // Delete mod
        $stmt = $pdo->prepare("DELETE FROM mods WHERE id = ?");
        $stmt->execute([$modId]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'Mod deleted successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteLicenseKey($keyId) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Get key details
        $stmt = $pdo->prepare("SELECT * FROM license_keys WHERE id = ?");
        $stmt->execute([$keyId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            throw new Exception("License key not found");
        }
        
        // If key was sold, create a refund transaction log
        if ($key['status'] == 'sold' && $key['sold_to']) {
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) 
                                  VALUES (?, ?, 'key_deleted', ?, 'completed')");
            $stmt->execute([$key['sold_to'], 0, 'License key #' . $keyId . ' was deleted']);
        }
        
        // Delete license key
        $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = ?");
        $stmt->execute([$keyId]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'License key deleted successfully'];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteMultipleLicenseKeys($keyIds) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        $deletedCount = 0;
        foreach ((array)$keyIds as $keyId) {
            $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = ?");
            if ($stmt->execute([$keyId])) {
                $deletedCount++;
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => "Deleted $deletedCount license keys successfully"];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getAllLicenseKeys($filters = []) {
    $pdo = getDBConnection();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['mod_id'])) {
        $where[] = "lk.mod_id = :mod_id";
        $params[':mod_id'] = $filters['mod_id'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "lk.status = :status";
        $params[':status'] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "lk.license_key LIKE :search";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    $sql = "SELECT lk.*, m.name as mod_name 
            FROM license_keys lk 
            LEFT JOIN mods m ON lk.mod_id = m.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY lk.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
