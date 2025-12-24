<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');

$pdo = getDBConnection();
$stats = ['total_users' => 0, 'total_balance' => 0, 'avg_balance' => 0, 'total_income' => 0, 'total_expenses' => 0];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(balance), 0) as total_balance, COALESCE(AVG(balance), 0) as avg_balance FROM users WHERE role = 'user'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats['total_users'] = (int)($row['count'] ?? 0);
        $stats['total_balance'] = (float)($row['total_balance'] ?? 0);
        $stats['avg_balance'] = (float)($row['avg_balance'] ?? 0);
    }
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as income FROM transactions WHERE amount > 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_income'] = (float)($row['income'] ?? 0);
    
    $stmt = $pdo->query("SELECT COALESCE(ABS(SUM(amount)), 0) as expenses FROM transactions WHERE amount < 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_expenses'] = (float)($row['expenses'] ?? 0);
} catch (Exception $e) {}

echo json_encode($stats);
?>
