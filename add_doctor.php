<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

require_once 'db_connect.php'; 

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ---------------- LANGUAGE SYSTEM ---------------- */
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'en');
$_SESSION['lang'] = $lang;
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

$t = [
    'en' => [
        'title' => 'Add New Doctor',
        'dashboard' => 'Dashboard',
        'logout' => 'Logout',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'nid' => 'National ID',
        'phone' => 'Phone Number',
        'email' => 'Email (Optional)',
        'hire_date' => 'Hire Date',
        'branch' => 'Hospital Branch',
        'specialty' => 'Medical Specialty',
        'fee' => 'Consultation Fee (EGP)',
        'submit' => 'Register Doctor',
        'success_title' => 'Account Created Successfully',
        'user' => 'Username',
        'pass' => 'Password',
        'lic' => 'License Number',
        'copy' => 'Copy Password',
        'add_more' => 'Add Another Doctor'
    ],
    'ar' => [
        'title' => 'إضافة طبيب جديد',
        'dashboard' => 'لوحة التحكم',
        'logout' => 'خروج',
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'nid' => 'الرقم القومي',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني (اختياري)',
        'hire_date' => 'تاريخ التعيين',
        'branch' => 'فرع المستشفى',
        'specialty' => 'التخصص الطبي',
        'fee' => 'رسوم الكشف (جنيه)',
        'submit' => 'تسجيل الطبيب',
        'success_title' => 'تم إنشاء الحساب بنجاح',
        'user' => 'اسم المستخدم',
        'pass' => 'كلمة المرور',
        'lic' => 'رقم الترخيص',
        'copy' => 'نسخ كلمة المرور',
        'add_more' => 'إضافة طبيب آخر'
    ]
][$lang];

