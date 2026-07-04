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

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['username'] ?? 'Admin';

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* =========================
   Helpers
========================= */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function lang_link($code) {
    return basename($_SERVER['PHP_SELF']) . '?lang=' . $code;
}

function tableExists(mysqli $conn, string $table): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");

    $stmt->bind_param("s", $table);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return ((int)($row['cnt'] ?? 0)) > 0;
}

function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");

    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return ((int)($row['cnt'] ?? 0)) > 0;
}

function logEmergencyAdminAction(mysqli $conn, array $caseRow, string $actionType, string $details): void {
    try {
        if (!tableExists($conn, 'emergency_logs')) {
            return;
        }

        $caseId       = (int)($caseRow['id'] ?? 0);
        $patientId    = (int)($caseRow['patient_id'] ?? 0);
        $doctorId     = (int)($caseRow['doctor_login_id'] ?? 0);
        $doctorUser   = (string)($caseRow['doctor_username'] ?? 'Unknown');
        $patientName  = (string)($caseRow['patient_name'] ?? 'Unknown');
        $nationalId   = (string)($caseRow['patient_national_id'] ?? '');

        $stmt = $conn->prepare(" 
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'admin')
        ");
        $stmt->bind_param(
            "iiisssss",
            $caseId,
            $patientId,
            $doctorId,
            $doctorUser,
            $patientName,
            $nationalId,
            $actionType,
            $details
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Do not stop the main action if logging fails.
    }
}

/* =========================
   Texts
========================= */
$text = [
    'app_name'        => ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals',
    'page_subtitle'   => ($lang === 'ar') ? 'سجل حالات الطوارئ' : 'Admin Emergency History',
    'dashboard'       => ($lang === 'ar') ? 'لوحة التحكم' : 'Dashboard',
    'emergency_panel' => ($lang === 'ar') ? 'لوحة الطوارئ' : 'Emergency Panel',
    'logout'          => ($lang === 'ar') ? 'تسجيل الخروج' : 'Logout',
    'hero_badge'      => ($lang === 'ar') ? 'إدارة الطوارئ' : 'Emergency Management',
    'hero_title'      => ($lang === 'ar') ? 'سجل كل حالات الطوارئ' : 'All Emergency Cases History',
    'hero_desc'       => ($lang === 'ar')
        ? 'من هذه الصفحة يستطيع الأدمن مراجعة كل حالات الطوارئ، معرفة اسم المريض، الدكتور الذي أنشأ الحالة، كود الطوارئ، وإغلاق أو حذف الحالة.'
        : 'From this page, the admin can review all emergency cases, check the patient name, the doctor who created the case, the emergency code, and close or delete cases.',
    'total_cases'     => ($lang === 'ar') ? 'إجمالي الحالات' : 'Total Cases',
    'active_cases'    => ($lang === 'ar') ? 'الحالات النشطة' : 'Active Cases',
    'closed_cases'    => ($lang === 'ar') ? 'الحالات المغلقة' : 'Closed Cases',
    'expired_cases'   => ($lang === 'ar') ? 'الحالات المنتهية' : 'Expired Cases',
    'table_title'     => ($lang === 'ar') ? 'قائمة حالات الطوارئ' : 'Emergency Cases List',
    'id'              => '#',
    'patient'         => ($lang === 'ar') ? 'اسم المريض' : 'Patient Name',
    'national_id'     => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'doctor'          => ($lang === 'ar') ? 'الدكتور / منشئ الحالة' : 'Doctor / Created By',
    'code'            => ($lang === 'ar') ? 'كود الطوارئ' : 'Emergency Code',
    'severity'        => ($lang === 'ar') ? 'درجة الخطورة' : 'Severity',
    'reason'          => ($lang === 'ar') ? 'سبب الحالة' : 'Reason',
    'created_at'      => ($lang === 'ar') ? 'وقت الإنشاء' : 'Created At',
    'expires_at'      => ($lang === 'ar') ? 'وقت الانتهاء' : 'Expires At',
    'closed_at'       => ($lang === 'ar') ? 'وقت الإغلاق' : 'Closed At',
    'status'          => ($lang === 'ar') ? 'الحالة' : 'Status',
    'actions'         => ($lang === 'ar') ? 'الإجراءات' : 'Actions',
    'close'           => ($lang === 'ar') ? 'إغلاق' : 'Close',
    'delete'          => ($lang === 'ar') ? 'حذف' : 'Delete',
    'no_cases'        => ($lang === 'ar') ? 'لا توجد حالات طوارئ مسجلة حتى الآن.' : 'No emergency cases have been recorded yet.',
    'closed_success'  => ($lang === 'ar') ? 'تم إغلاق حالة الطوارئ بنجاح.' : 'Emergency case closed successfully.',
    'deleted_success' => ($lang === 'ar') ? 'تم حذف حالة الطوارئ بنجاح.' : 'Emergency case deleted successfully.',
    'not_found'       => ($lang === 'ar') ? 'لم يتم العثور على الحالة المطلوبة.' : 'The selected emergency case was not found.',
    'error'           => ($lang === 'ar') ? 'حدث خطأ أثناء تنفيذ العملية.' : 'An error occurred while processing the request.',
    'confirm_close'   => ($lang === 'ar') ? 'هل أنت متأكد أنك تريد إغلاق هذه الحالة؟' : 'Are you sure you want to close this case?',
    'confirm_delete'  => ($lang === 'ar') ? 'هل أنت متأكد أنك تريد حذف هذه الحالة نهائيًا؟' : 'Are you sure you want to permanently delete this case?',
    'footer'          => ($lang === 'ar') ? 'واجهة احترافية لإدارة سجل الطوارئ للأدمن' : 'Professional Admin Emergency History Interface',
];

$message = '';
$messageType = '';

try {
    if (!tableExists($conn, 'emergency_cases')) {
        throw new Exception('emergency_cases table not found.');
    }

    /* =========================
       Auto Expire Active Cases
    ========================= */
    if (columnExists($conn, 'emergency_cases', 'expires_at')) {
        $conn->query(" 
            UPDATE emergency_cases
            SET status = 'Expired'
            WHERE status = 'Active'
              AND expires_at IS NOT NULL
              AND expires_at <= NOW()
        ");
    }

    /* =========================
       Close Case Action
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_case'])) {
        $caseId = (int)($_POST['case_id'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM emergency_cases WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $caseId);
        $stmt->execute();
        $caseResult = $stmt->get_result();
        $caseRow = $caseResult->fetch_assoc();
        $stmt->close();

        if (!$caseRow) {
            $message = $text['not_found'];
            $messageType = 'error';
        } else {
            if (columnExists($conn, 'emergency_cases', 'closed_at')) {
                $update = $conn->prepare("UPDATE emergency_cases SET status = 'Closed', closed_at = NOW() WHERE id = ?");
            } else {
                $update = $conn->prepare("UPDATE emergency_cases SET status = 'Closed' WHERE id = ?");
            }
            $update->bind_param("i", $caseId);
            $update->execute();
            $update->close();

            logEmergencyAdminAction($conn, $caseRow, 'ADMIN_CLOSE_CASE', 'Admin closed emergency case');

            $message = $text['closed_success'];
            $messageType = 'success';
        }
    }

    /* =========================
       Delete Case Action
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_case'])) {
        $caseId = (int)($_POST['case_id'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM emergency_cases WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $caseId);
        $stmt->execute();
        $caseResult = $stmt->get_result();
        $caseRow = $caseResult->fetch_assoc();
        $stmt->close();

        if (!$caseRow) {
            $message = $text['not_found'];
            $messageType = 'error';
        } else {
            // Delete related logs first to avoid foreign key errors if there is no ON DELETE CASCADE.
            if (tableExists($conn, 'emergency_logs')) {
                $deleteLogs = $conn->prepare("DELETE FROM emergency_logs WHERE emergency_case_id = ?");
                $deleteLogs->bind_param("i", $caseId);
                $deleteLogs->execute();
                $deleteLogs->close();
            }

            $delete = $conn->prepare("DELETE FROM emergency_cases WHERE id = ?");
            $delete->bind_param("i", $caseId);
            $delete->execute();
            $delete->close();

            $message = $text['deleted_success'];
            $messageType = 'success';
        }
    }

    /* =========================
       Fetch Statistics
    ========================= */
    $stats = [
        'total'   => 0,
        'active'  => 0,
        'closed'  => 0,
        'expired' => 0,
    ];

    $statsResult = $conn->query(" 
        SELECT
            COUNT(*) AS total_cases,
            SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_cases,
            SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) AS closed_cases,
            SUM(CASE WHEN status = 'Expired' THEN 1 ELSE 0 END) AS expired_cases
        FROM emergency_cases
    ");
    $statsRow = $statsResult->fetch_assoc();
    if ($statsRow) {
        $stats['total']   = (int)($statsRow['total_cases'] ?? 0);
        $stats['active']  = (int)($statsRow['active_cases'] ?? 0);
        $stats['closed']  = (int)($statsRow['closed_cases'] ?? 0);
        $stats['expired'] = (int)($statsRow['expired_cases'] ?? 0);
    }

    /* =========================
       Fetch Cases
    ========================= */
    $cases = [];
    $casesResult = $conn->query(" 
        SELECT
            id,
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
            expires_at,
            closed_at
        FROM emergency_cases
        ORDER BY created_at DESC, id DESC
    ");
    while ($row = $casesResult->fetch_assoc()) {
        $cases[] = $row;
    }
} catch (Throwable $ex) {
    $message = $text['error'] . ' ' . e($ex->getMessage());
    $messageType = 'error';
    $stats = ['total' => 0, 'active' => 0, 'closed' => 0, 'expired' => 0];
    $cases = [];
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($text['page_subtitle']) ?> - <?= e($text['app_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * { box-sizing: border-box; }

        :root {
            --bg-1: #07121d;
            --bg-2: #0c1c2d;
            --bg-3: #12314e;
            --white: #ffffff;
            --soft: rgba(255,255,255,0.78);
            --card: rgba(255,255,255,0.10);
            --stroke: rgba(255,255,255,0.14);
            --primary: #4cc9f0;
            --primary-2: #4895ef;
            --danger: #ef4444;
            --danger-2: #dc2626;
            --green: #22c55e;
            --yellow: #facc15;
            --shadow: 0 20px 50px rgba(0,0,0,0.28);
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--white);
            background:
                radial-gradient(circle at top left, rgba(76,201,240,0.18), transparent 24%),
                radial-gradient(circle at top right, rgba(239,68,68,0.14), transparent 24%),
                radial-gradient(circle at bottom center, rgba(72,149,239,0.12), transparent 28%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2) 45%, var(--bg-3));
        }

        a { text-decoration: none; color: inherit; }

        .page-shell {
            width: min(1500px, calc(100% - 32px));
            margin: 18px auto 34px;
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
            z-index: 10;
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
            background: linear-gradient(135deg, var(--danger), var(--danger-2));
            box-shadow: 0 15px 30px rgba(220,38,38,0.35);
        }

        .brand-text h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }

        .brand-text p {
            margin: 5px 0 0;
            color: var(--soft);
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
            gap: 6px;
            padding: 6px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .lang-toggle a {
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
        }

        .lang-toggle a.active,
        .lang-toggle a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 14px;
            padding: 12px 16px;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn:hover { transform: translateY(-2px); }
        .btn-blue { background: linear-gradient(135deg, var(--primary), var(--primary-2)); }
        .btn-red { background: linear-gradient(135deg, var(--danger), var(--danger-2)); }
        .btn-dark { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.14); }
        .btn-green { background: linear-gradient(135deg, #16a34a, var(--green)); }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .hero-main {
            border-radius: 30px;
            padding: 32px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 25%),
                linear-gradient(135deg, #7f1d1d, #dc2626 62%, #f87171);
            box-shadow: var(--shadow);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .hero-main h2 {
            margin: 0 0 14px;
            font-size: clamp(34px, 4vw, 56px);
            line-height: 1.05;
        }

        .hero-main p {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 16px;
            max-width: 850px;
            line-height: 1.7;
        }

        .stats-card {
            border-radius: 30px;
            padding: 20px;
        }

        .stat-box {
            background: rgba(255,255,255,0.09);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
        }

        .stat-box:last-child { margin-bottom: 0; }
        .stat-box span { color: var(--soft); font-size: 13px; display: block; margin-bottom: 8px; }
        .stat-box strong { font-size: 23px; display: block; }

        .alert {
            border-radius: 16px;
            padding: 15px 18px;
            margin-bottom: 18px;
            font-weight: 800;
        }
        .alert.success { color: #bbf7d0; background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.35); }
        .alert.error { color: #fecaca; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); }

        .table-section {
            border-radius: 30px;
            padding: 24px;
            overflow: hidden;
        }

        .section-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }

        .section-icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: rgba(76,201,240,0.18);
            color: #bff3ff;
            font-size: 20px;
        }

        .section-head h3 {
            margin: 0;
            font-size: 24px;
        }

        .table-wrap {
            overflow-x: auto;
            border-radius: 22px;
            border: 1px solid rgba(255,255,255,0.12);
        }

        table {
            width: 100%;
            min-width: 1300px;
            border-collapse: collapse;
            background: rgba(255,255,255,0.06);
        }

        th, td {
            padding: 16px 14px;
            text-align: <?= $dir === 'rtl' ? 'right' : 'left' ?>;
            vertical-align: top;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            font-size: 14px;
        }

        th {
            color: #67e8f9;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            font-size: 12px;
            background: rgba(72,149,239,0.25);
        }

        tr:last-child td { border-bottom: none; }

        .code-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(76,201,240,0.16);
            color: #a5f3fc;
            border: 1px solid rgba(76,201,240,0.25);
            font-weight: 900;
            white-space: nowrap;
        }

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 12px;
            border: 1px solid transparent;
        }
        .status.Active { color: #fde68a; border-color: rgba(250,204,21,0.55); background: rgba(250,204,21,0.10); }
        .status.Closed { color: #bbf7d0; border-color: rgba(34,197,94,0.45); background: rgba(34,197,94,0.10); }
        .status.Expired { color: #fecaca; border-color: rgba(239,68,68,0.45); background: rgba(239,68,68,0.10); }

        .reason-cell {
            max-width: 260px;
            line-height: 1.5;
            color: rgba(255,255,255,0.86);
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mini-btn {
            border: none;
            border-radius: 12px;
            padding: 9px 12px;
            color: #fff;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .mini-btn.close { background: linear-gradient(135deg, #16a34a, #22c55e); }
        .mini-btn.delete { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .mini-btn:disabled { opacity: 0.45; cursor: not-allowed; }

        .empty {
            padding: 44px 20px;
            text-align: center;
            color: var(--soft);
        }

        footer {
            text-align: center;
            color: rgba(255,255,255,0.55);
            font-size: 13px;
            margin-top: 24px;
        }

        @media (max-width: 900px) {
            .topbar, .hero { grid-template-columns: 1fr; }
            .hero { display: block; }
            .stats-card { margin-top: 18px; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .hero-main h2 { font-size: 34px; }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <header class="topbar glass">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-truck-medical"></i></div>
                <div class="brand-text">
                    <h1><?= e($text['app_name']) ?></h1>
                    <p><?= e($text['page_subtitle']) ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="lang-toggle">
                    <a href="<?= e(lang_link('en')) ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                    <a href="<?= e(lang_link('ar')) ?>" class="<?= $lang === 'ar' ? 'active' : '' ?>">AR</a>
                </div>
                <a class="btn btn-dark" href="admin_dashboard.php"><i class="fa-solid fa-house"></i> <?= e($text['dashboard']) ?></a>
                <a class="btn btn-blue" href="admin_emergency.php?lang=<?= e($lang) ?>"><i class="fa-solid fa-truck-medical"></i> <?= e($text['emergency_panel']) ?></a>
                <a class="btn btn-red" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <?= e($text['logout']) ?></a>
            </div>
        </header>

        <section class="hero">
            <div class="hero-main">
                <div class="badge"><i class="fa-solid fa-shield-heart"></i> <?= e($text['hero_badge']) ?></div>
                <h2><?= e($text['hero_title']) ?></h2>
                <p><?= e($text['hero_desc']) ?></p>
            </div>

            <aside class="stats-card glass">
                <div class="stat-box">
                    <span><?= e($text['total_cases']) ?></span>
                    <strong><?= (int)$stats['total'] ?></strong>
                </div>
                <div class="stat-box">
                    <span><?= e($text['active_cases']) ?></span>
                    <strong><?= (int)$stats['active'] ?></strong>
                </div>
                <div class="stat-box">
                    <span><?= e($text['closed_cases']) ?></span>
                    <strong><?= (int)$stats['closed'] ?></strong>
                </div>
                <div class="stat-box">
                    <span><?= e($text['expired_cases']) ?></span>
                    <strong><?= (int)$stats['expired'] ?></strong>
                </div>
            </aside>
        </section>

        <?php if ($message !== ''): ?>
            <div class="alert <?= e($messageType) ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <section class="table-section glass">
            <div class="section-head">
                <div class="section-icon"><i class="fa-solid fa-list-check"></i></div>
                <h3><?= e($text['table_title']) ?></h3>
            </div>

            <?php if (empty($cases)): ?>
                <div class="empty">
                    <i class="fa-solid fa-folder-open" style="font-size: 46px; margin-bottom: 14px; opacity: 0.75;"></i>
                    <p><?= e($text['no_cases']) ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><?= e($text['id']) ?></th>
                                <th><?= e($text['patient']) ?></th>
                                <th><?= e($text['national_id']) ?></th>
                                <th><?= e($text['doctor']) ?></th>
                                <th><?= e($text['code']) ?></th>
                                <th><?= e($text['severity']) ?></th>
                                <th><?= e($text['reason']) ?></th>
                                <th><?= e($text['created_at']) ?></th>
                                <th><?= e($text['expires_at']) ?></th>
                                <th><?= e($text['closed_at']) ?></th>
                                <th><?= e($text['status']) ?></th>
                                <th><?= e($text['actions']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                                <?php $status = $case['status'] ?? 'Active'; ?>
                                <tr>
                                    <td>#<?= (int)$case['id'] ?></td>
                                    <td><strong><?= e($case['patient_name'] ?? '-') ?></strong></td>
                                    <td><?= e($case['patient_national_id'] ?? '-') ?></td>
                                    <td><?= e($case['doctor_username'] ?? '-') ?></td>
                                    <td><span class="code-badge"><i class="fa-solid fa-barcode"></i> <?= e($case['emergency_code'] ?? '-') ?></span></td>
                                    <td><?= e($case['severity_level'] ?? '-') ?></td>
                                    <td class="reason-cell"><?= e($case['emergency_reason'] ?? '-') ?></td>
                                    <td><?= e($case['created_at'] ?? '-') ?></td>
                                    <td><?= e($case['expires_at'] ?? '-') ?></td>
                                    <td><?= e($case['closed_at'] ?? '-') ?></td>
                                    <td><span class="status <?= e($status) ?>"><?= e($status) ?></span></td>
                                    <td>
                                        <div class="actions">
                                            <form method="POST" onsubmit="return confirm('<?= e($text['confirm_close']) ?>');">
                                                <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                <button class="mini-btn close" type="submit" name="close_case" <?= $status !== 'Active' ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-lock"></i> <?= e($text['close']) ?>
                                                </button>
                                            </form>

                                            <form method="POST" onsubmit="return confirm('<?= e($text['confirm_delete']) ?>');">
                                                <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
                                                <button class="mini-btn delete" type="submit" name="delete_case">
                                                    <i class="fa-solid fa-trash"></i> <?= e($text['delete']) ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <footer>
            © 2026 <?= e($text['app_name']) ?> — <?= e($text['footer']) ?>
        </footer>
    </div>
</body>
</html>
