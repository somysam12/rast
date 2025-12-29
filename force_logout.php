<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id && $pdo) {
    try {
        // Delete all sessions for the user
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Log the force logout
        $stmt = $pdo->prepare("INSERT INTO force_logouts (user_id, logged_out_by) VALUES (?, ?)");
        $stmt->execute([$user_id, $_SESSION['user_id']]);
        
        header('Location: manage_users.php?success=User+forcefully+logged+out');
        exit();
    } catch (Exception $e) {
        header('Location: manage_users.php?error=Failed+to+logout+user');
        exit();
    }
} else {
    header('Location: manage_users.php?error=Invalid+user');
    exit();
}
?>
