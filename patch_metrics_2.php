<?php
$file = 'c:/xampp/htdocs/ACPO/editor.php';
$content = file_get_contents($file);

// Cumulative metrics logic update (Matching the updated query)
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

// Normalize line endings
$old_cum = str_replace("\r\n", "\n", $old_cum);
$content_norm = str_replace("\r\n", "\n", $content);

if (strpos($content_norm, $old_cum) !== false) {
    $content_norm = str_replace($old_cum, $new_cum, $content_norm);
    echo "Cumulative Block replaced\n";
} else {
    echo "Cumulative Block NOT found\n";
}

file_put_contents($file, $content_norm);
?>
