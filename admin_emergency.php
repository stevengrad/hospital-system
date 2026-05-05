<?php
session_start();
require_once __DIR__ . '/db_connect.php';

/* =========================
   Access Control
========================= */
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}
$adminLoginId = (int)($_SESSION['user_id'] ?? 0);
$adminName    = $_SESSION['username'] ?? 'Admin';

/* Temporary compatibility with doctor-based variable names */
$doctorLoginId = $adminLoginId;
$doctorName    = $adminName;
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
$adminLoginId = (int)($_SESSION['user_id'] ?? 0);
$adminName    = $_SESSION['username'] ?? 'Admin';

/* =========================
   Auto Expire After 1 Hour
========================= */
$conn->query("
    UPDATE emergency_cases
    SET status = 'Expired'
    WHERE status = 'Active'
      AND expires_at IS NOT NULL
      AND expires_at <= NOW()
");

/* =========================
   Helpers
========================= */
function lang_link_emergency($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

function generateEmergencyCode($length = 8) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $maxIndex = strlen($chars) - 1;
    $randomPart = '';

    for ($i = 0; $i < $length; $i++) {
        $randomPart .= $chars[random_int(0, $maxIndex)];
    }

    return 'ER-' . $randomPart;
}

function generateUniqueEmergencyCode(mysqli $conn, $length = 8) {
    do {
        $code = generateEmergencyCode($length);
        $stmt = $conn->prepare("SELECT id FROM emergency_cases WHERE emergency_code = ? LIMIT 1");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $code;
}

/* =========================
   Texts
========================= */
$text = [
    'app_name'                   => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'                 => ($lang === 'ar') ? 'صفحة الطوارئ' : 'Emergency Page',
    'dashboard'                  => ($lang === 'ar') ? 'لوحة التحكم' : 'Dashboard',
    'logout'                     => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'my_emergency_history'       => ($lang === 'ar') ? 'سجل الطوارئ الخاص بي' : 'My Emergency History',

    'hero_badge'                 => ($lang === 'ar') ? 'وضع الطوارئ' : 'Emergency Mode',
    'hero_title'                 => ($lang === 'ar') ? 'إدارة حالات الطوارئ بسرعة وأمان' : 'Manage emergency cases quickly and securely',
    'hero_desc'                  => ($lang === 'ar')
        ? 'من هذه الصفحة يمكن للطبيب إنشاء حالة طوارئ جديدة، توليد كود طوارئ فريد، عرض History المريض، أو إغلاق الحالة.'
        : 'From this page, the doctor can create a new emergency case, generate a unique emergency code, view patient history, or close the case.',

    'doctor_id'                  => ($lang === 'ar') ? 'رقم الدكتور' : 'Doctor ID',
    'doctor_name'                => ($lang === 'ar') ? 'اسم الدكتور' : 'Doctor Name',
    'security_note'              => ($lang === 'ar') ? 'ملاحظة أمنية' : 'Security Note',
    'security_note_value'        => ($lang === 'ar')
        ? 'كل عملية إنشاء أو عرض أو إغلاق لحالة الطوارئ يتم تسجيلها في السجل الأمني.'
        : 'Every emergency case creation, view, or close action is recorded in the security log.',

    'form_title'                 => ($lang === 'ar') ? 'إنشاء حالة طوارئ جديدة' : 'Create New Emergency Case',
    'form_subtitle'              => ($lang === 'ar')
        ? 'أدخل الرقم القومي واسم المريض ودرجة الخطورة وسبب الحالة، ثم أنشئ كود الطوارئ.'
        : 'Enter the national ID, patient name, severity level, and emergency reason, then generate the emergency code.',

    'national_id'                => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'patient_name'               => ($lang === 'ar') ? 'اسم المريض' : 'Patient Name',
    'severity'                   => ($lang === 'ar') ? 'درجة الخطورة' : 'Severity Level',
    'reason'                     => ($lang === 'ar') ? 'سبب حالة الطوارئ' : 'Emergency Reason',

    'placeholder_national_id'    => ($lang === 'ar') ? 'اكتب الرقم القومي للمريض' : 'Enter patient national ID',
    'placeholder_patient_name'   => ($lang === 'ar') ? 'اكتب الاسم كاملًا' : 'Enter full patient name',
    'placeholder_reason'         => ($lang === 'ar') ? 'اكتب سبب حالة الطوارئ بالتفصيل...' : 'Write the emergency reason in detail...',

    'severity_low'               => ($lang === 'ar') ? 'منخفضة' : 'Low',
    'severity_medium'            => ($lang === 'ar') ? 'متوسطة' : 'Medium',
    'severity_high'              => ($lang === 'ar') ? 'مرتفعة' : 'High',
    'severity_critical'          => ($lang === 'ar') ? 'حرجة جدًا' : 'Critical',

    'generate_btn'               => ($lang === 'ar') ? 'إنشاء كود الطوارئ' : 'Generate Emergency Code',
    'reset_btn'                  => ($lang === 'ar') ? 'إعادة تعيين' : 'Reset',
    'history_btn'                => ($lang === 'ar') ? 'عرض History المريض' : 'View Patient History',
    'close_btn'                  => ($lang === 'ar') ? 'إغلاق الحالة' : 'Close Case',

    'result_title'               => ($lang === 'ar') ? 'تم إنشاء حالة الطوارئ بنجاح' : 'Emergency Case Created Successfully',
    'result_code'                => ($lang === 'ar') ? 'كود الطوارئ' : 'Emergency Code',
    'result_patient'             => ($lang === 'ar') ? 'المريض' : 'Patient',
    'result_severity'            => ($lang === 'ar') ? 'درجة الخطورة' : 'Severity',
    'result_reason'              => ($lang === 'ar') ? 'سبب الحالة' : 'Reason',
    'result_doctor'              => ($lang === 'ar') ? 'الدكتور' : 'Doctor',
    'result_time'                => ($lang === 'ar') ? 'وقت الإنشاء' : 'Created At',
    'result_status'              => ($lang === 'ar') ? 'الحالة' : 'Status',

    'status_active'              => ($lang === 'ar') ? 'نشطة' : 'Active',
    'status_closed'              => ($lang === 'ar') ? 'مغلقة' : 'Closed',
    'status_expired'             => ($lang === 'ar') ? 'منتهية' : 'Expired',

    'required_error'             => ($lang === 'ar') ? 'من فضلك املأ جميع الحقول المطلوبة.' : 'Please fill in all required fields.',
    'patient_not_found'          => ($lang === 'ar') ? 'المريض غير موجود بهذا الرقم القومي.' : 'Patient not found with this national ID.',
    'patient_name_mismatch'      => ($lang === 'ar') ? 'اسم المريض غير مطابق للرقم القومي.' : 'Patient name does not match the national ID.',
    'doctor_not_found'           => ($lang === 'ar') ? 'تعذر التحقق من بيانات الدكتور.' : 'Unable to verify doctor information.',
    'already_active'             => ($lang === 'ar') ? 'هذا المريض لديه بالفعل حالة طوارئ نشطة.' : 'This patient already has an active emergency case.',
    'case_closed_success'        => ($lang === 'ar') ? 'تم إغلاق حالة الطوارئ بنجاح.' : 'Emergency case closed successfully.',
    'case_close_error'           => ($lang === 'ar') ? 'تعذر إغلاق الحالة.' : 'Unable to close the emergency case.',
    'not_available'              => ($lang === 'ar') ? 'غير متوفر' : 'Unavailable',

    'footer'                     => ($lang === 'ar') ? 'واجهة طوارئ احترافية للأطباء' : 'Professional Emergency Interface for Doctors',
];

$error = '';
$success = false;
$successMessage = '';
$closedMessage = '';

$generatedCode = '';
$createdAt = '';
$createdCaseId = 0;

$formData = [
    'national_id'   => '',
    'patient_name'  => '',
    'severity'      => '',
    'reason'        => ''
];

$createdCase = null;

/* =========================
   Close Emergency Case
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_case'])) {
    $caseId = (int)($_POST['case_id'] ?? 0);

    if ($caseId > 0) {
        $stmt = $conn->prepare("
            SELECT id, patient_id, doctor_login_id, doctor_username, patient_name, patient_national_id, status
            FROM emergency_cases
            WHERE id = ? AND doctor_login_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $caseId, $doctorLoginId);
        $stmt->execute();
        $caseResult = $stmt->get_result();
        $caseRow = $caseResult->fetch_assoc();
        $stmt->close();

        if ($caseRow && $caseRow['status'] === 'Active') {
            $update = $conn->prepare("
                UPDATE emergency_cases
                SET status = 'Closed', closed_at = NOW()
                WHERE id = ?
            ");
            $update->bind_param("i", $caseId);
            $update->execute();
            $update->close();

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
                ) VALUES (?, ?, ?, ?, ?, ?, 'CLOSE_CASE', 'Doctor closed emergency case', 'doctor')
            ");
            $logStmt->bind_param(
                "iiisss",
                $caseId,
                $caseRow['patient_id'],
                $caseRow['doctor_login_id'],
                $caseRow['doctor_username'],
                $caseRow['patient_name'],
                $caseRow['patient_national_id']
            );
            $logStmt->execute();
            $logStmt->close();

            $closedMessage = $text['case_closed_success'];
        } else {
            $error = $text['case_close_error'];
        }
    } else {
        $error = $text['case_close_error'];
    }
}

/* =========================
   Create Emergency Case
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_case'])) {
    $formData['national_id']  = trim($_POST['national_id'] ?? '');
    $formData['patient_name'] = trim($_POST['patient_name'] ?? '');
    $formData['severity']     = trim($_POST['severity'] ?? '');
    $formData['reason']       = trim($_POST['reason'] ?? '');

    if (
        $formData['national_id'] === '' ||
        $formData['patient_name'] === '' ||
        $formData['severity'] === '' ||
        $formData['reason'] === ''
    ) {
        $error = $text['required_error'];
    } else {
        $doctorStmt = $conn->prepare("
            SELECT id, username
FROM admin_login
WHERE id = ?
LIMIT 1
        ");
        $doctorStmt->bind_param("i", $doctorLoginId);
        $doctorStmt->execute();
        $doctorResult = $doctorStmt->get_result();
        $doctorRow = $doctorResult->fetch_assoc();
        $doctorStmt->close();

        if (!$doctorRow) {
            $error = $text['doctor_not_found'];
        } else {
            $doctorUsername = $doctorRow['username'];

            $patientStmt = $conn->prepare("
                SELECT PatientID, NationalID, FirstName, LastName
                FROM patients
                WHERE NationalID = ?
                LIMIT 1
            ");
            $patientStmt->bind_param("s", $formData['national_id']);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            $patientRow = $patientResult->fetch_assoc();
            $patientStmt->close();

            if (!$patientRow) {
                $error = $text['patient_not_found'];
            } else {
                $dbPatientName = trim($patientRow['FirstName'] . ' ' . $patientRow['LastName']);
                $inputPatientName = preg_replace('/\s+/', ' ', trim($formData['patient_name']));
                $dbPatientNameNormalized = preg_replace('/\s+/', ' ', trim($dbPatientName));

                if (mb_strtolower($inputPatientName) !== mb_strtolower($dbPatientNameNormalized)) {
                    $error = $text['patient_name_mismatch'];
                } else {
                    $patientId = (int)$patientRow['PatientID'];

                    $activeStmt = $conn->prepare("
                        SELECT id, emergency_code, created_at
                        FROM emergency_cases
                        WHERE patient_id = ? AND status = 'Active'
                        LIMIT 1
                    ");
                    $activeStmt->bind_param("i", $patientId);
                    $activeStmt->execute();
                    $activeResult = $activeStmt->get_result();
                    $activeRow = $activeResult->fetch_assoc();
                    $activeStmt->close();

                    if ($activeRow) {
                        $error = $text['already_active'] . ' (' . $activeRow['emergency_code'] . ')';
                    } else {
                        $generatedCode = generateUniqueEmergencyCode($conn, 8);

                        $insertStmt = $conn->prepare("
                            INSERT INTO emergency_cases (
                                patient_id,
                                doctor_login_id,
                                patient_national_id,
                                patient_name,
                                doctor_username,
                                emergency_code,
                                severity_level,
                                emergency_reason,
                                status,
                                created_at,
                                expires_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR))
                        ");
                        $insertStmt->bind_param(
                            "iissssss",
                            $patientId,
                            $doctorLoginId,
                            $formData['national_id'],
                            $dbPatientNameNormalized,
                            $doctorUsername,
                            $generatedCode,
                            $formData['severity'],
                            $formData['reason']
                        );
                        $insertStmt->execute();
                        $createdCaseId = (int)$insertStmt->insert_id;
                        $insertStmt->close();

                        /* =========================
                           Add Emergency Case To patient_history
                        ========================= */
                        $patientLoginStmt = $conn->prepare("
                            SELECT username
                            FROM login
                            WHERE national_id = ? AND role = 'patient'
                            LIMIT 1
                        ");
                        $patientLoginStmt->bind_param("s", $formData['national_id']);
                        $patientLoginStmt->execute();
                        $patientLoginResult = $patientLoginStmt->get_result();
                        $patientLoginRow = $patientLoginResult->fetch_assoc();
                        $patientLoginStmt->close();

                        if ($patientLoginRow && !empty($patientLoginRow['username'])) {
                            $patientUsername = $patientLoginRow['username'];

                            $historyDiagnosis = 'Emergency Case: ' . $formData['reason'];
                            $historyTreatment = 'Severity: ' . $formData['severity'] . ' | Emergency Code: ' . $generatedCode;

                            $historyStmt = $conn->prepare("
                                INSERT INTO patient_history (
                                    patient_username,
                                    visit_date,
                                    doctor_name,
                                    diagnosis,
                                    treatment
                                ) VALUES (?, NOW(), ?, ?, ?)
                            ");
                            $historyStmt->bind_param(
                                "ssss",
                                $patientUsername,
                                $doctorUsername,
                                $historyDiagnosis,
                                $historyTreatment
                            );
                            $historyStmt->execute();
                            $historyStmt->close();
                        }

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
                            ) VALUES (?, ?, ?, ?, ?, ?, 'CREATE_CASE', 'Doctor created emergency case', 'doctor')
                        ");
                        $logStmt->bind_param(
                            "iiisss",
                            $createdCaseId,
                            $patientId,
                            $doctorLoginId,
                            $doctorUsername,
                            $dbPatientNameNormalized,
                            $formData['national_id']
                        );
                        $logStmt->execute();
                        $logStmt->close();

                        $success = true;
                        $successMessage = $text['result_title'];
                        $createdAt = date('Y-m-d h:i A');

                        $createdCase = [
                            'id'             => $createdCaseId,
                            'patient_id'     => $patientId,
                            'patient_name'   => $dbPatientNameNormalized,
                            'national_id'    => $formData['national_id'],
                            'severity'       => $formData['severity'],
                            'reason'         => $formData['reason'],
                            'doctor_name'    => $doctorUsername,
                            'code'           => $generatedCode,
                            'status'         => 'Active',
                            'created_at'     => $createdAt,
                        ];
                    }
                }
            }
        }
    }
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
            --danger-1: #ef4444;
            --danger-2: #dc2626;
            --green: #22c55e;
            --shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--white);
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(76,201,240,0.18), transparent 24%),
                radial-gradient(circle at top right, rgba(239,68,68,0.14), transparent 24%),
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
            background: linear-gradient(135deg, var(--danger-1), var(--danger-2));
            box-shadow: 0 15px 30px rgba(220,38,38,0.35);
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

        .back-btn,
        .logout-btn,
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            transition: 0.25s ease;
            border: none;
            cursor: pointer;
        }

        .back-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 10px 25px rgba(72,149,239,0.30);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 25px rgba(239,68,68,0.30);
        }

        .action-btn.green {
            background: linear-gradient(135deg, #16a34a, #22c55e);
            box-shadow: 0 10px 25px rgba(34,197,94,0.30);
        }

        .action-btn.red {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            box-shadow: 0 10px 25px rgba(220,38,38,0.30);
        }

        .back-btn:hover,
        .logout-btn:hover,
        .action-btn:hover {
            transform: translateY(-2px);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            padding: 32px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 25%),
                linear-gradient(135deg, #7f1d1d, #dc2626 62%, #f87171);
            box-shadow: var(--shadow);
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
            background: linear-gradient(135deg, rgba(239,68,68,0.20), rgba(220,38,38,0.20));
            color: #fff;
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
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .alert-error {
            color: #fff;
            background: rgba(239,68,68,0.18);
            border-color: rgba(239,68,68,0.34);
        }

        .alert-success {
            color: #fff;
            background: rgba(34,197,94,0.18);
            border-color: rgba(34,197,94,0.34);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full { grid-column: 1 / -1; }

        label {
            font-size: 14px;
            font-weight: 700;
            color: #eef7ff;
        }

        input, select, textarea {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.08);
            color: #fff;
            border-radius: 16px;
            padding: 14px 15px;
            outline: none;
            font-size: 14px;
            transition: 0.25s ease;
        }

        input::placeholder, textarea::placeholder {
            color: rgba(255,255,255,0.52);
        }

        input:focus, select:focus, textarea:focus {
            border-color: rgba(76,201,240,0.6);
            box-shadow: 0 0 0 4px rgba(76,201,240,0.12);
        }

        select option { color: #111827; }

        textarea {
            min-height: 140px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .btn {
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 18px;
            border-radius: 14px;
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            transition: 0.25s ease;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-1), var(--danger-2));
            box-shadow: 0 10px 24px rgba(239,68,68,0.30);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #334155, #1e293b);
            box-shadow: 0 10px 24px rgba(15,23,42,0.28);
        }

        .btn:hover { transform: translateY(-2px); }

        .result-card {
            border-radius: 26px;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 30%),
                linear-gradient(135deg, rgba(127,29,29,0.95), rgba(220,38,38,0.92), rgba(248,113,113,0.88));
            border: 1px solid rgba(255,255,255,0.14);
            box-shadow: 0 22px 48px rgba(127,29,29,0.42);
            margin-bottom: 22px;
        }

        .result-top { padding: 24px 24px 12px; }
        .result-top h3 { margin: 0 0 8px; font-size: 25px; }
        .result-top p { margin: 0; color: rgba(255,255,255,0.9); line-height: 1.7; }

        .code-box {
            margin: 18px 24px 0;
            border-radius: 22px;
            padding: 22px;
            text-align: center;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .code-box .code-label {
            font-size: 13px;
            color: rgba(255,255,255,0.82);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .code-box .code-value {
            font-size: clamp(28px, 5vw, 42px);
            font-weight: 900;
            letter-spacing: 3px;
        }

        .result-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            padding: 24px;
        }

        .result-item {
            border-radius: 18px;
            padding: 16px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .result-item.full { grid-column: 1 / -1; }

        .result-item .label {
            font-size: 13px;
            color: rgba(255,255,255,0.80);
            margin-bottom: 8px;
        }

        .result-item .value {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.7;
            word-break: break-word;
        }

        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 0 24px 24px;
        }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.62);
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 900px) {
            .topbar { flex-direction: column; align-items: stretch; }
            .topbar-right { justify-content: space-between; }
            .form-grid, .result-grid { grid-template-columns: 1fr; }
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

            .back-btn, .logout-btn, .btn, .action-btn {
                width: 100%;
                justify-content: center;
            }

            .brand-text h1 { font-size: 18px; }
            .code-box .code-value { letter-spacing: 1px; }
        }
    </style>
