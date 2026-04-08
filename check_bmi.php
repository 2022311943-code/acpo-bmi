<?php
require 'db_connection.php';
$stmt = $pdo->prepare("SELECT u.name, u.unit, (SELECT bmi_classification FROM health_records h WHERE h.user_id = u.id ORDER BY date_taken DESC LIMIT 1) as bmi_class FROM users u WHERE role='user' LIMIT 20");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
