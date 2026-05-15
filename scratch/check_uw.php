<?php
require_once 'db_connection.php';
\ = \->query(\"
    SELECT u.name, h.bmi_classification, h.weight, h.height, h.date_taken, u.unit 
    FROM health_records h 
    JOIN users u ON h.user_id = u.id 
    WHERE h.bmi_classification = 'UNDERWEIGHT' 
      AND h.weight IS NOT NULL AND h.weight > 0
    ORDER BY h.date_taken DESC
\");
\ = \->fetchAll(PDO::FETCH_ASSOC);
print_r(\);
?>
