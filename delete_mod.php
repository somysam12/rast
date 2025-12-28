<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$modId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;
$error = $success = '';

// Get mod details
$stmt = $pdo->prepare("SELECT m.*, COUNT(lk.id) as key_count FROM mods m 
                      LEFT JOIN license_keys lk ON m.id = lk.mod_id 
                      WHERE m.id = ? 
                      GROUP BY m.id");
$stmt->execute([$modId]);
$mod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mod) {
    header('Location: manage_mods.php');
    exit();
}

// Handle deletion
if ($confirm == 1 && $_POST) {
    try {
        $pdo->beginTransaction();
        
        // Delete all associated license keys (cascades automatically)
        $stmt = $pdo->prepare("DELETE FROM license_keys WHERE mod_id = ?");
        $stmt->execute([$modId]);
        
        // Delete mod
        $stmt = $pdo->prepare("DELETE FROM mods WHERE id = ?");
        if ($stmt->execute([$modId])) {
            $pdo->commit();
            header('Location: manage_mods.php?success=Mod deleted successfully');
            exit();
        } else {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Failed to delete mod';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="assets/css/global.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Mod - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .delete-container { max-width: 500px; margin: 50px auto; }
        .delete-card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .warning-icon { font-size: 64px; color: #ff6b6b; text-align: center; margin-bottom: 20px; }
        .mod-info { background: #fff5f5; padding: 15px; border-radius: 8px; border-left: 4px solid #ff6b6b; margin: 20px 0; }
        .mod-info h5 { color: #991b1b; margin-bottom: 10px; }
        .mod-info p { color: #666; margin: 5px 0; }
        .btn-group-delete { gap: 10px; }
    </style>
    <link href="assets/css/theme.css" rel="stylesheet">
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h2 class="text-center mb-3">Delete Mod</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="mod-info">
                <h5><i class="fas fa-gamepad me-2"></i><?php echo htmlspecialchars($mod['name']); ?></h5>
                <p><strong>Status:</strong> <?php echo ucfirst($mod['status']); ?></p>
                <p><strong>License Keys:</strong> <?php echo $mod['key_count']; ?></p>
                <p><strong>Created:</strong> <?php echo date('d M Y', strtotime($mod['created_at'])); ?></p>
            </div>
            
            <div class="alert alert-warning">
                <strong><i class="fas fa-info-circle me-2"></i>Warning:</strong>
                <ul class="mb-0 mt-2">
                    <li>This action <strong>cannot be undone</strong></li>
                    <li>All <?php echo $mod['key_count']; ?> associated license keys will be permanently deleted</li>
                    <li>Users who purchased keys will not have their purchases reversed</li>
                </ul>
            </div>
            
            <form method="POST" class="mt-4">
                <div class="btn-group-delete d-flex">
                    <a href="manage_mods.php" class="btn btn-secondary flex-grow-1">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-danger flex-grow-1">
                        <i class="fas fa-trash me-2"></i>Yes, Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
