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

$doctorId = (int)($_SESSION['user_id'] ?? 0);
$doctorName = $_SESSION['username'] ?? 'Doctor';
$errorMsg = '';

/* =========================
   Get doctor display name
========================= */
try {
    $stmt = $conn->prepare("
        SELECT FirstName, LastName
        FROM employees
        WHERE EmployeeID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $fullName = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
        if ($fullName !== '') {
            $doctorName = $fullName;
        }
    }

    $stmt->close();
} catch (Exception $e) {
    // fallback to session username
}

/* =========================
   Date / Week Handling
========================= */
date_default_timezone_set('Africa/Cairo');

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$monday = new DateTime('monday this week');
if ($weekOffset !== 0) {
    $monday->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' week');
}

$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $day = clone $monday;
    $day->modify("+$i day");
    $weekDays[] = $day;
}

$weekStart = $weekDays[0]->format('Y-m-d');
$weekEnd   = $weekDays[6]->format('Y-m-d');

/* =========================
   Text
========================= */
$text = [
    'app_name'         => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'       => ($lang === 'ar') ? 'جدول الطبيب' : 'Doctor Schedule',
    'dashboard'        => ($lang === 'ar') ? 'لوحة الطبيب' : 'Doctor Dashboard',
    'logout'           => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'hero_badge'       => ($lang === 'ar') ? 'إدارة الوقت والعيادة' : 'Time & Clinic Management',
    'hero_title'       => ($lang === 'ar') ? 'جدول د. ' . $doctorName : 'Schedule of Dr. ' . $doctorName,
    'hero_desc'        => ($lang === 'ar')
        ? 'تابع جدولك الأسبوعي والمواعيد المرتبطة بك في واجهة احترافية ومنظمة.'
        : 'Track your weekly schedule and related appointments in a clean professional interface.',
    'week_schedule'    => ($lang === 'ar') ? 'الجدول الأسبوعي' : 'Weekly Schedule',
    'week_schedule_d'  => ($lang === 'ar')
        ? 'نظرة سريعة على الأيام الحالية والمواعيد المرتبطة بها.'
        : 'A quick overview of the current week and its related appointments.',
    'prev_week'        => ($lang === 'ar') ? 'الأسبوع السابق' : 'Previous Week',
    'next_week'        => ($lang === 'ar') ? 'الأسبوع التالي' : 'Next Week',
    'today'            => ($lang === 'ar') ? 'اليوم' : 'Today',
    'no_appointments'  => ($lang === 'ar') ? 'لا توجد مواعيد' : 'No appointments',
    'appointments'     => ($lang === 'ar') ? 'المواعيد' : 'Appointments',
    'time'             => ($lang === 'ar') ? 'الوقت' : 'Time',
    'patient'          => ($lang === 'ar') ? 'المريض' : 'Patient',
    'service'          => ($lang === 'ar') ? 'الخدمة' : 'Service',
    'status'           => ($lang === 'ar') ? 'الحالة' : 'Status',
    'summary'          => ($lang === 'ar') ? 'ملخص سريع' : 'Quick Summary',
    'summary_desc'     => ($lang === 'ar')
        ? 'إحصائيات مبسطة لأسبوعك الحالي.'
        : 'Simple statistics for your current week.',
    'days'             => ($lang === 'ar') ? 'الأيام' : 'Days',
    'total_appointments' => ($lang === 'ar') ? 'إجمالي المواعيد' : 'Total Appointments',
    'active_week'      => ($lang === 'ar') ? 'الأسبوع الحالي' : 'Current Week',
    'note'             => ($lang === 'ar') ? 'ملاحظة' : 'Note',
    'note_text'        => ($lang === 'ar')
        ? 'إذا لم تظهر بيانات، فقد لا تكون المواعيد مربوطة بجدول الطبيب في قاعدة البيانات بعد.'
        : 'If no data appears, appointments may not yet be linked to the doctor schedule in the database.',
    'not_set'          => ($lang === 'ar') ? 'غير محدد' : 'Not set',
];

/* =========================
   Day names
========================= */
$dayNames = [
    'en' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
    'ar' => ['الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد']
];

/* =========================
   Try to fetch appointments
   Flexible query with fallbacks
========================= */
$appointmentsByDate = [];
$totalAppointments = 0;

foreach ($weekDays as $day) {
    $appointmentsByDate[$day->format('Y-m-d')] = [];
}

try {
    $query = "
        SELECT
            a.AppointmentID,
            a.AppointmentDate,
            a.AppointmentTime,
            a.Status,
            COALESCE(p.FullName, CONCAT(p.FirstName, ' ', p.LastName), l.username, 'Patient') AS PatientName,
            s.Name AS ServiceName
        FROM appointments a
        LEFT JOIN patients p ON a.PatientID = p.PatientID
        LEFT JOIN login l ON a.PatientID = l.id
        LEFT JOIN services s ON a.ServiceID = s.ServiceID
        WHERE a.DoctorID = ?
          AND DATE(a.AppointmentDate) BETWEEN ? AND ?
        ORDER BY a.AppointmentDate ASC, a.AppointmentTime ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $doctorId, $weekStart, $weekEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dateKey = date('Y-m-d', strtotime($row['AppointmentDate']));
        if (!isset($appointmentsByDate[$dateKey])) {
            $appointmentsByDate[$dateKey] = [];
        }
        $appointmentsByDate[$dateKey][] = $row;
        $totalAppointments++;
    }

    $stmt->close();

} catch (Exception $e) {
    // if the structure differs, page still works
}
function lang_link_doctor_schedule($code, $weekOffset) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code . '&week=' . urlencode((string)$weekOffset);
}

