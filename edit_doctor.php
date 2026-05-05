<?php
session_start();
require_once 'db_connect.php';

// --------- Access control (admin only) ----------
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

// --------- Language handling ----------
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'      => 'Edit Doctor | Cairo Hospitals',
        'heading'         => 'Modify Doctor Profile',
        'subheading'      => 'Update medical staff credentials and professional details.',
        'back'            => 'Manage Doctors',
        'save'            => 'Update Records',
        'first_name'      => 'First Name',
        'last_name'       => 'Last Name',
        'branch'          => 'Hospital Branch',
        'specialty'       => 'Medical Specialty',
        'phone'           => 'Contact Phone',
        'email'           => 'Email Address',
        'hire_date'       => 'Hire Date',
        'license'         => 'License Number',
        'fee'             => 'Consultation Fee (EGP)',
        'not_found'       => 'Doctor not found.',
        'success'         => '✅ Profile updated successfully!',
        'error_generic'   => '❌ Error updating records.',
        'error_required'  => '⚠️ Please ensure all required fields are valid.',
        'dashboard'       => 'Dashboard'
    ],
    'ar' => [
        'page_title'      => 'تعديل بيانات طبيب | مستشفيات القاهرة',
        'heading'         => 'تعديل ملف الطبيب',
        'subheading'      => 'تحديث بيانات الكادر الطبي والبيانات المهنية.',
        'back'            => 'إدارة الأطباء',
        'save'            => 'تحديث البيانات',
        'first_name'      => 'الاسم الأول',
        'last_name'       => 'اسم العائلة',
        'branch'          => 'فرع المستشفى',
        'specialty'       => 'التخصص الطبي',
        'phone'           => 'رقم الهاتف',
        'email'           => 'البريد الإلكتروني',
        'hire_date'       => 'تاريخ التعيين',
        'license'         => 'رقم الرخصة',
        'fee'             => 'أتعاب الكشف (جنيه)',
        'not_found'       => 'لم يتم العثور على هذا الطبيب.',
        'success'         => '✅ تم تحديث البيانات بنجاح!',
        'error_generic'   => '❌ حدث خطأ أثناء التحديث.',
        'error_required'  => '⚠️ يرجى التأكد من صحة جميع الحقول المطلوبة.',
        'dashboard'       => 'الرئيسية'
    ],
];
$t = $texts[$lang];

// helper for toggle link
function lang_link_edit_doc($code) {
    $self = basename($_SERVER['PHP_SELF']);
    $id   = isset($_GET['id']) ? ('&id=' . urlencode($_GET['id'])) : '';
    return $self . '?lang=' . $code . $id;
}

$employeeID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($employeeID <= 0) die("Missing doctor ID.");

$successMsg = ''; $errorMsg = '';

// Fetch branches & specialties
$branches = $conn->query("SELECT BranchID, Name FROM branches ORDER BY Name")->fetch_all(MYSQLI_ASSOC);
$specialties = $conn->query("SELECT SpecialtyID, Name FROM specialties ORDER BY Name")->fetch_all(MYSQLI_ASSOC);

// Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $branchID = (int)$_POST['branch_id'];
    $specialtyID = (int)$_POST['specialty_id'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $hireDate = $_POST['hire_date'];
    $licenseNo = trim($_POST['license_no']);
    $fee = (float)$_POST['fee'];

    if ($firstName === '' || $lastName === '' || $branchID <= 0 || $specialtyID <= 0 || $fee <= 0) {
        $errorMsg = $t['error_required'];
    } else {
        $conn->begin_transaction();
        try {
            $stmtEmp = $conn->prepare("UPDATE employees SET BranchID=?, FirstName=?, LastName=?, ContactPhone=?, Email=?, HireDate=? WHERE EmployeeID=?");
            $stmtEmp->bind_param("isssssi", $branchID, $firstName, $lastName, $phone, $email, $hireDate, $employeeID);
            $stmtEmp->execute();

            $stmtDoc = $conn->prepare("UPDATE doctors SET SpecialtyID=?, LicenseNumber=?, ConsultationFee=? WHERE EmployeeID=?");
            $stmtDoc->bind_param("isdi", $specialtyID, $licenseNo, $fee, $employeeID);
            $stmtDoc->execute();

            $conn->commit();
            $successMsg = $t['success'];
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = $t['error_generic'];
        }
    }
}

