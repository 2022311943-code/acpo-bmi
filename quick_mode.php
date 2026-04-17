<?php
// quick_mode.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Access denied";
    header("Location: index.php");
    exit;
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$is_admin) {
    $_SESSION['flash_error'] = "Admin access required.";
    header("Location: main.php");
    exit;
}

$edit_user_id = isset($_GET['edit_user_id']) ? (int)$_GET['edit_user_id'] : 0;
if (!$edit_user_id) {
    header("Location: main.php");
    exit;
}

// Ensure the user exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$edit_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: main.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'logger.php';
    try {
        $pdo->beginTransaction();

        $unit = $_POST['unit'] ?? '';
        $age = (int)($_POST['age'] ?? 0);
        
        $height = $_POST['height'] ?? 0;
        $waist = $_POST['waist'] ?? 0;
        $hip = $_POST['hip'] ?? 0;
        $wrist = $_POST['wrist'] ?? 0;
        
        $selected_year = (int)($_POST['year'] ?? date('Y'));
        
        $monthly_weights_input = $_POST['monthly_weights'] ?? [];
        
        // 1. Update User Record
        $stmtUser = $pdo->prepare("UPDATE users SET unit = ?, age = ? WHERE id = ?");
        $stmtUser->execute([$unit, $age, $edit_user_id]);
        
        // Prepare JSON for monthly_weights (just capturing what's in the form for this year)
        $mt_weights = [];
        for ($m = 1; $m <= 12; $m++) {
            $m_str = sprintf('%02d', $m);
            if (!empty($monthly_weights_input[$m])) {
                $mt_weights["{$selected_year}-{$m_str}"] = $monthly_weights_input[$m];
            }
        }
        $mt_weights_json = json_encode($mt_weights);

        // 2. Loop through each month and update/insert health_records
        $records_modified = 0;
        for ($m = 1; $m <= 12; $m++) {
            if (isset($monthly_weights_input[$m]) && trim($monthly_weights_input[$m]) !== '') {
                $weight = $monthly_weights_input[$m];
                $m_str = sprintf('%02d', $m);
                $date_taken = "{$selected_year}-{$m_str}-01";
                
                // Calculate BMI if height and weight available
                $bmi_result = 0;
                $bmi_classification = 'N/A';
                $normal_weight = '';
                $weight_to_lose = '0';
                $intervention_package = 'N/A';
                
                if ($height > 0 && $weight > 0) {
                    $height_m = $height / 100;
                    $bmi_result = round($weight / ($height_m * $height_m), 2);
                    
                    // --- Age Dependent Logic ---
                    $isAcceptable = false;
                    
                    if ($bmi_result < 17) {
                        $bmi_classification = 'SEVERELY UNDERWEIGHT';
                    } elseif ($bmi_result >= 17 && $bmi_result <= 18.4) {
                        $bmi_classification = 'UNDERWEIGHT';
                    } elseif ($bmi_result >= 18.5 && $bmi_result < 25) {
                        $bmi_classification = 'NORMAL';
                    } else {
                        if ($age <= 29) {
                            if ($bmi_result >= 25 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result <= 34.9) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        } elseif ($age >= 30 && $age <= 34) {
                            if ($bmi_result >= 25 && $bmi_result <= 25) { $bmi_classification = 'ACCEPTABLE BMI'; $isAcceptable = true; }
                            elseif ($bmi_result > 25 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result <= 34.9) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        } elseif ($age >= 35 && $age <= 39) {
                            if ($bmi_result >= 25 && $bmi_result <= 25.5) { $bmi_classification = 'ACCEPTABLE BMI'; $isAcceptable = true; }
                            elseif ($bmi_result > 25.5 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result <= 34.9) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        } elseif ($age >= 40 && $age <= 44) {
                            if ($bmi_result >= 25 && $bmi_result <= 26) { $bmi_classification = 'ACCEPTABLE BMI'; $isAcceptable = true; }
                            elseif ($bmi_result > 26 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result <= 34.9) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        } elseif ($age >= 45 && $age <= 50) {
                            if ($bmi_result >= 25 && $bmi_result <= 26.5) { $bmi_classification = 'ACCEPTABLE BMI'; $isAcceptable = true; }
                            elseif ($bmi_result > 26.5 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result <= 34.9) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        } elseif ($age >= 51) {
                            if ($bmi_result >= 25 && $bmi_result <= 27) { $bmi_classification = 'ACCEPTABLE BMI'; $isAcceptable = true; }
                            elseif ($bmi_result > 27 && $bmi_result <= 29.9) $bmi_classification = 'OVERWEIGHT';
                            elseif ($bmi_result >= 30 && $bmi_result < 35) $bmi_classification = 'OBESE CLASS 1';
                            elseif ($bmi_result >= 35 && $bmi_result <= 39.9) $bmi_classification = 'OBESE CLASS 2';
                            elseif ($bmi_result >= 40) $bmi_classification = 'OBESE CLASS 3';
                        }
                        
                        if ($bmi_classification === 'N/A' && !$isAcceptable) {
                            if ($bmi_result < 25) $bmi_classification = 'NORMAL';
                            elseif ($bmi_result < 30) $bmi_classification = 'OVERWEIGHT';
                            else $bmi_classification = 'OBESE CLASS 1';
                        }
                    }

                    // Calculate Normal Weight Range
                    $minNormal = 18.5 * ($height_m * $height_m);
                    $maxNormal = 24.9 * ($height_m * $height_m);
                    $normal_weight = number_format($minNormal, 1) . "kg - " . number_format($maxNormal, 1) . "kg";

                    // Calculate Weight to Lose
                    if (in_array($bmi_classification, ['NORMAL', 'ACCEPTABLE BMI', 'UNDERWEIGHT', 'SEVERELY UNDERWEIGHT'])) {
                        $weight_to_lose = "0";
                    } else {
                        $loss = $weight - $maxNormal;
                        if ($loss > 0) {
                            $weight_to_lose = number_format($loss, 1) . " kg";
                        }
                    }

                    // Determine Intervention Package
                    switch ($bmi_classification) {
                        case 'SEVERELY UNDERWEIGHT':
                        case 'UNDERWEIGHT':
                            $intervention_package = 'Package A';
                            break;
                        case 'NORMAL':
                        case 'ACCEPTABLE BMI':
                            $intervention_package = 'Package B';
                            break;
                        case 'OVERWEIGHT':
                            $intervention_package = 'Package C';
                            break;
                        case 'OBESE CLASS 1':
                            $intervention_package = 'Package D';
                            break;
                        case 'OBESE CLASS 2':
                            $intervention_package = 'Package E';
                            break;
                        case 'OBESE CLASS 3':
                            $intervention_package = 'Package F';
                            break;
                    }
                }

                // Check if record exists for this month/year
                $checkStmt = $pdo->prepare("SELECT id FROM health_records WHERE user_id = ? AND MONTH(date_taken) = ? AND YEAR(date_taken) = ? LIMIT 1");
                $checkStmt->execute([$edit_user_id, $m, $selected_year]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $upStmt = $pdo->prepare("UPDATE health_records SET height=?, weight=?, waist=?, hip=?, wrist=?, bmi_result=?, bmi_classification=?, normal_weight=?, weight_to_lose=?, intervention_package=?, monthly_weights=? WHERE id=?");
                    $upStmt->execute([$height, $weight, $waist, $hip, $wrist, $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose, $intervention_package, $mt_weights_json, $existing['id']]);
                } else {
                    $inStmt = $pdo->prepare("INSERT INTO health_records (user_id, height, weight, waist, hip, wrist, bmi_result, bmi_classification, normal_weight, weight_to_lose, intervention_package, date_taken, monthly_weights) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $inStmt->execute([$edit_user_id, $height, $weight, $waist, $hip, $wrist, $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose, $intervention_package, $date_taken, $mt_weights_json]);
                }
                $records_modified++;
            }
        }
        
        $pdo->commit();
        logAction($_SESSION['user_id'], $edit_user_id, 'Quick Mode Update', "Admin updated properties and $records_modified monthly records via Quick Mode.");
        $_SESSION['flash_success'] = "Quick Mode updates applied successfully!";
        header("Location: quick_mode.php?edit_user_id={$edit_user_id}");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = "Error saving Quick Mode: " . $e->getMessage();
    }
}

