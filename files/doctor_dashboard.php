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
   Doctor Data
========================= */
$employeeID = (int)($_SESSION['user_id'] ?? 0);
$doctorName = $_SESSION['doctor_name'] ?? $_SESSION['username'] ?? 'Doctor';

/* =========================
   Texts
========================= */
$text = [
    'app_name'             => $T['app_name'] ?? 'Cairo Hospitals',
    'dashboard_title'      => ($lang === 'ar') ? 'لوحة تحكم الطبيب' : 'Doctor Dashboard',
    'welcome_title'        => ($lang === 'ar') ? 'مرحبًا، د. ' . $doctorName : 'Hello, Dr. ' . $doctorName,
    'welcome_sub'          => ($lang === 'ar')
        ? 'يمكنك من هنا إدارة ملفك الشخصي، المواعيد، المرضى، والتقارير الطبية بسهولة واحترافية.'
        : 'From here you can manage your profile, appointments, patients, and medical reports professionally and with ease.',
    'overview_badge'       => ($lang === 'ar') ? 'بوابة الطبيب الذكية' : 'Smart Doctor Portal',
    'overview_desc'        => ($lang === 'ar')
        ? 'واجهة متكاملة تساعدك على الوصول السريع لكل الوظائف الأساسية.'
        : 'An integrated interface that gives you fast access to all essential functions.',

    'profile'              => ($lang === 'ar') ? 'ملفي الشخصي' : 'My Profile',
    'profile_desc'         => ($lang === 'ar') ? 'عرض وتحديث بياناتك الشخصية والمهنية.' : 'View and update your personal and professional data.',

    'appointments'         => ($lang === 'ar') ? 'مواعيدي' : 'My Appointments',
    'appointments_desc'    => ($lang === 'ar') ? 'متابعة مواعيد اليوم والمواعيد القادمة.' : 'Track today’s and upcoming appointments.',

    'patients'             => ($lang === 'ar') ? 'مرضاي' : 'My Patients',
    'patients_desc'        => ($lang === 'ar') ? 'استعراض المرضى والسجلات المرتبطة بهم.' : 'Browse your patients and their related records.',

    'reports'              => ($lang === 'ar') ? 'التقارير' : 'Reports',
    'reports_desc'         => ($lang === 'ar') ? 'إنشاء وتعديل التقارير الطبية للمرضى.' : 'Create and edit patient medical reports.',

    'schedule'             => ($lang === 'ar') ? 'جدول العمل' : 'Schedule',
    'schedule_desc'        => ($lang === 'ar') ? 'تنظيم أوقات العمل والمتابعة اليومية.' : 'Organize work hours and daily follow-up.',

    'messages'             => ($lang === 'ar') ? 'الرسائل' : 'Messages',
    'messages_desc'        => ($lang === 'ar') ? 'تابع الإشعارات والرسائل المهمة بسرعة.' : 'Keep up with important messages and notifications.',

    'quick_access'         => ($lang === 'ar') ? 'الوصول السريع' : 'Quick Access',
    'logout'               => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'en'                   => 'EN',
    'ar'                   => 'AR',
    'role_label'           => ($lang === 'ar') ? 'نوع الحساب' : 'Account Type',
    'role_value'           => ($lang === 'ar') ? 'طبيب' : 'Doctor',
    'id_label'             => ($lang === 'ar') ? 'رقم الحساب' : 'Account ID',
    'portal_note'          => ($lang === 'ar')
        ? 'هذه الصفحة مخصصة لحسابات الأطباء فقط.'
        : 'This page is restricted to doctor accounts only.',

    /* Emergency */
    'emergency'            => ($lang === 'ar') ? 'الطوارئ' : 'Emergency',
    'emergency_badge'      => ($lang === 'ar') ? 'حالة عاجلة' : 'EMERGENCY',
    'emergency_desc'       => ($lang === 'ar')
        ? 'الوصول السريع إلى صفحة الطوارئ لإنشاء حالة طارئة ومتابعتها بأمان.'
        : 'Quick access to the emergency page to create and follow up urgent cases securely.',
    'emergency_cta'        => ($lang === 'ar') ? 'فتح صفحة الطوارئ' : 'Open Emergency Page',
];

