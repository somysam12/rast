<?php
require_once 'config/database.php';

echo "Initializing database...\n";

try {
    initializeDatabase();
    echo "Database tables created successfully!\n";
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $modCount = $stmt->fetchColumn();
    
    if ($modCount == 0) {
        $pdo->exec("INSERT INTO mods (name, description, status) VALUES
            ('ADMIN SERVER', 'Premium admin server mod with advanced features', 'active'),
            ('KING MOD APK', '4.0 (KUMARES) - King of mods with unlimited features', 'active'),
            ('TRX MOD', 'High-performance mod with TRX optimization', 'active'),
            ('ZERO KILL 120 FPS', '4.0 - Zero kill mod with 120 FPS support', 'inactive'),
            ('Zero kill mod key', '4.0 - Zero kill mod key for premium access', 'active')");
        echo "Sample mods inserted!\n";
        
        $pdo->exec("INSERT INTO license_keys (mod_id, license_key, duration, duration_type, price, status) VALUES
            (1, 'ADMINSER-1D-A1B2C3D4E5', 1, 'days', 90.00, 'available'),
            (1, 'ADMINSER-7D-F6G7H8I9J0', 7, 'days', 350.00, 'available'),
            (1, 'ADMINSER-30D-K1L2M3N4O5', 30, 'days', 850.00, 'available'),
            (2, 'KINGMOD-5H-P6Q7R8S9T0', 5, 'hours', 30.00, 'available'),
            (2, 'KINGMOD-1D-U1V2W3X4Y5', 1, 'days', 60.00, 'available'),
            (2, 'KINGMOD-3D-Z6A7B8C9D0', 3, 'days', 150.00, 'available'),
            (2, 'KINGMOD-7D-E1F2G3H4I5', 7, 'days', 300.00, 'available'),
            (2, 'KINGMOD-30D-J6K7L8M9N0', 30, 'days', 600.00, 'available'),
            (2, 'KINGMOD-60D-O1P2Q3R4S5', 60, 'days', 1200.00, 'available'),
            (3, 'TRXMOD-1D-T6U7V8W9X0', 1, 'days', 75.00, 'available'),
            (3, 'TRXMOD-7D-Y1Z2A3B4C5', 7, 'days', 400.00, 'available'),
            (4, 'ZEROKIL-1D-D6E7F8G9H0', 1, 'days', 50.00, 'available'),
            (5, 'ZEROKIL-1D-I1J2K3L4M5', 1, 'days', 45.00, 'available')");
        echo "Sample license keys inserted!\n";
    }
    
    echo "\nSetup complete!\n";
    echo "Admin Login: admin / admin123\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