// Prepare initial data for form population
$default_unit = $user['unit'] ?? '';
// Determine Age
$default_age = $user['age'] ?? 0;
if (empty($default_age) && !empty($user['birthday'])) {
    $dob = new DateTime($user['birthday']);
    $now = new DateTime();
    $default_age = $now->diff($dob)->y;
}

// Get the most recent physical stats
$recentStmt = $pdo->prepare("SELECT height, waist, hip, wrist FROM health_records WHERE user_id = ? ORDER BY date_taken DESC LIMIT 1");
$recentStmt->execute([$edit_user_id]);
$recentHealth = $recentStmt->fetch(PDO::FETCH_ASSOC);

$default_height = $recentHealth['height'] ?? '';
$default_waist = $recentHealth['waist'] ?? '';
$default_hip = $recentHealth['hip'] ?? '';
$default_wrist = $recentHealth['wrist'] ?? '';

// Determine active year
$active_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Fetch all health records for the active selected year to pre-fill the monthly weights
$monthsDataStmt = $pdo->prepare("SELECT MONTH(date_taken) as m, weight FROM health_records WHERE user_id = ? AND YEAR(date_taken) = ?");
$monthsDataStmt->execute([$edit_user_id, $active_year]);
$monthly_weights_db = [];
while ($row = $monthsDataStmt->fetch(PDO::FETCH_ASSOC)) {
    $monthly_weights_db[(int)$row['m']] = $row['weight'];
}