/* =========================
   Helper
========================= */
function lang_link_doctor_dashboard($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($text['dashboard_title']) ?> - <?= htmlspecialchars($text['app_name']) ?></title>

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
            --card-2: rgba(255,255,255,0.08);
            --stroke: rgba(255,255,255,0.14);
            --primary: #4cc9f0;
            --primary-2: #4895ef;
            --violet: #7b2ff7;
            --green: #22c55e;
            --orange: #f59e0b;
            --danger-1: #ef4444;
            --danger-2: #dc2626;
            --danger-3: #991b1b;
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

        .brand-text {
            min-width: 0;
        }

        .brand-text h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        .emergency-top-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--danger-1), var(--danger-2));
            box-shadow: 0 12px 28px rgba(239,68,68,0.34);
            transition: 0.25s ease;
        }

        .emergency-top-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(239,68,68,0.42);
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 25px rgba(239,68,68,0.30);
            transition: 0.25s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.25fr 0.75fr;
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
                linear-gradient(135deg, #6d28d9, #2563eb 62%, #38bdf8);
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
        }

        .panel {
            border-radius: 30px;
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
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.20));
            color: #dff8ff;
            font-size: 18px;
        }

        .panel-title h3 {
            margin: 0;
            font-size: 23px;
        }

        .panel-title p {
            margin: 4px 0 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(235px, 1fr));
            gap: 18px;
        }

        .dash-card {
            position: relative;
            overflow: hidden;
            display: block;
            min-height: 210px;
            padding: 22px 20px;
            border-radius: 24px;
            color: #fff;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.10), rgba(255,255,255,0.06)),
                rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 18px 35px rgba(0,0,0,0.18);
            transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease;
        }

        .dash-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 44px rgba(0,0,0,0.26);
            border-color: rgba(76,201,240,0.30);
        }

        .dash-card::before {
            content: "";
            position: absolute;
            top: -42px;
            right: -42px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(76,201,240,0.22), transparent 60%);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 24px;
            margin-bottom: 16px;
            border: 1px solid rgba(255,255,255,0.10);
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.22));
        }

        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .8px;
            text-transform: uppercase;
            margin-bottom: 10px;
            padding: 5px 10px;
            border-radius: 999px;
            color: #d7f7ff;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.10);
        }
                .dash-card h4 {
            margin: 0 0 8px;
            font-size: 20px;
            font-weight: 800;
        }

        .dash-card p {
            margin: 0;
            color: var(--text-soft);
            line-height: 1.75;
            font-size: 14px;
            max-width: 92%;
        }

        .emergency-card {
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 30%),
                linear-gradient(135deg, rgba(127,29,29,0.92), rgba(220,38,38,0.94), rgba(239,68,68,0.88));
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 22px 45px rgba(127,29,29,0.42);
        }

        .emergency-card:hover {
            border-color: rgba(255,255,255,0.30);
            box-shadow: 0 28px 50px rgba(127,29,29,0.55);
        }

        .emergency-card::before {
            background: radial-gradient(circle, rgba(255,255,255,0.20), transparent 62%);
        }

        .emergency-card .card-icon {
            background: linear-gradient(135deg, rgba(255,255,255,0.18), rgba(255,255,255,0.10));
            box-shadow: 0 10px 24px rgba(0,0,0,0.18);
        }

        .emergency-card .badge {
            color: #fff5f5;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
        }

        .emergency-card p {
            color: rgba(255,255,255,0.92);
        }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.62);
            font-size: 13px;
            margin-top: 18px;
        }

        @media (max-width: 1100px) {
            .hero {
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
                width: min(100% - 18px, 1280px);
                margin: 12px auto 24px;
            }

            .topbar,
            .panel,
            .hero-main,
            .hero-side {
                padding: 18px;
            }

            .hero-main h2 {
                font-size: 26px;
            }

            .panel-title h3 {
                font-size: 19px;
            }

            .logout-btn,
            .emergency-top-btn {
                width: 100%;
                justify-content: center;
            }

            .brand-text h1 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['dashboard_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_doctor_dashboard('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_doctor_dashboard('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="doctor_emergency.php" class="emergency-top-btn">
                <i class="fa-solid fa-truck-medical"></i>
                <span><?= htmlspecialchars($text['emergency']) ?></span>
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
                <i class="fa-solid fa-stethoscope"></i>
                <span><?= htmlspecialchars($text['overview_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['welcome_title']) ?></h2>

            <p><?= htmlspecialchars($text['welcome_sub']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['role_label']) ?></div>
                <div class="value"><?= htmlspecialchars($text['role_value']) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['id_label']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$employeeID) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'ملاحظة' : 'Note' ?></div>
                <div class="value" style="font-size:15px; line-height:1.7; font-weight:700;">
                    <?= htmlspecialchars($text['portal_note']) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="panel-title">
                <div class="icon">
                    <i class="fa-solid fa-table-cells-large"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['quick_access']) ?></h3>
                    <p><?= htmlspecialchars($text['overview_desc']) ?></p>
                </div>
            </div>
        </div>

        <div class="cards-grid">

            <a href="doctor_emergency.php" class="dash-card emergency-card">
                <div class="card-icon"><i class="fa-solid fa-truck-medical"></i></div>
                <div class="badge"><?= htmlspecialchars($text['emergency_badge']) ?></div>
                <h4><?= htmlspecialchars($text['emergency']) ?></h4>
                <p><?= htmlspecialchars($text['emergency_desc']) ?></p>
            </a>

            <a href="doctor_profile.php" class="dash-card">
                <div class="card-icon"><i class="fa-solid fa-id-badge"></i></div>
                <div class="badge"><?= ($lang === 'ar') ? 'الملف الشخصي' : 'PROFILE' ?></div>
                <h4><?= htmlspecialchars($text['profile']) ?></h4>
                <p><?= htmlspecialchars($text['profile_desc']) ?></p>
            </a>

            <a href="doctor_appointments.php" class="dash-card">
                <div class="card-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="badge"><?= ($lang === 'ar') ? 'المواعيد' : 'APPOINTMENTS' ?></div>
                <h4><?= htmlspecialchars($text['appointments']) ?></h4>
                <p><?= htmlspecialchars($text['appointments_desc']) ?></p>
            </a>

            <a href="doctor_patients.php" class="dash-card">
                <div class="card-icon"><i class="fa-solid fa-hospital-user"></i></div>
                <div class="badge"><?= ($lang === 'ar') ? 'المرضى' : 'PATIENTS' ?></div>
                <h4><?= htmlspecialchars($text['patients']) ?></h4>
                <p><?= htmlspecialchars($text['patients_desc']) ?></p>
            </a>

            <a href="doctor_schedule.php" class="dash-card">
                <div class="card-icon"><i class="fa-solid fa-clock"></i></div>
                <div class="badge"><?= ($lang === 'ar') ? 'الجدول' : 'SCHEDULE' ?></div>
                <h4><?= htmlspecialchars($text['schedule']) ?></h4>
                <p><?= htmlspecialchars($text['schedule_desc']) ?></p>
            </a>

            <a href="doctor_messages.php" class="dash-card">
                <div class="card-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <div class="badge"><?= ($lang === 'ar') ? 'الرسائل' : 'MESSAGES' ?></div>
                <h4><?= htmlspecialchars($text['messages']) ?></h4>
                <p><?= htmlspecialchars($text['messages_desc']) ?></p>
            </a>

        </div>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> — <?= ($lang === 'ar') ? 'واجهة احترافية للأطباء' : 'Professional Doctor Interface' ?>
    </div>

</div>

</body>
</html>