// Fetch doctor details
$stmt = $conn->prepare("SELECT e.*, d.SpecialtyID, d.LicenseNumber, d.ConsultationFee FROM employees e INNER JOIN doctors d ON e.EmployeeID = d.EmployeeID WHERE e.EmployeeID = ? LIMIT 1");
$stmt->bind_param("i", $employeeID);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
if (!$doctor) die($t['not_found']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['page_title'] ?></title>
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

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            margin: 0; padding: 20px;
        }

        .navbar {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .brand { display: flex; align-items: center; gap: 15px; }
        .brand i { background: var(--accent-blue); color: #0b141e; padding: 10px; border-radius: 10px; font-size: 20px; }
        
        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .btn-nav { background: #334155; color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        .glass-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
        }

        h1 { font-size: 26px; margin: 0; }
        .subheading { color: var(--text-muted); font-size: 14px; margin-bottom: 30px; }

        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }

        .form-group { text-align: left; }
        html[dir="rtl"] .form-group { text-align: right; }

        label {
            display: block; font-size: 11px; color: var(--accent-blue);
            text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 8px; font-weight: 600;
        }

        input, select {
            width: 100%; background: var(--input-bg);
            border: 1px solid #334155; border-radius: 12px;
            padding: 14px; color: #fff; box-sizing: border-box;
            transition: 0.3s;
        }

        input:focus, select:focus { border-color: var(--accent-blue); outline: none; }

        .btn-submit {
            width: 100%; background: var(--accent-blue);
            color: #0b141e; border: none; padding: 16px;
            border-radius: 15px; font-weight: 600; font-size: 16px;
            cursor: pointer; transition: 0.3s; margin-top: 20px;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(112, 209, 244, 0.2); }

        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; }
        .alert-success { background: rgba(74, 222, 128, 0.1); color: var(--success-green); border: 1px solid var(--success-green); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid #f87171; }

        .lang-switch { display: flex; gap: 5px; background: #334155; padding: 4px; border-radius: 50px; }
        .lang-switch a { text-decoration: none; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 50px; }
        .lang-switch a.active { background: var(--accent-blue); color: #0b141e; font-weight: bold; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-user-doctor"></i>
            <div>
                <h2 style="margin:0; font-size:18px;">Cairo Hospitals</h2>
                <p style="margin:0; font-size:11px; color:var(--text-muted)">Employee ID: #<?= $employeeID ?></p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="<?= lang_link_edit_doc('en') ?>" class="<?= $lang=='en'?'active':'' ?>">EN</a>
                <a href="<?= lang_link_edit_doc('ar') ?>" class="<?= $lang=='ar'?'active':'' ?>">AR</a>
            </div>
            <a href="admin_dashboard.php" class="btn-nav"><i class="fa-solid fa-chart-line"></i> <?= $t['dashboard'] ?></a>
            <a href="manage_doctors.php" class="btn-nav" style="background:var(--accent-blue); color:#000;"><i class="fa-solid fa-arrow-left"></i> <?= $t['back'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <h1><?= $t['heading'] ?></h1>
        <p class="subheading"><?= $t['subheading'] ?></p>

        <?php if ($successMsg): ?> <div class="alert alert-success"><?= $successMsg ?></div> <?php endif; ?>
        <?php if ($errorMsg): ?> <div class="alert alert-error"><?= $errorMsg ?></div> <?php endif; ?>

        <form method="post">
            <div class="grid">
                <div class="form-group">
                    <label><?= $t['first_name'] ?></label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($doctor['FirstName']) ?>" required>
                </div>
                <div class="form-group">
                    <label><?= $t['last_name'] ?></label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($doctor['LastName']) ?>" required>
                </div>
                <div class="form-group">
                    <label><?= $t['branch'] ?></label>
                    <select name="branch_id" required>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['BranchID'] ?>" <?= ($doctor['BranchID'] == $b['BranchID']) ? 'selected' : '' ?>><?= $b['Name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['specialty'] ?></label>
                    <select name="specialty_id" required>
                        <?php foreach ($specialties as $s): ?>
                            <option value="<?= $s['SpecialtyID'] ?>" <?= ($doctor['SpecialtyID'] == $s['SpecialtyID']) ? 'selected' : '' ?>><?= $s['Name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['phone'] ?></label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($doctor['ContactPhone']) ?>">
                </div>
                <div class="form-group">
                    <label><?= $t['email'] ?></label>
                    <input type="email" name="email" value="<?= htmlspecialchars($doctor['Email']) ?>">
                </div>
                <div class="form-group">
                    <label><?= $t['hire_date'] ?></label>
                    <input type="date" name="hire_date" value="<?= $doctor['HireDate'] ?>">
                </div>
                <div class="form-group">
                    <label><?= $t['license'] ?></label>
                    <input type="text" name="license_no" value="<?= htmlspecialchars($doctor['LicenseNumber']) ?>">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label><?= $t['fee'] ?></label>
                    <input type="number" step="0.01" name="fee" value="<?= $doctor['ConsultationFee'] ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> <?= $t['save'] ?>
            </button>
        </form>
    </div>
</body>
</html>