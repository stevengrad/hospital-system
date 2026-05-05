<?php
session_start();
require_once __DIR__ . '/db_connect.php';

/* =========================
   Access Control
========================= */
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: index.php");
    exit();
}

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}

require_once __DIR__ . '/lang/init_lang.php';

$lang = $_SESSION['lang'] ?? 'en';
$dir  = $T['dir'] ?? (($lang === 'ar') ? 'rtl' : 'ltr');

/* =========================
   DB Settings
========================= */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$employeeID = (int)($_SESSION['user_id'] ?? 0);
$sessionUsername = $_SESSION['username'] ?? 'doctor';
$successMsg = '';
$errorMsg   = '';

/* =========================
   Update Profile
   - employees: phone + email
   - login: password
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $newPwPlain = trim($_POST['new_password'] ?? '');

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = ($lang === 'ar')
            ? "صيغة البريد الإلكتروني غير صحيحة."
            : "Invalid email format.";
    } else {
        try {
            // update doctor contact info in employees
            $stmt = $conn->prepare("
                UPDATE employees
                SET ContactPhone = ?, Email = ?
                WHERE EmployeeID = ?
            ");
            $stmt->bind_param("ssi", $phone, $email, $employeeID);
            $stmt->execute();
            $stmt->close();

            // optional password update in login table
            if ($newPwPlain !== '') {
                if (strlen($newPwPlain) < 8) {
                    $errorMsg = ($lang === 'ar')
                        ? "كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل."
                        : "New password must be at least 8 characters.";
                } else {
                    $hash = password_hash($newPwPlain, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("
                        UPDATE login
                        SET password = ?
                        WHERE id = ? AND role = 'doctor'
                    ");
                    $stmt->bind_param("si", $hash, $employeeID);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            if ($errorMsg === '') {
                $successMsg = ($lang === 'ar')
                    ? "تم تحديث بيانات الملف الشخصي بنجاح."
                    : "Profile updated successfully.";
            }

        } catch (Exception $e) {
            $errorMsg = ($lang === 'ar')
                ? "حدث خطأ أثناء حفظ البيانات."
                : "An error occurred while saving the data.";
        }
    }
}

/* =========================
   Fetch Doctor Data
========================= */
$doctor = null;

