<?php
session_start();
include 'db_connect.php';

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_contact($code) {
    return basename($_SERVER['PHP_SELF']) . '?lang=' . $code;
}

$success = '';
$error   = '';
$name    = '';
$email   = '';
$message = '';

/* =========================
   Handle Form Submit
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '' || $message === '') {
        $error = ($lang === 'ar')
            ? "يرجى ملء جميع الحقول."
            : "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = ($lang === 'ar')
            ? "يرجى إدخال بريد إلكتروني صالح."
            : "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO contact_messages (name, email, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");

        if ($stmt === false) {
            $error = ($lang === 'ar')
                ? "خطأ في قاعدة البيانات."
                : "Database error.";
        } else {
            $stmt->bind_param("sss", $name, $email, $message);

            if ($stmt->execute()) {
                $success = ($lang === 'ar')
                    ? "شكرًا لتواصلك معنا! سنرد عليك قريبًا."
                    : "Thank you for contacting us! We'll respond soon.";

                $name = '';
                $email = '';
                $message = '';
            } else {
                $error = ($lang === 'ar')
                    ? "فشل حفظ رسالتك."
                    : "Failed to save your message.";
            }

            $stmt->close();
        }
    }
}

$text = [
    'app_name'      => ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals',
    'page_title'    => ($lang === 'ar') ? 'اتصل بنا' : 'Contact Us',
    'hero_badge'    => ($lang === 'ar') ? 'التواصل والدعم' : 'Communication & Support',
    'hero_title'    => ($lang === 'ar') ? 'اتصل بنا' : 'Contact Us',
    'hero_desc'     => ($lang === 'ar')
        ? 'يسعدنا تواصلك معنا، اكتب رسالتك وسنقوم بالرد عليك في أقرب وقت.'
        : "We'd love to hear from you. Send us your message and we'll get back to you soon.",
    'dashboard'     => ($lang === 'ar') ? 'العودة إلى اللوحة الرئيسية' : 'Back to Dashboard',
    'name'          => ($lang === 'ar') ? 'الاسم' : 'Your Name',
    'email'         => ($lang === 'ar') ? 'البريد الإلكتروني' : 'Email',
    'message'       => ($lang === 'ar') ? 'الرسالة' : 'Message',
    'send'          => ($lang === 'ar') ? 'إرسال الرسالة' : 'Send Message',
    'summary'       => ($lang === 'ar') ? 'نموذج التواصل' : 'Contact Form',
    'summary_desc'  => ($lang === 'ar')
        ? 'أرسل استفسارك أو اقتراحك أو شكواك بسهولة.'
        : 'Send your inquiry, suggestion, or complaint easily.',
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
            width: min(1100px, calc(100% - 32px));
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

        .alert-error {
            background: rgba(239,68,68,0.14);
            border: 1px solid rgba(239,68,68,0.28);
            color: #ffdede;
        }

        .alert-success {
            background: rgba(34,197,94,0.16);
            border: 1px solid rgba(34,197,94,0.30);
            color: #d8ffe3;
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

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.66);
            font-size: 15px;
        }

        [dir="ltr"] .input-wrap i {
            left: 15px;
        }

        [dir="rtl"] .input-wrap i {
            right: 15px;
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

        [dir="ltr"] .form-input,
        [dir="ltr"] .form-textarea {
            padding-left: 44px;
        }

        [dir="rtl"] .form-input,
        [dir="rtl"] .form-textarea {
            padding-right: 44px;
        }

        .form-textarea {
            min-height: 140px;
            resize: vertical;
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(255,255,255,0.55);
        }

        .send-btn {
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

        .send-btn:hover {
            transform: translateY(-2px);
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
                width: min(100% - 18px, 1100px);
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
                <i class="fa-solid fa-envelope-open-text"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_contact('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_contact('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="dashboard.php?lang=<?= urlencode($lang) ?>" class="nav-btn">
                <i class="fa-solid fa-table-columns"></i>
                <span><?= htmlspecialchars($text['dashboard']) ?></span>
            </a>
        </div>
    </header>

    <section class="hero">
        <div class="hero-badge">
            <i class="fa-solid fa-headset"></i>
            <span><?= htmlspecialchars($text['hero_badge']) ?></span>
        </div>

        <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
        <p><?= htmlspecialchars($text['hero_desc']) ?></p>
    </section>

    <section class="panel glass">
        <div class="panel-title-row">
            <div class="icon">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div>
                <h3><?= htmlspecialchars($text['summary']) ?></h3>
                <p><?= htmlspecialchars($text['summary_desc']) ?></p>
            </div>
        </div>
                <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <div class="form-row">
                <label><?= htmlspecialchars($text['name']) ?></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input
                        type="text"
                        name="name"
                        class="form-input"
                        value="<?= htmlspecialchars($name) ?>"
                        placeholder="<?= htmlspecialchars($text['name']) ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <label><?= htmlspecialchars($text['email']) ?></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input
                        type="email"
                        name="email"
                        class="form-input"
                        value="<?= htmlspecialchars($email) ?>"
                        placeholder="<?= htmlspecialchars($text['email']) ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <label><?= htmlspecialchars($text['message']) ?></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-comment-dots"></i>
                    <textarea
                        name="message"
                        class="form-textarea"
                        placeholder="<?= htmlspecialchars($text['message']) ?>"
                        required><?= htmlspecialchars($message) ?></textarea>
                </div>
            </div>

            <button type="submit" class="send-btn">
                <i class="fa-solid fa-paper-plane"></i>
                <span><?= htmlspecialchars($text['send']) ?></span>
            </button>
        </form>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> —
        <?= ($lang === 'ar') ? 'واجهة تواصل احترافية' : 'Professional Contact Interface' ?>
    </div>

</div>

</body>
</html>