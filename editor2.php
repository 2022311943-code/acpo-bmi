<?php
// editor.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Not so fast";
    header("Location: index.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$current_user_id = $_SESSION['user_id'];

// Determine Target User ID
$target_user_id = $current_user_id;
if ($is_admin && isset($_GET['edit_user_id'])) {
    $target_user_id = (int)$_GET['edit_user_id'];
}

// Handle Form Submission (Save Health Record)
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_record' && $is_admin) {
    try {
        require_once 'logger.php';
        $user_id_to_save = (int)$_POST['target_user_id'];
        
        // Prepare data
        $height = $_POST['height'] ?? 0;
        $weight = $_POST['weight'] ?? 0;
        $waist = $_POST['waist'] ?? 0;
        $hip = $_POST['hip'] ?? 0;
        $wrist = $_POST['wrist'] ?? 0;
        $bmi_result = $_POST['bmi_result'] ?? 0;
        $bmi_classification = $_POST['bmi_classification'] ?? '';
        $normal_weight = $_POST['normal_weight'] ?? '';
        $weight_to_lose = $_POST['weight_to_lose'] ?? '';
        $intervention = $_POST['intervention_package'] ?? '';
        $certified = $_POST['certified_correct'] ?? '';
        $date_taken = $_POST['date_taken'] ?? null;
        if (empty($date_taken)) $date_taken = date('Y-m-d');

        // Handle Monthly Metrics (JSON)
        $mt_weights = json_encode($_POST['monthly_weights'] ?? []);
        $mt_waists = json_encode($_POST['monthly_waists'] ?? []);
        $mt_hips = json_encode($_POST['monthly_hips'] ?? []);
        $mt_wrists = json_encode($_POST['monthly_wrists'] ?? []);

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
                $existing_record['id']
            ]);
            logAction($_SESSION['user_id'], $user_id_to_save, 'Update BMI Record', "Updated BMI for ".date('F Y', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
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
            logAction($_SESSION['user_id'], $user_id_to_save, 'Add BMI Record', "Added new BMI for ".date('F Y', strtotime($date_taken)).". BMI: $bmi_result ($bmi_classification)");
        }
        
        // PROPAGATE Metric Changes to other months' records
        $metrics_to_sync = [
            'monthly_weights' => 'weight',
            'monthly_waists' => 'waist',
            'monthly_hips' => 'hip',
            'monthly_wrists' => 'wrist'
        ];
        
        foreach ($metrics_to_sync as $post_key => $db_col) {
            $vals_array = $_POST[$post_key] ?? [];
            foreach ($vals_array as $m_key => $v_val) {
                if (empty($v_val) || !is_numeric($v_val)) continue;
                
                $parts = explode('-', $m_key);
                if (count($parts) === 2) {
                    $y_val = (int)$parts[0];
                    $m_val = (int)$parts[1];
                    
                    // If a record exists for that month, update its corresponding column
                    $up_sql = "UPDATE health_records SET $db_col = ? WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ?";
                    $up_stmt = $pdo->prepare($up_sql);
                    $up_stmt->execute([$v_val, $user_id_to_save, $m_val, $y_val]);
                }
            }
        }
        
        // SAVE IMAGES to users table
        $img_right = !empty($_POST['compressed_img_right']) ? $_POST['compressed_img_right'] : (!empty($_POST['existing_img_right']) ? $_POST['existing_img_right'] : null);
        $img_front = !empty($_POST['compressed_img_front']) ? $_POST['compressed_img_front'] : (!empty($_POST['existing_img_front']) ? $_POST['existing_img_front'] : null);
        $img_left  = !empty($_POST['compressed_img_left'])  ? $_POST['compressed_img_left']  : (!empty($_POST['existing_img_left'])  ? $_POST['existing_img_left']  : null);

        // Only update if at least one new image was uploaded
        if (!empty($_POST['compressed_img_right']) || !empty($_POST['compressed_img_front']) || !empty($_POST['compressed_img_left'])) {
            $img_sql = "UPDATE users SET img_right = ?, img_front = ?, img_left = ? WHERE id = ?";
            $img_stmt = $pdo->prepare($img_sql);
            $img_stmt->execute([$img_right, $img_front, $img_left, $user_id_to_save]);
        }

        $success_msg = "Health record saved successfully!";
        header("Location: editor.php?edit_user_id=" . $user_id_to_save . "&saved=1");
        exit;

    } catch (Exception $e) {
        $error_msg = "Error saving record: " . $e->getMessage();
    }
}

if (isset($_GET['saved'])) {
    $success_msg = "Health record saved successfully!";
}

// Fetch User & Health Data
$user_data = [];
$health_data = [];
$age = 0;