$months_list = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Fetch Profile Pic for Sidebar (Logged in User)
$sidebar_pfp = "images/placeholder.png";
if (isset($_SESSION['user_id'])) {
    $stmt_pfp = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt_pfp->execute([$_SESSION['user_id']]);
    $pfp_row = $stmt_pfp->fetch();
    if ($pfp_row && !empty($pfp_row['profile_pic'])) {
        $sidebar_pfp = $pfp_row['profile_pic'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Quick Mode</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }

        body {
            background-color: #f8f9fa;
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
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
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #ffffff;
            font-family: 'Agrandir', sans-serif;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .hamburger-icon {
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .hamburger-icon:hover {
            color: #e0e0e0 !important;
        }

        /* Sidebar Styles */
        .user-sidebar {
            width: 380px !important;
            border-left: none;
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%) !important;
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
            color: #ffffff;
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
            text-align: center;
            display: block;
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.4);
            transition: all 0.3s ease;
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 65, 108, 0.6);
        }

        /* Theme Switch */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
        }
        .theme-switch {
            display: inline-block;
            height: 40px;
            position: relative;
            width: 76px;
        }
        .theme-switch input {
            display: none;
        }
        .theme-switch-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            cursor: pointer;
            height: 100%;
            width: 100%;
            position: relative;
            transition: all 0.5s;
        }
        .theme-switch-knob {
            background: #ffffff;
            border-radius: 50%;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            left: 3px;
            top: 2.5px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        [data-bs-theme="dark"] .theme-switch-knob {
            transform: translateX(36px);
            background: #2b2e38;
        }
        .sun-icon { color: #f39c12; }
        .moon-icon { color: #33AFFF; display: none; }
        [data-bs-theme="dark"] .sun-icon { display: none; }
        [data-bs-theme="dark"] .moon-icon { display: block; }

        /* Dark Mode */
        [data-bs-theme="dark"] body { background-color: #121212 !important; color: #e0e0e0 !important; }
        [data-bs-theme="dark"] .glass-panel { background: #1e1e1e !important; border-color: #333 !important; color: #fff !important; }
        [data-bs-theme="dark"] .section-title { color: #fff; border-bottom-color: #333; }
        [data-bs-theme="dark"] .month-item { background: #2d2d2d; border-color: #444; color: #fff; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select, [data-bs-theme="dark"] .input-group-text {
            background-color: #2d2d2d !important; border-color: #444 !important; color: #ffffff !important;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.4);
            padding: 2.5rem;
            backdrop-filter: blur(10px);
        }
        .input-group label {
            width: 130px;
            background-color: #f1f3f5;
            font-weight: bold;
            color: #495057;
            border-right: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 15px;
        }
        .section-title {
            color: #1700ad;
            font-weight: 800;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(23,0,173,0.1);
            padding-bottom: 0.5rem;
        }

        /* Monthly Weights Grid */
        .month-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        .month-item {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 10px 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }
        [data-bs-theme="dark"] .month-item {
            background: #2d2d2d;
            border-color: #444;
            color: #fff;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
    <div class="container-fluid px-2 px-lg-4">
        <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5">
            <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
            <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
            <span class="acpo-header-text">ANGELES CITY POLICE OFFICE</span>
        </a>
        <div class="d-flex align-items-center justify-content-end me-3 me-lg-5 pe-lg-5">
            <button class="btn border-0 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar" aria-controls="userSidebar">
                <i class="bi bi-list text-white hamburger-icon"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Hamburger Overlay Menu -->
<div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar" aria-labelledby="userSidebarLabel">
    <div class="offcanvas-header pb-0 border-0">
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <a href="settings.php" class="profile-placeholder d-flex justify-content-center text-decoration-none">
            <img src="<?php echo $sidebar_pfp; ?>" alt="Profile" class="img-fluid rounded-circle shadow-sm" style="width: 130px; height: 130px; object-fit: cover; border: 3px solid #fff;" onerror="this.onerror=null; this.src='images/placeholder.png'">
        </a>
        
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
            <a href="main.php"><i class="bi bi-house-door"></i> HOME</a>
            <a href="editor.php"><i class="bi bi-calculator"></i> BMI CALCULATOR</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin_users.php"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                <a href="audit_logs.php"><i class="bi bi-shield-lock"></i> AUDIT LOGS</a>
            <?php endif; ?>
            <a href="settings.php"><i class="bi bi-gear"></i> USER SETTINGS</a>
            <a href="about.php"><i class="bi bi-info-circle"></i> ABOUT</a>
            <a href="contact.php"><i class="bi bi-envelope"></i> CONTACT</a>
        </div>
        
        <div class="logout-btn-container">
            <a href="login.php?action=logout" class="logout-btn">
                LOGOUT <i class="bi bi-box-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>

<div class="container py-5">
    
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 border-0" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 border-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="glass-panel mx-auto" style="max-width: 900px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0 fw-bold" style="color: #1700ad;">
                <i class="bi bi-person-fill me-2"></i> <?php echo htmlspecialchars($user['name'] ?? 'Unknown Personnel'); ?>
                <span class="badge bg-secondary ms-2" style="font-size: 0.4em; vertical-align: middle;">QUICK MODE</span>
            </h3>
            
            <a href="editor.php?edit_user_id=<?php echo $edit_user_id; ?>&mode=edit" class="btn btn-outline-primary rounded-pill btn-sm">
                Switch to Form Mode
            </a>
        </div>

        <form method="POST" action="quick_mode.php?edit_user_id=<?php echo $edit_user_id; ?>">
            <div class="row g-4">
                
                <!-- Shared Attributes -->
                <div class="col-12">
                    <h5 class="section-title"><i class="bi bi-clipboard2-pulse me-2"></i> Base Health & Demographic Information</h5>
                    <p class="small text-muted mb-3">These values will be saved universally and carried across the records.</p>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Unit/Office</label>
                        <input type="text" class="form-control rounded-end-pill" name="unit" list="pnp-units" value="<?php echo htmlspecialchars($default_unit); ?>">
                        <datalist id="pnp-units">
                            <option value="CHQ">
                            <option value="PS1">
                            <option value="PS2">
                            <option value="PS3">
                            <option value="PS4">
                            <option value="PS5">
                            <option value="PS6">
                            <option value="CMFC">
                            <option value="TPU">
                            <option value="MPU">
                            <option value="CARMU">
                            <option value="CIU">
                            <option value="AOMU">
                            <option value="CCADU">
                            <option value="ARDDO">
                            <option value="CPPU">
                            <option value="GSO">
                            <option value="DEU">
                            <option value="COMU">
                            <option value="ODCDO">
                        </datalist>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Age</label>
                        <input type="number" class="form-control rounded-end-pill" name="age" value="<?php echo $default_age; ?>" min="18" max="100">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Height (cm)</label>
                        <input type="number" step="0.01" class="form-control rounded-end-pill" name="height" value="<?php echo $default_height; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Waist (cm)</label>
                        <input type="number" step="0.01" class="form-control rounded-end-pill" name="waist" value="<?php echo $default_waist; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Hip (cm)</label>
                        <input type="number" step="0.01" class="form-control rounded-end-pill" name="hip" value="<?php echo $default_hip; ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="input-group">
                        <label class="input-group-text rounded-start-pill">Wrist (cm)</label>
                        <input type="number" step="0.01" class="form-control rounded-end-pill" name="wrist" value="<?php echo $default_wrist; ?>">
                    </div>
                </div>

                <!-- Monthly Weight Entries Section -->
                <div class="col-12 mt-5">
                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2" style="border-color: rgba(23,0,173,0.1) !important;">
                        <h5 class="mb-0 fw-bold" style="color: #1700ad;">
                            <i class="bi bi-calendar3-range me-2"></i> Monthly Weights
                        </h5>
                        <div class="d-flex align-items-center">
                            <label class="me-2 fw-bold text-muted small text-uppercase">Recording Year:</label>
                            <select name="year" id="yearSelector" class="form-select form-select-sm fw-bold rounded-pill text-center shadow-sm border-primary" style="width: 100px; color: #1700ad;" onchange="changeYear(this.value)">
                                <?php for($y = date('Y') + 1; $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $active_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <p class="small text-muted mb-4">Enter a weight (kg) for any month. Leaving a month blank will skip it. Updating a month will adjust its BMI automatically using the height provided above.</p>

                    <div class="month-grid">
                        <?php foreach($months_list as $m_num => $m_name): 
                            $m_val = isset($monthly_weights_db[$m_num]) ? $monthly_weights_db[$m_num] : '';
                        ?>
                            <div class="month-item">
                                <label class="d-block fw-bold mb-1 small text-secondary text-uppercase"><?php echo $m_name; ?></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" name="monthly_weights[<?php echo $m_num; ?>]" class="form-control text-center" placeholder="kg" value="<?php echo htmlspecialchars($m_val); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12 mt-5 text-end">
                    <hr>
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow fw-bold" style="background-color: #1700ad; border-color: #1700ad;">
                        <i class="bi bi-save me-2"></i> Save Quick Batch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeSwitch = document.getElementById('theme-switch-checkbox');
        const htmlElement = document.documentElement;

        if (localStorage.getItem('theme') === 'dark' || htmlElement.getAttribute('data-bs-theme') === 'dark') {
            htmlElement.setAttribute('data-bs-theme', 'dark');
            if (themeSwitch) themeSwitch.checked = true;
        }

        if (themeSwitch) {
            themeSwitch.addEventListener('change', function() {
                const newTheme = this.checked ? 'dark' : 'light';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });
        }
    });

    function changeYear(year) {
        window.location.href = `quick_mode.php?edit_user_id=<?php echo $edit_user_id; ?>&year=${year}`;
    }
</script>
</body>
</html>
