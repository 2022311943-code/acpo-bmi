<?php
require 'db_connection.php';
try {
    $pdo->exec("ALTER TABLE health_records ADD COLUMN monthly_waists TEXT AFTER monthly_weights");
    $pdo->exec("ALTER TABLE health_records ADD COLUMN monthly_hips TEXT AFTER monthly_waists");
    $pdo->exec("ALTER TABLE health_records ADD COLUMN monthly_wrists TEXT AFTER monthly_hips");
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
