<?php
session_start();
require_once __DIR__ . '/db_connect.php';

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_profile($code) {
    return basename($_SERVER['PHP_SELF']) . '?lang=' . $code;
}

/* =========================
   Auth Check
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$login_id = (int)$_SESSION['user_id'];

$success = '';
$error   = '';
$user    = null;

/* =========================
   Helpers
========================= */
function getTableColumns(mysqli $conn, string $table): array {
    $cols = [];
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $result->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function findFirstExisting(array $availableColumns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $availableColumns, true)) {
            return $candidate;
        }
    }
    return null;
}

/* =========================
   Detect Actual Columns
========================= */
$loginColumns   = getTableColumns($conn, 'login');
$patientColumns = getTableColumns($conn, 'patients');

$loginIdCol        = findFirstExisting($loginColumns, ['id', 'ID', 'LoginID', 'UserID']);
$loginUsernameCol  = findFirstExisting($loginColumns, ['username', 'Username', 'user_name']);
$loginPasswordCol  = findFirstExisting($loginColumns, ['password', 'Password', 'pass']);
$loginNationalCol  = findFirstExisting($loginColumns, ['national_id', 'NationalID', 'nationalID']);

$patientNationalCol = findFirstExisting($patientColumns, ['NationalID', 'national_id', 'nationalID']);
$patientFirstCol    = findFirstExisting($patientColumns, ['FirstName', 'first_name', 'firstname']);
$patientLastCol     = findFirstExisting($patientColumns, ['LastName', 'last_name', 'lastname']);
$patientEmailCol    = findFirstExisting($patientColumns, ['Email', 'email']);
$patientPhoneCol    = findFirstExisting($patientColumns, ['ContactNumber', 'Phone', 'PhoneNumber', 'Mobile', 'Contact', 'phone']);
$patientAddressCol  = findFirstExisting($patientColumns, ['Address', 'address']);
$patientIdCol       = findFirstExisting($patientColumns, ['PatientID', 'id', 'ID']);

/* =========================
   Validate Minimum Needed Columns
========================= */
if (!$loginIdCol || !$loginUsernameCol) {
    die("Login table does not contain the required columns.");
}

if (!$loginNationalCol) {
    $error = ($lang === 'ar')
        ? 'جدول login لا يحتوي على العمود المسؤول عن الربط مع patients مثل national_id أو NationalID.'
        : 'The login table does not contain the linking column like national_id or NationalID.';
}

