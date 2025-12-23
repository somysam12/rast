<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$pdo = getDBConnection();
$keyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$confirm = isset($_GET['confirm']) ? (int)$_GET['confirm'] : 0;
$error = $success = '';

// Get key details
$stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name FROM license_keys lk 
                      LEFT JOIN mods m ON lk.mod_id = m.id 
                      WHERE lk.id = ?");
$stmt->execute([$keyId]);
$key = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$key) {
    header('Location: available_keys.php');
    exit();
}

// Handle deletion
if ($confirm == 1 && $_POST) {
    try {
        $stmt = $pdo->prepare("DELETE FROM license_keys WHERE id = ?");
        if ($stmt->execute([$keyId])) {
            header('Location: available_keys.php?success=License key deleted successfully');
            exit();
        } else {
            $error = 'Failed to delete license key';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete License Key - SilentMultiPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .delete-container { max-width: 500px; margin: 50px auto; }
        .delete-card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .warning-icon { font-size: 64px; color: #ff6b6b; text-align: center; margin-bottom: 20px; }
        .key-info { background: #fff5f5; padding: 15px; border-radius: 8px; border-left: 4px solid #ff6b6b; margin: 20px 0; }
        .key-info h5 { color: #991b1b; margin-bottom: 10px; }
        .key-info p { color: #666; margin: 5px 0; font-family: 'Courier New', monospace; font-weight: bold; }
        .btn-group-delete { gap: 10px; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-sold { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h2 class="text-center mb-3">Delete License Key</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="key-info">
                <h5><i class="fas fa-key me-2"></i>License Key Details</h5>
                <p><?php echo htmlspecialchars($key['license_key']); ?></p>
                <p><strong>Mod:</strong> <?php echo htmlspecialchars($key['mod_name']); ?></p>
                <p><strong>Duration:</strong> <?php echo $key['duration'] . ' ' . $key['duration_type']; ?></p>
                <p><strong>Price:</strong> ₹<?php echo number_format($key['price'], 2); ?></p>
                <p style="margin-top: 10px;">
                    <strong>Status:</strong> 
                    <span class="status-badge status-<?php echo $key['status']; ?>">
                        <?php echo ucfirst($key['status']); ?>
                    </span>
                </p>
                <?php if ($key['status'] == 'sold'): ?>
                    <p style="color: #991b1b; margin-top: 10px;">
                        <strong>⚠️ This key has been sold!</strong><br>
                        Deleting it won't refund the customer.
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="alert alert-warning">
                <strong><i class="fas fa-info-circle me-2"></i>Warning:</strong>
                <ul class="mb-0 mt-2">
                    <li>This action <strong>cannot be undone</strong></li>
                    <li>The license key will be permanently removed</li>
                    <?php if ($key['status'] == 'sold'): ?>
                        <li>The customer will retain their license (no refund issued)</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <form method="POST" class="mt-4">
                <div class="btn-group-delete d-flex">
                    <a href="available_keys.php" class="btn btn-secondary flex-grow-1">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-danger flex-grow-1">
                        <i class="fas fa-trash me-2"></i>Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
