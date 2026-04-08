<?php
/**
 * emergency.php
 * Mass Registration Tool for personnel
 * Hidden feature - Standalone access
 */

session_start();
require_once 'db_connection.php';

// Access Control - Admins Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. You must be an administrator to access this tool.");
}

$success_count = 0;
$error_msg = '';
$registered_names = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mass_register'])) {
    $last_names = explode("\n", str_replace("\r", "", $_POST['last_names']));
    $first_names = explode("\n", str_replace("\r", "", $_POST['first_names']));
    $middle_names = explode("\n", str_replace("\r", "", $_POST['middle_names']));
    $suffixes = explode("\n", str_replace("\r", "", $_POST['suffixes']));
    
    $common_rank = trim($_POST['common_rank'] ?? 'Patrolman / Patrolwoman (Pat)');
    $common_gender = $_POST['common_gender'] ?? 'male';
    $default_age = 25;
    $birthYear = (int)date('Y') - $default_age;
    $default_birthday = "$birthYear-01-01";
    $default_password = password_hash('Password@1234', PASSWORD_DEFAULT);

    // Get max lines among Last Name and First Name
    $max_lines = max(count($last_names), count($first_names));

    try {
        $pdo->beginTransaction();
        
        // Get current max count of user roles for username generation (mimicking legacy logic if needed)
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
        $user_counter = (int)$stmt_count->fetchColumn() + 1;

        for ($i = 0; $i < $max_lines; $i++) {
            $ln = trim($last_names[$i] ?? '');
            $fn = trim($first_names[$i] ?? '');
            $mn = trim($middle_names[$i] ?? '');
            $sx = trim($suffixes[$i] ?? '');

            // Normalize Name Case: Capitalize first letters, lowercase the rest
            $normalize = function($str) {
                return ucwords(strtolower($str));
            };

            $ln = $normalize($ln);
            $fn = $normalize($fn);
            $mn = $normalize($mn);
            $sx = $normalize($sx);

            // Skip if no names provided on this line
            if (empty($ln) && empty($fn)) continue;

            // 1. Construct Full Name
            $name_parts = [];
            if (!empty($ln)) $name_parts[] = $ln . ",";
            if (!empty($fn)) $name_parts[] = $fn;
            if (!empty($mn)) $name_parts[] = $mn;
            if (!empty($sx)) $name_parts[] = $sx;
            $full_name = trim(implode(" ", $name_parts));

            // 2. Generate Username
            // Format: rankabbr_lastname_counter
            preg_match('/\((.*?)\)/', $common_rank, $matches);
            $rank_abbr = isset($matches[1]) ? strtolower(str_replace(' ', '', $matches[1])) : 'user';
            $safe_last_name = strtolower(preg_replace('/[^a-z0-9]/', '', $ln));
            
            $username = $rank_abbr . "_" . $safe_last_name . "_" . $user_counter;
            
            // Final Collision Check
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            if ($check_stmt->fetch()) {
                // If collision, add more randomness
                $username .= "_" . bin2hex(random_bytes(2));
            }

            // 3. Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (
                username, password, role, last_name, first_name, middle_name, suffix, name, 
                rank, gender, unit, birthday, age, status
            ) VALUES (?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, 'No Unit', ?, ?, 'active')");
            
            $stmt->execute([
                $username, 
                $default_password, 
                $ln, 
                $fn, 
                $mn, 
                $sx, 
                $full_name, 
                $common_rank, 
                $common_gender, 
                $default_birthday, 
                $default_age
            ]);
            
            $new_user_id = $pdo->lastInsertId();

            // 4. Insert initial Health Record (Bypassed fields)
            $stmt_health = $pdo->prepare("INSERT INTO health_records (
                user_id, height, weight, waist, hip, wrist, bmi_result, 
                bmi_classification, normal_weight, weight_to_lose, intervention_package, date_taken
            ) VALUES (?, 0, 0, 0, 0, 0, 0, 'N/A', 'N/A', 'N/A', 'N/A', ?)");
            
            $stmt_health->execute([$new_user_id, date('Y-m-d')]);

            $registered_names[] = $full_name;
            $success_count++;
            $user_counter++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_msg = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Mass Registration - ACPO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2');
            font-weight: bold;
        }
        :root {
            --primary: #1700ad;
            --accent: #33AFFF;
            --dark-bg: #0d005e;
            --glass: rgba(255, 255, 255, 0.1);
        }
        body {
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%);
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-vh-100;
            padding: 40px 0;
        }
        .header-logo {
            height: 80px;
            margin-bottom: 20px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        h1 {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 30px;
            color: #fff;
            text-align: center;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
            margin-bottom: 10px;
        }
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 12px;
            padding: 12px;
            color-scheme: dark; /* Forces browser to use dark-themed dropdown elements */
        }
        .form-select option {
            background-color: #0d005e;
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 0 0 4px rgba(51, 175, 255, 0.2);
        }
        textarea.form-control {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            min-height: 200px;
            white-space: pre;
            overflow-x: auto;
        }
        .btn-register {
            background: linear-gradient(135deg, #33AFFF 0%, #1700ad 100%);
            border: none;
            color: #fff;
            font-weight: bold;
            font-family: 'Agrandir', sans-serif;
            padding: 15px 40px;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(23, 0, 173, 0.3);
            width: 100%;
            margin-top: 20px;
        }
        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(23, 0, 173, 0.5);
            color: #fff;
        }
        .info-pill {
            background: rgba(51, 175, 255, 0.1);
            border: 1px solid rgba(51, 175, 255, 0.2);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.9);
        }
        .sync-scroll-active {
            border-color: var(--accent) !important;
        }
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.3); }

        .result-list {
            max-height: 200px;
            overflow-y: auto;
            background: rgba(0,0,0,0.2);
            border-radius: 12px;
            padding: 10px;
            margin-top: 20px;
        }
        .result-item {
            padding: 5px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-11">
                <div class="text-center mb-4">
                    <img src="images/acpologo.png" alt="ACPO" class="header-logo">
                    <h1>Emergency Mass Registration</h1>
                </div>

                <?php if ($success_count > 0): ?>
                    <div class="alert alert-success bg-success border-0 text-white rounded-4 p-4 mb-4 shadow">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill fs-2 me-3"></i>
                            <div>
                                <h5 class="mb-1 fw-bold">Registration Successful!</h5>
                                <p class="mb-0"><?php echo $success_count; ?> personnel have been added to the system under "No Unit".</p>
                            </div>
                        </div>
                        <div class="result-list mt-3">
                            <?php foreach ($registered_names as $name): ?>
                                <div class="result-item"><i class="bi bi-person-fill me-2 text-info"></i><?php echo htmlspecialchars($name); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-end mt-3">
                            <a href="main.php" class="btn btn-light btn-sm rounded-pill px-4">Back to Home</a>
                            <a href="emergency.php" class="btn btn-outline-light btn-sm rounded-pill px-4 ms-2">Register More</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-danger bg-danger border-0 text-white rounded-4 p-4 mb-4 shadow">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_count == 0): ?>
                <div class="glass-card">
                    <div class="info-pill">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Paste lists of names below. Each line should correspond to one individual across all fields. 
                        <strong>Username</strong> will be auto-generated. 
                        <strong>Password</strong> defaults to <code>Password@1234</code>. 
                        <strong>Unit</strong> is set to <code>No Unit</code>.
                        All BMI fields are bypassed.
                    </div>

                    <form method="POST">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Common Rank for this Batch</label>
                                <select class="form-select" name="common_rank">
                                    <option value="Police General (PGEN)">Police General (PGEN)</option>
                                    <option value="Police Lieutenant General (PLTGEN)">Police Lieutenant General (PLTGEN)</option>
                                    <option value="Police Major General (PMGEN)">Police Major General (PMGEN)</option>
                                    <option value="Police Brigadier General (PBGEN)">Police Brigadier General (PBGEN)</option>
                                    <option value="Police Colonel (PCOL)">Police Colonel (PCOL)</option>
                                    <option value="Police Lieutenant Colonel (PLTCOL)">Police Lieutenant Colonel (PLTCOL)</option>
                                    <option value="Police Major (PMAJ)">Police Major (PMAJ)</option>
                                    <option value="Police Captain (PCPT)">Police Captain (PCPT)</option>
                                    <option value="Police Lieutenant (PLT)">Police Lieutenant (PLT)</option>
                                    <option value="Police Executive Master Sergeant (PEMS)">Police Executive Master Sergeant (PEMS)</option>
                                    <option value="Police Chief Master Sergeant (PCMS)">Police Chief Master Sergeant (PCMS)</option>
                                    <option value="Police Senior Master Sergeant (PSMS)">Police Senior Master Sergeant (PSMS)</option>
                                    <option value="Police Master Sergeant (PMSg)">Police Master Sergeant (PMSg)</option>
                                    <option value="Police Staff Sergeant (PSSg)">Police Staff Sergeant (PSSg)</option>
                                    <option value="Police Corporal (PCpl)">Police Corporal (PCpl)</option>
                                    <option value="Patrolman / Patrolwoman (Pat)" selected>Patrolman / Patrolwoman (Pat)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Common Gender</label>
                                <select class="form-select" name="common_gender">
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Last Names <span class="text-danger">*</span></label>
                                <textarea name="last_names" class="form-control" placeholder="Dela Cruz&#10;Gomez&#10;Reyes" required id="ta_ln"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">First Names <span class="text-danger">*</span></label>
                                <textarea name="first_names" class="form-control" placeholder="Juan&#10;Maria&#10;Jose" required id="ta_fn"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Middle Names</label>
                                <textarea name="middle_names" class="form-control" placeholder="Bautista&#10;Santos&#10;Garcia" id="ta_mn"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Suffix (Jr, Sr...)</label>
                                <textarea name="suffixes" class="form-control" placeholder="&#10;Jr&#10;" id="ta_sx"></textarea>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" name="mass_register" class="btn btn-register">
                                <i class="bi bi-lightning-fill me-2"></i> PERFORM MASS REGISTRATION
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <p class="footer-text">
                    ACPO Emergency Administration Tool &bull; &copy; <?php echo date('Y'); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Synchronized Scrolling for textareas (Quality of Life)
        const textareas = [
            document.getElementById('ta_ln'),
            document.getElementById('ta_fn'),
            document.getElementById('ta_mn'),
            document.getElementById('ta_sx')
        ];

        textareas.forEach(ta => {
            ta.addEventListener('scroll', () => {
                textareas.forEach(otherTa => {
                    if (ta !== otherTa) {
                        otherTa.scrollTop = ta.scrollTop;
                    }
                });
            });
            
            ta.addEventListener('focus', () => ta.classList.add('sync-scroll-active'));
            ta.addEventListener('blur', () => ta.classList.remove('sync-scroll-active'));
        });
    </script>
</body>
</html>
