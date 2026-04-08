<?php
// settings.php
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
$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Profile Picture Removal
if (isset($_GET['action']) && $_GET['action'] === 'remove_pfp') {
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['flash_success'] = "Profile picture removed.";
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
    }
    header("Location: settings.php");
    exit;
}


// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Handle Profile Picture Upload FIRST — redirect immediately after
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
            $fileName = $_FILES['profile_pic']['name'];
            $fileSize = $_FILES['profile_pic']['size'];
            $fileType = $_FILES['profile_pic']['type'];
            
            $allowedfileExtensions = ['jpg', 'gif', 'png', 'jpeg'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            if (in_array($fileExtension, $allowedfileExtensions)) {
                if ($fileSize < 5000000) {
                    $data = file_get_contents($fileTmpPath);
                    $base64 = 'data:' . $fileType . ';base64,' . base64_encode($data);
                    
                    $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$base64, $user_id]);
                    unset($data, $base64); // Free memory immediately

                    $_SESSION['flash_success'] = "Profile picture updated!";
                } else {
                    $_SESSION['flash_error'] = "File is too large. Max 5MB.";
                }
            } else {
                $_SESSION['flash_error'] = "Invalid file type. Only JPG, PNG, and GIF allowed.";
            }
            // Always redirect after PFP upload to avoid heavy SELECT * with large base64
            header("Location: settings.php");
            exit;
        }

        // Common fields (only accessed for non-PFP form submissions)
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Handle Password Change if submitted
        if (isset($_POST['change_password']) && $_POST['change_password'] == "1" && !empty($_POST['new_password'])) {
            $current_pw = $_POST['current_password'];
            $new_pw = $_POST['new_password'];
            $confirm_pw = $_POST['confirm_password'];

            $pw_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $pw_stmt->execute([$user_id]);
            $db_user = $pw_stmt->fetch();

            if (!password_verify($current_pw, $db_user['password'])) {
                $error_msg = "Current password is incorrect.";
            } elseif ($new_pw !== $confirm_pw) {
                $error_msg = "New passwords do not match.";
            } elseif (strlen($new_pw) < 8) {
                $error_msg = "New password must be at least 8 characters long.";
            } else {
                $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
                $update_pw_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_pw_stmt->execute([$hashed_pw, $user_id]);
                
                $_SESSION['password_changed_logout'] = true;
                header("Location: settings.php");
                exit;
            }
        }

        if ($is_admin) {
            $last_name = trim($_POST['last_name'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $middle_name = trim($_POST['middle_name'] ?? '');
            $suffix = trim($_POST['suffix'] ?? '');
            $rank = trim($_POST['rank'] ?? '');
            $birthday = $_POST['birthday'] ?? '';
            $nationality = trim($_POST['nationality'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $religion = trim($_POST['religion'] ?? '');

            // Reconstruct full name: LAST, FIRST MI. SUFFIX
            $m_initial = !empty($middle_name) ? (strlen($middle_name) == 1 ? $middle_name . '.' : $middle_name) : '';
            $full_name = trim($last_name . ", " . $first_name . " " . $m_initial . " " . $suffix);
            $full_name = str_replace('  ', ' ', $full_name); // Clean double spaces

            $age = 0;
            if ($birthday) {
                $dob = new DateTime($birthday);
                $now = new DateTime();
                $age = $now->diff($dob)->y;
            }

            $stmt = $pdo->prepare("UPDATE users SET name=?, last_name=?, first_name=?, middle_name=?, suffix=?, rank=?, birthday=?, age=?, nationality=?, address=?, religion=?, contact=?, email=? WHERE id=?");
            if ($stmt->execute([$full_name, $last_name, $first_name, $middle_name, $suffix, $rank, $birthday, $age, $nationality, $address, $religion, $contact, $email, $user_id])) {
                $success_msg = "Profile updated successfully!";
                $_SESSION['name'] = $full_name;
            } else {
                $error_msg = "Failed to update profile.";
            }
        } else {
            $stmt = $pdo->prepare("UPDATE users SET contact=?, email=? WHERE id=?");
            if ($stmt->execute([$contact, $email, $user_id])) {
                $success_msg = "Contact info updated successfully!";
            } else {
                $error_msg = "Failed to update profile.";
            }
        }
    } catch (PDOException $e) {
        $error_msg = "Database Error: " . $e->getMessage();
    }
}

// Fetch Current User Data (excluding large profile_pic to avoid max_allowed_packet issues)
try {
    $stmt = $pdo->prepare("SELECT id, username, name, last_name, first_name, middle_name, suffix, rank, birthday, age, gender, nationality, address, religion, contact, email, role, status, unit, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching user data.");
}

// Fetch profile_pic separately (handles large base64 strings)
$user['profile_pic'] = '';
try {
    $pfp_stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $pfp_stmt->execute([$user_id]);
    $pfp_row = $pfp_stmt->fetch(PDO::FETCH_ASSOC);
    if ($pfp_row && !empty($pfp_row['profile_pic'])) {
        $user['profile_pic'] = $pfp_row['profile_pic'];
    }
} catch (PDOException $e) {
    // Profile pic fetch failed — page still works, just no picture
    $user['profile_pic'] = '';
}

// Helper to safely display values
function val($key) {
    global $user;
    return isset($user[$key]) ? htmlspecialchars($user[$key]) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACPO - User Settings</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .logout-btn i {
            font-size: 1.3rem;
        }

        /* ----- Settings Page CSS ----- */
        .settings-container {
            width: 100%;
            max-width: 1000px;
            margin: 3rem auto;
            background-color: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            padding: 3rem;
            position: relative;
        }
        
        .settings-title {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 2rem;
            color: #1700ad;
            margin-bottom: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 3px solid #f0f0f0;
            padding-bottom: 1rem;
            display: inline-block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 700;
            text-transform: uppercase;
            color: #444;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .form-control-custom {
            border: none;
            border-bottom: 2px solid #ddd;
            border-radius: 0;
            padding: 0.75rem 0.5rem;
            background-color: transparent;
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
            transition: border-color 0.3s ease, background-color 0.3s ease;
        }

        .form-control-custom:focus {
            box-shadow: none;
            border-bottom-color: #1700ad;
            background-color: rgba(23, 0, 173, 0.02);
        }

        .form-control-custom:disabled, 
        .form-control-custom[readonly] {
            background-color: #f9f9f9;
            color: #6c757d;
            border-bottom-color: #eee;
            opacity: 1;
        }
        
        .btn-action {
            border-radius: 50px;
            padding: 0.7rem 2.5rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-save {
            background-color: #1700ad;
            color: #fff;
            box-shadow: 0 4px 15px rgba(23, 0, 173, 0.3);
        }
        
        .btn-save:hover {
            background-color: #12008a;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 0, 173, 0.4);
        }

        .hidden { display: none !important; }

        .profile-pfp-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 2.5rem auto;
            position: relative;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            border: 4px solid #f0f0f0;
        }
        .profile-pfp-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.2);
            border-color: #1700ad;
        }
        .profile-pfp-main {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .pfp-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(23, 0, 173, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .profile-pfp-container:hover .pfp-overlay { opacity: 1; }

        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] body { background-color: #121212 !important; color: #e0e0e0 !important; }
        [data-bs-theme="dark"] .settings-container { background-color: #1e1e1e !important; box-shadow: 0 8px 30px rgba(0,0,0,0.4); }
        [data-bs-theme="dark"] .settings-title { color: #ffffff !important; border-bottom-color: #333; }
        [data-bs-theme="dark"] .form-label { color: #ffffff !important; opacity: 0.9; }
        [data-bs-theme="dark"] .form-control-custom { border-bottom: 2px solid #555 !important; color: #ffffff !important; background-color: #2d2d2d !important; padding: 0.75rem 1rem !important; }
        [data-bs-theme="dark"] .acpo-nav { background: #0d005e !important; }

        /* Premium Modern Solar-Lunar Toggle */
        .theme-switch-wrapper { display: flex; align-items: center; }
        .theme-switch { display: inline-block; height: 40px; position: relative; width: 76px; user-select: none; }
        .theme-switch input { display: none; }
        .theme-switch-track {
            background: rgba(255, 255, 255, 0.1); border-radius: 50px; cursor: pointer; position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); width: 100%; height: 100%;
        }
        [data-bs-theme="dark"] .theme-switch-track { background: rgba(0, 0, 0, 0.3); }
        .theme-switch-knob {
            background: #ffffff; border-radius: 50%; height: 32px; width: 32px; display: flex; align-items: center;
            justify-content: center; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: absolute; left: 3px; top: 2.5px; z-index: 2;
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
<body class="d-flex flex-column min-vh-100 overflow-x-hidden">

    <!-- Header -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-2 px-lg-4">
            <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5">
                <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
            </a>
            <div class="d-flex align-items-center justify-content-end me-3 me-lg-5 pe-lg-5">
                <button class="btn border-0 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar" aria-label="Toggle user menu">
                    <i class="bi bi-list text-white hamburger-icon" style="font-size: 2rem;"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Hamburger Overlay Menu -->
    <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar">
        <div class="offcanvas-header pb-0 border-0">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <div class="profile-placeholder d-flex justify-content-center">
                <?php if (!empty($user['profile_pic'])): ?>
                    <img src="<?php echo $user['profile_pic']; ?>" alt="Profile" class="img-fluid rounded-circle shadow-sm" style="width: 130px; height: 130px; object-fit: cover; border: 3px solid #fff;">
                <?php else: ?>
                    <img src="images/placeholder.png" alt="Profile" class="img-fluid rounded-circle shadow-sm" style="width: 130px; height: 130px; object-fit: cover; border: 3px solid #fff;" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23f8f9fa\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
                <?php endif; ?>
            </div>
            
            <h4 class="sidebar-name"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h4>
            
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
                <?php if ($is_admin): ?>
                    <a href="admin_users.php"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                    <a href="audit_logs.php"><i class="bi bi-shield-lock"></i> AUDIT LOGS</a>
                <?php endif; ?>
                <a href="settings.php" class="active-sidebar-link"><i class="bi bi-gear"></i> USER SETTINGS</a>
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

    <!-- Main Content -->
    <main class="flex-grow-1 container py-5">
        <div class="settings-container">
            <h2 class="settings-title">Account Settings</h2>

            <?php 
            // Merge session flash messages with local ones
            if (isset($_SESSION['flash_success'])) { $success_msg = $_SESSION['flash_success']; unset($_SESSION['flash_success']); }
            if (isset($_SESSION['flash_error'])) { $error_msg = $_SESSION['flash_error']; unset($_SESSION['flash_error']); }
            ?>

            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-pill px-4 shadow-sm mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-pill px-4 shadow-sm mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="profile-pfp-container" onclick="document.getElementById('pfpInput').click();">
                <img src="<?php echo !empty($user['profile_pic']) ? $user['profile_pic'] : 'images/placeholder.png'; ?>" class="profile-pfp-main">
                <div class="pfp-overlay"><i class="bi bi-camera-fill fs-3"></i></div>
            </div>
            
            <div class="text-center mb-5">
                <?php if (!empty($user['profile_pic'])): ?>
                    <a href="settings.php?action=remove_pfp" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold" onclick="return confirm('Remove your profile picture?')">Remove Photo</a>
                <?php else: ?>
                    <span class="text-secondary small fw-bold text-uppercase">Click photo to upload</span>
                <?php endif; ?>
            </div>

            <form action="settings.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_pic" id="pfpInput" class="d-none" accept="image/*" onchange="this.form.submit()">
                
                <div class="mb-5 pb-4 border-bottom">
                    <h5 class="fw-bold mb-4 text-secondary text-uppercase"><i class="bi bi-person-badge me-2 text-primary"></i>Personal Identification</h5>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control-custom w-100" value="<?php echo val('last_name'); ?>" title="<?php echo val('last_name'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> required>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control-custom w-100" value="<?php echo val('first_name'); ?>" title="<?php echo val('first_name'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> required>
                            </div>
                        </div>
                        <div class="col-md-1 col-6">
                            <div class="form-group mb-0">
                                <label class="form-label">M.I.</label>
                                <input type="text" name="middle_name" class="form-control-custom w-100 text-center" value="<?php echo val('middle_name'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> maxlength="1">
                            </div>
                        </div>
                        <div class="col-md-1 col-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Suffix</label>
                                <input type="text" name="suffix" class="form-control-custom w-100 text-center" value="<?php echo val('suffix'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> placeholder="Jr/Sr">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4 border-end">
                        <h5 class="fw-bold mb-4 text-secondary text-uppercase"><i class="bi bi-award me-2 text-primary"></i>Service Details</h5>
                        <div class="form-group"><label class="form-label">Rank</label><input type="text" name="rank" class="form-control-custom w-100" value="<?php echo val('rank'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> required></div>
                        <div class="form-group"><label class="form-label">Birthday</label><input type="date" name="birthday" class="form-control-custom w-100" value="<?php echo val('birthday'); ?>" <?php echo $is_admin ? '' : 'readonly'; ?> required></div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <h5 class="fw-bold mb-4 text-secondary text-uppercase"><i class="bi bi-shield-lock me-2 text-primary"></i>Security & Contact</h5>
                        <div class="form-group"><label class="form-label">Contact</label><input type="text" name="contact" class="form-control-custom w-100" value="<?php echo val('contact'); ?>"></div>
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control-custom w-100" value="<?php echo val('email'); ?>"></div>
                        
                        <div id="passwordSection" class="hidden mt-3 p-3 bg-light rounded border">
                            <input type="hidden" name="change_password" id="change_password_flag" value="0">
                            <input type="password" name="current_password" class="form-control mb-2" placeholder="Current Password">
                            <input type="password" name="new_password" class="form-control mb-2" placeholder="New Password">
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password">
                        </div>

                        <button type="button" class="btn btn-outline-warning w-100 rounded-pill mt-4 fw-bold" id="changePwBtn">Change Password</button>
                        <button type="submit" class="btn btn-save w-100 rounded-pill mt-3 py-3 fw-bold">Save All Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <footer class="mt-auto py-4 bg-white border-top text-center text-secondary small fw-bold text-uppercase" style="letter-spacing: 2px;">
        &copy; <?php echo date('Y'); ?> Angeles City Police Office.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const changePwBtn = document.getElementById('changePwBtn');
            const pwSection = document.getElementById('passwordSection');
            const pwFlag = document.getElementById('change_password_flag');
            
            if (changePwBtn) {
                changePwBtn.addEventListener('click', function() {
                    pwSection.classList.toggle('hidden');
                    const isHidden = pwSection.classList.contains('hidden');
                    pwFlag.value = isHidden ? "0" : "1";
                    changePwBtn.innerText = isHidden ? "Change Password" : "Cancel Password Change";
                });
            }

            const themeSwitch = document.getElementById('theme-switch-checkbox');
            if (document.documentElement.getAttribute('data-bs-theme') === 'dark') themeSwitch.checked = true;
            themeSwitch.addEventListener('change', function() {
                const theme = this.checked ? 'dark' : 'light';
                document.documentElement.setAttribute('data-bs-theme', theme);
                localStorage.setItem('theme', theme);
            });
        });
    </script>
</body>
</html>