<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

requireAdmin();

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle admin action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'] ?? null;
    $action = $_POST['action'] ?? null;
    
    if ($requestId && in_array($action, ['approve', 'reject'])) {
        try {
            // Get request details
            $stmt = $pdo->prepare("SELECT * FROM key_requests WHERE id = ?");
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

// Get pending requests with user info
$pendingRequests = [];
try {
    $stmt = $pdo->query("SELECT kr.*, u.username FROM key_requests kr 
                        JOIN users u ON kr.user_id = u.id 
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
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);
        }
        
        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .badge-block {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .badge-reset {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .btn-approve, .btn-reject {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .btn-approve {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
            border: 1px solid #16a34a;
        }
        
        .btn-approve:hover {
            background: #16a34a;
            color: white;
        }
        
        .btn-reject {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid #dc2626;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-back {
            background: var(--purple);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-back:hover {
            background: var(--purple-600);
            color: white;
            text-decoration: none;
        }
        
        .alert-custom {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
            color: #16a34a;
        }
    </style>
</head>
<body>
    <div class="navbar-custom">
        <div class="container-custom">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: var(--purple); font-weight: 700;">📋 Block And Reset Requests</h2>
                <a href="admin_dashboard.php" class="btn-back">← Back</a>
            </div>
        </div>
    </div>
    
    <div class="container-custom">
        <?php if ($message): ?>
            <div class="alert-custom alert-<?php echo $messageType; ?>">
                <strong><?php echo $messageType === 'success' ? '✓' : '✕'; ?></strong> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card-custom">
            <?php if (empty($pendingRequests)): ?>
                <p style="color: var(--muted); text-align: center; font-size: 1.1rem;">No pending requests at this time.</p>
            <?php else: ?>
                <h3 style="margin-bottom: 1.5rem; color: var(--text);">Total Pending Requests: <?php echo count($pendingRequests); ?></h3>
                
                <?php foreach ($pendingRequests as $req): ?>
                    <div class="request-card">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <h4 style="margin: 0; color: var(--text);">👤 <?php echo htmlspecialchars($req['username']); ?></h4>
                                <p style="margin: 0.5rem 0; color: var(--muted); font-size: 0.9rem;">
                                    <strong>Product:</strong> <?php echo htmlspecialchars($req['mod_name']); ?>
                                </p>
                                <p style="margin: 0.5rem 0; color: var(--muted); font-size: 0.85rem;">
                                    <strong>Request Date:</strong> <?php echo date('d M Y H:i', strtotime($req['created_at'])); ?>
                                </p>
                            </div>
                            <span class="badge-custom badge-<?php echo $req['request_type'] === 'block' ? 'block' : 'reset'; ?>">
                                <?php echo $req['request_type'] === 'block' ? '🚫 BLOCK' : '↻ RESET'; ?>
                            </span>
                        </div>
                        
                        <?php if ($req['reason']): ?>
                            <p style="background: var(--line); padding: 0.75rem; border-radius: 6px; margin: 1rem 0; color: var(--text); font-size: 0.9rem;">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($req['reason']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: flex; gap: 0.75rem;">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve">✓ Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-reject">✕ Reject</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