/* =========================
   Load User Data Dynamically
========================= */
if ($error === '') {
    $selectParts = [];
    $selectParts[] = "l.`$loginIdCol` AS login_id";
    $selectParts[] = "l.`$loginUsernameCol` AS username";
    $selectParts[] = "l.`$loginNationalCol` AS login_national_id";

    if ($patientFirstCol)   $selectParts[] = "p.`$patientFirstCol` AS first_name";
    if ($patientLastCol)    $selectParts[] = "p.`$patientLastCol` AS last_name";
    if ($patientEmailCol)   $selectParts[] = "p.`$patientEmailCol` AS email";
    if ($patientPhoneCol)   $selectParts[] = "p.`$patientPhoneCol` AS contact_number";
    if ($patientAddressCol) $selectParts[] = "p.`$patientAddressCol` AS address";
    if ($patientNationalCol)$selectParts[] = "p.`$patientNationalCol` AS patient_national_id";
    if ($patientIdCol)      $selectParts[] = "p.`$patientIdCol` AS patient_id";

    $sql = "
        SELECT " . implode(", ", $selectParts) . "
        FROM `login` l
        LEFT JOIN `patients` p
            ON p.`$patientNationalCol` = l.`$loginNationalCol`
        WHERE l.`$loginIdCol` = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = ($lang === 'ar')
            ? 'تعذر تحميل بيانات المستخدم.'
            : 'Could not load user data.';
    } else {
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

/* =========================
   Handle Update
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $username       = trim($_POST['username'] ?? '');
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $new_password   = trim($_POST['new_password'] ?? '');
    $confirm_pass   = trim($_POST['confirm_password'] ?? '');

    if ($username === '') {
        $error = ($lang === 'ar')
            ? 'اسم المستخدم مطلوب.'
            : 'Username is required.';
    } elseif (($patientFirstCol && $first_name === '') || ($patientLastCol && $last_name === '')) {
        $error = ($lang === 'ar')
            ? 'من فضلك املئي الحقول المطلوبة.'
            : 'Please fill in the required fields.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = ($lang === 'ar')
            ? 'صيغة البريد الإلكتروني غير صحيحة.'
            : 'Invalid email format.';
    } elseif ($new_password !== '' && strlen($new_password) < 6) {
        $error = ($lang === 'ar')
            ? 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.'
            : 'Password must be at least 6 characters.';
    } elseif ($new_password !== '' && $new_password !== $confirm_pass) {
        $error = ($lang === 'ar')
            ? 'كلمتا المرور غير متطابقتين.'
            : 'Passwords do not match.';
    } else {
        try {
            $conn->begin_transaction();

            /* Check username uniqueness */
            $checkStmt = $conn->prepare("
                SELECT `$loginIdCol`
                FROM `login`
                WHERE `$loginUsernameCol` = ?
                  AND `$loginIdCol` != ?
                LIMIT 1
            ");
            $checkStmt->bind_param("si", $username, $login_id);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();

            if ($checkRes->num_rows > 0) {
                throw new Exception(
                    ($lang === 'ar')
                    ? 'اسم المستخدم مستخدم بالفعل، اختاري اسمًا آخر.'
                    : 'Username already exists. Please choose another one.'
                );
            }
            $checkStmt->close();

            /* Update login table */
            if ($new_password !== '' && $loginPasswordCol) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmtLogin = $conn->prepare("
                    UPDATE `login`
                    SET `$loginUsernameCol` = ?, `$loginPasswordCol` = ?
                    WHERE `$loginIdCol` = ?
                ");
                $stmtLogin->bind_param("ssi", $username, $hashed_password, $login_id);
            } else {
                $stmtLogin = $conn->prepare("
                    UPDATE `login`
                    SET `$loginUsernameCol` = ?
                    WHERE `$loginIdCol` = ?
                ");
                $stmtLogin->bind_param("si", $username, $login_id);
            }
            $stmtLogin->execute();
            $stmtLogin->close();

            /* Update patients table only for existing columns */
            if ($patientNationalCol && !empty($user['login_national_id'])) {
                $setParts = [];
                $types = '';
                $values = [];

                if ($patientFirstCol) {
                    $setParts[] = "`$patientFirstCol` = ?";
                    $types .= 's';
                    $values[] = $first_name;
                }

                if ($patientLastCol) {
                    $setParts[] = "`$patientLastCol` = ?";
                    $types .= 's';
                    $values[] = $last_name;
                }

                if ($patientEmailCol) {
                    $setParts[] = "`$patientEmailCol` = ?";
                    $types .= 's';
                    $values[] = $email;
                }

                if ($patientPhoneCol) {
                    $setParts[] = "`$patientPhoneCol` = ?";
                    $types .= 's';
                    $values[] = $contact_number;
                }

                if ($patientAddressCol) {
                    $setParts[] = "`$patientAddressCol` = ?";
                    $types .= 's';
                    $values[] = $address;
                }

                if (!empty($setParts)) {
                    $sqlPatient = "
                        UPDATE `patients`
                        SET " . implode(", ", $setParts) . "
                        WHERE `$patientNationalCol` = ?
                    ";

                    $types .= 's';
                    $values[] = $user['login_national_id'];

                    $stmtPatient = $conn->prepare($sqlPatient);
                    $stmtPatient->bind_param($types, ...$values);
                    $stmtPatient->execute();
                    $stmtPatient->close();
                }
            }

            $conn->commit();
            $_SESSION['username'] = $username;

            $success = ($lang === 'ar')
                ? 'تم تحديث الملف الشخصي بنجاح.'
                : 'Profile updated successfully.';

            /* Reload updated data */
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $login_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$text = [
    'app_name'          => ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals',
    'page_title'        => ($lang === 'ar') ? 'تعديل الملف الشخصي' : 'Edit Profile',
    'hero_badge'        => ($lang === 'ar') ? 'إدارة الحساب الشخصي' : 'Manage Your Personal Account',
    'hero_title'        => ($lang === 'ar') ? 'تعديل الملف الشخصي' : 'Edit Profile',
    'hero_desc'         => ($lang === 'ar')
        ? 'يمكنك تحديث بياناتك الشخصية بسهولة من خلال هذه الصفحة بنفس الواجهة الاحترافية.'
        : 'You can easily update your personal information from this page with the same professional interface.',
    'dashboard'         => ($lang === 'ar') ? 'العودة إلى اللوحة الرئيسية' : 'Back to Dashboard',
    'profile_info'      => ($lang === 'ar') ? 'بيانات الحساب' : 'Account Information',
    'username'          => ($lang === 'ar') ? 'اسم المستخدم' : 'Username',
    'first_name'        => ($lang === 'ar') ? 'الاسم الأول' : 'First Name',
    'last_name'         => ($lang === 'ar') ? 'الاسم الأخير' : 'Last Name',
    'email'             => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'contact_number'    => ($lang === 'ar') ? 'رقم التواصل' : 'Contact Number',
    'address'           => ($lang === 'ar') ? 'العنوان' : 'Address',
    'national_id'       => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'new_password'      => ($lang === 'ar') ? 'كلمة مرور جديدة' : 'New Password',
    'confirm_password'  => ($lang === 'ar') ? 'تأكيد كلمة المرور' : 'Confirm Password',
    'password_note'     => ($lang === 'ar') ? 'اتركيها فارغة إذا كنتِ لا تريدين تغيير كلمة المرور' : 'Leave it empty if you do not want to change the password',
    'save_changes'      => ($lang === 'ar') ? 'حفظ التعديلات' : 'Save Changes',
    'success'           => ($lang === 'ar') ? 'تم بنجاح' : 'Success',
    'error'             => ($lang === 'ar') ? 'خطأ' : 'Error',
    'required_note'     => ($lang === 'ar') ? 'الحقول المطلوبة' : 'Required fields',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($text['page_title']) ?> - <?= htmlspecialchars($text['app_name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * { box-sizing: border-box; }

        :root {
            --bg-1: #07121d;
            --bg-2: #0c1c2d;
            --bg-3: #12314e;
            --white: #ffffff;
            --text-soft: rgba(255,255,255,0.82);
            --card: rgba(255,255,255,0.10);
            --stroke: rgba(255,255,255,0.14);
            --primary: #4cc9f0;
            --primary-2: #4895ef;
            --success: #10b981;
            --danger: #ef4444;
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--white);
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(76,201,240,0.18), transparent 24%),
                radial-gradient(circle at top right, rgba(123,47,247,0.14), transparent 24%),
                radial-gradient(circle at bottom center, rgba(72,149,239,0.12), transparent 28%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2) 45%, var(--bg-3));
        }

        a { text-decoration: none; }

        .page-shell {
            width: min(1280px, calc(100% - 32px));
            margin: 18px auto 32px;
        }

        .glass {
            background: var(--card);
            border: 1px solid var(--stroke);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--shadow);
        }

        .topbar {
            position: sticky;
            top: 14px;
            z-index: 999;
            border-radius: 24px;
            padding: 16px 22px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 15px 30px rgba(72,149,239,0.35);
        }

        .brand-text h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
        }

        .brand-text p {
            margin: 5px 0 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .lang-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .lang-toggle a {
            color: var(--white);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            transition: 0.25s ease;
        }

        .lang-toggle a.active,
        .lang-toggle a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 10px 22px rgba(76,201,240,0.25);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #fff;
            padding: 12px 16px;
            border-radius: 14px;
            font-weight: 700;
            background: linear-gradient(135deg, rgba(76,201,240,0.22), rgba(72,149,239,0.18));
            border: 1px solid rgba(255,255,255,0.12);
            transition: .25s ease;
        }

        .back-btn:hover { transform: translateY(-2px); }

        .hero {
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: auto -90px -90px auto;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(76,201,240,0.22), transparent 60%);
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(76,201,240,0.14);
            border: 1px solid rgba(76,201,240,0.24);
            color: #dff8ff;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .hero h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.15;
            font-weight: 800;
        }

        .hero p {
            margin: 0;
            color: var(--text-soft);
            font-size: 16px;
            line-height: 1.8;
            max-width: 850px;
        }

        .panel {
            border-radius: 28px;
            padding: 24px;
        }

        .panel-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .panel-title .icon {
            width: 46px;
            height: 46px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.20));
            color: #dff8ff;
            font-size: 18px;
        }

        .panel-title h3 {
            margin: 0;
            font-size: 22px;
        }

        .panel-title p {
            margin: 4px 0 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .alert {
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-weight: 600;
            line-height: 1.7;
        }

        .alert.success {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.30);
            color: #d1fae5;
        }

        .alert.error {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.30);
            color: #fee2e2;
        }

        .profile-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field-group.full { grid-column: 1 / -1; }

        .field-group label {
            font-size: 14px;
            font-weight: 700;
            color: #f5fbff;
        }

        .required-mark {
            color: #ffb4b4;
            margin-inline-start: 4px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.62);
            font-size: 15px;
            pointer-events: none;
        }

        [dir="ltr"] .input-wrap i { left: 15px; }
        [dir="rtl"] .input-wrap i { right: 15px; }

        .form-input,
        .form-textarea {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.08);
            color: #fff;
            outline: none;
            border-radius: 16px;
            font-size: 15px;
            transition: .25s ease;
        }

        .form-input {
            height: 52px;
            padding: 0 16px;
        }

        [dir="ltr"] .input-wrap .form-input.with-icon { padding-left: 44px; }
        [dir="rtl"] .input-wrap .form-input.with-icon { padding-right: 44px; }

        .form-textarea {
            min-height: 120px;
            padding: 14px 16px;
            resize: vertical;
        }

        .form-input:focus,
        .form-textarea:focus {
            border-color: rgba(76,201,240,0.45);
            box-shadow: 0 0 0 4px rgba(76,201,240,0.10);
        }

        .form-input[readonly] {
            opacity: .78;
            cursor: not-allowed;
        }

        .helper-text {
            color: var(--text-soft);
            font-size: 12.5px;
            line-height: 1.6;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .save-btn {
            border: none;
            outline: none;
            padding: 14px 22px;
            border-radius: 16px;
            color: #07141f;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(135deg, #9aefff, #4cc9f0);
            box-shadow: 0 14px 28px rgba(76,201,240,0.22);
            transition: .25s ease;
        }

        .save-btn:hover { transform: translateY(-2px); }

        .secondary-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 20px;
            border-radius: 16px;
            color: #fff;
            font-weight: 700;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            transition: .25s ease;
        }

        .secondary-btn:hover { transform: translateY(-2px); }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.60);
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 900px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }
            .topbar-right {
                justify-content: space-between;
            }
            .profile-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100% - 18px, 1280px);
                margin: 12px auto 24px;
            }
            .topbar, .hero, .panel {
                padding: 18px;
            }
            .brand-text h1 { font-size: 17px; }
            .hero h2 { font-size: 27px; }
            .panel-title h3 { font-size: 18px; }
            .save-btn, .secondary-btn, .back-btn {
                width: 100%;
                justify-content: center;
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">
    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-user-pen"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_profile('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_profile('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="dashboard.php" class="back-btn">
                <i class="fa-solid fa-house"></i>
                <span><?= htmlspecialchars($text['dashboard']) ?></span>
            </a>
        </div>
    </header>

    <section class="hero glass">
        <div class="hero-badge">
            <i class="fa-solid fa-shield-heart"></i>
            <span><?= htmlspecialchars($text['hero_badge']) ?></span>
        </div>
        <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
        <p><?= htmlspecialchars($text['hero_desc']) ?></p>
    </section>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="panel-title">
                <div class="icon">
                    <i class="fa-solid fa-id-card"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['profile_info']) ?></h3>
                    <p><?= htmlspecialchars($text['required_note']) ?></p>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="POST" class="profile-form" autocomplete="off">

                <div class="field-group">
                    <label for="username">
                        <?= htmlspecialchars($text['username']) ?>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="username" name="username" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="field-group">
                    <label for="national_id"><?= htmlspecialchars($text['national_id']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-id-card-clip"></i>
                        <input type="text" id="national_id" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['login_national_id'] ?? '') ?>" readonly>
                    </div>
                </div>

                <?php if ($patientFirstCol): ?>
                <div class="field-group">
                    <label for="first_name">
                        <?= htmlspecialchars($text['first_name']) ?>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-signature"></i>
                        <input type="text" id="first_name" name="first_name" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($patientLastCol): ?>
                <div class="field-group">
                    <label for="last_name">
                        <?= htmlspecialchars($text['last_name']) ?>
                        <span class="required-mark">*</span>
                    </label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-signature"></i>
                        <input type="text" id="last_name" name="last_name" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($patientEmailCol): ?>
                <div class="field-group">
                    <label for="email"><?= htmlspecialchars($text['email']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($patientPhoneCol): ?>
                <div class="field-group">
                    <label for="contact_number"><?= htmlspecialchars($text['contact_number']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone"></i>
                        <input type="text" id="contact_number" name="contact_number" class="form-input with-icon"
                               value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($patientAddressCol): ?>
                <div class="field-group full">
                    <label for="address"><?= htmlspecialchars($text['address']) ?></label>
                    <textarea id="address" name="address" class="form-textarea"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>

                <div class="field-group">
                    <label for="new_password"><?= htmlspecialchars($text['new_password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" class="form-input with-icon">
                    </div>
                    <div class="helper-text"><?= htmlspecialchars($text['password_note']) ?></div>
                </div>

                <div class="field-group">
                    <label for="confirm_password"><?= htmlspecialchars($text['confirm_password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-key"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input with-icon">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="save-btn">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <?= htmlspecialchars($text['save_changes']) ?>
                    </button>

                    <a href="dashboard.php" class="secondary-btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        <?= htmlspecialchars($text['dashboard']) ?>
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?>
    </div>
</div>

</body>
</html>