<?php
require_once '../config/database.php';
try {
    $pdo = getDBConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS stock_alerts (
        id SERIAL PRIMARY KEY,
        mod_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        username VARCHAR(255) NOT NULL,
        mod_name VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table created successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