</head>
<body>
<div class="page-shell">

    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-truck-medical"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_emergency('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_emergency('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

           <a href="admin_dashboard.php?lang=<?= urlencode($lang) ?>" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= htmlspecialchars($text['dashboard']) ?></span>
            </a>

            <a href="admin_emergency_history.php?lang=<?= urlencode($lang) ?>" class="back-btn">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span><?= htmlspecialchars($text['my_emergency_history']) ?></span>
            </a>

            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?= htmlspecialchars($text['logout']) ?></span>
            </a>
        </div>
    </header>

    <section class="hero">
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-siren-on"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['doctor_name']) ?></div>
                <div class="value"><?= htmlspecialchars($doctorName) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['doctor_id']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$doctorLoginId) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['security_note']) ?></div>
                <div class="value" style="font-size:15px;">
                    <?= htmlspecialchars($text['security_note_value']) ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($closedMessage !== ''): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <?= htmlspecialchars($closedMessage) ?>
        </div>
    <?php endif; ?>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="icon">
                <i class="fa-solid fa-notes-medical"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['form_title']) ?></h3>
                <p><?= htmlspecialchars($text['form_subtitle']) ?></p>
            </div>
        </div>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="national_id"><?= htmlspecialchars($text['national_id']) ?></label>
                    <input
                        type="text"
                        id="national_id"
                        name="national_id"
                        value="<?= htmlspecialchars($formData['national_id']) ?>"
                        placeholder="<?= htmlspecialchars($text['placeholder_national_id']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="patient_name"><?= htmlspecialchars($text['patient_name']) ?></label>
                    <input
                        type="text"
                        id="patient_name"
                        name="patient_name"
                        value="<?= htmlspecialchars($formData['patient_name']) ?>"
                        placeholder="<?= htmlspecialchars($text['placeholder_patient_name']) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="severity"><?= htmlspecialchars($text['severity']) ?></label>
                    <select id="severity" name="severity" required>
                        <option value=""><?= ($lang === 'ar') ? 'اختر درجة الخطورة' : 'Select severity level' ?></option>
                        <option value="Low" <?= ($formData['severity'] === 'Low') ? 'selected' : '' ?>><?= htmlspecialchars($text['severity_low']) ?></option>
                        <option value="Medium" <?= ($formData['severity'] === 'Medium') ? 'selected' : '' ?>><?= htmlspecialchars($text['severity_medium']) ?></option>
                        <option value="High" <?= ($formData['severity'] === 'High') ? 'selected' : '' ?>><?= htmlspecialchars($text['severity_high']) ?></option>
                        <option value="Critical" <?= ($formData['severity'] === 'Critical') ? 'selected' : '' ?>><?= htmlspecialchars($text['severity_critical']) ?></option>
                    </select>
                </div>

                <div class="form-group full">
                    <label for="reason"><?= htmlspecialchars($text['reason']) ?></label>
                    <textarea
                        id="reason"
                        name="reason"
                        placeholder="<?= htmlspecialchars($text['placeholder_reason']) ?>"
                        required
                    ><?= htmlspecialchars($formData['reason']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="generate_case" class="btn btn-danger">
                    <i class="fa-solid fa-bolt"></i>
                    <span><?= htmlspecialchars($text['generate_btn']) ?></span>
                </button>

                <button type="reset" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate-right"></i>
                    <span><?= htmlspecialchars($text['reset_btn']) ?></span>
                </button>
            </div>
        </form>
    </section>

    <?php if ($success && $createdCase): ?>
        <section class="result-card">
            <div class="result-top">
                <h3><?= htmlspecialchars($successMessage) ?></h3>
                <p>
                    <?= ($lang === 'ar')
                        ? 'تم حفظ الحالة في قاعدة البيانات، وإضافتها إلى patient_history، مع تسجيل العملية في السجل الأمني.'
                        : 'The case has been saved to the database, added to patient_history, and recorded in the security log.' ?>
                </p>
            </div>

            <div class="code-box">
                <div class="code-label"><?= htmlspecialchars($text['result_code']) ?></div>
                <div class="code-value"><?= htmlspecialchars($createdCase['code']) ?></div>
            </div>

            <div class="result-grid">
                <div class="result-item">
                    <div class="label"><?= htmlspecialchars($text['result_patient']) ?></div>
                    <div class="value">
                        <?= htmlspecialchars($createdCase['patient_name']) ?><br>
                        <small><?= htmlspecialchars($createdCase['national_id']) ?></small>
                    </div>
                </div>

                <div class="result-item">
                    <div class="label"><?= htmlspecialchars($text['result_severity']) ?></div>
                    <div class="value"><?= htmlspecialchars($createdCase['severity']) ?></div>
                </div>

                <div class="result-item">
                    <div class="label"><?= htmlspecialchars($text['result_doctor']) ?></div>
                    <div class="value"><?= htmlspecialchars($createdCase['doctor_name']) ?></div>
                </div>

                <div class="result-item">
                    <div class="label"><?= htmlspecialchars($text['result_time']) ?></div>
                    <div class="value"><?= htmlspecialchars($createdCase['created_at']) ?></div>
                </div>

                <div class="result-item">
                    <div class="label"><?= htmlspecialchars($text['result_status']) ?></div>
                    <div class="value status-active"><?= htmlspecialchars($text['status_active']) ?></div>
                </div>

                <div class="result-item full">
                    <div class="label"><?= htmlspecialchars($text['result_reason']) ?></div>
                    <div class="value"><?= htmlspecialchars($createdCase['reason']) ?></div>
                </div>
            </div>

            <div class="result-actions">
                <a href="doctor_patient_emergency_history.php?patient_id=<?= urlencode((string)$createdCase['patient_id']) ?>&case_id=<?= urlencode((string)$createdCase['id']) ?>&lang=<?= urlencode($lang) ?>" class="action-btn green">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span><?= htmlspecialchars($text['history_btn']) ?></span>
                </a>

                <form method="POST" action="" style="margin:0;">
                    <input type="hidden" name="case_id" value="<?= htmlspecialchars((string)$createdCase['id']) ?>">
                    <button type="submit" name="close_case" class="action-btn red">
                        <i class="fa-solid fa-lock"></i>
                        <span><?= htmlspecialchars($text['close_btn']) ?></span>
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> — <?= htmlspecialchars($text['footer']) ?>
    </div>

</div>
</body>
</html>