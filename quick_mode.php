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
        body {
            background-color: #f8f9fa;
        }
        .acpo-blue {
            background-color: #1700ad !important;
        }
        .acpo-nav {
            border-bottom: 2px solid #e9ecef;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.4);
            padding: 2rem;
            backdrop-filter: blur(10px);
        }
        .input-group label {
            width: 120px;
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
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark acpo-blue acpo-nav py-2" style="position: sticky; top: 0; z-index: 1030;">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand d-flex align-items-center" href="main.php" style="text-decoration: none;">
            <img src="images/acpo_logo.png" alt="ACPO Logo" height="50" class="me-3" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));">
            <span class="acpo-header-text d-none d-sm-block text-white" style="line-height: 1.2;">
                QCPO<br><small style="font-size: 0.75em; opacity: 0.9;">Monitoring System</small>
            </span>
        </a>
        <div class="d-flex align-items-center">
            <a href="main.php" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-arrow-left me-2"></i> Back to Masterlist
            </a>
        </div>
    </div>
</nav>

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
    function changeYear(year) {
        // preserve the user id and reload page with new year
        window.location.href = `quick_mode.php?edit_user_id=<?php echo $edit_user_id; ?>&year=${year}`;
    }
</script>
</body>
</html>
