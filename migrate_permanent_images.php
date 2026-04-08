<?php
require_once 'db_connection.php';

echo "<h2>ACPO Image Migration: Permanent Profile Pictures</h2>";
echo "<p>This script moves user photos from individual health records to the main user profile to make them permanent.</p>";

try {
    // 1. Add columns to 'users' table if they don't exist
    $pdo->exec("ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS img_right LONGTEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS img_front LONGTEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS img_left LONGTEXT DEFAULT NULL");
    echo "<div style='color:green;'>✓ Added image columns to 'users' table.</div>";

    // 2. Fetch all users
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        $user_id = $user['id'];
        
        // 3. Get the absolute LATEST images available for this user from any of their health records
        $img_stmt = $pdo->prepare("SELECT img_right, img_front, img_left 
                                   FROM health_records 
                                   WHERE user_id = ? 
                                   AND (img_right IS NOT NULL OR img_front IS NOT NULL OR img_left IS NOT NULL)
                                   ORDER BY date_taken DESC, id DESC LIMIT 1");
        $img_stmt->execute([$user_id]);
        $latest = $img_stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest) {
            // 4. Update the user profile with these images
            $update_stmt = $pdo->prepare("UPDATE users SET img_right = ?, img_front = ?, img_left = ? WHERE id = ?");
            $update_stmt->execute([
                $latest['img_right'],
                $latest['img_front'],
                $latest['img_left'],
                $user_id
            ]);
            echo "<div style='color:blue;'>→ Migrated images for User ID: $user_id</div>";
        }
    }

    echo "<p><b>Migration Successful!</b> Images are now stored in the user profile.</p>";
    echo "<p style='color:red;'><i>You can now safely delete this file (migrate_permanent_images.php).</i></p>";

} catch (Exception $e) {
    echo "<div style='color:red;'><h3>CRITICAL ERROR:</h3> " . $e->getMessage() . "</div>";
}
?>
