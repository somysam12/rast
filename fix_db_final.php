<?php
require_once 'config/database.php';
$pdo = getDBConnection();

try {
    // Add description column if missing
    $pdo->exec("ALTER TABLE transactions ADD COLUMN description TEXT");
} catch (Exception $e) {
    // Column might already exist
}

// Fix existing records
// 1. Set status to completed for all processed transactions
$pdo->exec("UPDATE transactions SET status = 'completed' WHERE status = 'pending' OR status IS NULL");

// 2. Make debit/purchase amounts negative
$pdo->exec("UPDATE transactions SET amount = -ABS(amount) WHERE type IN ('debit', 'purchase')");

// 3. Update descriptions for old records if empty
$pdo->exec("UPDATE transactions SET description = 'License key purchase' WHERE (description IS NULL OR description = '') AND type IN ('debit', 'purchase')");
$pdo->exec("UPDATE transactions SET description = 'Balance added by admin' WHERE (description IS NULL OR description = '') AND type = 'balance_add'");

echo "Database maintenance completed successfully";
?>