<?php
session_start();

/* =========================
   Access Control
========================= */
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lang/init_lang.php';

$lang = $_SESSION['lang'] ?? 'en';
$dir  = $T['dir'] ?? (($lang === 'ar') ? 'rtl' : 'ltr');

$doctorID  = (int)($_SESSION['user_id'] ?? 0);
$patientID = (int)($_GET['patient_id'] ?? 0);

if ($patientID <= 0) {
    header("Location: doctor_patients.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$successMsg = '';
$errorMsg   = '';
$records    = [];
$patient    = null;

/* =========================
   Helpers
========================= */
function lang_link_reports($langCode, $patientID) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?patient_id=' . (int)$patientID . '&lang=' . $langCode;
}

function get_table_columns(mysqli $conn, string $tableName): array {
    $cols = [];
    $result = $conn->query("SHOW COLUMNS FROM `$tableName`");
    while ($row = $result->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function first_existing_column(array $available, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            return $candidate;
        }
    }
    return null;
}

/* =========================
   Load Patient Info
========================= */
try {
    $stmt = $conn->prepare("
        SELECT PatientID, FirstName, LastName, NationalID, DOB, Gender, BloodType, ContactPhone, Email, Address
        FROM patients
        WHERE PatientID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    $errorMsg = ($lang === 'ar')
        ? 'حدث خطأ أثناء تحميل بيانات المريض.'
        : 'An error occurred while loading patient data.';
}

if (!$patient) {
    $errorMsg = ($lang === 'ar')
        ? 'لم يتم العثور على بيانات المريض.'
        : 'Patient not found.';
}

$patientName = trim(($patient['FirstName'] ?? '') . ' ' . ($patient['LastName'] ?? ''));
if ($patientName === '') {
    $patientName = ($lang === 'ar') ? 'المريض' : 'Patient';
}

/* =========================
   Detect medical_records schema
========================= */
$recordTable = 'medical_records';
$recordCols  = [];

try {
    $recordCols = get_table_columns($conn, $recordTable);
} catch (Exception $e) {
    $errorMsg = ($lang === 'ar')
        ? 'تعذر قراءة جدول السجل الطبي.'
        : 'Could not read the medical records table.';
}

$recordIdCol   = first_existing_column($recordCols, ['RecordID', 'MedicalRecordID', 'ID', 'id']);
$patientCol    = first_existing_column($recordCols, ['PatientID']);
$doctorCol     = first_existing_column($recordCols, ['DoctorID', 'EmployeeID']);
$visitDateCol  = first_existing_column($recordCols, ['VisitDate', 'Visit_Date', 'RecordDate', 'CreatedAt']);
$diagnosisCol  = first_existing_column($recordCols, ['Diagnosis', 'Diagnoses']);
$treatmentCol  = first_existing_column($recordCols, ['Treatment', 'TreatmentPlan', 'Treatment_Plan']);
$notesCol      = first_existing_column($recordCols, ['Notes', 'AdditionalNotes', 'Note']);

/* =========================
   Handle New Report Submission
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitDate  = trim($_POST['visit_date'] ?? '');
    $diagnosis  = trim($_POST['diagnosis'] ?? '');
    $treatment  = trim($_POST['treatment'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($visitDate === '' || $diagnosis === '' || $treatment === '') {
        $errorMsg = ($lang === 'ar')
            ? 'يرجى إدخال التاريخ، التشخيص، وخطة العلاج.'
            : 'Please enter visit date, diagnosis, and treatment.';
    } elseif (!$patientCol || !$doctorCol || !$visitDateCol || !$diagnosisCol || !$treatmentCol) {
        $errorMsg = ($lang === 'ar')
            ? 'هيكل جدول medical_records غير متوافق مع الصفحة.'
            : 'The medical_records table structure is not compatible with this page.';
    } else {
        try {
            $insertCols = [$patientCol, $doctorCol, $visitDateCol, $diagnosisCol, $treatmentCol];
            $placeholders = ['?', '?', '?', '?', '?'];
            $types = 'iisss';
            $values = [$patientID, $doctorID, $visitDate, $diagnosis, $treatment];

            if ($notesCol) {
                $insertCols[] = $notesCol;
                $placeholders[] = '?';
                $types .= 's';
                $values[] = $notes;
            }

            $sql = "INSERT INTO `$recordTable` (" . implode(', ', array_map(fn($c) => "`$c`", $insertCols)) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();

            $successMsg = ($lang === 'ar')
                ? 'تم حفظ التقرير بنجاح.'
                : 'Report saved successfully.';

        } catch (Exception $e) {
            $errorMsg = ($lang === 'ar')
                ? 'حدث خطأ أثناء حفظ التقرير. تحققي من أسماء أعمدة الجدول.'
                : 'An error occurred while saving the report. Check your table column names.';
        }
    }
}

/* =========================
   Load Existing Records
========================= */
if ($patientCol && $visitDateCol && $diagnosisCol && $treatmentCol) {
    try {
        $selectList = [];

        if ($recordIdCol) {
            $selectList[] = "`$recordIdCol` AS RecordID";
        } else {
            $selectList[] = "0 AS RecordID";
        }

        $selectList[] = "`$visitDateCol` AS VisitDate";
        $selectList[] = "`$diagnosisCol` AS Diagnosis";
        $selectList[] = "`$treatmentCol` AS Treatment";

        if ($notesCol) {
            $selectList[] = "`$notesCol` AS Notes";
        } else {
            $selectList[] = "'' AS Notes";
        }

        if ($doctorCol) {
            $selectList[] = "`$doctorCol` AS DoctorID";
        } else {
            $selectList[] = "0 AS DoctorID";
        }

        $sql = "
            SELECT " . implode(', ', $selectList) . "
            FROM `$recordTable`
            WHERE `$patientCol` = ?
            ORDER BY `$visitDateCol` DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $patientID);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
        }

        $stmt->close();
    } catch (Exception $e) {
        if ($errorMsg === '') {
            $errorMsg = ($lang === 'ar')
                ? 'حدث خطأ أثناء تحميل السجل الطبي.'
                : 'An error occurred while loading the medical history.';
        }
    }
}

