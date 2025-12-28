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
            // Save scroll position to localStorage before redirect
            echo '<script>localStorage.setItem("scrollPos", window.scrollY); window.location.href="available_keys.php?success=License key deleted successfully";</script>';
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
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="assets/css/styles.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .delete-container { max-width: 500px; margin: 50px auto; }
        .delete-card { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); animation: slideDown 0.5s ease-out; }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .warning-icon { font-size: 64px; color: #ff6b6b; text-align: center; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .key-info { background: #fff5f5; padding: 15px; border-radius: 8px; border-left: 4px solid #ff6b6b; margin: 20px 0; }
        .key-info h5 { color: #991b1b; margin-bottom: 10px; }
        .key-info p { color: #666; margin: 5px 0; font-family: 'Courier New', monospace; font-weight: bold; }
        .btn-group-delete { gap: 10px; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-available { background: #d1fae5; color: #065f46; }
        .status-sold { background: #fee2e2; color: #991b1b; }
        
        /* Custom SweetAlert2 Styling */
        .swal-delete-popup {
            border-radius: 16px !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%) !important;
            border: 1px solid rgba(102, 126, 234, 0.15) !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
            animation: popupSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        }
        
        @keyframes popupSlideIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .swal-delete-title {
            font-size: 1.75rem !important;
            font-weight: 700 !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
        }
        
        .swal2-html-container {
            color: #333 !important;
            font-size: 1rem !important;
            line-height: 1.6 !important;
        }
        
        .swal-delete-confirm {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 12px 32px !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4) !important;
            transition: all 0.3s ease !important;
        }
        
        .swal-delete-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.6) !important;
        }
        
        .swal-delete-confirm:active {
            transform: translateY(0) !important;
        }
        
        .swal-delete-cancel {
            background: linear-gradient(135deg, #e0e0e0 0%, #d0d0d0 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 12px 32px !important;
            color: #333 !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .swal-delete-cancel:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
        }
        
        .swal2-icon {
            border: none !important;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Force hide all backdrops */
        .swal2-backdrop-show {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        
        .swal2-backdrop {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
    </style>
    <link href="assets/css/theme.css" rel="stylesheet">
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
                <p><strong>Price:</strong> ₹<?php echo number_format($key['price'], 2, '.', ','); ?></p>
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
            
            <form method="POST" class="mt-4" id="deleteForm">
                <div class="btn-group-delete d-flex">
                    <a href="available_keys.php" class="btn btn-secondary flex-grow-1">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="button" class="btn btn-danger flex-grow-1" id="deleteBtn">
                        <i class="fas fa-trash me-2"></i>Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.getElementById('deleteBtn').addEventListener('click', function() {
            const keyValue = '<?php echo htmlspecialchars($key['license_key']); ?>';
            const modName = '<?php echo htmlspecialchars($key['mod_name']); ?>';
            
            Swal.fire({
                title: 'Confirm Deletion',
                html: `<div style="text-align: left;">
                    <div style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
                        <div style="position: relative; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <div style="position: absolute; width: 100%; height: 100%; border: 3px solid rgba(255, 107, 107, 0.2); border-radius: 50%; animation: spin 3s linear infinite;"></div>
                            <i class="fas fa-exclamation-triangle" style="font-size: 2.5rem; color: #ff6b6b;"></i>
                        </div>
                    </div>
                    <p style="color: #333; font-size: 1rem; margin-bottom: 1rem;">
                        <strong>This action cannot be undone!</strong>
                    </p>
                    <div style="background: #fff5f5; padding: 12px; border-radius: 8px; border-left: 4px solid #ff6b6b; margin-bottom: 1rem;">
                        <p style="margin: 5px 0; color: #666; font-size: 0.9rem;">
                            <strong>License Key:</strong><br>
                            <code style="background: #f0f0f0; padding: 6px 10px; border-radius: 4px; display: inline-block; margin-top: 5px; font-weight: bold; color: #333;">` + keyValue + `</code>
                        </p>
                        ${modName ? '<p style="margin: 8px 0; color: #666; font-size: 0.9rem;"><strong>Mod:</strong> ' + modName + '</p>' : ''}
                    </div>
                    <p style="color: #d32f2f; font-size: 0.9rem; margin: 0;">
                        <i class="fas fa-shield-alt me-2"></i>Are you absolutely sure you want to delete this license key?
                    </p>
                </div>`,
                icon: undefined,
                showCancelButton: true,
                confirmButtonColor: '#ff6b6b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Yes, Delete Permanently',
                cancelButtonText: '<i class="fas fa-ban me-2"></i>Cancel',
                customClass: {
                    popup: 'swal-delete-popup',
                    title: 'swal-delete-title',
                    confirmButton: 'swal-delete-confirm',
                    cancelButton: 'swal-delete-cancel',
                    htmlContainer: 'swal-html-container'
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                didClose: () => {
                    document.querySelectorAll('.swal2-backdrop').forEach(el => el.remove());
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading animation
                    Swal.fire({
                        title: 'Deleting...',
                        html: `<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px;">
                            <div style="width: 50px; height: 50px; border: 4px solid rgba(102, 126, 234, 0.2); border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                            <p style="margin-top: 1rem; color: #666; font-weight: 500;">Processing deletion...</p>
                        </div>`,
                        icon: undefined,
                        customClass: {
                            popup: 'swal-delete-popup'
                        },
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                            // Submit the form after a brief delay
                            setTimeout(() => {
                                document.getElementById('deleteForm').submit();
                            }, 500);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
