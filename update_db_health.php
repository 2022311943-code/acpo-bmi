<?php
require_once 'db_connection.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS health_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        height DECIMAL(5,2) DEFAULT 0,
        weight DECIMAL(5,2) DEFAULT 0,
        waist DECIMAL(5,2) DEFAULT 0,
        hip DECIMAL(5,2) DEFAULT 0,
        wrist DECIMAL(5,2) DEFAULT 0,
        bmi_result DECIMAL(5,2) DEFAULT 0,
        bmi_classification VARCHAR(100) DEFAULT '',
        normal_weight VARCHAR(100) DEFAULT '',
        weight_to_lose VARCHAR(100) DEFAULT '',
        intervention_package VARCHAR(100) DEFAULT '',
        certified_correct VARCHAR(100) DEFAULT '',
        date_taken DATE DEFAULT NULL,
        img_right LONGTEXT,
        img_front LONGTEXT,
        img_left LONGTEXT,
        monthly_weights TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "Table 'health_records' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>