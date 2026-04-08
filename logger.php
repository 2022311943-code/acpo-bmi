<?php
// logger.php
require_once 'db_connection.php';

function logAction($admin_id, $target_user_id, $action_type, $action_details) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, target_user_id, action_type, action_details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $target_user_id, $action_type, $action_details]);
        return true;
    } catch (PDOException $e) {
        // Silently fail or log to error log
        return false;
    }
}
?>
