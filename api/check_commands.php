<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$key = $_GET['key'] ?? '';
$deviceFingerprint = $_GET['device_id'] ?? '';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

if (empty($key)) {
    echo json_encode(['status' => 'error', 'message' => 'MISSING_KEY']);
    exit;
}

$pdo = getDBConnection();

// Get key ID
$stmt = $pdo->prepare("SELECT id FROM user_keys WHERE key_value = ? LIMIT 1");
$stmt->execute([$key]);
$keyData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keyData) {
    echo json_encode(['status' => 'error', 'message' => 'INVALID_KEY']);
    exit;
}

// Track activity
$stmt = $pdo->prepare("INSERT INTO device_activity (key_id, device_fingerprint, ip_address, last_seen) VALUES (?, ?, ?, ?)");
$stmt->execute([$keyData['id'], $deviceFingerprint, $ipAddress, date('Y-m-d H:i:s')]);

// Check for commands
$stmt = $pdo->prepare("SELECT * FROM admin_commands WHERE (target = 'ALL' OR (target = 'KEY' AND target_id = ?)) AND is_executed = 0 ORDER BY created_at DESC");
$stmt->execute([$keyData['id']]);
$commands = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'clear_cookies' => false,
    'clear_session' => false,
    'force_logout' => false,
    'app_enabled' => true
];

foreach ($commands as $cmd) {
    switch ($cmd['command']) {
        case 'CLEAR_COOKIES': $response['clear_cookies'] = true; break;
        case 'CLEAR_SESSION': $response['clear_session'] = true; break;
        case 'FORCE_LOGOUT': $response['force_logout'] = true; break;
        case 'DISABLE_APP': $response['app_enabled'] = false; break;
    }
    // Mark as executed for this specific key if targeted, or handle differently for 'ALL'
    // For 'ALL', we might need a join table to track execution per key, but for simplicity:
    if ($cmd['target'] === 'KEY') {
        $pdo->prepare("UPDATE admin_commands SET is_executed = 1 WHERE id = ?")->execute([$cmd['id']]);
    }
}

echo json_encode($response);
