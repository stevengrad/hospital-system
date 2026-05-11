<?php
session_start();
include('db_connect.php');

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_services($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

/* =========================
   Fetch Services
========================= */
$services = [];

$sql = "
    SELECT
        SpecialtyID,
        Name,
        Description,
        Price
    FROM specialties
    ORDER BY Name ASC
";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

$text = [
    'app_name'        => ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals',
    'page_title'      => ($lang === 'ar') ? 'خدمات المستشفى' : 'Hospital Services',
    'hero_badge'      => ($lang === 'ar') ? 'الرعاية والخدمات الطبية' : 'Medical Care & Services',
    'hero_title'      => ($lang === 'ar') ? 'خدمات المستشفى' : 'Our Hospital Services',
    'hero_desc'       => ($lang === 'ar')
        ? 'استعرض التخصصات والخدمات الطبية المتاحة واحجز موعدك بسهولة.'
        : 'Explore available specialties and medical services, then book your appointment with ease.',
    'dashboard'       => ($lang === 'ar') ? 'اللوحة الرئيسية' : 'Dashboard',
    'login'           => ($lang === 'ar') ? 'تسجيل الدخول' : 'Login',
    'logout'          => ($lang === 'ar') ? 'تسجيل الخروج' : 'Logout',
    'price'           => ($lang === 'ar') ? 'السعر' : 'Price',
    'book'            => ($lang === 'ar') ? 'حجز موعد' : 'Book Appointment',
    'login_to_book'   => ($lang === 'ar') ? 'سجّل الدخول للحجز' : 'Login to Book',
    'summary'         => ($lang === 'ar') ? 'ملخص سريع' : 'Quick Summary',
    'summary_desc'    => ($lang === 'ar')
        ? 'عدد الخدمات والتخصصات المتاحة حاليًا.'
        : 'Number of currently available services and specialties.',
    'total_services'  => ($lang === 'ar') ? 'إجمالي الخدمات' : 'Total Services',
    'availability'    => ($lang === 'ar') ? 'الحالة' : 'Availability',
    'available'       => ($lang === 'ar') ? 'متاحة' : 'Available',
    'varies'          => ($lang === 'ar') ? 'حسب الحالة' : 'Varies',
    'egp'             => ($lang === 'ar') ? 'جنيه' : 'EGP',
    'empty'           => ($lang === 'ar') ? 'لا توجد خدمات متاحة حاليًا.' : 'No services available yet.',
];

$totalServices = count($services);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($text['page_title']) ?> - <?= htmlspecialchars($text['app_name']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">

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
        .login-btn,
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

        .nav-btn,
        .login-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 25px rgba(239,68,68,0.30);
        }

        .nav-btn:hover,
        .login-btn:hover,
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

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }

        .service-card {
            border-radius: 24px;
            padding: 22px 20px;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.10), rgba(255,255,255,0.06)),
                rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 18px 35px rgba(0,0,0,0.18);
            transition: 0.28s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 290px;
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 44px rgba(0,0,0,0.26);
            border-color: rgba(76,201,240,0.30);
        }

        .service-card::before {
            content: "";
            position: absolute;
            top: -42px;
            right: -42px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(76,201,240,0.22), transparent 60%);
        }

        .service-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: grid;
            place-items: center;
            font-size: 26px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.22));
            border: 1px solid rgba(255,255,255,0.10);
        }

        .service-name {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .service-desc {
            color: var(--text-soft);
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 16px;
            flex-grow: 1;
        }

        .price-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            margin-bottom: 16px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            color: #d7f7ff;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .book-btn,
        .login-book-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
            font-weight: 800;
            transition: 0.25s ease;
        }

        .book-btn {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            color: #fff;
        }

        .login-book-btn {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .book-btn:hover,
        .login-book-btn:hover {
            transform: translateY(-2px);
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
        .logo-icon {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid rgba(255,255,255,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 10px 24px rgba(0,0,0,0.16);
}

.logo-icon img {
    width: 58px;
    height: 58px;
    object-fit: contain;
    border-radius: 14px;
    display: block;
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

            .panel-title-row h3 {
                font-size: 19px;
            }

            .nav-btn,
            .login-btn,
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
             <div class="logo-icon">
                 <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals">
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_services('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_services('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php?lang=<?= urlencode($lang) ?>" class="nav-btn">
                    <i class="fa-solid fa-table-columns"></i>
                    <span><?= htmlspecialchars($text['dashboard']) ?></span>
                </a>

                <a href="logout.php" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span><?= htmlspecialchars($text['logout']) ?></span>
                </a>
            <?php else: ?>
                <a href="index.php?lang=<?= urlencode($lang) ?>" class="login-btn">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span><?= htmlspecialchars($text['login']) ?></span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <section class="hero">
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-stethoscope"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['total_services']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$totalServices) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['availability']) ?></div>
                <div class="value"><?= htmlspecialchars($text['available']) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['summary']) ?></div>
                <div class="value" style="font-size:15px; line-height:1.7;">
                    <?= htmlspecialchars($text['summary_desc']) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="icon">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['page_title']) ?></h3>
                <p><?= htmlspecialchars($text['hero_desc']) ?></p>
            </div>
        </div>
                <?php if (!empty($services)): ?>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fa-solid fa-briefcase-medical"></i>
                        </div>

                        <div class="service-name">
                            <?= htmlspecialchars($service['Name'] ?? '') ?>
                        </div>

                        <div class="service-desc">
                            <?= htmlspecialchars($service['Description'] ?? '') ?>
                        </div>

                        <div class="price-box">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <span>
                                <?= htmlspecialchars($text['price']) ?>:
                                <?php
                                if (isset($service['Price']) && (float)$service['Price'] > 0) {
                                    echo htmlspecialchars(number_format((float)$service['Price'], 2)) . ' ' . htmlspecialchars($text['egp']);
                                } else {
                                    echo htmlspecialchars($text['varies']);
                                }
                                ?>
                            </span>
                        </div>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="book_appointment.php?specialty_id=<?= (int)$service['SpecialtyID'] ?>" class="book-btn">
                                <i class="fa-solid fa-calendar-check"></i>
                                <span><?= htmlspecialchars($text['book']) ?></span>
                            </a>
                        <?php else: ?>
                            <a href="index.php?lang=<?= urlencode($lang) ?>" class="login-book-btn">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span><?= htmlspecialchars($text['login_to_book']) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-box">
                <i class="fa-regular fa-folder-open" style="font-size:34px; margin-bottom:12px;"></i><br>
                <?= htmlspecialchars($text['empty']) ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> —
        <?= ($lang === 'ar') ? 'واجهة خدمات احترافية' : 'Professional Services Interface' ?>
    </div>

</div>

</body>
</html>