try {
    // Get User Details (Name, Rank, Unit/Office (Address?))
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        // Calculate Age
        if (!empty($user_data['birthday'])) {
            $dob = new DateTime($user_data['birthday']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        } else {
            $age = (int)($user_data['age'] ?? 0);
        }

        // Fetch ALL historical weights to merge them (including record-level weights)
        $stmt_all = $pdo->prepare("SELECT weight, waist, hip, wrist, monthly_weights, monthly_waists, monthly_hips, monthly_wrists, date_taken FROM health_records WHERE user_id = ? ORDER BY date_taken ASC");
        $stmt_all->execute([$target_user_id]);
        $all_history = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

$cumulative_weights = [];
        $cumulative_waists = [];
        $cumulative_hips = [];
        $cumulative_wrists = [];
        
        foreach ($all_history as $rec) {
            $m_key_rec = date('Y-m', strtotime($rec['date_taken']));
            
            // Record-level wins (Primary source)
            if (!empty($rec['weight'])) $cumulative_weights[$m_key_rec] = $rec['weight'];
            if (!empty($rec['waist'])) $cumulative_waists[$m_key_rec] = $rec['waist'];
            if (!empty($rec['hip'])) $cumulative_hips[$m_key_rec] = $rec['hip'];
            if (!empty($rec['wrist'])) $cumulative_wrists[$m_key_rec] = $rec['wrist'];

            // Monitoring-level fills (JSON sources)
            $fill_settings = [
                'monthly_weights' => &$cumulative_weights,
                'monthly_waists' => &$cumulative_waists,
                'monthly_hips' => &$cumulative_hips,
                'monthly_wrists' => &$cumulative_wrists
            ];
            foreach($fill_settings as $json_key => &$target_arr) {
                $m_json = json_decode($rec[$json_key] ?? '[]', true);
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
            'WEIGHT' => $cumulative_weights,
            'WAIST' => $cumulative_waists,
            'HIP' => $cumulative_hips,
            'WRIST' => $cumulative_wrists
        ];
        $monthly_weights = $cumulative_weights; // Backward compatibility for chart if needed


        // Prepare Chart Data
        $chartMonths = array_keys($cumulative_weights);
        sort($chartMonths);
        
        $cHeight = $health_data['height'] ?? 0;
        if (!$cHeight) {
            foreach($all_history as $r) { if(!empty($r['height'])) { $cHeight = $r['height']; break; } }
        }

        $bmiData = [];
        $labels = [];
        foreach ($chartMonths as $m) {
            $w = $cumulative_weights[$m];
            if ($cHeight > 0) {
                $hM = $cHeight / 100;
                $bmi = $w / ($hM * $hM);
                $bmiData[] = round($bmi, 2);
                $labels[] = date('M Y', strtotime($m . "-01"));
            }
        }

        // Age Threshold
        $thresholdVal = 24.9;
        if ($age >= 30 && $age <= 34) $thresholdVal = 25.0;
        elseif ($age >= 35 && $age <= 39) $thresholdVal = 25.5;
        elseif ($age >= 40 && $age <= 44) $thresholdVal = 26.0;
        elseif ($age >= 45 && $age <= 50) $thresholdVal = 26.5;
        elseif ($age >= 51) $thresholdVal = 27.0;

        $thresholdData = array_fill(0, count($bmiData), $thresholdVal);
        $normalLine = array_fill(0, count($bmiData), 24.9);

        // Determine which specific record to load for the form
        $requested_date = $_GET['date'] ?? null;
        if ($requested_date) {
            $req_m = date('m', strtotime($requested_date));
            $req_y = date('Y', strtotime($requested_date));
            
            $stmt = $pdo->prepare("SELECT * FROM health_records WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ? ORDER BY date_taken DESC LIMIT 1");
            $stmt->execute([$target_user_id, $req_m, $req_y]);
            $health_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no record exists for that month, start a new "Recovery" template
            if (!$health_data) {
                $target_m_key = date('Y-m', strtotime($requested_date));
                $health_data = [
                    'date_taken' => $requested_date,
                    'weight' => $cumulative_weights[$target_m_key] ?? ($latest_rec['weight'] ?? 0),
                    'height' => $latest_rec['height'] ?? 0,
                    'waist' => $latest_rec['waist'] ?? 0,
                    'hip' => $latest_rec['hip'] ?? 0,
                    'wrist' => $latest_rec['wrist'] ?? 0,
                    'monthly_weights' => json_encode($cumulative_weights)
                ];
            }
        } else {
            // Default: Load the latest record
            $stmt = $pdo->prepare("SELECT * FROM health_records WHERE user_id = ? ORDER BY date_taken DESC LIMIT 1");
            $stmt->execute([$target_user_id]);
            $health_data = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    // Handle error
}

// Helper for safe display
function hval($data, $key, $default = '') {
    return isset($data[$key]) && $data[$key] !== '' ? htmlspecialchars($data[$key]) : $default;
}
function hval_raw($data, $key, $default = '') {
    return isset($data[$key]) ? $data[$key] : $default;
}

// Parse Monthly Weights (Use merged data if available)
$monthly_weights = isset($merged_weights) ? $merged_weights : (isset($health_data['monthly_weights']) ? json_decode($health_data['monthly_weights'], true) : []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Health Record Editor</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }

        /* ----- Navbar CSS (matching other pages) ----- */
        body {
            background-color: #f8f9fa;
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card, .acpo-nav, .user-sidebar, .offcanvas, .table, .form-control, .form-select, .btn {
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        /* Theme reveal animation */
        .theme-transition-active {
            animation: theme-reveal 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes theme-reveal {
            0% { opacity: 0.9; transform: scale(0.995); filter: brightness(1.2); }
            100% { opacity: 1; transform: scale(1); filter: brightness(1); }
        }

        .acpo-blue {
            background-color: #1700ad !important;
        }
        .acpo-nav {
            position: relative;
            z-index: 1030;
            border-bottom: 2px solid #e9ecef;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .acpo-nav .navbar-brand {
            display: flex;
            align-items: center;
        }
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #212529;
            font-family: inherit;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .acpo-nav .acpo-header-text {
            color: #ffffff !important;
        }
        .acpo-nav .nav-link.acpo-header-text {
            padding: 0.5rem 0;
            transition: color 0.3s ease;
            color: #ffffff !important;
        }
        .acpo-nav .nav-link.acpo-header-text:hover {
            color: #e0e0e0 !important;
        }
        .acpo-nav .navbar-nav {
            gap: 2.5rem !important;
        }
        @media (min-width: 992px) {
            .acpo-nav .navbar-nav {
                gap: 5rem !important;
            }
        }
        @media (max-width: 991.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.9rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 45px !important;
            }
            /* Reduce brand spacing on smaller screens */
            .acpo-brand-container {
                margin-left: 0.5rem !important;
                padding-left: 0 !important;
            }
            .acpo-btn-container {
                margin-right: 0.5rem !important;
                padding-right: 0 !important;
            }
            .hamburger-icon {
                font-size: 2rem !important;
            }
        }
        @media (max-width: 575.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.8rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 38px !important;
            }
            .hamburger-icon {
                font-size: 1.8rem !important;
            }
            /* Possible hide brand text if it's still too long on very narrow screens */
            @media (max-width: 400px) {
                .acpo-brand-text {
                    display: none;
                }
            }
        }
        .hamburger-icon {
            font-size: 2.8rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .hamburger-icon:hover {
            color: #e0e0e0 !important;
        }
        .nav-link-underline {
            position: relative;
            display: inline-block;
        }
        .nav-link-underline::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -2px;
            height: 3px;
            width: 0;
            background: currentColor;
            transition: width 0.35s ease;
        }
        .nav-link-underline:hover::after {
            width: 100%;
        }
        @keyframes activeLoop {
            0% { width: 20%; opacity: 0.5; }
            50% { width: 100%; opacity: 1; }
            100% { width: 20%; opacity: 0.5; }
        }
        .nav-link-underline.active-nav-link::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: -2px;
            height: 3px;
            background: currentColor;
            animation: activeLoop 2.5s ease-in-out infinite;
        }

        /* ----- BMI Dashboard Table CSS ----- */
        .bmi-container {
            width: 100%;
            max-width: 1300px;
            margin: 2rem auto;
            border: 2px solid #000;
            background-color: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 0.85rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .border-black {
            border-color: #000 !important;
        }

        .bg-lavender {
            background-color: #e2e4ff;
        }

        .bg-purple {
            background-color: #7b85ff;
            color: #fff;
        }
        @media print {
            * {
                color: #000 !important;
                background-color: transparent !important;
                background: transparent !important;
                box-shadow: none !important;
                text-shadow: none !important;
            }
            /* Keep borders black */
            .border-black, .border-bottom, .border-end, .border-top, .border-start {
                border-color: #000 !important;
            }
            .no-print {
                display: none !important;
            }
            /* Optional: ensure background graphics are off (handled by * but redundant safety) */
            body {
                background: white !important;
            }
        }

        .small-text {
            font-size: 0.85rem;
        }
        
        .fw-bold {
            font-weight: 700;
        }

        /* Top Block Layout */
        .top-block {
            display: flex;
            width: 100%;
        }

        .left-col {
            width: 40%;
            display: flex;
            flex-direction: column;
        }

        .right-col {
            width: 60%;
            display: flex;
            flex-direction: column;
        }

        /* Left Column Specifics */
        .pics-area {
            display: flex;
            flex-grow: 1;
        }

        .pic-box {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: bold;
            padding: 20px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: background-color 0.2s ease;
        }
        
        .pic-box:hover {
            background-color: #d0d3ff;
        }

        .pic-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .pic-box span {
            position: relative;
            z-index: 2;
        }

        .view-label {
            flex: 1;
            padding: 4px 0;
        }

        .classification-box {
            min-height: 80px;
        }

        .class-standard {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 6px;
        }

        /* Right Column Specifics */
        .right-header {
            display: flex;
            padding: 6px 12px;
        }

        .data-row {
            display: flex;
            padding: 4px 12px;
            align-items: center;
        }

        .label-col {
            width: 50%;
        }

        .val-col {
            width: 50%;
        }

        .bottom-right-box {
            display: flex;
            flex-grow: 1;
        }

        .sig-box {
            width: 50%;
            display: flex;
            flex-direction: column;
            padding: 6px 12px;
        }

        /* Bottom Monthly Section */
        .monthly-col-yr {
            width: 15%;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .monthly-col-prev {
            width: 12.14%;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .monthly-col-curr {
            flex-grow: 1;
            display: flex;
            align-items: center;
            padding: 4px 12px;
        }

        .dashed-arrow {
            position: relative;
            flex-grow: 1;
            border-bottom: 1px dashed #000;
            height: 1px;
            margin: 0 10px;
        }

        .dashed-arrow::after {
            content: '>';
            position: absolute;
            right: -6px;
            top: -9px;
            font-weight: bold;
            font-family: monospace;
            font-size: 16px;
        }
        
        .timeline-month {
            display: flex;
            width: 100%;
            justify-content: space-between;
            align-items: center;
        }

        /* ----- Hamburger Menu Sidebar CSS ----- */
        .user-sidebar {
            width: 380px !important;
            border-left: none;
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%) !important;
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
            color: #ffffff;
        }
        .user-sidebar .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .user-sidebar .btn-close:hover {
            opacity: 1;
        }
        .user-sidebar .offcanvas-body {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .profile-placeholder {
            width: 100%;
            max-width: 130px;
            aspect-ratio: 1 / 1;
            margin-bottom: 1rem;
            border-radius: 50%;
            padding: 5px;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }
        .profile-placeholder:hover {
            transform: scale(1.05);
        }
        .profile-placeholder img {
            border: 3px solid #ffffff !important;
            border-radius: 50%;
        }
        .sidebar-name {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.6rem;
            color: #ffffff;
            margin-bottom: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            text-align: center;
        }
        .sidebar-links-container {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.8rem;
            width: 100%;
            margin-bottom: auto;
        }
                .sidebar-links-container a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.85) !important;
            text-decoration: none;
            font-family: 'Agrandir', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 8px;
        }
        .sidebar-links-container a i {
            font-size: 1.3rem;
            margin-right: 15px;
            color: #33AFFF;
            transition: transform 0.3s ease;
        }
        .sidebar-links-container a:hover, .sidebar-links-container a.active-sidebar-link {
            background: rgba(255,255,255,0.15);
            color: #ffffff !important;
            transform: translateX(5px);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .sidebar-links-container a:hover i, .sidebar-links-container a.active-sidebar-link i {
            transform: scale(1.2);
            color: #fff;
        }
        .logout-btn-container {
            margin-top: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff !important;
            border-radius: 50px;
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 12px 40px;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.4);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 65, 108, 0.6);
            color: #fff !important;
        }
        .sidebar-links-container a i {
            font-size: 1.3rem;
            margin-right: 15px;
            color: #33AFFF;
            transition: transform 0.3s ease;
        }
        .sidebar-links-container a:hover, .sidebar-links-container a.active-sidebar-link {
            background: rgba(255,255,255,0.15);
            color: #ffffff !important;
            transform: translateX(5px);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .sidebar-links-container a:hover i, .sidebar-links-container a.active-sidebar-link i {
            transform: scale(1.2);
            color: #fff;
        }
        .logout-btn-container {
            margin-top: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff !important;
            border-radius: 50px;
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 12px 40px;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.4);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 65, 108, 0.6);
            color: #fff !important;
        }
        .logout-btn i {
            font-size: 1.3rem;
        }
        
        /* Premium Administrative Dock Tabs */
        .admin-nav-toggle {
            display: flex !important;
            flex-wrap: nowrap !important;
            border-radius: 100px !important;
            padding: 6px !important;
            background: rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
            margin: 0 auto 2.5rem auto !important;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            width: fit-content !important;
        }
        .admin-nav-toggle .nav-item {
            display: flex;
            align-items: center;
        }
        .admin-nav-toggle .nav-link {
            border-radius: 100px !important;
            padding: 12px 28px !important;
            font-size: 0.95rem;
            font-weight: 600 !important;
            color: #6c757d !important;
            border: none !important;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-nav-toggle .nav-link i {
            font-size: 1.15rem;
            transition: transform 0.3s ease;
        }
        .admin-nav-toggle .nav-link.active {
            background: linear-gradient(135deg, #1700ad 0%, #33AFFF 100%) !important;
            color: #fff !important;
            box-shadow: 0 10px 20px rgba(23, 0, 173, 0.3);
            transform: scale(1.05);
        }
        .admin-nav-toggle .nav-link.active i {
            transform: scale(1.1);
        }
        .admin-nav-toggle .nav-link:not(.active):hover {
            background: rgba(0, 0, 0, 0.05);
            color: #1700ad !important;
        }

        @media (max-width: 575.98px) {
            .admin-nav-toggle {
                max-width: 95% !important;
            }
            .admin-nav-toggle .nav-link {
                padding: 10px 12px !important;
                font-size: 0.8rem !important;
            }
        }

        .hidden {
            display: none !important;
        }

        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-white {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-light {
            background-color: #2d2d2d !important;
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .card, [data-bs-theme="dark"] .bmi-container {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-lavender {
            background-color: #2a2d48 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .table {
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .table-striped>tbody>tr:nth-of-type(odd)>* {
            --bs-table-accent-bg: rgba(255, 255, 255, 0.05) !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select, [data-bs-theme="dark"] .input-group-text {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .text-black, [data-bs-theme="dark"] .text-dark, [data-bs-theme="dark"] .text-primary, [data-bs-theme="dark"] .text-secondary {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6, [data-bs-theme="dark"] .acpo-brand-text {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] [style*="color: #1700ad"], [data-bs-theme="dark"] [style*="color: rgb(23, 0, 173)"] {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] [style*="border-color: #000"], [data-bs-theme="dark"] [style*="border-color: black"] {
            border-color: #444 !important;
        }
        [data-bs-theme="dark"] .border-black {
            border-color: #444 !important;
        }
        [data-bs-theme="dark"] .modal-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        [data-bs-theme="dark"] .acpo-nav {
            position: relative;
            z-index: 1030;
            background: #0d005e !important;
            border-bottom: none !important;
            box-shadow: none !important;
        }
        [data-bs-theme="dark"] .reminder-box {
            background-color: #2a2d48 !important;
            color: #ffffff !important;
        }

                /* Premium Modern Solar-Lunar Toggle */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
        }
        .theme-switch {
            display: inline-block;
            height: 40px;
            position: relative;
            width: 76px;
            user-select: none;
        }
        .theme-switch input {
            display: none;
        }
        .theme-switch-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            cursor: pointer;
            position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        [data-bs-theme="dark"] .theme-switch-track {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        .theme-switch-knob {
            background: #ffffff;
            border-radius: 50%;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: absolute;
            left: 3px;
            top: 2.5px;
            z-index: 2;
        }
        [data-bs-theme="dark"] .theme-switch-knob {
            transform: translateX(36px);
            background: #2b2e38;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4), 0 0 10px rgba(51, 175, 255, 0.3);
        }
        .theme-switch-knob i {
            font-size: 18px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .sun-icon {
            color: #f39c12;
            transform: rotate(0deg) scale(1);
        }
        .moon-icon {
            color: #33AFFF;
            position: absolute;
            opacity: 0;
            transform: rotate(-90deg) scale(0);
        }
        [data-bs-theme="dark"] .sun-icon {
            opacity: 0;
            transform: rotate(90deg) scale(0);
        }
        [data-bs-theme="dark"] .moon-icon {
            opacity: 1;
            transform: rotate(0deg) scale(1);
        }
        .theme-switch:hover .theme-switch-knob {
            transform: scale(1.05);
        }
        [data-bs-theme="dark"] .theme-switch:hover .theme-switch-knob {
            transform: translateX(36px) scale(1.05);
        }
        .theme-switch:active .theme-switch-knob {
            transform: scale(0.95);
        }
        [data-bs-theme="dark"] .theme-switch:active .theme-switch-knob {
            transform: translateX(36px) scale(0.95);
        }/* Print Styles - MOVED TO END FOR SPECIFICITY */
        @media print {
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            body {
                background-color: white !important;
                color: black !important;
                zoom: 80%; /* Scale down to fit Legal-like layout on A4 */
            }
            /* Explicitly override theme-specific dark mode colors during print */
            [data-bs-theme="dark"] body {
                background-color: white !important;
                color: black !important;
            }
            .acpo-nav, 
            .no-print,
            .btn-primary,
            .btn-dark,
            .nav-tabs,
            .nav-pills {
                display: none !important;
            }
            .bmi-container {
                background-color: white !important;
                color: black !important;
                box-shadow: none !important;
                border: 2px solid #000 !important;
                margin: 50px auto 0 !important;
                max-width: 100% !important;
                page-break-inside: avoid;
            }
            [data-bs-theme="dark"] .bmi-container {
                background-color: white !important;
                color: black !important;
                border-color: black !important;
            }
            /* Standard Light Mode Colors for Print */
            .bg-lavender, [data-bs-theme="dark"] .bg-lavender {
                background-color: #e2e4ff !important;
                color: #1700ad !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-purple, [data-bs-theme="dark"] .bg-purple {
                background-color: #7b85ff !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .bg-white, [data-bs-theme="dark"] .bg-white {
                background-color: white !important;
                color: black !important;
            }
            .border-black, [data-bs-theme="dark"] .border-black {
                border-color: #000 !important;
            }
            .left-col, .right-col, .pic-box,
            [data-bs-theme="dark"] .left-col, 
            [data-bs-theme="dark"] .right-col,
            [data-bs-theme="dark"] .pic-box {
                border-color: #000 !important;
            }
            /* Force standard primary blue color for labels */
            [style*="color: #1700ad"], [data-bs-theme="dark"] [style*="color: #1700ad"] {
                color: #1700ad !important;
            }
            main {
                padding: 0 !important;
            }
            /* Ensure tab content is visible when printing if active */
            .tab-pane.active {
                display: block !important;
                opacity: 1 !important;
            }
        }

        /* Clickable Month Labels */
        .month-clickable {
            cursor: pointer;
            transition: background 0.2s, color 0.2s, transform 0.15s;
            user-select: none;
        }
        .month-clickable:hover {
            background: #1700ad !important;
            color: #fff !important;
            transform: scale(1.05);
            border-radius: 2px;
        }
        .month-clickable.month-active {
            background: #1700ad !important;
            color: #fff !important;
            font-weight: 900;
        }
    </style>
    <script>
        // Apply theme immediately to prevent flicker
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100 overflow-x-hidden">

    <!-- Header (same as other pages) -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-2 px-lg-4">
            <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5 acpo-brand-container">
                <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
            </a>
            <div class="d-flex align-items-center justify-content-end me-3 me-lg-5 pe-lg-5 acpo-btn-container">
                                <button class="btn border-0 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar" aria-controls="userSidebar" aria-label="Toggle user menu">
                    <i class="bi bi-list text-white hamburger-icon" style="font-size: 2rem; cursor: pointer; position: relative; "></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hamburger Overlay Menu -->
    <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar" aria-labelledby="userSidebarLabel">
        <div class="offcanvas-header pb-0 border-0">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <!-- Profile Placeholder Image -->
            <div class="profile-placeholder d-flex justify-content-center">
                <img src="images/placeholder.png" alt="Profile Background" class="img-fluid rounded-circle shadow-sm" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23e9ecef\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
            </div>
            
            <h4 class="sidebar-name"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h4>
            
            <!-- Dark Mode Switch in Sidebar -->
            <div class="d-flex justify-content-center mb-4">
                <div class="theme-switch-wrapper">
                        <label class="theme-switch" for="theme-switch-checkbox">
                            <input type="checkbox" id="theme-switch-checkbox">
                            <div class="theme-switch-track">
                                <div class="theme-switch-knob">
                                    <i class="bi bi-sun-fill sun-icon"></i>
                                    <i class="bi bi-moon-stars-fill moon-icon"></i>
                                </div>
                            </div>
                        </label>
                    </div>
            </div>
            
            <div class="sidebar-links-container">
                <a href="main.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'main.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-house-door"></i> HOME</a>
                <a href="editor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'editor.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-calculator"></i> BMI CALCULATOR</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                    <a href="audit_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-shield-lock"></i> AUDIT LOGS</a>
                <?php endif; ?>
                <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-gear"></i> USER SETTINGS</a>
                <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-info-circle"></i> ABOUT</a>
                <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-envelope"></i> CONTACT</a>
            </div>
            
            <div class="logout-btn-container">
                <a href="login.php?action=logout" class="logout-btn">
                    LOGOUT <i class="bi bi-box-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow-1 p-3">
        <div class="container-fluid">
            
            <!-- Alert Messages -->
            <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert" style="max-width: 800px; margin: 0 auto 1rem auto;">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert" style="max-width: 800px; margin: 0 auto 1rem auto;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- Navigation Toggle Tabs -->
            <?php if ($is_admin): ?>
            <ul class="nav nav-pills admin-nav-toggle no-print" id="adminTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="editor-tab" data-bs-toggle="pill" data-bs-target="#editor-pane" type="button" role="tab" aria-controls="editor-pane" aria-selected="true">
                        <i class="bi bi-person-badge"></i> Health Record Editor
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="calculator-tab" data-bs-toggle="pill" data-bs-target="#calculator-pane" type="button" role="tab" aria-controls="calculator-pane" aria-selected="false">
                        <i class="bi bi-calculator-fill"></i> BMI Calculator
                    </button>
                </li>
            </ul>
            <?php endif; ?>

            <div class="tab-content" id="mainContent">
                
                <?php if ($is_admin): ?>
                <!-- Health Record View (Tab 1 - Admin Only) -->
                <div class="tab-pane fade show active" id="editor-pane" role="tabpanel" aria-labelledby="editor-tab" tabindex="0">
                    
                    <form method="POST" enctype="multipart/form-data" id="healthRecordForm">
                    <input type="hidden" name="action" value="save_record">
                    <input type="hidden" name="target_user_id" value="<?php echo $target_user_id; ?>">
                    <input type="hidden" name="existing_img_right" value="<?php echo hval_raw($user_data, 'img_right'); ?>">
                    <input type="hidden" name="existing_img_front" value="<?php echo hval_raw($user_data, 'img_front'); ?>">
                    <input type="hidden" name="existing_img_left" value="<?php echo hval_raw($user_data, 'img_left'); ?>">
                    <!-- Hidden inputs for compressed images -->
                    <input type="hidden" name="compressed_img_right" id="compressed_img_right">
                    <input type="hidden" name="compressed_img_front" id="compressed_img_front">
                    <input type="hidden" name="compressed_img_left" id="compressed_img_left">

<!-- Editor Header Section -->
                    <div class="editor-header mb-4 mt-3 no-print" style="max-width: 1300px; margin: 0 auto;">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <h2 class="fw-bold text-uppercase mb-2 d-flex align-items-center flex-wrap gap-3" style="color: #1700ad; font-family: 'Agrandir', sans-serif;">
                                    <i class="bi bi-pencil-square me-2"></i><?php echo $is_admin ? 'Health Record Editor' : 'Health Record'; ?>
                                </h2>
                                
                                <!-- Record Timeline Navigation -->
                                <div class="mt-3 d-flex align-items-center gap-3 no-print">
                                    <div class="btn-group shadow-sm rounded-pill overflow-hidden border border-primary-subtle bg-white">
                                        <?php 
                                            $viewing_date = $health_data['date_taken'] ?? date('Y-m-d');
                                            $prev_year_ts = strtotime("-1 year", strtotime($viewing_date));
                                            $next_year_ts = strtotime("+1 year", strtotime($viewing_date));
                                            $prev_date = date('Y-m-d', $prev_year_ts);
                                            $next_date = date('Y-m-d', $next_year_ts);
                                        ?>
                                        <a href="?edit_user_id=<?php echo $target_user_id; ?>&date=<?php echo $prev_date; ?>" 
                                           class="btn btn-outline-primary border-0 px-3 fw-bold small text-uppercase" 
                                           style="font-size: 0.75rem;" title="Previous Year">
                                            <i class="bi bi-chevron-left me-1"></i> Prev Year
                                        </a>
                                        
                                        <div class="dropdown">
                                            <button class="btn btn-primary btn-sm px-4 dropdown-toggle rounded-0 border-0 fw-bold text-uppercase" 
                                                    style="background: linear-gradient(135deg, #1700ad 0%, #33AFFF 100%); font-size: 0.75rem;" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <?php echo date('Y', strtotime($viewing_date)); ?>
                                            </button>
                                            <ul class="dropdown-menu shadow border-0 py-2" style="max-height: 400px; overflow-y: auto; min-width: 250px;">
                                                <li class="dropdown-header small text-uppercase fw-bold text-secondary mb-1">Select Record Year</li>
                                                <?php 
                                                    $record_history = [];
                                                    foreach (($all_history ?? []) as $rec) {
                                                        if (!empty($rec['date_taken'])) {
                                                            $y = date('Y', strtotime($rec['date_taken']));
                                                            $m = date('F Y', strtotime($rec['date_taken']));
                                                            $record_history[$y][] = ['date' => $rec['date_taken'], 'label' => $m];
                                                        }
                                                    }
                                                    krsort($record_history); // Newest years first 
                                                ?>
                                                <?php foreach($record_history as $year => $records): ?>
                                                    <li class="dropdown-header text-dark fw-bold mt-2" style="font-size: 0.8rem; background: rgba(0,0,0,0.02);"><?php echo $year; ?></li>
                                                    <?php 
                                                        // Sort records within year newest first
                                                        usort($records, function($a, $b) { return strcmp($b['date'], $a['date']); });
                                                        foreach($records as $r): 
                                                    ?>
                                                        <li><a class="dropdown-item py-1 px-4 <?php echo ($viewing_date == $r['date']) ? 'active' : ''; ?>" 
                                                              style="font-size: 0.85rem;"
                                                              href="?edit_user_id=<?php echo $target_user_id; ?>&date=<?php echo $r['date']; ?>">
                                                            <i class="bi <?php echo ($viewing_date == $r['date']) ? 'bi-check-circle-fill' : 'bi-calendar3'; ?> me-2"></i>
                                                            <?php echo $r['label']; ?>
                                                        </a></li>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item py-2 px-3 small" href="?edit_user_id=<?php echo $target_user_id; ?>&date=<?php echo date('Y-m-d'); ?>">
                                                    <i class="bi bi-calendar-event me-2"></i> Current Month
                                                </a></li>
                                            </ul>
                                        </div>
                                        
                                        <a href="?edit_user_id=<?php echo $target_user_id; ?>&date=<?php echo $next_date; ?>" 
                                           class="btn btn-outline-primary border-0 px-3 fw-bold small text-uppercase" 
                                           style="font-size: 0.75rem;" title="Next Year">
                                            Next Year <i class="bi bi-chevron-right ms-1"></i>
                                        </a>
                                    </div>
                                    <div class="small text-secondary fw-semibold text-uppercase opacity-75" style="letter-spacing: 0.5px; font-size: 0.7rem;">Yearly Timeline</div>
                                </div>

                                <p class="text-secondary fs-5 mb-0 mt-3">
                                    <?php echo $is_admin ? 'Update physical measurements and BMI data below.' : 'View your physical measurements and BMI data.'; ?>
                                </p>
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0">
                                <div class="alert alert-info border-0 shadow-sm rounded-4 mb-0 d-flex align-items-center reminder-box" role="alert">
                                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                                    <div>
                                        <small class="fw-bold text-uppercase d-block mb-1">Reminder</small>
                                        <small>Ensure all measurements are taken in centimeters (cm) and weight in kilograms (kg).</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($bmiData)): ?>
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 no-print" style="background: white; max-width: 1300px; margin: 0 auto;">
                        <div class="card-header bg-lavender border-bottom py-2 px-3">
                            <h6 class="mb-0 fw-bold text-uppercase small" style="color: #1700ad;"><i class="bi bi-graph-up-arrow me-2"></i>BMI Progress Tracking</h6>
                        </div>
                        <div class="card-body p-3">
                            <canvas id="bmiProgressChart" style="max-height: 250px; width: 100%;"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="bmi-container">
                        <div class="top-block">
                            <!-- Left Column -->
                            <div class="left-col border-end border-black">
                                <div class="pics-area bg-lavender">
                                    <?php 
                                    $imgs = ['1'=>'right', '2'=>'front', '3'=>'left'];
                                    foreach($imgs as $num => $type): 
                                        $dbKey = 'img_'.$type;
                                        $hasImg = !empty($user_data[$dbKey]);
                                        $src = $hasImg ? $user_data[$dbKey] : '';
                                        $label = ucfirst($type) . ' View';
                                    ?>
                                    <div class="pic-box border-end border-black border-bottom border-black" onclick="<?php echo $is_admin ? "document.getElementById('img-upload-$num').click()" : ''; ?>">
                                        <span id="img-text-<?php echo $num; ?>" class="<?php echo $hasImg ? 'd-none' : ''; ?>"><?php echo $hasImg ? '' : "Upload $label Here"; ?></span>
                                        <img id="img-preview-<?php echo $num; ?>" src="<?php echo $src; ?>" alt="<?php echo $label; ?>" class="<?php echo $hasImg ? '' : 'd-none'; ?>">
                                        <?php if ($is_admin): ?>
                                        <input type="file" name="img_<?php echo $type; ?>" id="img-upload-<?php echo $num; ?>" class="d-none" accept="image/*" onchange="previewImage(this, 'img-preview-<?php echo $num; ?>', 'img-text-<?php echo $num; ?>')">
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex text-center border-bottom border-black fw-bold small-text bg-white">
                                    <div class="view-label border-end border-black">Right View</div>
                                    <div class="view-label border-end border-black">Front View</div>
                                    <div class="view-label">Left View</div>
                                </div>
                                <div class="text-center fw-bold py-1 border-bottom border-black small-text bg-white">BMI CLASSIFICATION</div>
                                <div class="d-flex classification-box">
                                    <div class="class-standard border-end border-black bg-lavender small-text">
                                        <span class="fw-bold">PNP BMI ACCEPTABLE STANDARD:</span>
                                        <div class="m-auto fw-bold fs-6 mt-3">
                                            <span class="editable-text" id="text-class"><?php echo hval($health_data, 'bmi_classification'); ?></span>
                                            <input type="text" name="bmi_classification" class="form-control form-control-sm d-none editable-input" id="input-class" value="<?php echo hval($health_data, 'bmi_classification'); ?>">
                                        </div>
                                    </div>
                                    <div class="class-standard bg-white small-text">
                                        <span class="fw-bold">WHO Standard</span>
                                        <span class="m-auto fw-bold fs-6 mt-3" id="text-who-class"><?php echo hval($health_data, 'bmi_classification'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="right-col d-flex flex-column">
                                <div class="right-header bg-purple border-bottom border-black fw-bold small-text">
                                    <?php 
                                    $rank = hval_raw($user_data, 'rank');
                                    $rank_display = $rank;
                                    if (preg_match('/\((.*?)\)/', $rank, $match)) {
                                        $rank_display = $match[1];
                                    }
                                    ?>
                                    <div class="w-50">Rank/Name: <span class="text-uppercase"><?php echo htmlspecialchars($rank_display) . ' ' . hval($user_data, 'name'); ?></span></div>
                                    <div class="w-50">Unit/Office: <?php echo hval($user_data, 'unit'); ?></div>
                                </div>
                                
                                <?php 
                                $fields = [
                                    'Age' => ['key'=>'age', 'val'=>$age, 'readonly'=>true], // Age is calculated
                                    'Height' => ['key'=>'height', 'db'=>'height'],
                                    'Weight' => ['key'=>'weight', 'db'=>'weight'],
                                    'Waist' => ['key'=>'waist', 'db'=>'waist'],
                                    'Hip' => ['key'=>'hip', 'db'=>'hip'],
                                    'Wrist' => ['key'=>'wrist', 'db'=>'wrist'],
                                    // Capitalize gender for display (e.g., male -> Male)
                                    'Gender' => [
                                        'key'=>'gender',
                                        'val'=>htmlspecialchars(ucfirst(strtolower(hval_raw($user_data, 'gender')))),
                                        'readonly'=>true
                                    ],
                                    'Date Taken' => ['key'=>'date_taken', 'db'=>'date_taken', 'type'=>'date'],
                                    'BMI Result' => ['key'=>'bmi_result', 'db'=>'bmi_result'],
                                    'Normal Weight' => ['key'=>'normal_weight', 'db'=>'normal_weight'],
                                    'Weight to Lose' => ['key'=>'weight_to_lose', 'db'=>'weight_to_lose'],
                                ];
                                $i = 0;
                                foreach($fields as $label => $conf): 
                                    $bg = ($i++ % 2 == 0) ? 'bg-lavender' : 'bg-white';
                                    $val = isset($conf['val']) ? $conf['val'] : hval($health_data, $conf['db'] ?? '');
                                    $is_readonly = isset($conf['readonly']) && $conf['readonly'];
                                    $row_class = "data-row $bg border-bottom border-black fw-bold small-text";
                                    if (in_array($label, ['Waist', 'Hip', 'Wrist'])) $row_class .= ' no-print';
                                ?>
                                <div class="<?php echo $row_class; ?>">
                                    <div class="label-col"><?php echo $label; ?>:</div>
                                    <div class="val-col">
                                        <span class="<?php echo $is_readonly ? '' : 'editable-text'; ?>" id="text-<?php echo $conf['key'] ?? ''; ?>"><?php echo $val; ?></span>
                                        <?php if (!$is_readonly): ?>
                                        <input type="<?php echo $conf['type'] ?? 'text'; ?>" name="<?php echo $conf['db'] ?? ''; ?>" class="form-control form-control-sm d-none editable-input" id="input-<?php echo $conf['key'] ?? ''; ?>" value="<?php echo $val; ?>" style="height: 20px; font-size: 0.85rem;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>

                                <div class="bottom-right-box bg-white">
                                    <div class="sig-box border-end border-black small-text fw-bold">
                                        <span>Intervention Package:</span>
                                        <div class="m-auto fs-6 pt-3">
                                            <span class="editable-text" id="text-intervention"><?php echo hval($health_data, 'intervention_package'); ?></span>
                                            <input type="text" name="intervention_package" class="form-control form-control-sm d-none editable-input" id="input-intervention" value="<?php echo hval($health_data, 'intervention_package'); ?>">
                                        </div>
                                    </div>
                                    <div class="sig-box small-text fw-bold">
                                        <span>Certified Correct Signature:</span>
                                        <div class="m-auto fs-6 pt-3">
                                            <span class="editable-text" id="text-certified"><?php echo hval($health_data, 'certified_correct'); ?></span>
                                            <input type="text" name="certified_correct" class="form-control form-control-sm d-none editable-input" id="input-certified" value="<?php echo hval($health_data, 'certified_correct'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly -->
                        <div class="border-top border-black d-flex flex-column border-2">
                            <div class="text-center fw-bold py-1 border-bottom border-black small-text bg-white">MONTHLY WEIGHT MONITORING</div>
                            
                            <?php 
                                // Adaptive Years based on Date Taken
                                $anchor_date = !empty($health_data['date_taken']) ? $health_data['date_taken'] : date('Y-m-d');
                                $curr_year = date('Y', strtotime($anchor_date));
                                $prev_year = $curr_year - 1;

                                $m_prev = ["$prev_year-11", "$prev_year-12"];
                                $m_curr = [];
                                for ($m = 1; $m <= 12; $m++) {
                                    $m_curr[] = "$curr_year-" . str_pad($m, 2, '0', STR_PAD_LEFT);
                                }
                                $all_months = array_merge($m_prev, $m_curr);
                            ?>

                            <!-- YEAR Row -->
                            <div class="d-flex border-bottom border-black fw-bold small-text bg-lavender">
                                <div class="monthly-col-yr border-end border-black">YEAR</div>
                                <div class="monthly-col-prev border-end border-black justify-content-center bg-lavender"><?php echo $prev_year; ?></div>
                                <div class="monthly-col-curr justify-content-center bg-lavender"><?php echo $curr_year; ?></div>
                            </div>
                            
                            <!-- MONTH Row -->
                            <div class="d-flex border-bottom border-black fw-bold small-text bg-white">
                                <div class="monthly-col-yr border-end border-black">MONTH</div>
                                <div class="monthly-col-prev border-end border-black p-0 d-flex">
                                    <div class="w-50 border-end border-black text-center py-1 month-clickable" onclick="setDateFromMonth('<?php echo $prev_year; ?>', '11')" title="Click to set Date Taken to Nov <?php echo $prev_year; ?>">NOV</div>
                                    <div class="w-50 text-center py-1 month-clickable" onclick="setDateFromMonth('<?php echo $prev_year; ?>', '12')" title="Click to set Date Taken to Dec <?php echo $prev_year; ?>">DEC</div>
                                </div>
                                <div class="monthly-col-curr p-0 d-flex">
                                    <?php 
                                    $m_labels = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
                                    foreach ($m_labels as $idx => $label): 
                                        $border = ($idx < 11) ? 'border-end border-black' : '';
                                        $m_num = str_pad($idx + 1, 2, '0', STR_PAD_LEFT);
                                    ?>
                                        <div class="flex-fill <?php echo $border; ?> text-center py-1 month-clickable" style="width: 8.33%;" onclick="setDateFromMonth('<?php echo $curr_year; ?>', '<?php echo $m_num; ?>')" title="Click to set Date Taken to <?php echo $label . ' ' . $curr_year; ?>"><?php echo $label; ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                             <?php 
                                foreach($monthly_metrics as $metric_label => $metric_values):
                                    $m_vals = [];
                                    foreach($all_months as $m) {
                                        $raw = $metric_values[$m] ?? '';
                                        if (is_numeric($raw)) {
                                            $m_vals[$m] = (float)$raw;
                                        } else {
                                            $m_vals[$m] = $raw;
                                        }
                                    }
                                    $post_name = "monthly_" . strtolower($metric_label) . "s";
                                    $row_bg = ($metric_label === 'WEIGHT' || $metric_label === 'HIP') ? 'bg-lavender' : 'bg-white';
                                    $print_class = ($metric_label !== 'WEIGHT') ? 'no-print' : '';
                             ?>
                             <div class="d-flex fw-bold small-text <?php echo $row_bg; ?> border-bottom border-black <?php echo $print_class; ?>">
                                <div class="monthly-col-yr border-end border-black py-2"><?php echo $metric_label; ?></div>
                                <div class="monthly-col-prev border-end border-black p-0 d-flex">
                                    <?php foreach($m_prev as $m): ?>
                                    <div class="w-50 border-end border-black d-flex align-items-center justify-content-center" style="position: relative; min-height: 38px;">
                                        <span class="editable-text" id="text-mw-<?php echo $post_name.'-'.$m; ?>"><?php echo $m_vals[$m]; ?></span>
                                        <input type="text" name="<?php echo $post_name; ?>[<?php echo $m; ?>]" class="form-control form-control-sm d-none editable-input m-0 p-0 text-center border-0 bg-transparent" id="input-mw-<?php echo $post_name.'-'.$m; ?>" value="<?php echo $m_vals[$m]; ?>" style="width:100%; height: 100%; font-size: 0.8rem; position: absolute; top:0; left:0;">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="monthly-col-curr p-0 d-flex">
                                    <?php 
                                    foreach($m_curr as $idx => $m): 
                                        $border = ($idx < 11) ? 'border-end border-black' : '';
                                    ?>
                                    <div class="flex-fill <?php echo $border; ?> d-flex align-items-center justify-content-center" style="width: 8.33%; position: relative; min-height: 38px;">
                                        <span class="editable-text" id="text-mw-<?php echo $post_name.'-'.$m; ?>"><?php echo $m_vals[$m]; ?></span>
                                        <input type="text" name="<?php echo $post_name; ?>[<?php echo $m; ?>]" class="form-control form-control-sm d-none editable-input m-0 p-0 text-center border-0 bg-transparent" id="input-mw-<?php echo $post_name.'-'.$m; ?>" value="<?php echo $m_vals[$m]; ?>" style="width:100%; height: 100%; font-size: 0.8rem; position: absolute; top:0; left:0;">
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 mb-4 no-print" style="max-width: 1300px; margin: 0 auto;">
                        <?php if ($is_admin): ?>
                        <button type="button" id="editBtn" class="btn btn-warning px-4 py-2 fw-bold d-flex align-items-center gap-2" style="border-radius: 5px; transition: transform 0.2s;">
                            <i class="bi bi-pencil-square"></i> EDIT
                        </button>
                        <button type="button" id="cancelBtn" class="btn btn-secondary px-4 py-2 fw-bold d-flex align-items-center gap-2 d-none" style="border-radius: 5px; transition: transform 0.2s;">
                            <i class="bi bi-x-circle"></i> CANCEL EDIT
                        </button>
                        <button type="submit" id="confirmBtn" class="btn btn-success px-4 py-2 fw-bold d-flex align-items-center gap-2 d-none" style="border-radius: 5px; transition: transform 0.2s;">
                            <i class="bi bi-check-circle"></i> CONFIRM SAVE
                        </button>
                        <?php endif; ?>
                    </div>
                    </form>
                    
                    <!-- Print Button -->
                    <div class="d-flex justify-content-center mb-5 no-print">
                        <button onclick="printRecord('<?php echo htmlspecialchars(addslashes(hval_raw($user_data, 'name'))); ?>')" class="btn btn-primary px-5 py-2 fw-bold d-flex align-items-center gap-2" style="font-size: 1.1rem; border-radius: 50px; background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%); border: none; box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3); transition: transform 0.2s, box-shadow 0.2s;">
                            <i class="bi bi-printer-fill fs-5"></i> PRINT RECORD
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Calculator View (Tab 2) -->
                <div class="tab-pane fade <?php echo (!$is_admin) ? 'show active' : ''; ?>" id="calculator-pane" role="tabpanel" aria-labelledby="calculator-tab" tabindex="0">
                    <div class="container" style="max-width: 1000px; margin-top: <?php echo $is_admin ? '3rem' : '0'; ?>;">
                        <h1 class="fw-bold text-uppercase mb-4" style="color: #1700ad; font-family: 'Agrandir', sans-serif;">BMI Calculator</h1>
                        
                        <div class="card border-0 shadow-sm bg-light mb-4">
                            <div class="card-body p-4">
                                <form id="bmiForm">
                                    <div class="row g-3 align-items-center justify-content-center">
                                        <div class="col-md-3">
                                            <label for="calc-age" class="form-label mb-1 fw-bold text-uppercase small text-secondary">Age</label>
                                            <input type="number" class="form-control form-control-lg text-center fw-bold" id="calc-age" value="<?php echo $age; ?>" <?php echo $is_admin ? 'placeholder="Age"' : 'readonly style="background-color: #e9ecef;"'; ?>>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="calc-height" class="form-label mb-1 fw-bold text-uppercase small text-secondary">Height (cm)</label>
                                            <input type="number" class="form-control form-control-lg text-center" id="calc-height" placeholder="e.g. 170" required min="50" max="300" step="0.1">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="calc-weight" class="form-label mb-1 fw-bold text-uppercase small text-secondary">Weight (kg)</label>
                                            <input type="number" class="form-control form-control-lg text-center" id="calc-weight" placeholder="e.g. 70" required min="20" max="500" step="0.1">
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                                        <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow-sm" style="background: linear-gradient(135deg, #1700ad 0%, #12008a 100%); border: none;">
                                            CALCULATE BMI <i class="bi bi-calculator ms-2"></i>
                                        </button>
                                        <button type="button" id="clearBtn" class="btn btn-secondary btn-lg px-5 rounded-pill fw-bold shadow-sm" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); border: none;">
                                            CLEAR <i class="bi bi-x-circle ms-2"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Results Section (Hidden by default) -->
                        <div id="bmiResults" class="hidden">
                            <div class="card border-0 shadow rounded-4 overflow-hidden">
                                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                                    <h3 class="fw-bold text-uppercase mb-0" style="color: #444;">Your Results</h3>
                                </div>
                                <div class="card-body p-4">
                                    <div class="text-center mb-4">
                                        <div class="display-4 fw-bold mb-1" id="bmiValue" style="color: #1700ad;">0.0</div>
                                        <div class="fs-5 text-secondary">BMI Score</div>
                                        <div class="mt-2">
                                            <span class="badge rounded-pill px-4 py-2 fs-6" id="bmiClassBadge">CLASSIFICATION</span>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered align-middle">
                                            <thead class="bg-light text-uppercase small text-center">
                                                <tr>
                                                    <th width="30%" style="color: #1700ad;">Classification</th>
                                                    <th width="15%" style="color: #1700ad;">Package</th>
                                                    <th width="20%" style="color: #1700ad;">Duration</th>
                                                    <th width="35%" style="color: #1700ad;">Recommendation</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr id="resultRow" class="text-center fw-bold">
                                                    <td id="res-class"></td>
                                                    <td id="res-package"></td>
                                                    <td id="res-duration"></td>
                                                    <td id="res-recommendation"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Cortisol Guide Image -->
                        <div class="text-center mt-5 mb-5 no-print">
                            <img src="images/cortisol.png" alt="Cortisol Guide" class="img-fluid rounded-4 shadow-sm" style="max-width: 100%; height: auto; border: 1px solid #e0e0e0; padding: 10px; background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <script>
        // Set Date Taken from clicking a month in the monitoring table
        var _selectedMonthEl = null;
        var _pickerYear = '';
        var _pickerMonth = '';
        function setDateFromMonth(year, month) {
            // Only allow when in edit mode (editBtn is hidden when editing)
            var editBtn = document.getElementById('editBtn');
            if (editBtn && !editBtn.classList.contains('d-none')) {
                // Not in edit mode — show a warning toast
                var warn = document.createElement('div');
                warn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Click EDIT first to change the date';
                warn.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#dc3545;color:#fff;padding:10px 24px;border-radius:50px;font-weight:bold;font-size:0.9rem;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,0.3);animation:fadeIn 0.3s;';
                document.body.appendChild(warn);
                setTimeout(function() { warn.remove(); }, 2000);
                return;
            }

            _selectedMonthEl = event.target;
            _pickerYear = year;
            _pickerMonth = month;
            
            var monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            var monthName = monthNames[parseInt(month)] || month;

            // Calculate last day of the month
            var lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();

            // Populate day dropdown
            var daySelect = document.getElementById('monthPickerDay');
            daySelect.innerHTML = '';
            for (var d = 1; d <= lastDay; d++) {
                var opt = document.createElement('option');
                opt.value = d;
                opt.textContent = d;
                daySelect.appendChild(opt);
            }
            // Default to today's day if within this month, otherwise 1
            var today = new Date();
            if (today.getFullYear() == parseInt(year) && (today.getMonth() + 1) == parseInt(month)) {
                daySelect.value = today.getDate();
            } else {
                daySelect.value = 1;
            }

            // Update modal title
            document.getElementById('monthPickerTitle').textContent = monthName + ' ' + year;

            // Show the modal
            var modal = new bootstrap.Modal(document.getElementById('monthPickerModal'));
            modal.show();
        }

        function confirmMonthPicker() {
            var day = document.getElementById('monthPickerDay').value;
            if (!day) return;

            // Build full date string
            var dateStr = _pickerYear + '-' + _pickerMonth + '-' + String(day).padStart(2, '0');

            // Update the date input
            var dateInput = document.getElementById('input-date_taken');
            if (dateInput) {
                dateInput.value = dateStr;
                dateInput.classList.remove('d-none');
                var dateText = document.getElementById('text-date_taken');
                if (dateText) dateText.classList.add('d-none');
            }

            // Highlight the selected month
            document.querySelectorAll('.month-clickable').forEach(function(el) {
                el.classList.remove('month-active');
            });
            if (_selectedMonthEl) _selectedMonthEl.classList.add('month-active');

            // Close the modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('monthPickerModal'));
            if (modal) modal.hide();

            // Format for toast
            var d = new Date(dateStr + 'T00:00:00');
            var options = { year: 'numeric', month: 'long', day: 'numeric' };
            var formatted = d.toLocaleDateString('en-US', options);

            // Toast notification
            var toast = document.createElement('div');
            toast.textContent = 'Date Taken set to ' + formatted;
            toast.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#1700ad;color:#fff;padding:10px 24px;border-radius:50px;font-weight:bold;font-size:0.9rem;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,0.3);animation:fadeIn 0.3s;';
            document.body.appendChild(toast);
            setTimeout(function() { toast.remove(); }, 2500);
        }

        function printRecord(name) {
            const originalTitle = document.title;
            document.title = name + "_BMI_RECORD";
            window.print();
            setTimeout(() => {
                document.title = originalTitle;
            }, 1000);
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Highlighting current active page logic (if ever needed on main.php)
            // Display current date
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const today = new Date();
            const dateDisplay = document.getElementById('current-date-display');
            if (dateDisplay) {
                dateDisplay.textContent = today.toLocaleDateString('en-US', dateOptions);
            }
            
            // Health Record Editor Logic
            const inputHeight = document.getElementById('input-height');
            const inputWeight = document.getElementById('input-weight');
            const inputDateTaken = document.getElementById('input-date_taken');
            const userAge = parseInt("<?php echo $age; ?>") || 0; // PHP value or 0

            // Set default date if empty
            if (inputDateTaken && !inputDateTaken.value) {
                const today = new Date().toISOString().split('T')[0];
                inputDateTaken.value = today;
                // Trigger change to update monthly weight if needed
                syncMonthlyMonitoring();
            }

            function calculateHealthMetrics() {
                const heightCm = parseFloat(inputHeight.value);
                const weightKg = parseFloat(inputWeight.value);
                
                if (!heightCm || !weightKg) return;

                // BMI Calculation
                const heightM = heightCm / 100;
                const bmi = weightKg / (heightM * heightM);
                const bmiRounded = bmi.toFixed(2);
                const bmiNumeric = parseFloat(bmiRounded); // Use this for classification logic

                // Classification Logic (Same as Calculator)
                let classification = '';
                
                // --- SEVERELY UNDERWEIGHT ---
                if (bmiNumeric < 17) {
                    classification = 'SEVERELY UNDERWEIGHT';
                } 
                // --- UNDERWEIGHT ---
                else if (bmiNumeric >= 17 && bmiNumeric <= 18.4) {
                    classification = 'UNDERWEIGHT';
                } 
                // --- NORMAL ---
                else if (bmiNumeric >= 18.5 && bmiNumeric < 25) {
                    classification = 'NORMAL';
                }
                else {
                    // --- Age Dependent Logic ---
                    let isAcceptable = false;
                    const age = userAge;
                    
                    if (age <= 29) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                            else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    else if (age >= 30 && age <= 34) {
                        if (bmiNumeric >= 25 && bmiNumeric <= 25) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                        else if (bmiNumeric > 25 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                        else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; }
                        else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                        else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    else if (age >= 35 && age <= 39) {
                        if (bmiNumeric >= 25 && bmiNumeric <= 25.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                        else if (bmiNumeric > 25.5 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                        else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; }
                        else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                        else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    else if (age >= 40 && age <= 44) {
                        if (bmiNumeric >= 25 && bmiNumeric <= 26) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                        else if (bmiNumeric > 26 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                        else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; }
                        else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                        else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    else if (age >= 45 && age <= 50) {
                        if (bmiNumeric >= 25 && bmiNumeric <= 26.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                        else if (bmiNumeric > 26.5 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                        else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; }
                        else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                        else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    else if (age >= 51) {
                        if (bmiNumeric >= 25 && bmiNumeric <= 27) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                        else if (bmiNumeric > 27 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; }
                        else if (bmiNumeric >= 30 && bmiNumeric < 35) { classification = 'OBESE CLASS 1'; }
                        else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; }
                        else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; }
                    }
                    
                    if (!classification && !isAcceptable) {
                        if (bmiNumeric < 25) { classification = 'NORMAL'; }
                        else if (bmiNumeric < 30) { classification = 'OVERWEIGHT'; }
                        else { classification = 'OBESE CLASS 1'; }
                    }
                }

                // Normal Weight Logic (Based on BMI 18.5 - 24.9)
                const minNormal = 18.5 * (heightM * heightM);
                const maxNormal = 24.9 * (heightM * heightM);
                const normalWeightRange = minNormal.toFixed(1) + "kg - " + maxNormal.toFixed(1) + "kg";

                // Weight to Lose Logic
                let weightToLose = "0";
                if (classification === 'NORMAL' || classification === 'ACCEPTABLE BMI' || classification === 'UNDERWEIGHT' || classification === 'SEVERELY UNDERWEIGHT') {
                    weightToLose = "0"; // Or "Maintain/Gain"
                } else {
                    // Assuming goal is to reach top of normal range (24.9)
                    const loss = weightKg - maxNormal;
                    if (loss > 0) {
                        weightToLose = loss.toFixed(1) + " kg";
                    }
                }

                // Update Fields
                updateField('bmi_result', bmiRounded);
                updateField('bmi_classification', classification); // Also updates side panel
                updateField('normal_weight', normalWeightRange);
                updateField('weight_to_lose', weightToLose);
                
                // Also update the classification text in left column
                const textClass = document.getElementById('text-class');
                const inputClass = document.getElementById('input-class');
                const textWhoClass = document.getElementById('text-who-class');
                
                if (textClass) textClass.textContent = classification;
                if (inputClass) inputClass.value = classification;
                if (textWhoClass) textWhoClass.textContent = classification;

                // --- Intervention Package Logic ---
                let packageCode = '';
                
                switch(classification) {
                    case 'SEVERELY UNDERWEIGHT':
                        packageCode = 'Package A'; // Using full name 'Package A' or just 'A' based on desired display? The calculator used 'A'
                        break;
                    case 'UNDERWEIGHT':
                        packageCode = 'Package A';
                        break;
                    case 'NORMAL':
                        packageCode = 'Package B';
                        break;
                    case 'ACCEPTABLE BMI':
                        packageCode = 'Package B';
                        break;
                    case 'OVERWEIGHT':
                        packageCode = 'Package C';
                        break;
                    case 'OBESE CLASS 1':
                        packageCode = 'Package D';
                        break;
                    case 'OBESE CLASS 2':
                        packageCode = 'Package E';
                        break;
                    case 'OBESE CLASS 3':
                        packageCode = 'Package F';
                        break;
                }
                
                updateField('intervention', packageCode);
            }

            function updateField(key, value) {
                const text = document.getElementById('text-' + key);
                const input = document.getElementById('input-' + key);
                if (text) text.textContent = value;
                if (input) input.value = value;
            }

            function syncMonthlyMonitoring() {
                const dateVal = inputDateTaken.value;
                if (!dateVal) return;
                
                const dateObj = new Date(dateVal);
                if (isNaN(dateObj.getTime())) return;
                
                const year = dateObj.getFullYear();
                const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                const mKey = `${year}-${month}`;
                
                const mappings = [
                    { top: 'weight', mw: 'monthly_weights' },
                    { top: 'waist', mw: 'monthly_waists' },
                    { top: 'hip', mw: 'monthly_hips' },
                    { top: 'wrist', mw: 'monthly_wrists' }
                ];
                
                mappings.forEach(map => {
                    const topInput = document.getElementById('input-' + map.top);
                    if (!topInput) return;
                    
                    const val = topInput.value;
                    const cellId = 'input-mw-' + map.mw + '-' + mKey;
                    const monthInput = document.getElementById(cellId);
                    const monthText = document.getElementById('text-mw-' + map.mw + '-' + mKey);
                    
                    if (monthInput) {
                        monthInput.value = val;
                        if (monthText) monthText.textContent = val;
                    }
                });
            }

            const inputWaist = document.getElementById('input-waist');
            const inputHip = document.getElementById('input-hip');
            const inputWrist = document.getElementById('input-wrist');

            if (inputHeight) inputHeight.addEventListener('input', calculateHealthMetrics);
            if (inputWeight) inputWeight.addEventListener('input', calculateHealthMetrics);

            // Edit & Confirm Logic - only if elements exist
            const editBtn = document.getElementById('editBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            
            if (editBtn && confirmBtn && cancelBtn) {
                const editableTexts = document.querySelectorAll('.editable-text');
                const editableInputs = document.querySelectorAll('.editable-input');

                editBtn.addEventListener('click', function() {
                    // Hide texts, show inputs
                    editableTexts.forEach(span => span.classList.add('d-none'));
                    editableInputs.forEach(input => {
                        input.classList.remove('d-none');
                    });
                    
                    // Toggle buttons
                    editBtn.classList.add('d-none');
                    confirmBtn.classList.remove('d-none');
                    cancelBtn.classList.remove('d-none');
                });

                cancelBtn.addEventListener('click', function() {
                    // Show texts, hide inputs
                    editableTexts.forEach(span => span.classList.remove('d-none'));
                    editableInputs.forEach(input => {
                        input.classList.add('d-none');
                        // Restore original values from spans
                        const textId = input.id.replace('input-', 'text-');
                        const textEl = document.getElementById(textId);
                        if (textEl) {
                            input.value = textEl.textContent.trim();
                        }
                    });
                    
                    // Toggle buttons
                    editBtn.classList.remove('d-none');
                    confirmBtn.classList.add('d-none');
                    cancelBtn.classList.add('d-none');
                    
                    // Refresh calculations to ensure labels/packages are reset
                    calculateHealthMetrics();
                });

                // Trigger monthly weight update only on CONFIRM SAVE
                const healthForm = document.getElementById('healthRecordForm');
                if (healthForm) {
                    healthForm.addEventListener('submit', function() {
                        syncMonthlyMonitoring();
                    });
                }

                // Sync from TABLE to FORM (Bidirectional)
                document.querySelectorAll('.editable-input[id^="input-mw-"]').forEach(input => {
                    input.addEventListener('input', function() {
                        const dateVal = inputDateTaken.value;
                        if (!dateVal) return;
                        
                        const dateObj = new Date(dateVal);
                        const year = dateObj.getFullYear();
                        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
                        const currentKey = `${year}-${month}`;
                        const changedKey = this.id.replace('input-mw-', '');
                        
                        if (changedKey === currentKey) {
                            inputWeight.value = this.value;
                            calculateHealthMetrics();
                        }
                    });
                });
            }

            // BMI Calculator Logic (For both User and Admin)
            const bmiForm = document.getElementById('bmiForm');
            const bmiResults = document.getElementById('bmiResults');
            const clearBtn = document.getElementById('clearBtn');
            const ageInput = document.getElementById('calc-age');
            const originalAge = ageInput ? ageInput.value : '';
            const isAgeReadOnly = ageInput ? ageInput.hasAttribute('readonly') : false;
            
            if (bmiForm) {
                // Clear Button Logic
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        document.getElementById('calc-height').value = '';
                        document.getElementById('calc-weight').value = '';
                        
                        // Only clear age if it's editable (admin)
                        if (!isAgeReadOnly) {
                            ageInput.value = '';
                        }
                        
                        bmiResults.classList.add('hidden');
                    });
                }

                bmiForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const age = parseInt(document.getElementById('calc-age').value);
                    const heightCm = parseFloat(document.getElementById('calc-height').value);
                    const weightKg = parseFloat(document.getElementById('calc-weight').value);
                    
                    if (!age || !heightCm || !weightKg) {
                        alert("Please provide valid age, height, and weight.");
                        return;
                    }

                    // BMI Calculation
                    const heightM = heightCm / 100;
                    const bmi = weightKg / (heightM * heightM);
                    const bmiRounded = bmi.toFixed(2);
                    const bmiNumeric = parseFloat(bmiRounded);
                    
                    // Classification Logic
                    let classification = '';
                    let badgeColor = '';
                    
                    // --- SEVERELY UNDERWEIGHT ---
                    if (bmiNumeric < 17) {
                        classification = 'SEVERELY UNDERWEIGHT';
                        badgeColor = '#FF00FF'; // Magenta
                    } 
                    // --- UNDERWEIGHT ---
                    else if (bmiNumeric >= 17 && bmiNumeric <= 18.4) {
                        classification = 'UNDERWEIGHT';
                        badgeColor = '#0000FF'; // Blue
                    } 
                    // --- NORMAL ---
                    else if (bmiNumeric >= 18.5 && bmiNumeric < 25) {
                        classification = 'NORMAL';
                        badgeColor = '#00FF00'; // Green
                    }
                    else {
                        // --- Age Dependent Logic ---
                        let isAcceptable = false;
                        
                        if (age <= 29) {
                             if (bmiNumeric >= 25 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                             else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                             else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                             else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        else if (age >= 30 && age <= 34) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 25) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; badgeColor = '#00FFFF'; }
                            else if (bmiNumeric > 25 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        else if (age >= 35 && age <= 39) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 25.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; badgeColor = '#00FFFF'; }
                            else if (bmiNumeric > 25.5 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        else if (age >= 40 && age <= 44) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 26) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; badgeColor = '#00FFFF'; }
                            else if (bmiNumeric > 26 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        else if (age >= 45 && age <= 50) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 26.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; badgeColor = '#00FFFF'; }
                            else if (bmiNumeric > 26.5 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        else if (age >= 51) {
                            if (bmiNumeric >= 25 && bmiNumeric <= 27) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; badgeColor = '#00FFFF'; }
                            else if (bmiNumeric > 27 && bmiNumeric <= 29.9) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else if (bmiNumeric >= 30 && bmiNumeric < 35) { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                            else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) { classification = 'OBESE CLASS 2'; badgeColor = '#FF9900'; }
                            else if (bmiNumeric >= 40) { classification = 'OBESE CLASS 3'; badgeColor = '#FF0000'; }
                        }
                        
                        if (!classification && !isAcceptable) {
                            if (bmiNumeric < 25) { classification = 'NORMAL'; badgeColor = '#00FF00'; }
                            else if (bmiNumeric < 30) { classification = 'OVERWEIGHT'; badgeColor = '#FFFF00'; }
                            else { classification = 'OBESE CLASS 1'; badgeColor = '#FFCC00'; }
                        }
                    }

                    // Package Logic
                    let packageCode = '';
                    let duration = '';
                    let recommendation = '';
                    
                    switch(classification) {
                        case 'SEVERELY UNDERWEIGHT':
                            packageCode = 'A'; duration = '48 WEEKS'; recommendation = '1-3KG WEIGHT GAIN/ MONTH';
                            break;
                        case 'UNDERWEIGHT':
                            packageCode = 'A'; duration = '48 WEEKS'; recommendation = '1-3KG WEIGHT GAIN/ MONTH';
                            break;
                        case 'NORMAL':
                            packageCode = 'B'; duration = '12 WEEKS'; recommendation = 'MAINTAIN';
                            break;
                        case 'ACCEPTABLE BMI':
                            packageCode = 'B'; duration = '12 WEEKS'; recommendation = 'MAINTAIN';
                            break;
                        case 'OVERWEIGHT':
                            packageCode = 'C'; duration = '24 WEEKS'; recommendation = '2 KGS WEIGHT LOSS/ MONTH';
                            break;
                        case 'OBESE CLASS 1':
                            packageCode = 'D'; duration = '36 WEEKS'; recommendation = '2 KGS WEIGHT LOSS/ MONTH';
                            break;
                        case 'OBESE CLASS 2':
                            packageCode = 'E'; duration = '48 WEEKS'; recommendation = '2 KGS WEIGHT LOSS/ MONTH';
                            break;
                        case 'OBESE CLASS 3':
                            packageCode = 'F'; duration = '60 WEEKS'; recommendation = '2 KGS WEIGHT LOSS/ MONTH';
                            break;
                    }

                    // Update UI
                    document.getElementById('bmiValue').textContent = bmiRounded;
                    const badge = document.getElementById('bmiClassBadge');
                    badge.textContent = classification;
                    badge.style.backgroundColor = badgeColor;
                    badge.style.color = (badgeColor === '#FFFF00' || badgeColor === '#00FFFF' || badgeColor === '#00FF00' || badgeColor === '#FFCC00') ? '#000' : '#fff';
                    
                    document.getElementById('res-class').textContent = classification;
                    document.getElementById('res-class').style.color = (badgeColor === '#FFFF00') ? '#b3a200' : badgeColor;
                    if(badgeColor === '#00FFFF') document.getElementById('res-class').style.color = '#00aaaa';
                    
                    document.getElementById('res-package').textContent = packageCode;
                    document.getElementById('res-duration').textContent = duration;
                    document.getElementById('res-recommendation').textContent = recommendation;
                    
                    bmiResults.classList.remove('hidden');
                });
            }

            // Auto Edit Mode Logic
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('mode') === 'edit' && document.getElementById('editBtn')) {
                document.getElementById('editBtn').click();
            }
        });

        // Function to handle image preview and compression
        function previewImage(input, imgId, textId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    var img = new Image();
                    img.onload = function() {
                        var canvas = document.createElement('canvas');
                        var ctx = canvas.getContext('2d');
                        
                        // Calculate new size (max 800px)
                        var MAX_WIDTH = 800;
                        var MAX_HEIGHT = 800;
                        var width = img.width;
                        var height = img.height;
                        
                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }
                        
                        canvas.width = width;
                        canvas.height = height;
                        
                        // Fill white background for transparent PNGs before converting to JPEG
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, width, height);

                        ctx.drawImage(img, 0, 0, width, height);
                        
                        // Compress to JPEG with 0.7 quality
                        var dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                        
                        // Display preview
                        var imgElement = document.getElementById(imgId);
                        var textElement = document.getElementById(textId);
                        
                        imgElement.src = dataUrl;
                        imgElement.classList.remove('d-none');
                        textElement.classList.add('d-none');
                        
                        // Set compressed data to hidden input
                        // Extract input name (img_right, img_front, img_left) from input id (img-upload-1, etc) 
                        // Actually easier: use the input name attribute which is 'img_right' etc
                        var inputName = input.getAttribute('name');
                        var hiddenInputId = 'compressed_' + inputName;
                        var hiddenInput = document.getElementsByName('compressed_' + inputName)[0];
                        
                        if(hiddenInput) {
                            hiddenInput.value = dataUrl;
                        }
                    }
                    img.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Initialize BMI Progress Chart if data exists
        <?php if (!empty($bmiData)): ?>
        const chartCtx = document.getElementById('bmiProgressChart').getContext('2d');
        new Chart(chartCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'BMI Progress',
                        data: <?php echo json_encode($bmiData); ?>,
                        borderColor: '#1700ad',
                        backgroundColor: 'rgba(23, 0, 173, 0.1)',
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: '#1700ad',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Acceptable/Normal Limit (Age-based)',
                        data: <?php echo json_encode($thresholdData); ?>,
                        borderColor: '#dc3545',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false
                    },
                    {
                        label: 'Standard Normal (24.9)',
                        data: <?php echo json_encode($normalLine); ?>,
                        borderColor: '#198754',
                        borderWidth: 1.5,
                        borderDash: [2, 2],
                        pointRadius: 0,
                        fill: false,
                        hidden: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { display: false },
                        border: { display: false },
                        title: { display: false },
                        grace: '15%'
                    }
                },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 20, font: { size: 11, weight: 'bold' } } },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(23, 0, 173, 0.9)',
                        padding: 12,
                        callbacks: {
                            footer: function(tooltipItems) {
                                const bmi = tooltipItems[0].parsed.y;
                                const threshold = <?php echo $thresholdVal; ?>;
                                if (bmi <= 24.9) {
                                    return "Status: Meeting Normal BMI (Ideal).";
                                } else if (bmi <= threshold) {
                                    return "Status: Meeting Age-Adjusted Acceptable BMI.";
                                } else {
                                    return "Status: Above recommended BMI (Target: " + threshold + ").";
                                }
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeSwitch = document.getElementById('theme-switch-checkbox');
            const htmlElement = document.documentElement;

            if (htmlElement.getAttribute('data-bs-theme') === 'dark') {
                if (themeSwitch) themeSwitch.checked = true;
            }

            if (themeSwitch) {
                themeSwitch.addEventListener('change', function() {
                    const newTheme = this.checked ? 'dark' : 'light';
                    
                    // Add animation class
                    document.body.classList.add('theme-transition-active');
                    
                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    // Remove animation class after it finishes
                    setTimeout(() => {
                        document.body.classList.remove('theme-transition-active');
                    }, 800);
                });
            }
        });
    </script>

    <!-- Month Date Picker Modal -->
    <div class="modal fade" id="monthPickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header border-0 py-3 px-4" style="background: linear-gradient(135deg, #1700ad 0%, #0d005e 100%);">
                    <div class="text-white">
                        <div class="small text-uppercase opacity-75 fw-bold" style="letter-spacing: 1px;">Select Date</div>
                        <h5 class="fw-bold mb-0 mt-1" id="monthPickerTitle">Month Year</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <label class="form-label small fw-bold text-secondary text-uppercase mb-2">Select Day</label>
                    <select id="monthPickerDay" class="form-select form-select-lg text-center fw-bold border-2 rounded-3 mx-auto" style="font-size: 1.3rem; border-color: #1700ad; max-width: 120px;"></select>
                </div>
                <div class="modal-footer border-0 px-4 pb-4 pt-0 d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn flex-fill rounded-pill fw-bold text-white" style="background: linear-gradient(135deg, #1700ad 0%, #0d005e 100%);" onclick="confirmMonthPicker()">
                        <i class="bi bi-check-lg me-1"></i> Set Date
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
