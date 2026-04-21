<?php
require_once 'db_connection.php';
$month = date('m');
$year = date('Y');
$sql = "SELECT COUNT(*) FROM users u 
        INNER JOIN health_records h ON u.id = h.user_id 
        WHERE u.role = 'user' 
        AND MONTH(h.date_taken) = ? AND YEAR(h.date_taken) = ?
        AND (u.img_front IS NULL OR u.img_front = '' OR u.img_right IS NULL OR u.img_right = '' OR u.img_left IS NULL OR u.img_left = '')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$month, $year]);
echo "Count with record but no pics: " . $stmt->fetchColumn();
?>