/* ---------------- Helper: generate random doctor password ---------------- */
function generateDoctorPassword(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    $rand   = '';
    for ($i = 0; $i < 6; $i++) {
        $rand .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'doc' . $rand;
}

$errorMsg = '';
$successMsg = '';
$generatedUsername = '';
$generatedPassword = '';
$generatedLicense = '';
$showForm = true;

/* ---------------- Fetch branches & specialties ---------------- */
$branches    = $conn->query("SELECT BranchID, Name FROM branches ORDER BY Name");
$specialties = $conn->query("SELECT SpecialtyID, Name FROM specialties ORDER BY Name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName   = trim($_POST['first_name']   ?? '');
    $lastName    = trim($_POST['last_name']    ?? '');
    $nationalID  = trim($_POST['national_id']  ?? '');
    $phone       = trim($_POST['phone']        ?? '');
    $email       = trim($_POST['email']        ?? '');
    $hireDate    = $_POST['hire_date']          ?? date('Y-m-d');
    $branchID    = (int)($_POST['branch_id']    ?? 0);
    $specialtyID = (int)($_POST['specialty_id'] ?? 0);
    $fee         = (float)($_POST['fee']        ?? 0);

    if ($firstName === '' || $lastName === '' || !preg_match('/^[0-9]{14}$/', $nationalID) || $branchID <= 0 || $specialtyID <= 0 || $fee <= 0) {
        $errorMsg = ($lang == 'ar') ? "يرجى التأكد من ملء جميع الحقول والبيانات بشكل صحيح." : "Please ensure all fields are filled correctly.";
    }

    if ($errorMsg === '') {
        try {
            $conn->begin_transaction();

            // Check Unique NID
            $stmt = $conn->prepare("SELECT EmployeeID FROM employees WHERE NationalID = ?");
            $stmt->bind_param("s", $nationalID);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) throw new Exception("National ID already exists.");

            // Generate Credentials
            if ($email === '') $email = 'doc' . strtolower($firstName) . rand(1000, 9999) . '@cairohosp.com';
            $generatedUsername = 'doc' . strtolower($firstName) . rand(100, 999);
            $generatedPassword = generateDoctorPassword();
            $passwordHash      = password_hash($generatedPassword, PASSWORD_DEFAULT);

            // Get Next License
            $licRes = $conn->query("SELECT LicenseNumber FROM doctors ORDER BY CAST(LicenseNumber AS UNSIGNED) DESC LIMIT 1");
            $generatedLicense = ($licRes && $licRes->num_rows > 0) ? (int)$licRes->fetch_assoc()['LicenseNumber'] + 1 : 1001;

            // Insert Employee
            $stmtEmp = $conn->prepare("INSERT INTO employees (BranchID, NationalID, Role, FirstName, LastName, ContactPhone, Email, HireDate, DoctorUsername, PasswordHash) VALUES (?, ?, 'Doctor', ?, ?, ?, ?, ?, ?, ?)");
            $stmtEmp->bind_param("issssssss", $branchID, $nationalID, $firstName, $lastName, $phone, $email, $hireDate, $generatedUsername, $passwordHash);
            $stmtEmp->execute();
            $employeeID = $stmtEmp->insert_id;

            // Insert Doctor
            $stmtDoc = $conn->prepare("INSERT INTO doctors (EmployeeID, SpecialtyID, LicenseNumber, ConsultationFee) VALUES (?, ?, ?, ?)");
            $stmtDoc->bind_param("iisd", $employeeID, $specialtyID, $generatedLicense, $fee);
            $stmtDoc->execute();

            $conn->commit();
            $successMsg = "Success";
            $showForm = false;
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['title'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0b141e;
            --card-bg: #1c2a39;
            --accent-blue: #70d1f4;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: #253447;
            --success-green: #4ade80;
        }

        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-dark); color: var(--text-main); margin: 0; padding: 20px; }
        
        /* Navbar Styling */
        .navbar { background: var(--card-bg); border-radius: 20px; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .brand { display: flex; align-items: center; gap: 15px; }
        .brand i { background: var(--accent-blue); color: #0b141e; padding: 10px; border-radius: 10px; font-size: 20px; }
        .brand h2 { margin: 0; font-size: 20px; }
        .nav-controls { display: flex; gap: 15px; }
        .btn-nav { background: #334155; color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px; font-weight: 600; }
        
        /* Container */
        .glass-card { background: var(--card-bg); border-radius: 30px; padding: 40px; max-width: 850px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); }
        
        h1 { font-size: 28px; margin-bottom: 30px; border-left: 5px solid var(--accent-blue); padding-left: 15px; }
        html[dir="rtl"] h1 { border-left: 0; border-right: 5px solid var(--accent-blue); padding-left: 0; padding-right: 15px; }

        /* Form Grid */
        form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }
        
        .form-group { text-align: left; }
        html[dir="rtl"] .form-group { text-align: right; }
        
        label { display: block; font-size: 12px; color: var(--accent-blue); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; font-weight: 600; }
        input, select { width: 100%; background: var(--input-bg); border: 1px solid #334155; border-radius: 12px; padding: 12px 15px; color: #fff; font-family: inherit; transition: 0.3s; box-sizing: border-box;}
        input:focus, select:focus { border-color: var(--accent-blue); outline: none; box-shadow: 0 0 0 4px rgba(112, 209, 244, 0.1); }

        .btn-submit { grid-column: span 2; background: var(--accent-blue); color: #0b141e; border: none; padding: 15px; border-radius: 15px; font-weight: 600; font-size: 16px; cursor: pointer; margin-top: 10px; transition: 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(112, 209, 244, 0.3); }

        /* Results Box */
        .result-box { text-align: center; }
        .creds-card { background: rgba(112, 209, 244, 0.1); border: 1px dashed var(--accent-blue); padding: 25px; border-radius: 20px; margin: 20px 0; display: inline-block; min-width: 300px; }
        .creds-row { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 5px; }
        .creds-val { color: var(--accent-blue); font-weight: 600; }
        
        .copy-btn { background: #334155; color: #fff; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; margin-top: 10px; font-size: 12px; }
        .btn-add-more { display: inline-block; text-decoration: none; color: var(--accent-blue); font-weight: 600; margin-top: 20px; }

        .error-msg { background: rgba(239, 68, 68, 0.1); color: #f87171; padding: 15px; border-radius: 12px; border: 1px solid #f87171; margin-bottom: 20px; grid-column: span 2; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-user-doctor"></i>
            <div>
                <h2>Cairo Hospitals</h2>
                <p style="margin:0; font-size:12px; color:var(--text-muted)">Staff Registration</p>
            </div>
        </div>
        <div class="nav-controls">
            <a href="admin_dashboard.php" class="btn-nav"><i class="fa-solid fa-chart-line"></i> <?= $t['dashboard'] ?></a>
            <a href="logout.php" class="btn-nav" style="background:var(--accent-blue); color:#000;"><i class="fa-solid fa-power-off"></i> <?= $t['logout'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <?php if ($showForm): ?>
            <h1><?= $t['title'] ?></h1>
            <form method="POST">
                <?php if ($errorMsg): ?> <div class="error-msg"><?= $errorMsg ?></div> <?php endif; ?>

                <div class="form-group">
                    <label><?= $t['first_name'] ?></label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label><?= $t['last_name'] ?></label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label><?= $t['nid'] ?></label>
                    <input type="text" name="national_id" maxlength="14" placeholder="14 Digits" required>
                </div>
                <div class="form-group">
                    <label><?= $t['phone'] ?></label>
                    <input type="text" name="phone">
                </div>
                <div class="form-group">
                    <label><?= $t['email'] ?></label>
                    <input type="email" name="email" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label><?= $t['hire_date'] ?></label>
                    <input type="date" name="hire_date" value="<?= date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label><?= $t['branch'] ?></label>
                    <select name="branch_id" required>
                        <option value=""><?= ($lang=='ar'?'-- اختر --':'-- Select --') ?></option>
                        <?php while ($b = $branches->fetch_assoc()): ?>
                            <option value="<?= $b['BranchID'] ?>"><?= $b['Name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['specialty'] ?></label>
                    <select name="specialty_id" required>
                        <option value=""><?= ($lang=='ar'?'-- اختر --':'-- Select --') ?></option>
                        <?php while ($s = $specialties->fetch_assoc()): ?>
                            <option value="<?= $s['SpecialtyID'] ?>"><?= $s['Name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label><?= $t['fee'] ?></label>
                    <input type="number" step="0.01" name="fee" required>
                </div>

                <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> <?= $t['submit'] ?></button>
            </form>

        <?php else: ?>
            <div class="result-box">
                <i class="fa-solid fa-circle-check" style="color:var(--success-green); font-size: 60px;"></i>
                <h1 style="border:0; text-align:center;"><?= $t['success_title'] ?></h1>
                
                <div class="creds-card">
                    <div class="creds-row"><span><?= $t['user'] ?>:</span> <span class="creds-val"><?= $generatedUsername ?></span></div>
                    <div class="creds-row"><span><?= $t['pass'] ?>:</span> <span class="creds-val" id="raw-pw"><?= $generatedPassword ?></span></div>
                    <div class="creds-row"><span><?= $t['lic'] ?>:</span> <span class="creds-val"><?= $generatedLicense ?></span></div>
                    <button class="copy-btn" onclick="copyPassword()"><i class="fa-solid fa-copy"></i> <?= $t['copy'] ?></button>
                </div>
                <br>
                <a href="add_doctor.php" class="btn-add-more"><?= $t['add_more'] ?> →</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function copyPassword() {
        const pw = document.getElementById('raw-pw').innerText;
        navigator.clipboard.writeText(pw).then(() => alert('<?= ($lang=='ar'?'تم النسخ!':'Copied!') ?>'));
    }
    </script>
</body>
</html>