<?php require_once "includes/optimization.php"; ?>
<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $keyId = $_POST['key_id'] ?? null;
    $requestType = $_POST['request_type'] ?? null;
    $reason = $_POST['reason'] ?? '';

    if ($keyId && $requestType) {
        try {
            // Get key details
            $stmt = $pdo->prepare("SELECT lk.id, lk.license_key, m.name FROM license_keys lk 
                                  JOIN mods m ON lk.mod_id = m.id 
                                  WHERE lk.id = ? AND lk.sold_to = ?");
            $stmt->execute([$keyId, $userId]);
            $key = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($key) {
                // Check if request already exists
                $stmt = $pdo->prepare("SELECT id FROM key_requests WHERE key_id = ? AND user_id = ? AND status = 'pending'");
                $stmt->execute([$keyId, $userId]);
                
                if ($stmt->fetchColumn()) {
                    $message = 'पहले से एक लंबित अनुरोध मौजूद है!';
                    $messageType = 'danger';
                } else {
                    // Create request
                    $stmt = $pdo->prepare("INSERT INTO key_requests (user_id, key_id, request_type, mod_name, reason) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $keyId, $requestType, $key['name'], $reason]);
                    
                    $message = 'आपकी अनुरोध सफलतापूर्वक जमा की गई है! एडमिन जल्द ही इसे प्रोसेस करेगा।';
                    $messageType = 'success';
                }
            } else {
                $message = 'यह कुंजी आपकी नहीं है!';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'त्रुटि: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get user's keys
$userKeys = [];
try {
    $stmt = $pdo->prepare("SELECT lk.id, lk.license_key, m.name as mod_name, lk.status, lk.duration, lk.duration_type 
                          FROM license_keys lk 
                          JOIN mods m ON lk.mod_id = m.id 
                          WHERE lk.sold_to = ? 
                          ORDER BY lk.sold_at DESC");
    $stmt->execute([$userId]);
    $userKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block Or Reset Key - Mod APK Manager</title>
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
            --accent: #7c3aed;
            --accent-600: #6d28d9;
            --accent-100: #f3e8ff;
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
            max-width: 900px;
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
        
        .btn-primary-custom {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .btn-primary-custom:hover {
            background: var(--accent-600);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .key-item {
            background: var(--bg);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .key-item:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.2);
        }
        
        .badge-custom {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .badge-sold {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
        
        .form-group-custom {
            margin-bottom: 1.5rem;
        }
        
        .form-group-custom label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-group-custom input,
        .form-group-custom select,
        .form-group-custom textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--bg);
            color: var(--text);
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group-custom input:focus,
        .form-group-custom select:focus,
        .form-group-custom textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
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
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="navbar-custom">
        <div class="container-custom">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: var(--accent); font-weight: 700;">🔐 Block Or Reset Key</h2>
                <a href="user_dashboard.php" class="btn-primary-custom">← Back</a>
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
            <h3 style="margin-bottom: 2rem; color: var(--accent);">अपनी कुंजी चुनें और कार्रवाई करें</h3>
            
            <?php if (empty($userKeys)): ?>
                <p style="color: var(--muted); text-align: center;">आपके पास कोई खरीदी हुई कुंजियाँ नहीं हैं।</p>
            <?php else: ?>
                <?php foreach ($userKeys as $key): ?>
                    <div class="key-item">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div>
                                <h4 style="margin: 0; color: var(--text);">📱 <?php echo htmlspecialchars($key['mod_name']); ?></h4>
                                <p style="margin: 0.5rem 0 0; color: var(--muted); font-size: 0.9rem; word-break: break-all;">
                                    Key: <strong><?php echo htmlspecialchars($key['license_key']); ?></strong>
                                </p>
                            </div>
                            <span class="badge-custom badge-sold">✓ सक्रिय</span>
                        </div>
                        
                        <form method="POST" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                            <input type="hidden" name="submit_request" value="1">
                            
                            <div style="flex: 1; min-width: 200px;">
                                <select name="request_type" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--line); border-radius: 6px; background: var(--bg); color: var(--text);">
                                    <option value="">-- कार्रवाई चुनें --</option>
                                    <option value="block">🚫 Block करें</option>
                                    <option value="reset">↻ Reset करें</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-primary-custom" style="border: none; white-space: nowrap;">जमा करें</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
