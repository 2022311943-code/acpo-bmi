<?php
require_once 'db_connection.php';

echo "<h2>ACPO Database Migration Tool</h2>";
echo "<p>This script will allow multiple health records per user by removing the UNIQUE constraint on 'user_id'.</p>";

try {
    // 1. Drop foreign key first (it depends on the unique index usually)
    try {
        $pdo->exec("ALTER TABLE health_records DROP FOREIGN KEY health_records_ibfk_1");
        echo "<div style='color:green;'>✓ Dropped foreign key constraint.</div>";
    } catch (PDOException $e) {
        echo "<div style='color:orange;'>! Notice FK: " . $e->getMessage() . " (May already be dropped)</div>";
    }

    // 2. Drop the UNIQUE index
    try {
        $pdo->exec("ALTER TABLE health_records DROP INDEX user_id");
        echo "<div style='color:green;'>✓ Dropped UNIQUE index on user_id.</div>";
    } catch (PDOException $e) {
        echo "<div style='color:orange;'>! Notice Index: " . $e->getMessage() . " (May not exist or name is different)</div>";
    }

    // 3. Add a normal (non-unique) index for the foreign key and performance
    try {
        $pdo->exec("ALTER TABLE health_records ADD INDEX idx_user_id (user_id)");
        echo "<div style='color:green;'>✓ Added normal index on user_id.</div>";
    } catch (PDOException $e) {
        echo "<div style='color:red;'>✗ Error Adding Index: " . $e->getMessage() . "</div>";
    }

    // 4. Re-add the foreign key
    try {
        $pdo->exec("ALTER TABLE health_records ADD CONSTRAINT health_records_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE");
        echo "<div style='color:green;'>✓ Re-added foreign key constraint.</div>";
    } catch (PDOException $e) {
        echo "<div style='color:red;'>✗ Error Re-adding FK: " . $e->getMessage() . "</div>";
    }

    echo "<p><b>Migration Complete!</b> You can now save multiple records per user for different months.</p>";
    echo "<p style='color:red;'><i>Please delete this file (fix_db.php) for security.</i></p>";

} catch (Exception $e) {
    echo "<div style='color:red;'><h3>CRITICAL ERROR:</h3> " . $e->getMessage() . "</div>";
}
?>
