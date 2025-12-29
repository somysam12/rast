<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$mod_id = isset($_POST['mod_id']) ? (int)$_POST['mod_id'] : 0;

if (!$mod_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid mod ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    
    // Get mod name
    $stmt = $pdo->prepare("SELECT name FROM mods WHERE id = ?");
    $stmt->execute([$mod_id]);
    $mod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mod) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Mod not found']);
        exit;
    }
    
    // Check if alert already exists for this user and mod
    $stmt = $pdo->prepare("SELECT id FROM stock_alerts WHERE mod_id = ? AND user_id = ? AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$mod_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'You already sent an alert for this product in the last 24 hours']);
        exit;
    }
    
    // Insert alert
    $stmt = $pdo->prepare("INSERT INTO stock_alerts (mod_id, user_id, username, mod_name) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$mod_id, $user_id, $username, $mod['name']])) {
        echo json_encode(['success' => true, 'message' => 'Alert sent successfully! Admin will be notified']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send alert']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>