<?php
session_start();
include('db_connect.php');

// Protect admin area
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

/* -----------------------------------------------------------
   Gate to allow direct URL access to reset_doctor_passwords.php
   (works for 5 minutes after loading the dashboard)
----------------------------------------------------------- */
$_SESSION['reset_pw_gate'] = true;
$_SESSION['reset_pw_gate_expires'] = time() + 300; // 5 minutes

// Handle language safely (only en / ar)
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$texts = [
    'en' => [
        'title'           => 'Admin Dashboard',
        'welcome'         => 'Welcome, Admin ',
        'manage_doctors'  => 'Manage Doctors',
        'manage_users'    => 'Manage Users',
        'manage_services' => 'Manage Services',
        'appointments'    => 'Appointments',
        'reports'         => 'Reports',
        'messages'        => 'Messages',
        'emergency'       => 'Emergency',
        'emergency_nav'   => 'Emergency Panel',
        'logout'          => 'Logout',
        'hospital'        => 'Hospital Admin Panel',
    ],
    'ar' => [
        'title'           => 'لوحة تحكم المسؤول',
        'welcome'         => 'مرحباً أيها المسؤول ',
        'manage_doctors'  => 'إدارة الأطباء',
        'manage_users'    => 'إدارة المستخدمين',
        'manage_services' => 'إدارة الخدمات',
        'appointments'    => 'المواعيد',
        'reports'         => 'التقارير',
        'messages'        => 'الرسائل',
        'emergency'       => 'الطوارئ',
        'emergency_nav'   => 'لوحة الطوارئ',
        'logout'          => 'تسجيل الخروج',
        'hospital'        => 'لوحة إدارة المستشفى',
    ]
];
$t = $texts[$lang];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $lang === 'ar' ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($t['title']) ?></title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root{
        --bg-1:#07121d;
        --bg-2:#0c1c2d;
        --bg-3:#12314e;
        --white:#ffffff;
        --text-soft:rgba(255,255,255,0.82);
        --text-faint:rgba(255,255,255,0.64);
        --card:rgba(255,255,255,0.10);
        --card-strong:rgba(255,255,255,0.14);
        --stroke:rgba(255,255,255,0.14);
        --primary:#4cc9f0;
        --primary-2:#4895ef;
        --danger-1:#ef4444;
        --danger-2:#dc2626;
        --warning-1:#f59e0b;
        --warning-2:#f97316;
        --shadow:0 20px 50px rgba(0, 0, 0, 0.28);
        --shadow-strong:0 24px 55px rgba(0,0,0,0.34);
        --radius-xl:30px;
        --radius-lg:22px;
        --radius-md:18px;
    }

    *{
        box-sizing:border-box;
    }

    html, body{
        margin:0;
        padding:0;
    }

    body{
        font-family:'Inter',"Cairo",Arial,sans-serif;
        color:var(--white);
        min-height:100vh;
        background:
            radial-gradient(circle at top left, rgba(76,201,240,0.16), transparent 24%),
            radial-gradient(circle at top right, rgba(239,68,68,0.12), transparent 22%),
            radial-gradient(circle at bottom center, rgba(72,149,239,0.12), transparent 28%),
            linear-gradient(135deg, var(--bg-1), var(--bg-2) 46%, var(--bg-3));
    }

    a{
        text-decoration:none;
    }

    .page-shell{
        width:min(1280px, calc(100% - 32px));
        margin:18px auto 28px;
    }

    .glass{
        background:var(--card);
        border:1px solid var(--stroke);
        backdrop-filter:blur(16px);
        -webkit-backdrop-filter:blur(16px);
        box-shadow:var(--shadow);
    }

    .navbar{
        position:sticky;
        top:14px;
        z-index:999;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:16px;
        padding:16px 22px;
        margin-bottom:22px;
        border-radius:28px;
        background:rgba(255,255,255,0.10);
        border:1px solid rgba(255,255,255,0.12);
        backdrop-filter:blur(18px);
        -webkit-backdrop-filter:blur(18px);
        box-shadow:var(--shadow-strong);
    }

    .navbar-left{
        display:flex;
        align-items:center;
        gap:14px;
        min-width:0;
    }

    .brand-icon{
        width:54px;
        height:54px;
        border-radius:18px;
        display:grid;
        place-items:center;
        background:linear-gradient(135deg, var(--primary), var(--primary-2));
        box-shadow:0 14px 30px rgba(72,149,239,0.34);
        font-size:22px;
        flex-shrink:0;
    }

    .navbar-left a{
        color:#fff;
        font-weight:800;
        font-size:22px;
        letter-spacing:-0.4px;
    }

    .navbar-left a:hover{
        opacity:0.92;
    }

    .navbar-right{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .quick-btn,
    .logout-btn{
        color:#fff;
        font-weight:700;
        padding:11px 16px;
        border-radius:14px;
        border:1px solid rgba(255,255,255,0.14);
        transition:0.25s ease;
        display:inline-flex;
        align-items:center;
        gap:8px;
        white-space:nowrap;
    }

    .quick-btn{
        background:linear-gradient(135deg, var(--danger-1), var(--danger-2));
        box-shadow:0 12px 26px rgba(220,38,38,0.28);
    }

    .quick-btn:hover{
        transform:translateY(-2px);
        box-shadow:0 16px 32px rgba(220,38,38,0.34);
    }

    .logout-btn{
        background:rgba(255,255,255,0.08);
    }

    .logout-btn:hover{
        transform:translateY(-2px);
        background:#fff;
        color:#111827;
    }

    .lang-toggle a{
        text-decoration:none;
    }

    .lang-toggle button{
        background:#ffc107;
        color:#111827;
        border:none;
        padding:10px 13px;
        border-radius:12px;
        font-weight:800;
        cursor:pointer;
        transition:0.25s ease;
        box-shadow:0 8px 22px rgba(245,158,11,0.22);
    }

    .lang-toggle button:hover{
        transform:translateY(-1px);
    }

    .hero{
        display:grid;
        grid-template-columns:1.1fr 0.9fr;
        gap:22px;
        margin-bottom:22px;
    }

    .hero-main{
        position:relative;
        overflow:hidden;
        border-radius:var(--radius-xl);
        padding:32px;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,0.13), transparent 26%),
            linear-gradient(135deg, rgba(17,24,39,0.92), rgba(30,41,59,0.92), rgba(59,130,246,0.72));
        border:1px solid rgba(255,255,255,0.12);
        box-shadow:var(--shadow-strong);
    }

    .hero-main::after{
        content:"";
        position:absolute;
        width:220px;
        height:220px;
        border-radius:50%;
        background:radial-gradient(circle, rgba(255,255,255,0.12), transparent 62%);
        bottom:-90px;
        right:-70px;
    }

    .hero-badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:9px 14px;
        border-radius:999px;
        background:rgba(255,255,255,0.14);
        border:1px solid rgba(255,255,255,0.16);
        font-size:13px;
        font-weight:700;
        margin-bottom:16px;
    }

    .hero-main h1{
        margin:0 0 12px;
        font-size:clamp(30px, 4vw, 42px);
        line-height:1.12;
        font-weight:800;
        letter-spacing:-1px;
    }

    .hero-main p{
        margin:0;
        max-width:720px;
        font-size:16px;
        line-height:1.8;
        color:var(--text-soft);
    }

    .hero-side{
        padding:22px;
        border-radius:var(--radius-xl);
        display:flex;
        flex-direction:column;
        gap:14px;
    }

    .mini-card{
        border-radius:20px;
        padding:18px;
        background:rgba(255,255,255,0.08);
        border:1px solid rgba(255,255,255,0.10);
    }

    .mini-card .label{
        font-size:13px;
        color:var(--text-faint);
        margin-bottom:8px;
    }

    .mini-card .value{
        font-size:18px;
        font-weight:800;
        line-height:1.6;
    }

    .container{
        text-align:center;
        padding:8px 6px 20px;
    }

    h2{
        font-size:26px;
        margin:0 0 10px;
        text-shadow:0 4px 18px rgba(0,0,0,0.18);
    }

    .subtitle{
        font-size:16px;
        color:var(--text-soft);
        margin:0;
    }

    .cards{
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
        gap:18px;
        margin-top:34px;
    }

    .card{
        background:rgba(255,255,255,0.10);
        border-radius:22px;
        padding:26px 20px;
        text-align:center;
        transition:0.3s ease;
        backdrop-filter:blur(10px);
        -webkit-backdrop-filter:blur(10px);
        border:1px solid rgba(255,255,255,0.12);
        box-shadow:0 16px 30px rgba(0,0,0,0.18);
        min-height:210px;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
    }

    .card:hover{
        background:rgba(255,255,255,0.16);
        transform:translateY(-6px);
        box-shadow:0 24px 40px rgba(0,0,0,0.24);
    }

    .card-icon{
        width:68px;
        height:68px;
        margin:0 auto 16px;
        border-radius:20px;
        display:grid;
        place-items:center;
        font-size:26px;
        background:rgba(255,255,255,0.12);
        border:1px solid rgba(255,255,255,0.10);
        color:#fff;
        box-shadow:0 10px 26px rgba(0,0,0,0.14);
    }

    .card a{
        color:#fff;
        font-weight:800;
        font-size:19px;
        display:inline-block;
        line-height:1.5;
    }

    .card.emergency{
        background:linear-gradient(135deg, rgba(127,29,29,0.95), rgba(220,38,38,0.88));
        border:1px solid rgba(255,255,255,0.16);
        box-shadow:0 18px 36px rgba(127,29,29,0.34);
    }

    .card.emergency:hover{
        background:linear-gradient(135deg, rgba(127,29,29,1), rgba(239,68,68,0.94));
    }

    .card.emergency a{
        color:#fff7d6;
    }

    .footer-note{
        text-align:center;
        color:rgba(255,255,255,0.74);
        font-size:14px;
        padding:20px 10px 28px;
    }

    @media (max-width: 980px){
        .hero{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 760px){
        .navbar{
            flex-direction:column;
            align-items:stretch;
        }

        .navbar-right{
            justify-content:space-between;
        }

        .quick-btn,
        .logout-btn{
            justify-content:center;
        }

        h2{
            font-size:22px;
        }
    }

    @media (max-width: 640px){
        .page-shell{
            width:min(100% - 18px, 1280px);
            margin:12px auto 22px;
        }

        .navbar,
        .hero-main,
        .hero-side{
            padding:18px;
        }

        .navbar-left a{
            font-size:19px;
        }

        .hero-main h1{
            font-size:27px;
        }

        .card{
            min-height:190px;
        }
    }
</style>
</head>
<body>

<div class="page-shell">

    <div class="navbar">
        <div class="navbar-left">
            <div class="brand-icon">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <a href="admin_dashboard.php"><?= htmlspecialchars($t['title']) ?></a>
        </div>

        <div class="navbar-right">
            <a href="admin_emergency.php?lang=<?= urlencode($lang) ?>" class="quick-btn">
                <i class="fa-solid fa-truck-medical"></i>
                <span><?= htmlspecialchars($t['emergency_nav']) ?></span>
            </a>

            <div class="lang-toggle">
                <?php if ($lang === 'en'): ?>
                    <a href="?lang=ar"><button>🇪🇬 العربية</button></a>
                <?php else: ?>
                    <a href="?lang=en"><button>🇬🇧 English</button></a>
                <?php endif; ?>
            </div>

            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?= htmlspecialchars($t['logout']) ?></span>
            </a>
        </div>
    </div>

    <section class="hero">
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-shield-halved"></i>
                <span><?= ($lang === 'ar') ? 'لوحة تحكم إدارية' : 'Administrative Control Panel' ?></span>
            </div>

            <h1><?= htmlspecialchars($t['hospital']) ?></h1>
            <p>
                <?= ($lang === 'ar')
                    ? 'تحكم في الأطباء، المستخدمين، الخدمات، المواعيد، الرسائل، والتقارير من مكان واحد بواجهة احترافية وآمنة.'
                    : 'Manage doctors, users, services, appointments, messages, and reports from one secure and professional interface.' ?>
            </p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'المسؤول الحالي' : 'Current Admin' ?></div>
                <div class="value"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'حالة الوصول' : 'Access Status' ?></div>
                <div class="value"><?= ($lang === 'ar') ? 'مصرح به' : 'Authorized' ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'الوصول السريع' : 'Quick Access' ?></div>
                <div class="value" style="font-size:15px;">
                    <?= ($lang === 'ar')
                        ? 'يمكنك الوصول السريع إلى لوحة الطوارئ من الزر العلوي أو بطاقة الطوارئ بالأسفل.'
                        : 'You can quickly access the emergency panel from the top button or the emergency card below.' ?>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <h2><?= htmlspecialchars($t['welcome'] . ($_SESSION['username'] ?? '')) ?></h2>
        <p class="subtitle">
            <?= ($lang === 'ar')
                ? 'اختر القسم الذي تريد إدارته من البطاقات التالية.'
                : 'Choose the section you want to manage from the cards below.' ?>
        </p>

        <div class="cards">
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <a href="manage_doctors.php"><?= htmlspecialchars($t['manage_doctors']) ?></a>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                <a href="manage_users.php"><?= htmlspecialchars($t['manage_users']) ?></a>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-stethoscope"></i></div>
                <a href="manage_services.php"><?= htmlspecialchars($t['manage_services']) ?></a>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <a href="manage_appointments.php"><?= htmlspecialchars($t['appointments']) ?></a>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                <a href="manage_reports.php"><?= htmlspecialchars($t['reports']) ?></a>
            </div>

            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <a href="admin_messages.php"><?= htmlspecialchars($t['messages']) ?></a>
            </div>

            <div class="card emergency">
                <div class="card-icon"><i class="fa-solid fa-truck-medical"></i></div>
                <a href="admin_emergency.php"><?= htmlspecialchars($t['emergency']) ?></a>
            </div>
        </div>
    </div>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($t['hospital']) ?>
    </div>
</div>

</body>
</html>