function week_link($weekOffset, $lang) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . urlencode($lang) . '&week=' . urlencode((string)$weekOffset);
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
            --success: #22c55e;
            --warning: #f59e0b;
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
        }

        .panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
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

        .week-nav {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .week-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            transition: 0.25s ease;
        }

        .week-btn:hover {
            transform: translateY(-2px);
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(180px, 1fr));
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .day-card {
            min-width: 180px;
            border-radius: 24px;
            padding: 18px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .day-card.today {
            border-color: rgba(76,201,240,0.45);
            box-shadow: 0 0 0 1px rgba(76,201,240,0.22) inset;
        }

        .day-name {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .day-date {
            color: var(--text-soft);
            font-size: 13px;
            margin-bottom: 14px;
        }

        .appt-item {
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 12px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .appt-item:last-child {
            margin-bottom: 0;
        }

        .appt-time {
            font-size: 13px;
            font-weight: 800;
            color: #bff6ff;
            margin-bottom: 8px;
        }

        .appt-line {
            font-size: 13px;
            line-height: 1.7;
            color: rgba(255,255,255,0.90);
            margin-bottom: 4px;
        }

        .appt-line span {
            color: var(--text-soft);
            font-weight: 600;
        }

        .empty-day {
            border-radius: 18px;
            padding: 16px;
            text-align: center;
            color: var(--text-soft);
            background: rgba(255,255,255,0.05);
            border: 1px dashed rgba(255,255,255,0.16);
            font-size: 13px;
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

            .nav-btn,
            .logout-btn,
            .week-btn {
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
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_doctor_schedule('en', $weekOffset)) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_doctor_schedule('ar', $weekOffset)) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

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
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-clock"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['active_week']) ?></div>
                <div class="value">
                    <?= htmlspecialchars($weekDays[0]->format('d M Y')) ?> - <?= htmlspecialchars($weekDays[6]->format('d M Y')) ?>
                </div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['days']) ?></div>
                <div class="value">7</div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['total_appointments']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$totalAppointments) ?></div>
            </div>
        </div>
    </section>

    <section class="panel glass">
        <div class="panel-head">
            <div class="panel-title">
                <div class="icon">
                    <i class="fa-solid fa-table-list"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['week_schedule']) ?></h3>
                    <p><?= htmlspecialchars($text['week_schedule_d']) ?></p>
                </div>
            </div>

            <div class="week-nav">
                <a href="<?= htmlspecialchars(week_link($weekOffset - 1, $lang)) ?>" class="week-btn">
                    <i class="fa-solid fa-chevron-left"></i>
                    <span><?= htmlspecialchars($text['prev_week']) ?></span>
                </a>

                <a href="<?= htmlspecialchars(week_link(0, $lang)) ?>" class="week-btn">
                    <i class="fa-solid fa-calendar-day"></i>
                    <span><?= htmlspecialchars($text['today']) ?></span>
                </a>

                <a href="<?= htmlspecialchars(week_link($weekOffset + 1, $lang)) ?>" class="week-btn">
                    <span><?= htmlspecialchars($text['next_week']) ?></span>
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
        </div>

                <div class="schedule-grid">
            <?php foreach ($weekDays as $index => $day): ?>
                <?php
                    $dateKey = $day->format('Y-m-d');
                    $items = $appointmentsByDate[$dateKey] ?? [];
                    $isToday = ($dateKey === date('Y-m-d'));
                    $dayName = $dayNames[$lang === 'ar' ? 'ar' : 'en'][$index] ?? $day->format('l');
                ?>
                <div class="day-card <?= $isToday ? 'today' : '' ?>">
                    <div class="day-name"><?= htmlspecialchars($dayName) ?></div>
                    <div class="day-date"><?= htmlspecialchars($day->format('d/m/Y')) ?></div>

                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $appt): ?>
                            <div class="appt-item">
                                <div class="appt-time">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= htmlspecialchars(!empty($appt['AppointmentTime']) ? date('h:i A', strtotime($appt['AppointmentTime'])) : $text['not_set']) ?>
                                </div>

                                <div class="appt-line">
                                    <span><?= htmlspecialchars($text['patient']) ?>:</span>
                                    <?= htmlspecialchars($appt['PatientName'] ?? $text['not_set']) ?>
                                </div>

                                <div class="appt-line">
                                    <span><?= htmlspecialchars($text['service']) ?>:</span>
                                    <?= htmlspecialchars($appt['ServiceName'] ?? $text['not_set']) ?>
                                </div>

                                <div class="appt-line">
                                    <span><?= htmlspecialchars($text['status']) ?>:</span>
                                    <?= htmlspecialchars($appt['Status'] ?? $text['not_set']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-day">
                            <i class="fa-regular fa-calendar-xmark"></i><br><br>
                            <?= htmlspecialchars($text['no_appointments']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel glass" style="margin-top:22px;">
        <div class="panel-title">
            <div class="icon">
                <i class="fa-solid fa-chart-simple"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['summary']) ?></h3>
                <p><?= htmlspecialchars($text['summary_desc']) ?></p>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-top:18px;">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['total_appointments']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$totalAppointments) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['active_week']) ?></div>
                <div class="value">
                    <?= htmlspecialchars($weekStart) ?> → <?= htmlspecialchars($weekEnd) ?>
                </div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['note']) ?></div>
                <div class="value" style="font-size:15px; line-height:1.7;">
                    <?= htmlspecialchars($text['note_text']) ?>
                </div>
            </div>
        </div>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> — <?= ($lang === 'ar') ? 'واجهة جدول احترافية للطبيب' : 'Professional Doctor Schedule Interface' ?>
    </div>
</div>

</body>
</html>