$text = [
    'app_name'        => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'      => ($lang === 'ar') ? 'تقارير المريض' : 'Patient Reports',
    'my_patients'     => ($lang === 'ar') ? 'مرضاي' : 'My Patients',
    'dashboard'       => ($lang === 'ar') ? 'لوحة الطبيب' : 'Dashboard',
    'logout'          => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'hero_badge'      => ($lang === 'ar') ? 'السجل والتقارير الطبية' : 'Medical Reports & Records',
    'hero_title'      => ($lang === 'ar') ? 'تقارير المريض: ' . $patientName : 'Patient Reports: ' . $patientName,
    'hero_desc'       => ($lang === 'ar')
        ? 'أضف تقارير جديدة وراجع السجل الطبي للمريض من واجهة احترافية.'
        : 'Add new reports and review the patient medical history in a professional interface.',
    'patient_info'    => ($lang === 'ar') ? 'بيانات المريض' : 'Patient Information',
    'new_report'      => ($lang === 'ar') ? 'إضافة تقرير جديد' : 'Add New Report',
    'medical_history' => ($lang === 'ar') ? 'السجل الطبي' : 'Medical History',
    'visit_date'      => ($lang === 'ar') ? 'تاريخ الزيارة' : 'Visit Date',
    'diagnosis'       => ($lang === 'ar') ? 'التشخيص' : 'Diagnosis',
    'treatment'       => ($lang === 'ar') ? 'خطة العلاج' : 'Treatment Plan',
    'notes'           => ($lang === 'ar') ? 'ملاحظات إضافية' : 'Additional Notes',
    'save_report'     => ($lang === 'ar') ? 'حفظ التقرير' : 'Save Report',
    'dob'             => ($lang === 'ar') ? 'تاريخ الميلاد' : 'Date of Birth',
    'gender'          => ($lang === 'ar') ? 'الجنس' : 'Gender',
    'blood'           => ($lang === 'ar') ? 'فصيلة الدم' : 'Blood Type',
    'phone'           => ($lang === 'ar') ? 'الهاتف' : 'Phone',
    'email'           => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'address'         => ($lang === 'ar') ? 'العنوان' : 'Address',
    'empty'           => ($lang === 'ar') ? 'لا توجد تقارير سابقة لهذا المريض.' : 'There are no previous reports for this patient yet.',
    'record_note'     => ($lang === 'ar')
        ? 'يمكنك تسجيل ملاحظاتك الطبية ومتابعة السجل السابق.'
        : 'You can record your medical notes and review the previous history.',
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
            grid-template-columns: 1fr;
            gap: 22px;
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
            flex-wrap: wrap;
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

        .form-input,
        .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.10);
            color: #fff;
            outline: none;
            font-size: 15px;
            font-family: inherit;
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(255,255,255,0.55);
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

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.10);
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            background: rgba(255,255,255,0.04);
        }

        th {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff;
            padding: 16px 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 800;
        }

        td {
            padding: 16px 14px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.03);
            font-size: 14px;
            color: rgba(255,255,255,0.95);
            vertical-align: top;
            line-height: 1.8;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .empty-box {
            border-radius: 24px;
            padding: 28px 20px;
            text-align: center;
            background: rgba(255,255,255,0.06);
            border: 1px dashed rgba(255,255,255,0.16);
            color: var(--text-soft);
            font-size: 14px;
            line-height: 1.9;
        }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.62);
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
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100% - 18px, 1280px);
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
                <i class="fa-solid fa-file-waveform"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_reports('en', $patientID)) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_reports('ar', $patientID)) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="doctor_patients.php?lang=<?= urlencode($lang) ?>" class="nav-btn">
                <i class="fa-solid fa-hospital-user"></i>
                <span><?= htmlspecialchars($text['my_patients']) ?></span>
            </a>

            <a href="doctor_dashboard.php?lang=<?= urlencode($lang) ?>" class="nav-btn">
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
            <i class="fa-solid fa-notes-medical"></i>
            <span><?= htmlspecialchars($text['hero_badge']) ?></span>
        </div>

        <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
        <p><?= htmlspecialchars($text['hero_desc']) ?></p>
    </section>

    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <div class="content-grid">

        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-user-injured"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['patient_info']) ?></h3>
                    <p><?= htmlspecialchars($patientName) ?></p>
                </div>
            </div>

            <?php if ($patient): ?>
                <div class="info-grid">
                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['dob']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['DOB'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['gender']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['Gender'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['blood']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['BloodType'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['phone']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['ContactPhone'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['email']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['Email'] ?? '') ?></div>
                    </div>

                    <div class="info-card">
                        <div class="info-label"><?= htmlspecialchars($text['address']) ?></div>
                        <div class="info-value"><?= htmlspecialchars($patient['Address'] ?? '') ?></div>
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
                    <h3><?= htmlspecialchars($text['new_report']) ?></h3>
                    <p><?= htmlspecialchars($text['record_note']) ?></p>
                </div>
            </div>

            <form method="post" class="form-grid">
                <div class="form-row">
                    <label><?= htmlspecialchars($text['visit_date']) ?></label>
                    <input type="date" name="visit_date" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-row">
                    <label><?= htmlspecialchars($text['diagnosis']) ?></label>
                    <textarea name="diagnosis" class="form-textarea" required></textarea>
                </div>

                <div class="form-row">
                    <label><?= htmlspecialchars($text['treatment']) ?></label>
                    <textarea name="treatment" class="form-textarea" required></textarea>
                </div>

                <div class="form-row">
                    <label><?= htmlspecialchars($text['notes']) ?></label>
                    <textarea name="notes" class="form-textarea"></textarea>
                </div>

                <button type="submit" class="save-btn">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?= htmlspecialchars($text['save_report']) ?></span>
                </button>
            </form>
        </section>

        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['medical_history']) ?></h3>
                </div>
            </div>

            <?php if (empty($records)): ?>
                <div class="empty-box">
                    <i class="fa-regular fa-folder-open" style="font-size:34px; margin-bottom:12px;"></i><br>
                    <?= htmlspecialchars($text['empty']) ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th><?= htmlspecialchars($text['visit_date']) ?></th>
                                <th><?= htmlspecialchars($text['diagnosis']) ?></th>
                                <th><?= htmlspecialchars($text['treatment']) ?></th>
                                <th><?= htmlspecialchars($text['notes']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($records as $rec): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($rec['VisitDate'] ?? '') ?></td>
                                    <td><?= nl2br(htmlspecialchars($rec['Diagnosis'] ?? '')) ?></td>
                                    <td><?= nl2br(htmlspecialchars($rec['Treatment'] ?? '')) ?></td>
                                    <td><?= nl2br(htmlspecialchars($rec['Notes'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> —
        <?= ($lang === 'ar') ? 'واجهة تقارير احترافية للطبيب' : 'Professional Doctor Reports Interface' ?>
    </div>

</div>

</body>
</html>