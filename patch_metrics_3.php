<?php
$file = 'c:/xampp/htdocs/ACPO/editor.php';
$content = file_get_contents($file);

$search = '$cumulative_weights = $merged_weights;';
$replace = '$cumulative_weights = [];
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

// Also need to remove the old loop...
// The old loop starts after the new query and goes until $cumulative_weights = $merged_weights;

$content = str_replace("\r\n", "\n", $content);
$pattern = '/\$all_history = \$all_history = \$stmt_all->fetchAll\(PDO::FETCH_ASSOC\);.*?\$cumulative_weights = \$merged_weights;/s';
// Wait, my Step 260 change had a duplicate $all_history... no.

// Let's just do a greedy replacement from "sort($chartMonths" upwards to the query.
// No, risky.

// Replacement 1: The query was already fixed.
// Replacement 2: Replacing the entire block from the query result fetch down to the metrics initialization.
$start_marker = '$all_history = $stmt_all->fetchAll(PDO::FETCH_ASSOC);';
$end_marker = '$cumulative_weights = $merged_weights;';

$start_pos = strpos($content, $start_marker);
$end_pos = strpos($content, $end_marker);

if ($start_pos !== false && $end_pos !== false) {
    $before = substr($content, 0, $start_pos + strlen($start_marker));
    $after = substr($content, $end_pos + strlen($end_marker));
    $new_content = $before . "\n\n" . $replace . "\n" . $after;
    file_put_contents($file, $new_content);
    echo "SUCCESS";
} else {
    echo "MARKERS NOT FOUND";
}
?>
