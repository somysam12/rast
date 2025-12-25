<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

function formatDate($dt){
    if(!$dt){ return '-'; }
    return date('d M Y, h:i A', strtotime($dt));
}

// Handle admin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($requestId && in_array($action, ['approve', 'reject'])) {
        try {
            // Get request details
            $stmt = $pdo->prepare("SELECT kr.*, lk.license_key, lk.duration, lk.duration_type FROM key_requests kr 
                                   LEFT JOIN license_keys lk ON lk.id = kr.key_id WHERE kr.id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                
                // Update request status
                $stmt = $pdo->prepare("UPDATE key_requests SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $requestId]);
                
                // Create confirmation notification
                if ($action === 'approve') {
                    $msgText = "Your request to {$request['request_type']} the key for {$request['mod_name']} has been approved. Your key has been {$request['request_type']}ed.";
                    $actionType = $request['request_type'];
                } else {
                    $msgText = "Your request to {$request['request_type']} the key for {$request['mod_name']} has been rejected.";
                    $actionType = $request['request_type'];
                }
                
                $stmt = $pdo->prepare("INSERT INTO key_confirmations (user_id, request_id, action_type, message) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$request['user_id'], $requestId, $actionType, $msgText]);
                
                $message = $action === 'approve' ? 'Request approved!' : 'Request rejected!';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get pending requests with key details
$pendingRequests = [];
try {
    $stmt = $pdo->query("SELECT kr.*, u.username, lk.license_key, lk.duration, lk.duration_type FROM key_requests kr 
                        JOIN users u ON kr.user_id = u.id 
                        LEFT JOIN license_keys lk ON lk.id = kr.key_id
                        WHERE kr.status = 'pending' 
                        ORDER BY kr.created_at DESC");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block And Reset Requests - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f9fafb;
            --card: #ffffff;
            --text: #374151;
            --muted: #6b7280;
            --line: #e5e7eb;
            --purple: #8b5cf6;
            --purple-600: #7c3aed;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }
        
        [data-theme="dark"] {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --line: #334155;
        }
        
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        .navbar-custom {
            background: var(--card);
            border-bottom: 1px solid var(--line);
            padding: 1rem 1.5rem;
        }
        
        .container-custom {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .card-custom {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-light);
        }
        
        .request-card {
            background: var(--bg);
            border-left: 4px solid var(--purple);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .request-card.block {
            border-left-color: #ef4444;
        }
        
        .request-card.reset {
            border-left-color: #f59e0b;
        }
        
        .btn-back {
            margin-bottom: 2rem;
        }
        
        .btn-back a {
            color: var(--purple);
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-back a:hover {
            text-decoration: underline;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .request-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .request-type-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .request-type-badge.block {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .request-type-badge.reset {
            background: #fef3c7;
            color: #92400e;
        }
        
        .key-details {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .key-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--line);
        }
        
        .key-detail-row:last-child {
            border-bottom: none;
        }
        
        .key-detail-label {
            font-weight: 600;
            color: var(--muted);
        }
        
        .key-detail-value {
            color: var(--text);
            font-family: 'Courier New', monospace;
        }
        
        .reason-box {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .btn-approve:hover {
            background: #059669;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .btn-reject:hover {
            background: #dc2626;
        }
        
        .total-pending {
            background: var(--purple);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .total-pending-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
    </style>
    <link href="assets/css/mobile-fixes.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <script src="assets/js/dark-mode.js"></script>
    <link href="assets/css/hamburger-fix.css" rel="stylesheet">
</head>
<body>
    <div class="navbar-custom">
        <div class="container-custom">
            <h1 style="color: var(--purple); margin: 0; font-weight: 700;">Block And Reset Requests</h1>
        </div>
    </div>
    
    <div class="container-custom">
        <div class="btn-back">
            <a href="admin_dashboard.php">← Back to Dashboard</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="total-pending">
            <div>Total Pending Requests:</div>
            <div class="total-pending-number"><?php echo count($pendingRequests); ?></div>
        </div>
        
        <?php if (empty($pendingRequests)): ?>
            <div class="card-custom text-center">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--purple); margin-bottom: 1rem; display: block;"></i>
                <p style="color: var(--muted);">No pending requests at this moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingRequests as $request): ?>
                <div class="request-card <?php echo htmlspecialchars($request['request_type']); ?>">
                    <div class="request-header">
                        <div class="request-user">
                            <i class="fas fa-user-circle" style="font-size: 1.5rem;"></i>
                            <div>
                                <div><?php echo htmlspecialchars($request['username']); ?></div>
                                <div style="font-size: 0.85em; color: var(--muted); font-weight: 400;">
                                    Request Date: <?php echo formatDate($request['created_at']); ?>
                                </div>
                            </div>
                        </div>
                        <span class="request-type-badge <?php echo htmlspecialchars($request['request_type']); ?>">
                            <?php echo strtoupper($request['request_type']); ?>
                        </span>
                    </div>
                    
                    <!-- Key Details -->
                    <div class="key-details">
                        <div class="key-detail-row">
                            <span class="key-detail-label">Product:</span>
                            <span class="key-detail-value"><?php echo htmlspecialchars($request['mod_name']); ?></span>
                        </div>
                        <div class="key-detail-row">
                            <span class="key-detail-label">Duration:</span>
                            <span class="key-detail-value"><?php echo htmlspecialchars($request['duration'] . ' ' . ucfirst($request['duration_type'])); ?></span>
                        </div>
                        <div class="key-detail-row">
                            <span class="key-detail-label">License Key:</span>
                            <span class="key-detail-value"><?php echo htmlspecialchars(substr($request['license_key'], 0, 30)) . (strlen($request['license_key']) > 30 ? '...' : ''); ?></span>
                        </div>
                    </div>
                    
                    <!-- Reason -->
                    <div>
                        <strong style="color: var(--muted);">Reason:</strong>
                        <div class="reason-box">
                            <?php echo htmlspecialchars($request['reason']); ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve">
                                <i class="fas fa-check me-2"></i>Approve
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" name="action" value="reject" class="btn-reject">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
