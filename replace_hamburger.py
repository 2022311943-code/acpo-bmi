import re

css_new = """        /* ----- Hamburger Menu Sidebar CSS ----- */
        .user-sidebar {
            width: 380px !important;
            border-left: none;
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%);
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
        }"""

html_new = """    <!-- Hamburger Overlay Menu -->
    <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar" aria-labelledby="userSidebarLabel">
        <div class="offcanvas-header pb-0 border-0">
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-0">
            <!-- Profile Placeholder Image -->
            <div class="profile-placeholder d-flex justify-content-center">
                <img src="images/placeholder.png" alt="Profile Background" class="img-fluid rounded-circle shadow-sm" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' viewBox=\\'0 0 200 200\\'><circle cx=\\'100\\' cy=\\'100\\' r=\\'100\\' fill=\\'%23e9ecef\\'/><path d=\\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\\' fill=\\'%23adb5bd\\'/></svg>'">
            </div>
            
            <h4 class="sidebar-name"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h4>
            
            <div class="sidebar-links-container">
                <a href="main.php"><i class="bi bi-house-door"></i> HOME</a>
                <a href="about.php"><i class="bi bi-info-circle"></i> ABOUT</a>
                <a href="contact.php"><i class="bi bi-envelope"></i> CONTACT</a>
                <a href="editor.php"><i class="bi bi-calculator"></i> BMI CALCULATOR</a>
                <a href="settings.php"><i class="bi bi-gear"></i> USER SETTINGS</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin_users.php"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                <?php endif; ?>
            </div>
            
            <div class="logout-btn-container">
                <a href="login.php?action=logout" class="logout-btn">
                    LOGOUT <i class="bi bi-box-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </div>"""

files_to_edit = ['c:\\\\xampp\\\\htdocs\\\\ACPO\\\\main.php', 'c:\\\\xampp\\\\htdocs\\\\ACPO\\\\settings.php', 'c:\\\\xampp\\\\htdocs\\\\ACPO\\\\editor.php', 'c:\\\\xampp\\\\htdocs\\\\ACPO\\\\contact.php', 'c:\\\\xampp\\\\htdocs\\\\ACPO\\\\admin_users.php', 'c:\\\\xampp\\\\htdocs\\\\ACPO\\\\about.php']

for file in files_to_edit:
    print(f"Processing {file}")
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()

    css_pattern = re.compile(r' {8}/\* ----- Hamburger Menu Sidebar CSS ----- \*/[\s\S]*? \.logout-btn:hover \{[\s\S]*?\}')
    
    html_pattern = re.compile(r' {4,8}<!-- Hamburger Overlay Menu -->[\s\S]*?</div>\r?\n {4}</div>')
    
    new_content = css_pattern.sub(css_new, content)
    new_content = html_pattern.sub(html_new, new_content)

    print("CSS changes:", content != new_content)
    with open(file, 'w', encoding='utf-8') as f:
        f.write(new_content)
