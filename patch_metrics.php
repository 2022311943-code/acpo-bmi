<?php
$file = 'c:/xampp/htdocs/ACPO/editor.php';
$content = file_get_contents($file);

// 1. Save logic update
$old_save = '        // Handle Monthly Weights (JSON)
        $monthly_weights_raw = $_POST[\'monthly_weights\'] ?? [];
        $monthly_weights = json_encode($monthly_weights_raw);

        // Check if record exists for this specific month/year
        $month = date(\'m\', strtotime($date_taken));
        $year = date(\'Y\', strtotime($date_taken));
        
        $stmt = $pdo->prepare("SELECT id FROM health_records WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ?");
        $stmt->execute([$user_id_to_save, $month, $year]);
        $existing_record = $stmt->fetch();
        
        // Handle Image Uploads
        $img_right = $_POST[\'existing_img_right\'] ?? null;
        $img_front = $_POST[\'existing_img_front\'] ?? null;
        $img_left = $_POST[\'existing_img_left\'] ?? null;

        function handleImageUpload($inputName, $defaultValue) {
            if (isset($_POST[\'compressed_\' . $inputName]) && !empty($_POST[\'compressed_\' . $inputName])) {
                return $_POST[\'compressed_\' . $inputName];
            }
            return $defaultValue;
        }

        $img_right = handleImageUpload(\'img_right\', $img_right);
        $img_front = handleImageUpload(\'img_front\', $img_front);
        $img_left = handleImageUpload(\'img_left\', $img_left);

        // Save Images to USERS table (Permanent)
        $user_img_sql = "UPDATE users SET img_right = ?, img_front = ?, img_left = ? WHERE id = ?";
        $user_img_stmt = $pdo->prepare($user_img_sql);
        $user_img_stmt->execute([$img_right, $img_front, $img_left, $user_id_to_save]);

        if ($existing_record) {
            // Update the existing record for this month
            $sql = "UPDATE health_records SET 
                height = ?, weight = ?, waist = ?, hip = ?, wrist = ?, 
                bmi_result = ?, bmi_classification = ?, normal_weight = ?, weight_to_lose = ?, 
                intervention_package = ?, certified_correct = ?, date_taken = ?, 
                monthly_weights = ?
                WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $height, $weight, $waist, $hip, $wrist, 
                $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose,
                $intervention, $certified, $date_taken,
                $monthly_weights,
                $existing_record[\'id\']
            ]);
            logAction($_SESSION[\'user_id\'], $user_id_to_save, \'Update BMI Record\', "Updated BMI for ".date(\'F Y\', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
        } else {
            // Insert new record for this month
            $sql = "INSERT INTO health_records 
                (user_id, height, weight, waist, hip, wrist, bmi_result, bmi_classification, normal_weight, weight_to_lose, intervention_package, certified_correct, date_taken, monthly_weights)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id_to_save, $height, $weight, $waist, $hip, $wrist, 
                $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose,
                $intervention, $certified, $date_taken,
                $monthly_weights
            ]);
            logAction($_SESSION[\'user_id\'], $user_id_to_save, \'Add BMI Record\', "Added new BMI for ".date(\'F Y\', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
        }';

$new_save = '        // Handle Monthly Metrics (JSON)
        $mt_weights = json_encode($_POST[\'monthly_weights\'] ?? []);
        $mt_waists = json_encode($_POST[\'monthly_waists\'] ?? []);
        $mt_hips = json_encode($_POST[\'monthly_hips\'] ?? []);
        $mt_wrists = json_encode($_POST[\'monthly_wrists\'] ?? []);

        if ($existing_record) {
            // Update the existing record for this month
            $sql = "UPDATE health_records SET 
                height = ?, weight = ?, waist = ?, hip = ?, wrist = ?, 
                bmi_result = ?, bmi_classification = ?, normal_weight = ?, weight_to_lose = ?, 
                intervention_package = ?, certified_correct = ?, date_taken = ?, 
                monthly_weights = ?, monthly_waists = ?, monthly_hips = ?, monthly_wrists = ?
                WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $height, $weight, $waist, $hip, $wrist, 
                $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose,
                $intervention, $certified, $date_taken,
                $mt_weights, $mt_waists, $mt_hips, $mt_wrists,
                $existing_record[\'id\']
            ]);
            logAction($_SESSION[\'user_id\'], $user_id_to_save, \'Update BMI Record\', "Updated BMI for ".date(\'F Y\', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
        } else {
            // Insert new record for this month
            $sql = "INSERT INTO health_records 
                (user_id, height, weight, waist, hip, wrist, bmi_result, bmi_classification, normal_weight, weight_to_lose, intervention_package, certified_correct, date_taken, monthly_weights, monthly_waists, monthly_hips, monthly_wrists)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $user_id_to_save, $height, $weight, $waist, $hip, $wrist, 
                $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose,
                $intervention, $certified, $date_taken,
                $mt_weights, $mt_waists, $mt_hips, $mt_wrists
            ]);
            logAction($_SESSION[\'user_id\'], $user_id_to_save, \'Add BMI Record\', "Added new BMI for ".date(\'F Y\', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
        }';

// Normalize line endings for replacement
$old_save = str_replace("\r\n", "\n", $old_save);
$content_norm = str_replace("\r\n", "\n", $content);

if (strpos($content_norm, $old_save) !== false) {
    $content_norm = str_replace($old_save, $new_save, $content_norm);
    echo "Save Block replaced\n";
} else {
    echo "Save Block NOT found\n";
}

// 2. Propagate logic update
$old_prop = '        // PROPAGATE Weight Changes to other months\' records
        $weights_array = $_POST[\'monthly_weights\'] ?? [];
        foreach ($weights_array as $m_key => $w_val) {
            if (empty($w_val) || !is_numeric($w_val)) continue;
            
            $parts = explode(\'-\', $m_key);
            if (count($parts) === 2) {
                $y_val = (int)$parts[0];
                $m_val = (int)$parts[1];
                
                // If a record exists for that month, update its \'weight\' column
                $up_sql = "UPDATE health_records SET weight = ? WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ?";
                $up_stmt = $pdo->prepare($up_sql);
                $up_stmt->execute([$w_val, $user_id_to_save, $m_val, $y_val]);
            }
        }';

$new_prop = '        // PROPAGATE Metric Changes to other months\' records
        $metrics_to_sync = [
            \'monthly_weights\' => \'weight\',
            \'monthly_waists\' => \'waist\',
            \'monthly_hips\' => \'hip\',
            \'monthly_wrists\' => \'wrist\'
        ];
        
        foreach ($metrics_to_sync as $post_key => $db_col) {
            $vals_array = $_POST[$post_key] ?? [];
            foreach ($vals_array as $m_key => $v_val) {
                if (empty($v_val) || !is_numeric($v_val)) continue;
                
                $parts = explode(\'-\', $m_key);
                if (count($parts) === 2) {
                    $y_val = (int)$parts[0];
                    $m_val = (int)$parts[1];
                    
                    // If a record exists for that month, update its corresponding column
                    $up_sql = "UPDATE health_records SET $db_col = ? WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ?";
                    $up_stmt = $pdo->prepare($up_sql);
                    $up_stmt->execute([$v_val, $user_id_to_save, $m_val, $y_val]);
                }
            }
        }';

$old_prop = str_replace("\r\n", "\n", $old_prop);
if (strpos($content_norm, $old_prop) !== false) {
    $content_norm = str_replace($old_prop, $new_prop, $content_norm);
    echo "Propagate Block replaced\n";
} else {
    echo "Propagate Block NOT found\n";
}

// 3. Cumulative metrics logic update
$old_cum = '        $json_weights = [];
        $record_weights = [];
        foreach ($all_history as $rec) {
            $latest_rec = $rec; // Keep track of the actual latest record
            
            // Collect Record Weight (Primary)
            $m_key_rec = date(\'Y-m\', strtotime($rec[\'date_taken\']));
            if (!empty($rec[\'weight\'])) $record_weights[$m_key_rec] = $rec[\'weight\'];
            
            // Collect Monitoring Weights (Filler)
            $w_json = json_decode($rec[\'monthly_weights\'] ?? \'[]\', true);
            if (is_array($w_json)) {
                foreach ($w_json as $mk => $wv) {
                    if (!empty($wv)) $json_weights[$mk] = $wv;
                }
            }
        }
        // RECORD weights always win over JSON historical weights
        $merged_weights = array_merge($json_weights, $record_weights);
        $cumulative_weights = $merged_weights;';

$new_cum = '        $cumulative_weights = [];
        $cumulative_waists = [];
        $cumulative_hips = [];
        $cumulative_wrists = [];
        
        foreach ($all_history as $rec) {
            $m_key_rec = date(\'Y-m\', strtotime($rec[\'date_taken\']));
            
            // Record-level wins (Primary source)
            if (!empty($rec[\'weight\'])) $cumulative_weights[$m_key_rec] = $rec[\'weight\'];
            if (!empty($rec[\'waist\'])) $cumulative_waists[$m_key_rec] = $rec[\'waist\'];
            if (!empty($rec[\'hip\'])) $cumulative_hips[$m_key_rec] = $rec[\'hip\'];
            if (!empty($rec[\'wrist\'])) $cumulative_wrists[$m_key_rec] = $rec[\'wrist\'];

            // Monitoring-level fills (JSON sources)
            $fill_settings = [
                \'monthly_weights\' => &$cumulative_weights,
                \'monthly_waists\' => &$cumulative_waists,
                \'monthly_hips\' => &$cumulative_hips,
                \'monthly_wrists\' => &$cumulative_wrists
            ];
            foreach($fill_settings as $json_key => &$target_arr) {
                $m_json = json_decode($rec[$json_key] ?? \'[]\', true);
                if (is_array($m_json)) {
                    foreach ($m_json as $mk => $mv) {
                        // Only fill if not already set by a primary record
                        if (!empty($mv) && !isset($target_arr[$mk])) {
                             $target_arr[$mk] = $mv;
                        }
                    }
                }
            }
        }
        $monthly_metrics = [
            \'WEIGHT\' => $cumulative_weights,
            \'WAIST\' => $cumulative_waists,
            \'HIP\' => $cumulative_hips,
            \'WRIST\' => $cumulative_wrists
        ];
        $monthly_weights = $cumulative_weights; // Backward compatibility for chart if needed';

$old_cum = str_replace("\r\n", "\n", $old_cum);
if (strpos($content_norm, $old_cum) !== false) {
    $content_norm = str_replace($old_cum, $new_cum, $content_norm);
    echo "Cumulative Block replaced\n";
} else {
    echo "Cumulative Block NOT found\n";
}

file_put_contents($file, $content_norm);
echo "DONE\n";
?>
