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
   Helper
========================= */
function lang_link_my_emergency_history($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

/* =========================
   Texts
========================= */
$text = [
    'app_name'         => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'       => ($lang === 'ar') ? 'سجل الطوارئ الخاص بي' : 'My Emergency History',
    'back_emergency'   => ($lang === 'ar') ? 'العودة إلى الطوارئ' : 'Back to Emergency',
    'logout'           => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),

    'hero_badge'       => ($lang === 'ar') ? 'سجل الطوارئ' : 'Emergency History',
    'hero_title'       => ($lang === 'ar') ? 'حالات الطوارئ الحالية والسابقة' : 'My Current & Previous Cases',
    'hero_desc'        => ($lang === 'ar')
        ? 'من هذه الصفحة يمكن للطبيب مراجعة جميع حالات الطوارئ التي قام بإنشائها.'
        : 'From this page, the doctor can review all emergency cases created by them.',

    'records_count'    => ($lang === 'ar') ? 'عدد الحالات' : 'Cases Count',
    'doctor_name'      => ($lang === 'ar') ? 'اسم الدكتور' : 'Doctor Name',
    'status_label'     => ($lang === 'ar') ? 'حالة البيانات' : 'Data Status',
    'available'        => ($lang === 'ar') ? 'متاح' : 'Available',

    'case_id'          => ($lang === 'ar') ? 'رقم الحالة' : 'Case ID',
    'case_code'        => ($lang === 'ar') ? 'كود الطوارئ' : 'Emergency Code',
    'patient_name'     => ($lang === 'ar') ? 'اسم المريض' : 'Patient Name',
    'national_id'      => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'severity'         => ($lang === 'ar') ? 'درجة الخطورة' : 'Severity Level',
    'reason'           => ($lang === 'ar') ? 'سبب الحالة' : 'Emergency Reason',
    'status'           => ($lang === 'ar') ? 'الحالة' : 'Status',
    'created_at'       => ($lang === 'ar') ? 'وقت الإنشاء' : 'Created At',
    'closed_at'        => ($lang === 'ar') ? 'وقت الإغلاق' : 'Closed At',
    'expires_at'       => ($lang === 'ar') ? 'وقت الانتهاء' : 'Expires At',
    'actions'          => ($lang === 'ar') ? 'الإجراءات' : 'Actions',
    'history_btn'      => ($lang === 'ar') ? 'عرض History المريض' : 'View Patient History',

    'active'           => ($lang === 'ar') ? 'نشطة' : 'Active',
    'closed'           => ($lang === 'ar') ? 'مغلقة' : 'Closed',
    'expired'          => ($lang === 'ar') ? 'منتهية' : 'Expired',
    'not_available'    => ($lang === 'ar') ? 'غير متوفر' : 'Unavailable',
    'empty'            => ($lang === 'ar') ? 'لا توجد حالات طوارئ لهذا الطبيب حتى الآن.' : 'No emergency cases for this doctor yet.',
];

/* =========================
   Fetch Doctor Cases
========================= */
$doctorCases = [];

$stmt = $conn->prepare("
    SELECT
        id,
        patient_id,
        patient_name,
        patient_national_id,
        emergency_code,
        severity_level,
        emergency_reason,
        status,
        created_at,
        closed_at,
        expires_at
    FROM emergency_cases
    WHERE doctor_login_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $doctorLoginId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $doctorCases[] = $row;
}
$stmt->close();
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
            --danger-1: #ef4444;
            --danger-2: #dc2626;
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
            width: min(1400px, calc(100% - 32px));
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

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.10);
        }

        table {
            width: 100%;
            min-width: 1200px;
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
        .status-expired { color: #fde68a; font-weight: 800; }

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
                width: min(100% - 18px, 1400px);
                margin: 12px auto 24px;
            }

            .topbar, .panel, .hero-main, .hero-side {
                padding: 18px;
            }

            .hero-main h2 { font-size: 26px; }
            .panel-title-row h3 { font-size: 19px; }

            .back-btn, .logout-btn, .action-btn {
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
                <i class="fa-solid fa-list-check"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_my_emergency_history('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_my_emergency_history('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="doctor_emergency.php?lang=<?= urlencode($lang) ?>" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= htmlspecialchars($text['back_emergency']) ?></span>
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
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['records_count']) ?></div>
                <div class="value"><?= htmlspecialchars((string)count($doctorCases)) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['doctor_name']) ?></div>
                <div class="value"><?= htmlspecialchars($doctorName) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['status_label']) ?></div>
                <div class="value"><?= htmlspecialchars($text['available']) ?></div>
            </div>
        </div>
    </section>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="icon">
                <i class="fa-solid fa-list"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['hero_title']) ?></h3>
                <p><?= htmlspecialchars($doctorName) ?></p>
            </div>
        </div>

        <?php if (empty($doctorCases)): ?>
            <div class="empty-box">
                <i class="fa-regular fa-folder-open" style="font-size:34px; margin-bottom:12px;"></i><br>
                <?= htmlspecialchars($text['empty']) ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars($text['case_id']) ?></th>
                            <th><?= htmlspecialchars($text['case_code']) ?></th>
                            <th><?= htmlspecialchars($text['patient_name']) ?></th>
                            <th><?= htmlspecialchars($text['national_id']) ?></th>
                            <th><?= htmlspecialchars($text['severity']) ?></th>
                            <th><?= htmlspecialchars($text['reason']) ?></th>
                            <th><?= htmlspecialchars($text['status']) ?></th>
                            <th><?= htmlspecialchars($text['created_at']) ?></th>
                            <th><?= htmlspecialchars($text['closed_at']) ?></th>
                            <th><?= htmlspecialchars($text['expires_at']) ?></th>
                            <th><?= htmlspecialchars($text['actions']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctorCases as $case): ?>
                            <?php
                            $statusValue = $case['status'] ?? '';
                            $statusClass = ($statusValue === 'Active') ? 'status-active' : (($statusValue === 'Closed') ? 'status-closed' : 'status-expired');
                            $statusText  = ($statusValue === 'Active') ? $text['active'] : (($statusValue === 'Closed') ? $text['closed'] : $text['expired']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$case['id']) ?></td>
                                <td><?= htmlspecialchars($case['emergency_code'] ?? '') ?></td>
                                <td><?= htmlspecialchars($case['patient_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($case['patient_national_id'] ?? '') ?></td>
                                <td><?= htmlspecialchars($case['severity_level'] ?? '') ?></td>
                                <td><?= nl2br(htmlspecialchars($case['emergency_reason'] ?? '')) ?></td>
                                <td class="<?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></td>
                                <td><?= htmlspecialchars($case['created_at'] ?? '') ?></td>
                                <td><?= htmlspecialchars($case['closed_at'] ?? $text['not_available']) ?></td>
                                <td><?= htmlspecialchars($case['expires_at'] ?? $text['not_available']) ?></td>
                                <td>
                                    <a href="doctor_patient_emergency_history.php?patient_id=<?= urlencode((string)$case['patient_id']) ?>&case_id=<?= urlencode((string)$case['id']) ?>&lang=<?= urlencode($lang) ?>" class="action-btn green">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                        <span><?= htmlspecialchars($text['history_btn']) ?></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> —
        <?= ($lang === 'ar') ? 'واجهة سجل طوارئ احترافية' : 'Professional Emergency History Interface' ?>
    </div>

</div>

</body>
</html>