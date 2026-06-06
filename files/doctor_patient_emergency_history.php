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

/* =========================
   Current Doctor
========================= */
$doctorLoginId = (int)($_SESSION['user_id'] ?? 0);
$doctorName    = $_SESSION['doctor_name'] ?? $_SESSION['username'] ?? 'Doctor';

/* =========================
   Helpers
========================= */
function lang_link_history($code, $patientId, $caseId = 0) {
    $self = basename($_SERVER['PHP_SELF']);
    $url = $self . '?lang=' . $code . '&patient_id=' . urlencode((string)$patientId);
    if ($caseId > 0) {
        $url .= '&case_id=' . urlencode((string)$caseId);
    }
    return $url;
}

/* =========================
   Input Validation
========================= */
$patientId = (int)($_GET['patient_id'] ?? 0);
$caseId    = (int)($_GET['case_id'] ?? 0);

if ($patientId <= 0) {
    header("Location: doctor_emergency.php");
    exit();
}

$error = '';
$patient = null;
$patientLogin = null;
$historyRows = [];
$emergencyCase = null;
$patientName = '';

/* =========================
   Get patient by PatientID
========================= */
$stmt = $conn->prepare("
    SELECT PatientID, NationalID, FirstName, LastName, DOB, Gender, BloodType, ContactPhone, Email, Address
    FROM patients
    WHERE PatientID = ?
    LIMIT 1
");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$patientResult = $stmt->get_result();
$patient = $patientResult->fetch_assoc();
$stmt->close();

if (!$patient) {
    $error = ($lang === 'ar')
        ? 'لم يتم العثور على بيانات المريض.'
        : 'Patient data was not found.';
} else {
    $patientName = trim(($patient['FirstName'] ?? '') . ' ' . ($patient['LastName'] ?? ''));
}

/* =========================
   Get patient login by national_id
========================= */
if (empty($error)) {
    $stmt = $conn->prepare("
        SELECT id, username, national_id
        FROM login
        WHERE national_id = ? AND role = 'patient'
        LIMIT 1
    ");
    $stmt->bind_param("s", $patient['NationalID']);
    $stmt->execute();
    $loginResult = $stmt->get_result();
    $patientLogin = $loginResult->fetch_assoc();
    $stmt->close();

    if (!$patientLogin) {
        $error = ($lang === 'ar')
            ? 'تعذر العثور على حساب المريض في جدول login.'
            : 'Could not find the patient account in the login table.';
    }
}

/* =========================
   Get emergency case details
========================= */
if (empty($error) && $caseId > 0) {
    $stmt = $conn->prepare("
        SELECT id, emergency_code, severity_level, emergency_reason, status, created_at, closed_at, doctor_username
        FROM emergency_cases
        WHERE id = ? AND patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $caseId, $patientId);
    $stmt->execute();
    $caseResult = $stmt->get_result();
    $emergencyCase = $caseResult->fetch_assoc();
    $stmt->close();
}

/* =========================
   Log View History
========================= */
if (empty($error) && $emergencyCase) {
    $updateStmt = $conn->prepare("
        UPDATE emergency_cases
        SET access_count = access_count + 1,
            last_viewed_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->bind_param("i", $caseId);
    $updateStmt->execute();
    $updateStmt->close();

    $logStmt = $conn->prepare("
        INSERT INTO emergency_logs (
            emergency_case_id,
            patient_id,
            doctor_login_id,
            doctor_username,
            patient_name,
            patient_national_id,
            action_type,
            action_details,
            viewed_by_role
        ) VALUES (?, ?, ?, ?, ?, ?, 'VIEW_HISTORY', 'Doctor viewed patient emergency history', 'doctor')
    ");
    $logStmt->bind_param(
        "iiisss",
        $caseId,
        $patientId,
        $doctorLoginId,
        $doctorName,
        $patientName,
        $patient['NationalID']
    );
    $logStmt->execute();
    $logStmt->close();
}

/* =========================
   Fetch history rows
========================= */
if (empty($error) && $patientLogin) {
    $stmt = $conn->prepare("
        SELECT id, patient_username, visit_date, doctor_name, diagnosis, treatment
        FROM patient_history
        WHERE patient_username = ?
        ORDER BY visit_date DESC, id DESC
    ");
    $stmt->bind_param("s", $patientLogin['username']);
    $stmt->execute();
    $historyResult = $stmt->get_result();

    while ($row = $historyResult->fetch_assoc()) {
        $historyRows[] = $row;
    }

    $stmt->close();
}

/* =========================
   Texts
========================= */
$text = [
    'app_name'           => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'         => ($lang === 'ar') ? 'سجل المريض في الطوارئ' : 'Patient Emergency History',
    'hero_badge'         => ($lang === 'ar') ? 'سجل الطوارئ الطبي' : 'Emergency Medical Record',
    'hero_title'         => ($lang === 'ar') ? 'History المريض' : 'Patient History',
    'hero_desc'          => ($lang === 'ar')
        ? 'من هذه الصفحة يستطيع الطبيب مراجعة بيانات المريض وسجله الطبي المرتبط بحالة الطوارئ.'
        : 'From this page, the doctor can review the patient information and medical history related to the emergency case.',

    'back_emergency'     => ($lang === 'ar') ? 'العودة إلى الطوارئ' : 'Back to Emergency',
    'patient_info'       => ($lang === 'ar') ? 'بيانات المريض' : 'Patient Information',
    'medical_history'    => ($lang === 'ar') ? 'السجل الطبي' : 'Medical History',
    'emergency_summary'  => ($lang === 'ar') ? 'ملخص حالة الطوارئ' : 'Emergency Summary',

    'dob'                => ($lang === 'ar') ? 'تاريخ الميلاد' : 'DOB',
    'gender'             => ($lang === 'ar') ? 'النوع' : 'Gender',
    'contact'            => ($lang === 'ar') ? 'رقم التواصل' : 'Contact',
    'blood_type'         => ($lang === 'ar') ? 'فصيلة الدم' : 'Blood Type',
    'email'              => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'address'            => ($lang === 'ar') ? 'العنوان' : 'Address',
    'national_id'        => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'patient_username'   => ($lang === 'ar') ? 'اسم مستخدم المريض' : 'Patient Username',

    'visit_date'         => ($lang === 'ar') ? 'تاريخ الزيارة' : 'Visit Date',
    'doctor'             => ($lang === 'ar') ? 'اسم الطبيب' : 'Doctor',
    'diagnosis'          => ($lang === 'ar') ? 'التشخيص' : 'Diagnosis',
    'treatment'          => ($lang === 'ar') ? 'العلاج' : 'Treatment',

    'case_code'          => ($lang === 'ar') ? 'كود الطوارئ' : 'Emergency Code',
    'severity'           => ($lang === 'ar') ? 'درجة الخطورة' : 'Severity',
    'reason'             => ($lang === 'ar') ? 'سبب الحالة' : 'Reason',
    'status'             => ($lang === 'ar') ? 'الحالة' : 'Status',
    'created_at'         => ($lang === 'ar') ? 'وقت الإنشاء' : 'Created At',
    'closed_at'          => ($lang === 'ar') ? 'وقت الإغلاق' : 'Closed At',
    'created_by'         => ($lang === 'ar') ? 'تم الإنشاء بواسطة' : 'Created By',

    'records_count'      => ($lang === 'ar') ? 'عدد السجلات' : 'Records Count',
    'available'          => ($lang === 'ar') ? 'متاح' : 'Available',
    'active'             => ($lang === 'ar') ? 'نشطة' : 'Active',
    'closed'             => ($lang === 'ar') ? 'مغلقة' : 'Closed',
    'empty'              => ($lang === 'ar') ? 'لا يوجد سجل طبي لهذا المريض حتى الآن.' : 'No medical history records available for this patient.',
    'not_available'      => ($lang === 'ar') ? 'غير متوفر' : 'Unavailable',
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

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            transition: 0.25s ease;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .hero-main {
            border-radius: 30px;
            padding: 30px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 25%),
                linear-gradient(135deg, #6d28d9, #2563eb 62%, #38bdf8);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .hero-main::after {
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

        .hero-main h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.15;
            font-weight: 800;
        }

        .hero-main p {
            margin: 0;
            font-size: 16px;
            line-height: 1.8;
            color: rgba(255,255,255,0.92);
            max-width: 760px;
        }

        .hero-side {
            border-radius: 30px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .mini-card {
            border-radius: 20px;
            padding: 18px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .mini-card .label {
            font-size: 13px;
            color: var(--text-soft);
            margin-bottom: 8px;
        }

        .mini-card .value {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.6;
        }

        .panel {
            border-radius: 30px;
            padding: 24px;
            margin-bottom: 22px;
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

        .alert-box {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 700;
            font-size: 14px;
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

        tr:last-child td { border-bottom: none; }

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

        .status-active { color: #86efac; font-weight: 800; }
        .status-closed { color: #fecaca; font-weight: 800; }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.62);
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 1100px) {
            .hero { grid-template-columns: 1fr; }
        }

        @media (max-width: 900px) {
            .topbar { flex-direction: column; align-items: stretch; }
            .topbar-right { justify-content: space-between; }
        }

        @media (max-width: 640px) {
            .page-shell {
                width: min(100% - 18px, 1280px);
                margin: 12px auto 24px;
            }

            .topbar, .panel, .hero-main, .hero-side {
                padding: 18px;
            }

            .hero-main h2 { font-size: 26px; }
            .panel-title-row h3 { font-size: 19px; }

            .nav-btn {
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
                <i class="fa-solid fa-file-medical"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_history('en', $patientId, $caseId)) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_history('ar', $patientId, $caseId)) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="doctor_emergency.php?lang=<?= urlencode($lang) ?>" class="nav-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= htmlspecialchars($text['back_emergency']) ?></span>
            </a>
        </div>
    </header>

    <?php if (!empty($error)): ?>
        <div class="alert-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="hero">
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-heart-pulse"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['records_count']) ?></div>
                <div class="value"><?= htmlspecialchars((string)count($historyRows)) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['status']) ?></div>
                <div class="value"><?= !empty($error) ? htmlspecialchars($text['not_available']) : htmlspecialchars($text['available']) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['patient_info']) ?></div>
                <div class="value" style="font-size:15px; line-height:1.7;">
                    <?= htmlspecialchars($patientName ?: $text['not_available']) ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($emergencyCase): ?>
        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['emergency_summary']) ?></h3>
                    <p><?= htmlspecialchars($patientName) ?></p>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['case_code']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($emergencyCase['emergency_code'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['severity']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($emergencyCase['severity_level'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['status']) ?></div>
                    <div class="info-value <?= (($emergencyCase['status'] ?? '') === 'Active') ? 'status-active' : 'status-closed' ?>">
                        <?= (($emergencyCase['status'] ?? '') === 'Active') ? htmlspecialchars($text['active']) : htmlspecialchars($text['closed']) ?>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['created_by']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($emergencyCase['doctor_username'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['created_at']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($emergencyCase['created_at'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['closed_at']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($emergencyCase['closed_at'] ?? $text['not_available']) ?></div>
                </div>

                <div class="info-card" style="grid-column: 1 / -1;">
                    <div class="info-label"><?= htmlspecialchars($text['reason']) ?></div>
                    <div class="info-value"><?= nl2br(htmlspecialchars($emergencyCase['emergency_reason'] ?? '')) ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($patient): ?>
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

            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['national_id']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['NationalID'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['patient_username']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patientLogin['username'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['dob']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['DOB'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['gender']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['Gender'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['contact']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['ContactPhone'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['email']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['Email'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['blood_type']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['BloodType'] ?? '') ?></div>
                </div>

                <div class="info-card">
                    <div class="info-label"><?= htmlspecialchars($text['address']) ?></div>
                    <div class="info-value"><?= htmlspecialchars($patient['Address'] ?? '') ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['medical_history']) ?></h3>
                <p><?= htmlspecialchars($patientName ?: $text['not_available']) ?></p>
            </div>
        </div>

        <?php if (empty($historyRows)): ?>
            <div class="empty-box">
                <i class="fa-regular fa-folder-open" style="font-size:34px; margin-bottom:12px;"></i><br>
                <?= htmlspecialchars($text['empty']) ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars($text['visit_date']) ?></th>
                            <th><?= htmlspecialchars($text['doctor']) ?></th>
                            <th><?= htmlspecialchars($text['diagnosis']) ?></th>
                            <th><?= htmlspecialchars($text['treatment']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historyRows as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['visit_date'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['doctor_name'] ?? '') ?></td>
                                <td><?= nl2br(htmlspecialchars($row['diagnosis'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars($row['treatment'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> —
        <?= ($lang === 'ar') ? 'واجهة سجل طبي احترافية للطوارئ' : 'Professional Emergency Medical History Interface' ?>
    </div>

</div>
</body>
</html>