try {
    $stmt = $conn->prepare("
        SELECT
            e.EmployeeID,
            e.FirstName,
            e.LastName,
            e.NationalID,
            e.ContactPhone,
            e.Email,
            e.HireDate,
            b.Name AS BranchName,
            d.LicenseNumber,
            d.ConsultationFee,
            s.Name AS SpecialtyName
        FROM employees e
        LEFT JOIN branches b    ON e.BranchID = b.BranchID
        LEFT JOIN doctors d     ON e.EmployeeID = d.EmployeeID
        LEFT JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
        WHERE e.EmployeeID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $employeeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $doctor = null;
}

if (!$doctor) {
    $errorMsg = ($lang === 'ar')
        ? "لم يتم العثور على بيانات الطبيب. يرجى التواصل مع الإدارة."
        : "Doctor record not found. Please contact administration.";
}

$fullName = trim(($doctor['FirstName'] ?? '') . ' ' . ($doctor['LastName'] ?? ''));
if ($fullName === '') {
    $fullName = $sessionUsername;
}

$text = [
    'app_name'          => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'        => ($lang === 'ar') ? 'الملف الشخصي للطبيب' : 'Doctor Profile',
    'hello'             => ($lang === 'ar') ? 'مرحبًا، د. ' . $fullName : 'Hello, Dr. ' . $fullName,
    'hero_badge'        => ($lang === 'ar') ? 'الملف المهني' : 'Professional Profile',
    'hero_desc'         => ($lang === 'ar')
        ? 'راجع بياناتك المهنية وبيانات التواصل وقم بتحديثها بسهولة.'
        : 'Review your professional information and update your contact details with ease.',
    'dashboard'         => ($lang === 'ar') ? 'لوحة الطبيب' : 'Doctor Dashboard',
    'logout'            => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'profile_info'      => ($lang === 'ar') ? 'بيانات الطبيب' : 'Doctor Information',
    'profile_info_desc' => ($lang === 'ar')
        ? 'هذه البيانات الأساسية المرتبطة بحسابك في النظام.'
        : 'These are the core details linked to your account in the system.',
    'contact_title'     => ($lang === 'ar') ? 'بيانات الاتصال وكلمة المرور' : 'Contact Info & Password',
    'contact_desc'      => ($lang === 'ar')
        ? 'يمكنك تعديل رقم الهاتف والبريد الإلكتروني وتحديث كلمة المرور.'
        : 'You can update your phone, email, and password.',
    'name'              => ($lang === 'ar') ? 'الاسم' : 'Name',
    'username'          => ($lang === 'ar') ? 'اسم المستخدم' : 'Username',
    'national_id'       => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'branch'            => ($lang === 'ar') ? 'الفرع' : 'Branch',
    'specialty'         => ($lang === 'ar') ? 'التخصص' : 'Specialty',
    'license'           => ($lang === 'ar') ? 'رقم الرخصة' : 'License Number',
    'fee'               => ($lang === 'ar') ? 'أتعاب الكشف' : 'Consultation Fee',
    'hire_date'         => ($lang === 'ar') ? 'تاريخ التعيين' : 'Hire Date',
    'phone'             => ($lang === 'ar') ? 'رقم الهاتف' : 'Phone',
    'email'             => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'new_password'      => ($lang === 'ar') ? 'كلمة مرور جديدة (اختياري)' : 'New Password (optional)',
    'password_hint'     => ($lang === 'ar')
        ? 'اترك الحقل فارغًا إذا كنت لا تريد تغيير كلمة المرور.'
        : 'Leave this field empty if you do not want to change your password.',
    'save'              => ($lang === 'ar') ? 'حفظ التعديلات' : 'Save Changes',
    'not_set'           => ($lang === 'ar') ? 'غير محدد' : 'Not set',
    'not_provided'      => ($lang === 'ar') ? 'غير متوفر' : 'Not provided',
];

function lang_link_doctor_profile($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($text['page_title']) ?> - <?= htmlspecialchars($text['app_name']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

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
            --danger: #ef4444;
            --success: #22c55e;
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

        a {
            text-decoration: none;
        }

        .page-shell {
            width: min(1240px, calc(100% - 32px));
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
            min-width: 0;
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
            flex-shrink: 0;
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
        }

        .nav-btn,
        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            transition: 0.25s ease;
        }

        .nav-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 25px rgba(239,68,68,0.30);
        }

        .nav-btn:hover,
        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .hero {
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 22px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 25%),
                linear-gradient(135deg, #6d28d9, #2563eb 62%, #38bdf8);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.14), transparent 60%);
            bottom: -90px;
            right: -70px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.18);
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
            font-size: 16px;
            line-height: 1.8;
            color: rgba(255,255,255,0.92);
            max-width: 850px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 22px;
            align-items: start;
        }

        .panel {
            border-radius: 30px;
            padding: 24px;
        }

        .panel-title-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .panel-title-row .icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.20));
            color: #dff8ff;
            font-size: 18px;
        }

        .panel-title-row h3 {
            margin: 0;
            font-size: 23px;
        }

        .panel-title-row p {
            margin: 4px 0 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .info-card {
            border-radius: 22px;
            padding: 18px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .info-label {
            color: var(--text-soft);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 17px;
            font-weight: 800;
            line-height: 1.6;
            word-break: break-word;
        }

        .alert {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 700;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(34,197,94,0.16);
            border: 1px solid rgba(34,197,94,0.30);
            color: #d8ffe3;
        }

        .alert-error {
            background: rgba(239,68,68,0.14);
            border: 1px solid rgba(239,68,68,0.28);
            color: #ffdede;
        }

        .form-grid {
            display: grid;
            gap: 16px;
        }

        .form-row label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-soft);
            font-size: 13px;
            font-weight: 600;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.66);
            font-size: 15px;
        }

        [dir="ltr"] .input-wrap i {
            left: 15px;
        }

        [dir="rtl"] .input-wrap i {
            right: 15px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.10);
            color: #fff;
            outline: none;
            font-size: 15px;
        }

        [dir="ltr"] .form-input {
            padding-left: 44px;
        }

        [dir="rtl"] .form-input {
            padding-right: 44px;
        }

        .form-input::placeholder {
            color: rgba(255,255,255,0.55);
        }

        .hint {
            margin-top: 8px;
            color: rgba(255,255,255,0.62);
            font-size: 12px;
            line-height: 1.7;
        }

        .save-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            border: none;
            border-radius: 16px;
            padding: 14px 18px;
            margin-top: 6px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 800;
            color: #07141f;
            background: linear-gradient(135deg, #8effd8, #2dd4bf);
            transition: 0.25s ease;
        }

        .save-btn:hover {
            transform: translateY(-2px);
        }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.62);
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 1100px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .topbar-right {
                justify-content: space-between;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100% - 18px, 1240px);
                margin: 12px auto 24px;
            }

            .topbar,
            .hero,
            .panel {
                padding: 18px;
            }

            .hero h2 {
                font-size: 26px;
            }

            .panel-title-row h3 {
                font-size: 19px;
            }

            .nav-btn,
            .logout-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-id-card"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_doctor_profile('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_doctor_profile('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="doctor_dashboard.php" class="nav-btn">
                <i class="fa-solid fa-table-columns"></i>
                <span><?= htmlspecialchars($text['dashboard']) ?></span>
            </a>

            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?= htmlspecialchars($text['logout']) ?></span>
            </a>
        </div>
    </header>

    <section class="hero">
        <div class="hero-badge">
            <i class="fa-solid fa-user-doctor"></i>
            <span><?= htmlspecialchars($text['hero_badge']) ?></span>
        </div>

        <h2><?= htmlspecialchars($text['hello']) ?></h2>
        <p><?= htmlspecialchars($text['hero_desc']) ?></p>
    </section>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="content-grid">
        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['profile_info']) ?></h3>
                    <p><?= htmlspecialchars($text['profile_info_desc']) ?></p>
                </div>
            </div>
                        <?php if ($doctor): ?>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['name']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($fullName) ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['username']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($sessionUsername) ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['national_id']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($doctor['NationalID'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['branch']) ?></div>
                        <div class="info-value">
                            <?= htmlspecialchars($doctor['BranchName'] ?? $text['not_set']) ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['specialty']) ?></div>
                        <div class="info-value">
                            <?= htmlspecialchars($doctor['SpecialtyName'] ?? $text['not_set']) ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['license']) ?></div>
                        <div class="info-value">
                            <?= htmlspecialchars($doctor['LicenseNumber'] ?? $text['not_provided']) ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['fee']) ?></div>
                        <div class="info-value">
                            <?php
                            if (isset($doctor['ConsultationFee']) && $doctor['ConsultationFee'] !== null && $doctor['ConsultationFee'] !== '') {
                                echo htmlspecialchars(number_format((float)$doctor['ConsultationFee'], 2)) . ' EGP';
                            } else {
                                echo htmlspecialchars($text['not_set']);
                            }
                            ?>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['hire_date']) ?></div>
                        <div class="info-value">
                            <?= htmlspecialchars($doctor['HireDate'] ?? $text['not_set']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['contact_title']) ?></h3>
                    <p><?= htmlspecialchars($text['contact_desc']) ?></p>
                </div>
            </div>

            <form method="POST" class="form-grid">
                <div class="form-row">
                    <label><?= htmlspecialchars($text['phone']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone"></i>
                        <input
                            type="text"
                            name="phone"
                            class="form-input"
                            value="<?= htmlspecialchars($doctor['ContactPhone'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($text['phone']) ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <label><?= htmlspecialchars($text['email']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-envelope"></i>
                        <input
                            type="email"
                            name="email"
                            class="form-input"
                            value="<?= htmlspecialchars($doctor['Email'] ?? '') ?>"
                            placeholder="<?= htmlspecialchars($text['email']) ?>"
                        >
                    </div>
                </div>

                <div class="form-row">
                    <label><?= htmlspecialchars($text['new_password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            name="new_password"
                            class="form-input"
                            placeholder="<?= htmlspecialchars($text['new_password']) ?>"
                        >
                    </div>
                    <div class="hint"><?= htmlspecialchars($text['password_hint']) ?></div>
                </div>

                <button type="submit" name="update_profile" class="save-btn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?= htmlspecialchars($text['save']) ?></span>
                </button>
            </form>
        </section>
    </div>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> — <?= ($lang === 'ar') ? 'الملف المهني للطبيب' : 'Doctor Professional Profile' ?>
    </div>
</div>

</body>
</html>