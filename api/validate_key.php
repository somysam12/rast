<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$key = $_GET['key'] ?? '';
$deviceFingerprint = $_GET['device_id'] ?? '';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($key) || empty($deviceFingerprint)) {
    echo json_encode(['status' => 'error', 'message' => 'MISSING_PARAMS']);
    exit;
}

$pdo = getDBConnection();

// STEP 1: Validate Key
$stmt = $pdo->prepare("SELECT * FROM user_keys WHERE key_value = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$key]);
$keyData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keyData) {
    echo json_encode(['status' => 'error', 'message' => 'INVALID_KEY']);
    exit;
}

if ($keyData['expiry'] && strtotime($keyData['expiry']) < time()) {
    echo json_encode(['status' => 'error', 'message' => 'KEY_EXPIRED']);
    exit;
}

// STEP 2: Check Existing Device
$stmt = $pdo->prepare("SELECT * FROM key_devices WHERE key_id = ? AND is_active = 1");
$stmt->execute([$keyData['id']]);
$activeDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$isSameDevice = false;
foreach ($activeDevices as $device) {
    if ($device['device_fingerprint'] === $deviceFingerprint) {
        $isSameDevice = true;
        break;
    }
}

if ($isSameDevice) {
    // allow login
    echo json_encode(['status' => 'success', 'message' => 'LOGIN_ALLOWED']);
    exit;
}

// Check device limit
if (count($activeDevices) >= $keyData['max_devices']) {
    // check reset eligibility
    $stmt = $pdo->prepare("SELECT * FROM key_resets WHERE key_id = ? LIMIT 1");
    $stmt->execute([$keyData['id']]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = time();
    $lastReset = $resetData ? strtotime($resetData['last_reset']) : 0;
    $resetCount = $resetData ? $resetData['reset_count'] : 0;

    if ($resetCount < 1 || ($now - $lastReset) >= 86400) {
        // Reset allowed - Deactivate old devices and allow new one
        $pdo->prepare("UPDATE key_devices SET is_active = 0 WHERE key_id = ?")->execute([$keyData['id']]);
        
        $stmt = $pdo->prepare("INSERT INTO key_devices (key_id, device_fingerprint, ip_address, last_active, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$keyData['id'], $deviceFingerprint, $ipAddress, date('Y-m-d H:i:s')]);

        if ($resetData) {
            $pdo->prepare("UPDATE key_resets SET reset_count = 1, last_reset = ? WHERE key_id = ?")
                ->execute([date('Y-m-d H:i:s'), $keyData['id']]);
        } else {
            $pdo->prepare("INSERT INTO key_resets (key_id, reset_count, last_reset) VALUES (?, 1, ?)")
                ->execute([$keyData['id'], date('Y-m-d H:i:s')]);
        }

        echo json_encode(['status' => 'success', 'message' => 'DEVICE_RESET_SUCCESS']);
    } else {
        $nextReset = $lastReset + 86400;
        $diff = $nextReset - $now;
        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        echo json_encode([
            'status' => 'error', 
            'message' => 'MAX_DEVICE_REACHED', 
            'next_reset' => "{$hours}h {$minutes}m"
        ]);
    }
} else {
    // Under limit, add new device
    $stmt = $pdo->prepare("INSERT INTO key_devices (key_id, device_fingerprint, ip_address, last_active, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$keyData['id'], $deviceFingerprint, $ipAddress, date('Y-m-d H:i:s')]);
    echo json_encode(['status' => 'success', 'message' => 'NEW_DEVICE_ADDED']);
}
