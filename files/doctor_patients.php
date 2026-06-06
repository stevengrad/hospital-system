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

$doctorID   = (int)($_SESSION['user_id'] ?? 0);
$username   = $_SESSION['username'] ?? 'Doctor';
$doctorName = $username;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* =========================
   Get Doctor Display Name
========================= */
try {
    $stmt = $conn->prepare("
        SELECT FirstName, LastName
        FROM employees
        WHERE EmployeeID = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $full = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));
        if ($full !== '') {
            $doctorName = $full;
        }
    }

    $stmt->close();
} catch (Exception $e) {
    // fallback to username
}

/* =========================
   Fetch Patients List
========================= */
$patientsData = [];

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.PatientID,
            p.FirstName,
            p.LastName,
            p.NationalID,
            p.ContactPhone,
            p.Email,
            p.DOB,
            p.Gender,
            p.BloodType,
            p.Address
        FROM appointments a
        INNER JOIN patients p ON a.PatientID = p.PatientID
        WHERE a.DoctorID = ?
        ORDER BY p.FirstName, p.LastName
    ");
    $stmt->bind_param("i", $doctorID);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $patientsData[] = $row;
    }

    $stmt->close();
} catch (Exception $e) {
    $patientsData = [];
}

$totalPatients = count($patientsData);

/* =========================
   Text
========================= */
$text = [
    'app_name'        => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'      => ($lang === 'ar') ? 'مرضاي' : 'My Patients',
    'dashboard'       => ($lang === 'ar') ? 'لوحة الطبيب' : 'Doctor Dashboard',
    'logout'          => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'hero_badge'      => ($lang === 'ar') ? 'إدارة المرضى والسجلات' : 'Patients & Records Management',
    'hero_title'      => ($lang === 'ar') ? 'مرضى د. ' . $doctorName : 'Patients of Dr. ' . $doctorName,
    'hero_desc'       => ($lang === 'ar')
        ? 'عرض المرضى المرتبطين بك والوصول السريع إلى تقاريرهم الطبية.'
        : 'View patients associated with you and access their medical reports quickly.',
    'total_patients'  => ($lang === 'ar') ? 'إجمالي المرضى' : 'Total Patients',
    'records_access'  => ($lang === 'ar') ? 'الوصول للسجلات' : 'Records Access',
    'active'          => ($lang === 'ar') ? 'نشط' : 'Active',
    'note'            => ($lang === 'ar') ? 'ملاحظة' : 'Note',
    'note_text'       => ($lang === 'ar')
        ? 'يتم عرض المرضى الذين لديهم مواعيد مرتبطة بك فقط.'
        : 'Only patients with appointments linked to you are shown.',
    'patients_list'   => ($lang === 'ar') ? 'قائمة المرضى' : 'Patients List',
    'patients_desc'   => ($lang === 'ar')
        ? 'هذه قائمة المرضى الذين لديهم مواعيد معك.'
        : 'This is the list of patients who have appointments with you.',
    'empty'           => ($lang === 'ar') ? 'لا توجد مرضى مرتبطين بك حاليًا.' : 'You currently have no patients assigned.',
    'name'            => ($lang === 'ar') ? 'الاسم' : 'Name',
    'national_id'     => ($lang === 'ar') ? 'الرقم القومي' : 'National ID',
    'phone'           => ($lang === 'ar') ? 'الهاتف' : 'Phone',
    'email'           => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'dob'             => ($lang === 'ar') ? 'تاريخ الميلاد' : 'DOB',
    'gender'          => ($lang === 'ar') ? 'الجنس' : 'Gender',
    'blood'           => ($lang === 'ar') ? 'الدم' : 'Blood',
    'address'         => ($lang === 'ar') ? 'العنوان' : 'Address',
    'reports'         => ($lang === 'ar') ? 'التقارير' : 'Reports',
    'report_action'   => ($lang === 'ar') ? 'عرض / إضافة تقارير' : 'View / Add Reports',
];

function lang_link_patients($langCode) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $langCode;
}
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

        .content-panel {
            border-radius: 30px;
            padding: 24px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .panel-head .icon {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.20));
            color: #dff8ff;
            font-size: 18px;
        }

        .panel-head h3 {
            margin: 0;
            font-size: 23px;
        }

        .panel-head p {
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
            min-width: 1100px;
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
        }

        tr:last-child td {
            border-bottom: none;
        }

        .patient-name {
            font-weight: 800;
            color: #ffffff;
        }

        .report-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 12px;
            color: #fff;
            background: rgba(76,201,240,0.16);
            border: 1px solid rgba(76,201,240,0.25);
            transition: 0.25s ease;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .report-link:hover {
            transform: translateY(-2px);
            background: rgba(76,201,240,0.22);
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
            .content-panel,
            .hero-main,
            .hero-side {
                padding: 18px;
            }

            .hero-main h2 {
                font-size: 26px;
            }

            .panel-head h3 {
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
                <i class="fa-solid fa-hospital-user"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_patients('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_patients('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
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
                <i class="fa-solid fa-user-group"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['total_patients']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$totalPatients) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['records_access']) ?></div>
                <div class="value"><?= htmlspecialchars($text['active']) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['note']) ?></div>
                <div class="value" style="font-size:15px; line-height:1.7;">
                    <?= htmlspecialchars($text['note_text']) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="content-panel glass">
        <div class="panel-head">
            <div class="icon">
                <i class="fa-solid fa-list"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['patients_list']) ?></h3>
                <p><?= htmlspecialchars($text['patients_desc']) ?></p>
            </div>
        </div>
                <?php if (empty($patientsData)): ?>
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
                            <th><?= htmlspecialchars($text['name']) ?></th>
                            <th><?= htmlspecialchars($text['national_id']) ?></th>
                            <th><?= htmlspecialchars($text['phone']) ?></th>
                            <th><?= htmlspecialchars($text['email']) ?></th>
                            <th><?= htmlspecialchars($text['dob']) ?></th>
                            <th><?= htmlspecialchars($text['gender']) ?></th>
                            <th><?= htmlspecialchars($text['blood']) ?></th>
                            <th><?= htmlspecialchars($text['address']) ?></th>
                            <th><?= htmlspecialchars($text['reports']) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                                                <?php $i = 1; ?>
                        <?php foreach ($patientsData as $p): ?>
                            <?php $fullName = trim(($p['FirstName'] ?? '') . ' ' . ($p['LastName'] ?? '')); ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td class="patient-name"><?= htmlspecialchars($fullName) ?></td>
                                <td><?= htmlspecialchars($p['NationalID'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['ContactPhone'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['Email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['DOB'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['Gender'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['BloodType'] ?? '') ?></td>
                                <td><?= htmlspecialchars($p['Address'] ?? '') ?></td>
                                <td>
                                    <a
    href="doctor_reports.php?patient_id=<?= (int)$p['PatientID'] ?>"
    class="report-link">
    <i class="fa-solid fa-file-medical"></i>
    <span><?= htmlspecialchars($text['report_action']) ?></span>
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
        <?= ($lang === 'ar') ? 'واجهة مرضى احترافية للطبيب' : 'Professional Doctor Patients Interface' ?>
    </div>

</div>

</body>
</html>