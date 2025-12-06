<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$response = [
    'status' => 'ok',
    'timestamp' => date('c')
];

try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    $pdo->query('SELECT 1');
    $response['database'] = 'connected';
} catch (Exception $e) {
    $response['database'] = 'disconnected';
    $response['status'] = 'degraded';
}

http_response_code($response['status'] === 'ok' ? 200 : 503);
echo json_encode($response);
?>
