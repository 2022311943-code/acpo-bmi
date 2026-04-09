<?php
// admin_register_personnel.php
session_start();
require_once 'db_connection.php';

// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Access Control - Admins Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['flash_error'] = "Not authorized.";
    header("Location: index.php");
    exit;
}

$error_msg = '';
$success_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_and_bmi') {
    require_once 'logger.php';
    
    try {
        $pdo->beginTransaction();

        // 1. Process User Information
        $last_name = trim($_POST['last_name'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $rank = trim($_POST['rank'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $unit_input = $_POST['unit'] ?? '';
        $unit = is_array($unit_input) ? implode(", ", array_map('trim', array_filter($unit_input))) : trim($unit_input);
        
        $age = isset($_POST['age']) ? (int)$_POST['age'] : null;
        $username = trim($_POST['username'] ?? '');
        $password = 'Password@1234';

        // 1.5 Calculate default birthday (Jan 1 of the calculated year) to maintain DB consistency
        $birthday = null;
        if ($age !== null) {
            $currentYear = (int)date('Y');
            $birthYear = $currentYear - $age;
            $birthday = "$birthYear-01-01";
        }

        // Construct full name for legacy 'name' column
        $name_parts = [];
        if (!empty($last_name)) $name_parts[] = $last_name . ",";
        if (!empty($first_name)) $name_parts[] = $first_name;
        if (!empty($middle_name)) $name_parts[] = $middle_name;
        if (!empty($suffix)) $name_parts[] = $suffix;
        $name = trim(implode(" ", $name_parts));

        // Validation
        if ($age !== null && $age < 21) {
            throw new Exception("Personnel must be at least 21 years old.");
        }
        if (empty($last_name) || empty($first_name) || empty($rank) || empty($gender) || $age === null || empty($username)) {
            throw new Exception("All required personnel fields must be filled.");
        }

        // Check Username
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            throw new Exception("Username already exists.");
        }

        // Compress images
        $img_right = !empty($_POST['compressed_img_right']) ? $_POST['compressed_img_right'] : null;
        $img_front = !empty($_POST['compressed_img_front']) ? $_POST['compressed_img_front'] : null;
        $img_left = !empty($_POST['compressed_img_left']) ? $_POST['compressed_img_left'] : null;

        // Insert User
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_user = $pdo->prepare("INSERT INTO users (username, password, role, last_name, first_name, middle_name, suffix, name, rank, gender, unit, birthday, age, status, img_right, img_front, img_left) VALUES (?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)");
        $insert_user->execute([$username, $hashed_password, $last_name, $first_name, $middle_name, $suffix, $name, $rank, $gender, $unit, $birthday, $age, $img_right, $img_front, $img_left]);
        $new_user_id = $pdo->lastInsertId();

        // 2. Process BMI Data
        $height = $_POST['height'] ?? 0;
        $weight = $_POST['weight'] ?? 0;
        $waist = $_POST['waist'] ?? 0;
        $hip = $_POST['hip'] ?? 0;
        $wrist = $_POST['wrist'] ?? 0;
        $bmi_result = $_POST['bmi_result'] ?? 0;
        $bmi_classification = $_POST['bmi_classification'] ?? '';
        $normal_weight = $_POST['normal_weight'] ?? '';
        $weight_to_lose = $_POST['weight_to_lose'] ?? '';
        $intervention = $_POST['intervention'] ?? '';
        $date_taken = date('Y-m-d'); // Use current date for initial registration

        // Insert initial Health Record
        $insert_health = $pdo->prepare("INSERT INTO health_records 
            (user_id, height, weight, waist, hip, wrist, bmi_result, bmi_classification, normal_weight, weight_to_lose, intervention_package, date_taken)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert_health->execute([
            $new_user_id, $height, $weight, $waist, $hip, $wrist,
            $bmi_result, $bmi_classification, $normal_weight, $weight_to_lose,
            $intervention, $date_taken
        ]);

        logAction($_SESSION['user_id'], $new_user_id, 'Admin Register User', "Admin registered new personnel $name and added initial BMI.");

        $pdo->commit();
        $_SESSION['flash_success'] = "Successfully registered $name and saved initial BMI data.";
        header("Location: main.php");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_msg = $e->getMessage();
    }
}

// Search Logic for Step 1
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "SELECT id, username, name, rank, unit FROM users WHERE role = 'user'";
$params = [];
if (!empty($search)) {
    $query .= " AND (name LIKE ? OR username LIKE ? OR rank LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$existing_users = $stmt->fetchAll();

// Get Next User Number for automatic username generation
$stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$next_user_no = (int)$stmt_count->fetchColumn() + 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - Register Personnel</title>
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
        /* ----- Navbar CSS (matching other pages) ----- */
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
        
        /* Sidebar Styling (EXACTLY from main.php) */
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
        .profile-placeholder:hover { transform: scale(1.05); }
        .profile-placeholder img { border: 3px solid #ffffff !important; border-radius: 50%; }
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
        .sidebar-links-container a i { font-size: 1.3rem; margin-right: 15px; color: #33AFFF; }
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
        .logout-btn i { font-size: 1.3rem; }

        /* Dark Mode Consistency */
        [data-bs-theme="dark"] body { background-color: #121212 !important; color: #e0e0e0 !important; }
        [data-bs-theme="dark"] .card { background-color: #1e1e1e !important; border-color: #333 !important; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background-color: #2d2d2d !important; border-color: #444 !important; color: #ffffff !important;
        }
        [data-bs-theme="dark"] .acpo-nav { background: #0d005e !important; border-bottom: none !important; box-shadow: none !important; }

        /* Form Components */
        .step-container { display: none; animation: fadeIn 0.4s; }
        .step-container.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .pic-box {
            background: #fff;
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px dashed #ccc;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            transition: all 0.3s;
        }
        .pic-box:hover { border-color: #1700ad; background: #f8f9fa; }
        [data-bs-theme="dark"] .pic-box { background: #2d2d2d; border-color: #444; }
        .pic-box img { width: 100%; height: 100%; object-fit: contain; }
        .view-label { text-align: center; font-weight: bold; margin-top: 5px; font-size: 0.9rem; }

        /* Premium Modern Solar-Lunar Toggle */
        .theme-switch-wrapper { display: flex; align-items: center; }
        .theme-switch { display: inline-block; height: 40px; position: relative; width: 76px; user-select: none; }
        .theme-switch input { display: none; }
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
        }
        [data-bs-theme="dark"] .theme-switch-knob { transform: translateX(36px); background: #2b2e38; }
        .sun-icon { color: #f39c12; }
        .moon-icon { color: #33AFFF; position: absolute; opacity: 0; }
        [data-bs-theme="dark"] .sun-icon { opacity: 0; }
        [data-bs-theme="dark"] .moon-icon { opacity: 1; }
    
    
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="d-flex flex-column min-vh-100">

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
            <!-- Profile Image (Clickable to settings) -->
            <a href="settings.php" class="profile-placeholder d-flex justify-content-center text-decoration-none">
                <?php 
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
                <img src="<?php echo $sidebar_pfp; ?>" alt="Profile" class="img-fluid rounded-circle shadow-sm" style="width: 130px; height: 130px; object-fit: cover; border: 3px solid #fff;" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23e9ecef\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
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

    <div class="d-flex flex-grow-1">
        <!-- Main Content -->
        <main class="flex-grow-1 p-4 w-100">
            <div class="container-fluid max-w-1200 mx-auto">
                <h2 class="fw-bold mb-4" style="color: #1700ad; font-family: 'Agrandir', sans-serif;"><i class="bi bi-person-plus-fill me-2"></i>Register Personnel</h2>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
                <?php endif; ?>

                <!-- Step 1: Check Existing -->
                <div id="step1" class="step-container active">
                    <div class="card shadow-sm border-0 rounded-4 mb-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Step 1: Verify Existing Personnel</h5>
                            <p class="text-secondary mb-4">Please check if the personnel already has an account before creating a new one to avoid duplicates.</p>
                            
                            <form method="GET" class="d-flex gap-2 mb-4">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, rank, or username..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-search"></i> Search</button>
                                <?php if($search): ?><a href="admin_register_personnel.php" class="btn btn-light border">Clear</a><?php endif; ?>
                            </form>

                            <div class="table-responsive rounded border mb-4">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Rank</th>
                                            <th>Name</th>
                                            <th>Unit</th>
                                            <th>Username</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($existing_users)): ?>
                                            <tr><td colspan="4" class="text-center py-4 text-secondary">No matching personnel found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach($existing_users as $u): ?>
                                                <tr>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($u['rank']); ?></span></td>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($u['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($u['unit']); ?></td>
                                                    <td class="text-secondary">@<?php echo htmlspecialchars($u['username']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end">
                                <button type="button" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow" onclick="nextStep()">
                                    Personnel Not Found? Proceed to Register <i class="bi bi-arrow-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Unified Form -->
                <div id="step2" class="step-container">
                    <form id="unifiedRegisterForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="register_and_bmi">
                        
                        <!-- Account & Personal Info -->
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold m-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Personal & Account Information</h5>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="prevStep()"><i class="bi bi-arrow-left"></i> Back to Search</button>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-3">
                                    <input type="hidden" name="user_number" id="reg-user-number" value="<?php echo $next_user_no; ?>">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="last_name" id="reg-last-name" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="first_name" id="reg-first-name" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Middle Name</label>
                                        <input type="text" class="form-control" name="middle_name">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Suffix</label>
                                        <input type="text" class="form-control" name="suffix" placeholder="e.g. Jr, Sr">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Rank <span class="text-danger">*</span></label>
                                        <input class="form-control" list="pnp-ranks" name="rank" id="reg-rank" required autocomplete="off">
                                        <datalist id="pnp-ranks">
                                            <option value="Police General (PGEN)">
                                            <option value="Police Lieutenant General (PLTGEN)">
                                            <option value="Police Major General (PMGEN)">
                                            <option value="Police Brigadier General (PBGEN)">
                                            <option value="Police Colonel (PCOL)">
                                            <option value="Police Lieutenant Colonel (PLTCOL)">
                                            <option value="Police Major (PMAJ)">
                                            <option value="Police Captain (PCPT)">
                                            <option value="Police Lieutenant (PLT)">
                                            <option value="Police Executive Master Sergeant (PEMS)">
                                            <option value="Police Chief Master Sergeant (PCMS)">
                                            <option value="Police Senior Master Sergeant (PSMS)">
                                            <option value="Police Master Sergeant (PMSg)">
                                            <option value="Police Staff Sergeant (PSSg)">
                                            <option value="Police Corporal (PCpl)">
                                            <option value="Patrolman / Patrolwoman (Pat)">
                                        </datalist>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" name="gender" required>
                                            <option value="" disabled selected>Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Unit/Office <span class="text-danger">*</span></label>
                                        <input class="form-control" list="pnp-units" name="unit[]" required autocomplete="off">
                                        <datalist id="pnp-units">
                                            <option value="CHQ"><option value="PS1"><option value="PS2"><option value="PS3">
                                            <option value="PS4"><option value="PS5"><option value="PS6"><option value="CMFC">
                                            <option value="TPU"><option value="CIU"><option value="MPU"><option value="CARMU">
                                            <option value="AOMU"><option value="CCADU"><option value="ARDDO"><option value="CPPU">
                                            <option value="GSO"><option value="DEU"><option value="TEU"><option value="COMU">
                                            <option value="ODCDO">
                                        </datalist>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Age <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="reg-age" name="age" required min="21" max="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold">Username <span class="text-secondary">(Auto-generated)</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">@</span>
                                            <input type="text" class="form-control bg-light" name="username" id="reg-username" required readonly tabindex="-1">
                                        </div>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <div class="alert alert-info py-2 m-0 small"><i class="bi bi-info-circle me-2"></i>Default password will be set to: <strong>Password@1234</strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Initial BMI Form -->
                        <div class="card shadow-sm border-0 rounded-4 mb-4">
                            <div class="card-header bg-white border-bottom py-3">
                                <h5 class="fw-bold m-0"><i class="bi bi-heart-pulse-fill me-2 text-danger"></i>Initial BMI Data</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <div class="col-lg-5">
                                        <!-- Photo Uploads -->
                                        <label class="form-label small fw-bold mb-3 d-block">Body Photos (Optional but recommended)</label>
                                        <div class="d-flex gap-3 justify-content-center flex-wrap flex-md-nowrap">
                                            <?php foreach(['right'=>1, 'front'=>2, 'left'=>3] as $type=>$num): ?>
                                                <div class="flex-grow-1" style="max-width: 140px;">
                                                    <div class="pic-box w-100" onclick="document.getElementById('img-upload-<?php echo $num; ?>').click()">
                                                        <span id="img-text-<?php echo $num; ?>" class="small text-secondary px-2 text-center">Click to upload<br><?php echo ucfirst($type); ?> View</span>
                                                        <img id="img-preview-<?php echo $num; ?>" class="d-none">
                                                    </div>
                                                    <div class="view-label"><?php echo ucfirst($type); ?> View</div>
                                                    <input type="file" id="img-upload-<?php echo $num; ?>" class="d-none" accept="image/*" onchange="previewImage(this, 'img-preview-<?php echo $num; ?>', 'img-text-<?php echo $num; ?>', '<?php echo $type; ?>')">
                                                    <input type="hidden" name="compressed_img_<?php echo $type; ?>" id="compressed_img_<?php echo $type; ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-7 border-lg-start ps-lg-4">
                                        <!-- Measurements -->
                                        <div class="row g-3 mb-4">
                                            <div class="col-sm-6">
                                                <label class="form-label small fw-bold">Height (cm) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.1" class="form-control form-control-lg fw-bold" id="input-height" name="height" required>
                                            </div>
                                            <div class="col-sm-6">
                                                <label class="form-label small fw-bold">Weight (kg) <span class="text-danger">*</span></label>
                                                <input type="number" step="0.1" class="form-control form-control-lg fw-bold" id="input-weight" name="weight" required>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-bold">Waist (cm)</label>
                                                <input type="number" step="0.1" class="form-control" name="waist">
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-bold">Hip (cm)</label>
                                                <input type="number" step="0.1" class="form-control" name="hip">
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small fw-bold">Wrist (cm)</label>
                                                <input type="number" step="0.1" class="form-control" name="wrist">
                                            </div>
                                        </div>

                                        <!-- Hidden Calculated Fields -->
                                        <input type="hidden" id="input-bmi_result" name="bmi_result">
                                        <input type="hidden" id="input-bmi_classification" name="bmi_classification">
                                        <input type="hidden" id="input-normal_weight" name="normal_weight">
                                        <input type="hidden" id="input-weight_to_lose" name="weight_to_lose">
                                        <input type="hidden" id="input-intervention" name="intervention">

                                        <!-- Live Preview Panel -->
                                        <div class="bg-light p-3 rounded border">
                                            <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="bi bi-calculator text-primary me-2"></i>Live Classification Preview</h6>
                                            <div class="row text-center align-items-center">
                                                <div class="col-6 border-end">
                                                    <div class="small text-secondary text-uppercase fw-bold">BMI</div>
                                                    <div class="fs-2 fw-bold text-primary" id="preview-bmi">0.00</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="small text-secondary text-uppercase fw-bold">Standard</div>
                                                    <div class="fs-5 fw-bold" id="preview-class">--</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mb-5">
                            <button type="submit" class="btn btn-success btn-lg px-5 rounded-pill fw-bold shadow">
                                <i class="bi bi-save me-2"></i> Complete Registration
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 p-1 bg-transparent">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2 z-3" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="modalImage" src="" class="img-fluid rounded" alt="Preview">
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // STEP NAVIGATION
        function nextStep() {
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
        }
        function prevStep() {
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step1').classList.add('active');
        }

        // RECALCULATE HEALTH METRICS ON AGE CHANGE
        document.getElementById('reg-age').addEventListener('input', function() {
            calculateHealthMetrics();
        });

        // AUTOMATIC USERNAME GENERATION
        const rankMap = {
            'Police General (PGEN)': 'pgen',
            'Police Lieutenant General (PLTGEN)': 'pltgen',
            'Police Major General (PMGEN)': 'pmgen',
            'Police Brigadier General (PBGEN)': 'pbgen',
            'Police Colonel (PCOL)': 'pcol',
            'Police Lieutenant Colonel (PLTCOL)': 'pltcol',
            'Police Major (PMAJ)': 'pmaj',
            'Police Captain (PCPT)': 'pcpt',
            'Police Lieutenant (PLT)': 'plt',
            'Police Executive Master Sergeant (PEMS)': 'pems',
            'Police Chief Master Sergeant (PCMS)': 'pcms',
            'Police Senior Master Sergeant (PSMS)': 'psms',
            'Police Master Sergeant (PMSg)': 'pmsg',
            'Police Staff Sergeant (PSSg)': 'pssg',
            'Police Corporal (PCpl)': 'pcpl',
            'Patrolman / Patrolwoman (Pat)': 'pat'
        };

        function generateUsername() {
            const userNo = document.getElementById('reg-user-number').value.trim();
            const lastName = document.getElementById('reg-last-name').value.trim().toLowerCase().replace(/\s+/g, '');
            const fullRank = document.getElementById('reg-rank').value.trim();
            
            // Extract abbreviation from map or fallback to parentheses content
            let rankAbbr = rankMap[fullRank];
            if (!rankAbbr && fullRank.includes('(')) {
                rankAbbr = fullRank.match(/\((.*?)\)/)[1].toLowerCase().replace(/\s+/g, '');
            }
            
            if (userNo && lastName && rankAbbr) {
                document.getElementById('reg-username').value = userNo + lastName + rankAbbr;
            } else {
                document.getElementById('reg-username').value = '';
            }
        }

        document.getElementById('reg-last-name').addEventListener('input', generateUsername);
        document.getElementById('reg-rank').addEventListener('input', generateUsername);
        document.getElementById('reg-rank').addEventListener('change', generateUsername);
        
        // Initial call in case fields are pre-filled (though unlikely for new registration)
        window.onload = generateUsername;

        // IMAGE PREVIEW & COMPRESS
        function previewImage(input, imgId, textId, typeName) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = new Image();
                    img.onload = function() {
                        var canvas = document.createElement('canvas');
                        var ctx = canvas.getContext('2d');
                        var MAX_WIDTH = 800;
                        var MAX_HEIGHT = 800;
                        var width = img.width; var height = img.height;
                        if (width > height) {
                            if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; }
                        } else {
                            if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT; }
                        }
                        canvas.width = width; canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);
                        var dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                        
                        var imgElement = document.getElementById(imgId);
                        var textElement = document.getElementById(textId);
                        var hiddenInput = document.getElementById('compressed_img_' + typeName);
                        
                        imgElement.src = dataUrl;
                        imgElement.classList.remove('d-none');
                        textElement.classList.add('d-none');
                        if(hiddenInput) hiddenInput.value = dataUrl;
                    }
                    img.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // VIEW IMAGE ON CLICK
        document.querySelectorAll('.pic-box img').forEach(img => {
            img.addEventListener('click', function(e) {
                if(!this.classList.contains('d-none')) {
                    e.stopPropagation(); // prevent file input click
                    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                    document.getElementById('modalImage').src = this.src;
                    modal.show();
                }
            });
        });

        // BMI LOGIC
        const inputHeight = document.getElementById('input-height');
        const inputWeight = document.getElementById('input-weight');

        function calculateHealthMetrics() {
            const heightCm = parseFloat(inputHeight.value);
            const weightKg = parseFloat(inputWeight.value);
            const userAge = parseInt(document.getElementById('reg-age').value) || 0;
            
            if (!heightCm || !weightKg) {
                document.getElementById('preview-bmi').textContent = "0.00";
                document.getElementById('preview-class').textContent = "--";
                return;
            }

            const heightM = heightCm / 100;
            const bmi = weightKg / (heightM * heightM);
            const bmiRounded = bmi.toFixed(2);
            const bmiNumeric = parseFloat(bmiRounded);

            let classification = '';
            let isAcceptable = false;

            if (bmiNumeric < 17) { classification = 'SEVERELY UNDERWEIGHT'; } 
            else if (bmiNumeric >= 17 && bmiNumeric <= 18.4) { classification = 'UNDERWEIGHT'; } 
            else if (bmiNumeric >= 18.5 && bmiNumeric < 25) { classification = 'NORMAL'; }
            else {
                const age = userAge;
                if (age <= 29) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                else if (age >= 30 && age <= 34) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 25) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                    else if (bmiNumeric > 25 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                else if (age >= 35 && age <= 39) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 25.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                    else if (bmiNumeric > 25.5 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                else if (age >= 40 && age <= 44) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 26) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                    else if (bmiNumeric > 26 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                else if (age >= 45 && age <= 50) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 26.5) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                    else if (bmiNumeric > 26.5 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric <= 34.9) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                else if (age >= 51) {
                    if (bmiNumeric >= 25 && bmiNumeric <= 27) { classification = 'ACCEPTABLE BMI'; isAcceptable = true; }
                    else if (bmiNumeric > 27 && bmiNumeric <= 29.9) classification = 'OVERWEIGHT';
                    else if (bmiNumeric >= 30 && bmiNumeric < 35) classification = 'OBESE CLASS 1';
                    else if (bmiNumeric >= 35 && bmiNumeric <= 39.9) classification = 'OBESE CLASS 2';
                    else if (bmiNumeric >= 40) classification = 'OBESE CLASS 3';
                }
                
                if (!classification && !isAcceptable) {
                    if (bmiNumeric < 25) classification = 'NORMAL';
                    else if (bmiNumeric < 30) classification = 'OVERWEIGHT';
                    else classification = 'OBESE CLASS 1';
                }
            }

            const minNormal = 18.5 * (heightM * heightM);
            const maxNormal = 24.9 * (heightM * heightM);
            const normalWeightRange = minNormal.toFixed(1) + "kg - " + maxNormal.toFixed(1) + "kg";

            let weightToLose = "0";
            if (!['NORMAL', 'ACCEPTABLE BMI', 'UNDERWEIGHT', 'SEVERELY UNDERWEIGHT'].includes(classification)) {
                const loss = weightKg - maxNormal;
                if (loss > 0) weightToLose = loss.toFixed(1) + " kg";
            }

            let packageCode = '';
            if (['SEVERELY UNDERWEIGHT', 'UNDERWEIGHT'].includes(classification)) packageCode = 'Package A';
            else if (['NORMAL', 'ACCEPTABLE BMI'].includes(classification)) packageCode = 'Package B';
            else if (classification === 'OVERWEIGHT') packageCode = 'Package C';
            else if (classification === 'OBESE CLASS 1') packageCode = 'Package D';
            else if (classification === 'OBESE CLASS 2') packageCode = 'Package E';
            else if (classification === 'OBESE CLASS 3') packageCode = 'Package F';

            // Set hidden inputs
            document.getElementById('input-bmi_result').value = bmiRounded;
            document.getElementById('input-bmi_classification').value = classification;
            document.getElementById('input-normal_weight').value = normalWeightRange;
            document.getElementById('input-weight_to_lose').value = weightToLose;
            document.getElementById('input-intervention').value = packageCode;

            // Update UI
            document.getElementById('preview-bmi').textContent = bmiRounded;
            const classText = document.getElementById('preview-class');
            classText.textContent = classification;
            
            // UI Colors
            classText.className = 'fs-5 fw-bold text-success';
            if (classification.includes('OBESE')) classText.className = 'fs-5 fw-bold text-danger';
            else if (classification === 'OVERWEIGHT' || classification.includes('UNDERWEIGHT')) classText.className = 'fs-5 fw-bold text-warning';
        }

        inputHeight.addEventListener('input', calculateHealthMetrics);
        inputWeight.addEventListener('input', calculateHealthMetrics);

        // Sidebar Theme Switch Logic
        document.addEventListener('DOMContentLoaded', function() {
            const themeSwitch = document.getElementById('theme-switch-checkbox');
            const currentTheme = localStorage.getItem('theme') || 'light';
            
            if (currentTheme === 'dark') {
                themeSwitch.checked = true;
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }

            themeSwitch.addEventListener('change', function() {
                if (this.checked) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });
        });
    </script>
</body>
</html>
