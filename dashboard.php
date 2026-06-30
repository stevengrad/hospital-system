<?php
session_start();
include 'db_connect.php';

// =========================
// Access Control
// =========================
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// =========================
// Language Handling
// =========================
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}

$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

// =========================
// Session Data
// =========================
$username  = $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'user';

// =========================
// Load Branches
// =========================
$branches = [];
$branch_query = $conn->query("SELECT BranchID, Name FROM branches ORDER BY BranchID ASC");

if ($branch_query && $branch_query->num_rows > 0) {
    while ($row = $branch_query->fetch_assoc()) {
        $branches[] = $row;
    }
}

// =========================
// Handle Branch Selection
// =========================
$selected_branch = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch'])) {
    $selected_branch = $_POST['branch'];
    $_SESSION['selected_branch'] = $selected_branch;
} elseif (isset($_SESSION['selected_branch'])) {
    $selected_branch = $_SESSION['selected_branch'];
}

// =========================
// Branch Map
// =========================
$branchNameMap = [];
foreach ($branches as $b) {
    $branchNameMap[$b['BranchID']] = $b['Name'];
}

// =========================
// Texts
// =========================
$text = [
    'hospital_name'      => ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals',
    'dashboard_title'    => ($lang === 'ar') ? 'لوحة التحكم' : 'Dashboard',
    'welcome'            => ($lang === 'ar') ? 'مرحباً، ' : 'Welcome, ',
    'welcome_sub'        => ($lang === 'ar')
        ? 'يمكنك الآن الوصول السريع لكل خدماتك الطبية من مكان واحد.'
        : 'You can now access all your medical services from one place.',
    'current_branch'     => ($lang === 'ar') ? 'الفرع الحالي' : 'Current Branch',
    'select_branch'      => ($lang === 'ar') ? 'اختر الفرع' : 'Select Branch',
    'doctors'            => ($lang === 'ar') ? 'الأطباء' : 'Doctors',
    'services'           => ($lang === 'ar') ? 'الخدمات' : 'Services',
    'appointments'       => ($lang === 'ar') ? 'مواعيدي' : 'My Appointments',
    'history'            => ($lang === 'ar') ? 'تاريخي الطبي' : 'Patient History',
    'contact'            => ($lang === 'ar') ? 'اتصل بنا' : 'Contact Us',
    'chatbot'            => ($lang === 'ar') ? 'الشات بوت الطبي' : 'Medical Chatbot',
    'search_placeholder' => ($lang === 'ar') ? 'ابحث عن طبيب أو خدمة...' : 'Search for a doctor or service...',
    'search'             => ($lang === 'ar') ? 'بحث' : 'Search',
    'logout'             => ($lang === 'ar') ? 'تسجيل الخروج' : 'Logout',
    'edit_profile'       => ($lang === 'ar') ? 'تعديل الملف الشخصي' : 'Edit Profile',
    'quick_access'       => ($lang === 'ar') ? 'الوصول السريع' : 'Quick Access',
    'chat_note'          => ($lang === 'ar')
        ? 'ملاحظة: الشات يستخدم حسابك الحالي'
        : 'Note: Chat uses your current session',
    'chat_placeholder'   => ($lang === 'ar') ? 'اكتب رسالتك هنا...' : 'Type your message here...',
    'send'               => ($lang === 'ar') ? 'إرسال' : 'Send',
    'chat_welcome'       => ($lang === 'ar')
        ? 'أهلًا! اكتب سؤالك أو اطلب حجزًا.'
        : 'Hi! Ask your question or request a booking.',
    'hero_badge'         => ($lang === 'ar') ? 'نظام رعاية ذكي وآمن' : 'Smart & Secure Care System',
    'hero_desc'          => ($lang === 'ar')
        ? 'تابع مواعيدك، ابحث عن الأطباء والخدمات، وتواصل بسهولة مع النظام الطبي.'
        : 'Manage appointments, explore doctors and services, and interact easily with the medical system.',
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($text['dashboard_title'] . ' - ' . $text['hospital_name']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --bg-1: #06131f;
            --bg-2: #0d2233;
            --card: rgba(255,255,255,0.10);
            --card-strong: rgba(255,255,255,0.16);
            --stroke: rgba(255,255,255,0.18);
            --white: #ffffff;
            --text-soft: rgba(255,255,255,0.82);
            --primary: #4cc9f0;
            --primary-2: #4895ef;
            --success: #2dd4bf;
            --warning: #fbbf24;
            --danger: #ef4444;
            --shadow: 0 20px 45px rgba(0, 0, 0, 0.28);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(76, 201, 240, 0.18), transparent 28%),
                radial-gradient(circle at top right, rgba(72, 149, 239, 0.15), transparent 24%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2));
            color: var(--white);
            min-height: 100vh;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        .page-shell {
            width: min(1280px, calc(100% - 32px));
            margin: 20px auto 34px;
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
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 22px;
            border-radius: 24px;
            margin-bottom: 22px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: fit-content;
        }

        .brand-icon {
            width: 52px;
            height: 52px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 22px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: #fff;
            box-shadow: 0 12px 24px rgba(72,149,239,0.35);
        }

        .brand-text h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: .2px;
        }

        .brand-text p {
            margin: 4px 0 0;
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
            background: rgba(255,255,255,0.08);
            padding: 6px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.12);
        }

        .lang-toggle a {
            color: var(--white);
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            transition: .25s ease;
        }

        .lang-toggle a.active,
        .lang-toggle a:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow: 0 10px 20px rgba(76,201,240,0.25);
        }


        .edit-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            outline: none;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            box-shadow: 0 10px 24px rgba(76,201,240,0.28);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .edit-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(76,201,240,0.34);
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            outline: none;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 10px 24px rgba(239,68,68,0.30);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(239,68,68,0.36);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.25fr .75fr;
            gap: 22px;
            align-items: stretch;
            margin-bottom: 22px;
        }

        .hero-main {
            padding: 30px;
            border-radius: var(--radius-xl);
            position: relative;
            overflow: hidden;
        }

        .hero-main::before {
            content: "";
            position: absolute;
            inset: auto -80px -80px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(76,201,240,0.25), transparent 60%);
            border-radius: 50%;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(76,201,240,0.14);
            border: 1px solid rgba(76,201,240,0.25);
            color: #dff8ff;
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
            color: var(--text-soft);
            font-size: 16px;
            line-height: 1.8;
            max-width: 760px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        .hero-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 18px;
            border-radius: 14px;
            font-weight: 700;
            transition: .25s ease;
        }

        .hero-btn.primary {
            color: #04111c;
            background: linear-gradient(135deg, #8ae8ff, #4cc9f0);
        }

        .hero-btn.secondary {
            color: #fff;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
        }

        .hero-btn:hover {
            transform: translateY(-2px);
        }

        .hero-side {
            border-radius: var(--radius-xl);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            justify-content: center;
        }

        .mini-stat {
            padding: 18px;
            border-radius: 18px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .mini-stat .label {
            color: var(--text-soft);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .mini-stat .value {
            font-size: 18px;
            font-weight: 800;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 22px;
        }

        .panel {
            border-radius: var(--radius-xl);
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
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.20));
            color: #dff8ff;
            font-size: 18px;
        }

        .panel-title h3 {
            margin: 0;
            font-size: 22px;
        }

        .panel-title p {
            margin: 4px 0 0;
            color: var(--text-soft);
            font-size: 13px;
        }

        .branch-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .branch-select {
            min-width: 220px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(255,255,255,0.10);
            color: #fff;
            outline: none;
            font-weight: 600;
        }

        .branch-select option {
            color: #111;
        }

        .search-area {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .search-box-inner {
            position: relative;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-input-wrap {
            flex: 1;
            min-width: 260px;
            position: relative;
        }

        .search-input-wrap i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.65);
            font-size: 15px;
        }

        [dir="ltr"] .search-input-wrap i {
            left: 16px;
        }

        [dir="rtl"] .search-input-wrap i {
            right: 16px;
        }

        .search-input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.10);
            color: #fff;
            outline: none;
            font-size: 15px;
        }

        [dir="ltr"] .search-input {
            padding-left: 44px;
        }

        [dir="rtl"] .search-input {
            padding-right: 44px;
        }

        .search-input::placeholder {
            color: rgba(255,255,255,0.60);
        }

        .search-btn {
            border: none;
            outline: none;
            border-radius: 16px;
            padding: 14px 20px;
            min-width: 130px;
            cursor: pointer;
            font-weight: 800;
            color: #07141f;
            background: linear-gradient(135deg, #9aefff, #4cc9f0);
            transition: .25s ease;
        }

        .search-btn:hover {
            transform: translateY(-2px);
        }

        .suggestions {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            border-radius: 16px;
            background: rgba(10, 24, 36, 0.96);
            border: 1px solid rgba(255,255,255,0.10);
            box-shadow: 0 18px 30px rgba(0,0,0,0.25);
            overflow: hidden;
            display: none;
            z-index: 50;
        }

        .suggestion-item {
            padding: 12px 14px;
            cursor: pointer;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: background .2s ease;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background: rgba(76,201,240,0.12);
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .nav-card {
            position: relative;
            overflow: hidden;
            padding: 22px 20px;
            border-radius: 22px;
            color: #fff;
            min-height: 180px;
            border: 1px solid rgba(255,255,255,0.12);
            background:
                linear-gradient(180deg, rgba(255,255,255,0.10), rgba(255,255,255,0.06)),
                rgba(255,255,255,0.06);
            box-shadow: 0 18px 35px rgba(0,0,0,0.18);
            transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease;
            cursor: pointer;
        }

        .nav-card::before {
            content: "";
            position: absolute;
            top: -40px;
            right: -40px;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(76,201,240,0.28), transparent 60%);
        }

        .nav-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 45px rgba(0,0,0,0.24);
            border-color: rgba(76,201,240,0.35);
        }

        .nav-card .card-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 23px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, rgba(76,201,240,0.20), rgba(72,149,239,0.22));
            border: 1px solid rgba(255,255,255,0.10);
        }

        .nav-card h4 {
            margin: 0 0 8px;
            font-size: 19px;
            font-weight: 800;
        }

        .nav-card p {
            margin: 0;
            color: var(--text-soft);
            font-size: 14px;
            line-height: 1.7;
        }

        .chatbot-panel {
            display: none;
            border-radius: var(--radius-xl);
            padding: 24px;
        }

        .chat-shell {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .chat-log {
            height: 320px;
            overflow-y: auto;
            padding: 16px;
            border-radius: 18px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
        }

        .chat-message {
            width: fit-content;
            max-width: 88%;
            margin-bottom: 12px;
            padding: 12px 14px;
            border-radius: 16px;
            line-height: 1.7;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 14px;
        }

        .chat-message.me {
            margin-inline-start: auto;
            background: rgba(76,201,240,0.18);
            border: 1px solid rgba(76,201,240,0.22);
        }

        .chat-message.bot {
            margin-inline-end: auto;
            background: rgba(45,212,191,0.14);
            border: 1px solid rgba(45,212,191,0.20);
        }


        .chat-feedback {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .chat-feedback-btn {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.10);
            color: #eaffff;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: .2s ease;
        }

        .chat-feedback-btn:hover {
            background: rgba(76,201,240,0.18);
            transform: translateY(-1px);
        }

        .chat-feedback-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }

        .chat-feedback-status {
            color: rgba(255,255,255,0.68);
            font-size: 12px;
        }

        .chat-input-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chat-input {
            flex: 1;
            min-width: 220px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.10);
            color: #fff;
            outline: none;
        }

        .chat-input::placeholder {
            color: rgba(255,255,255,0.60);
        }

        .chat-send,
        .chat-voice-btn,
        .chat-file-label {
            border: none;
            border-radius: 16px;
            padding: 14px 18px;
            font-weight: 800;
            cursor: pointer;
            color: #07141f;
            background: linear-gradient(135deg, #8effd8, #2dd4bf);
            transition: .25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .chat-voice-btn.recording {
            background: linear-gradient(135deg, #ffd166, #ff6b6b);
        }

        .chat-file-input {
            display: none;
        }

        .chat-send:hover,
        .chat-voice-btn:hover,
        .chat-file-label:hover {
            transform: translateY(-2px);
        }

        .chatbot-note {
            color: var(--text-soft);
            font-size: 13px;
            line-height: 1.7;
        }

        .chat-slots-wrap {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 4px;
        }

        .chat-slots-title {
            color: var(--text-soft);
            font-size: 13px;
            font-weight: 700;
        }

        .chat-slots-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .chat-slot-btn {
            border: 1px solid rgba(76, 201, 240, 0.35);
            background: rgba(76, 201, 240, 0.14);
            color: #dff8ff;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .22s ease;
        }

        .chat-slot-btn:hover {
            transform: translateY(-1px);
            background: rgba(76, 201, 240, 0.22);
        }

        .chat-slot-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .chat-debug {
            color: rgba(255,255,255,0.55);
            font-size: 12px;
            margin-top: 2px;
        }

        .footer-note {
            text-align: center;
            color: rgba(255,255,255,0.60);
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
            display: block;
            border-radius: 14px;
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
            .hero-side,
            .chatbot-panel {
                padding: 18px;
            }

            .brand-text h1 {
                font-size: 17px;
            }

            .hero-main h2 {
                font-size: 26px;
            }

            .panel-title h3 {
                font-size: 18px;
            }

            .search-btn,
            .chat-send,
    
        .edit-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            outline: none;
            padding: 12px 16px;
            border-radius: 14px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            box-shadow: 0 10px 24px rgba(76,201,240,0.28);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .edit-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(76,201,240,0.34);
        }

        .logout-btn {
                width: 100%;
                justify-content: center;
            }

            .search-form,
            .chat-input-row {
                flex-direction: column;
            }

            .branch-select {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="topbar glass">
        <div class="brand">
            <div class="logo-icon">
                <img src="assets/Cairo_hospitals1.png" alt="Cairo Hospitals">
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['hospital_name']) ?></h1>
                <p><?= ($lang === 'ar') ? 'صحتك متصلة وآمنة دائمًا' : 'Your health, connected and protected' ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="?lang=en" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="?lang=ar" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
            </div>

            <a href="edit_profile.php" class="edit-profile-btn">
                <i class="fa-solid fa-user-pen"></i>
                <span><?= htmlspecialchars($text['edit_profile']) ?></span>
            </a>

            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span><?= htmlspecialchars($text['logout']) ?></span>
            </a>
        </div>
    </header>

    <section class="hero">
        <div class="hero-main glass">
            <div class="hero-badge">
                <i class="fa-solid fa-shield-heart"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2>
                <?= htmlspecialchars($text['welcome']) . htmlspecialchars($username) ?> 👋
            </h2>

            <p><?= htmlspecialchars($text['welcome_sub']) ?></p>

            <div class="hero-actions">
                <a href="#quick-access" class="hero-btn primary">
                    <i class="fa-solid fa-bolt"></i>
                    <span><?= htmlspecialchars($text['quick_access']) ?></span>
                </a>

                <a href="#chatbot-section" class="hero-btn secondary" onclick="openChatbotSection()">
                    <i class="fa-solid fa-robot"></i>
                    <span><?= htmlspecialchars($text['chatbot']) ?></span>
                </a>
            </div>
        </div>

        <div class="hero-side glass">
            <div class="mini-stat">
                <div class="label"><?= htmlspecialchars($text['current_branch']) ?></div>
                <div class="value">
                    <?= $selected_branch ? htmlspecialchars($branchNameMap[$selected_branch] ?? '-') : '-' ?>
                </div>
            </div>

            <div class="mini-stat">
                <div class="label"><?= ($lang === 'ar') ? 'اسم المستخدم' : 'Username' ?></div>
                <div class="value"><?= htmlspecialchars($username) ?></div>
            </div>

            <div class="mini-stat">
                <div class="label"><?= ($lang === 'ar') ? 'نوع الحساب' : 'Role' ?></div>
                <div class="value"><?= htmlspecialchars(ucfirst($user_role)) ?></div>
            </div>
        </div>
    </section>

    <div class="content-grid">

        <section class="panel glass">
            <div class="panel-title-row">
                <div class="panel-title">
                    <div class="icon">
                        <i class="fa-solid fa-code-branch"></i>
                    </div>
                    <div>
                        <h3><?= ($lang === 'ar') ? 'اختيار الفرع والبحث' : 'Branch Selection & Search' ?></h3>
                        <p><?= htmlspecialchars($text['hero_desc']) ?></p>
                    </div>
                </div>
            </div>

            <div class="search-area">
                <form method="POST" class="branch-form">
                    <select name="branch" class="branch-select" onchange="this.form.submit()">
                        <option value=""><?= htmlspecialchars($text['select_branch']) ?></option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= htmlspecialchars($b['BranchID']) ?>"
                                <?= ($selected_branch == $b['BranchID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="search-box-inner">
                    <form action="search_results.php" method="GET" id="searchForm" class="search-form">
                        <div class="search-input-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input
                                type="text"
                                name="q"
                                id="searchBox"
                                class="search-input"
                                placeholder="<?= htmlspecialchars($text['search_placeholder']) ?>"
                                required
                                autocomplete="off"
                            >
                        </div>

                        <button type="submit" class="search-btn">
                            <?= htmlspecialchars($text['search']) ?>
                        </button>
                    </form>

                    <div id="suggestions" class="suggestions"></div>
                </div>
            </div>
        </section>

        <section class="panel glass" id="quick-access">
            <div class="panel-title-row">
                <div class="panel-title">
                    <div class="icon">
                        <i class="fa-solid fa-table-cells-large"></i>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($text['quick_access']) ?></h3>
                        <p><?= ($lang === 'ar') ? 'اختصارات سريعة لأهم الوظائف.' : 'Fast shortcuts to your most important functions.' ?></p>
                    </div>
                </div>
            </div>

            <div class="cards-grid">
                <a href="doctors.php" class="nav-card">
                    <div class="card-icon"><i class="fa-solid fa-user-doctor"></i></div>
                    <h4><?= htmlspecialchars($text['doctors']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'استعرض الأطباء والتخصصات المتاحة.' : 'Browse available doctors and specialties.' ?></p>
                </a>

                <a href="services.php" class="nav-card">
                    <div class="card-icon"><i class="fa-solid fa-syringe"></i></div>
                    <h4><?= htmlspecialchars($text['services']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'تعرف على الخدمات الطبية المتوفرة.' : 'Explore the available medical services.' ?></p>
                </a>

                <a href="my_appointments.php" class="nav-card">
                    <div class="card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <h4><?= htmlspecialchars($text['appointments']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'راجع وحافظ على تنظيم مواعيدك.' : 'Review and manage your appointments.' ?></p>
                </a>

                <a href="patient_history.php" class="nav-card">
                    <div class="card-icon"><i class="fa-solid fa-file-medical"></i></div>
                    <h4><?= htmlspecialchars($text['history']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'اطلع على سجلك وتاريخك الطبي.' : 'Access your medical and patient history.' ?></p>
                </a>

                <a href="contact.php" class="nav-card">
                    <div class="card-icon"><i class="fa-solid fa-envelope"></i></div>
                    <h4><?= htmlspecialchars($text['contact']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'تواصل بسهولة مع إدارة المستشفى.' : 'Reach the hospital team quickly and easily.' ?></p>
                </a>

                <div class="nav-card" onclick="toggleChatbot()">
                    <div class="card-icon"><i class="fa-solid fa-robot"></i></div>
                    <h4><?= htmlspecialchars($text['chatbot']) ?></h4>
                    <p><?= ($lang === 'ar') ? 'اسأل الشات بوت عن الخدمات أو الحجز.' : 'Ask the chatbot about services or booking.' ?></p>
                </div>
            </div>
        </section>

        <section class="chatbot-panel glass" id="chatbot-section">
            <div class="panel-title-row">
                <div class="panel-title">
                    <div class="icon">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($text['chatbot']) ?></h3>
                        <p><?= ($lang === 'ar') ? 'مساعد ذكي للتفاعل السريع مع النظام.' : 'A smart assistant for quick interaction with the system.' ?></p>
                    </div>
                </div>
            </div>

            <div class="chat-shell">
                <div id="chatLog" class="chat-log"></div>

                <div class="chat-input-row">
                    <input
                        id="chatMessage"
                        type="text"
                        class="chat-input"
                        placeholder="<?= htmlspecialchars($text['chat_placeholder']) ?>"
                    >

                    <label class="chat-file-label" for="chatAttachment" title="Upload prescription PDF or image">
                        📎
                    </label>
                    <input id="chatAttachment" class="chat-file-input" type="file" accept="application/pdf,image/*">

                    <button id="chatVoice" type="button" class="chat-voice-btn" title="Record voice message">
                        🎙️
                    </button>

                    <button id="chatSend" class="chat-send">
                        <?= htmlspecialchars($text['send']) ?>
                    </button>
                </div>

                <div class="chatbot-note">
                    <?= htmlspecialchars($text['chat_note']) ?>:
                    <strong><?= htmlspecialchars($username) ?></strong>
                </div>
            </div>
        </section>

    </div>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['hospital_name']) ?> — <?= ($lang === 'ar') ? 'واجهة مستخدم احترافية' : 'Professional User Interface' ?>
    </div>
</div>

<script>
// =========================
// Live Search Suggestions
// =========================
const searchInput   = document.getElementById('searchBox');
const suggestionsEl = document.getElementById('suggestions');

if (searchInput && suggestionsEl) {
    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        if (query.length < 2) {
            suggestionsEl.style.display = 'none';
            suggestionsEl.innerHTML = '';
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'search_suggestions.php?q=' + encodeURIComponent(query), true);

        xhr.onload = function () {
            if (xhr.status === 200) {
                const html = xhr.responseText.trim();

                if (html) {
                    suggestionsEl.innerHTML = html;
                    suggestionsEl.style.display = 'block';
                } else {
                    suggestionsEl.style.display = 'none';
                    suggestionsEl.innerHTML = '';
                }
            }
        };

        xhr.send();
    });

    suggestionsEl.addEventListener('click', function (e) {
        const item = e.target.closest('.suggestion-item');
        if (!item) return;

        const value = item.getAttribute('data-value');
        if (value) {
            searchInput.value = value;
            suggestionsEl.style.display = 'none';
            suggestionsEl.innerHTML = '';
            document.getElementById('searchForm').submit();
        }
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box-inner')) {
            suggestionsEl.style.display = 'none';
        }
    });
}

// =========================
// Chatbot Toggle
// =========================
function toggleChatbot() {
    const chatbot = document.getElementById("chatbot-section");
    if (!chatbot) return;

    if (chatbot.style.display === "none" || chatbot.style.display === "") {
        chatbot.style.display = "block";
        chatbot.scrollIntoView({ behavior: "smooth", block: "start" });
    } else {
        chatbot.style.display = "none";
    }
}

function openChatbotSection() {
    const chatbot = document.getElementById("chatbot-section");
    if (!chatbot) return;
    chatbot.style.display = "block";
    chatbot.scrollIntoView({ behavior: "smooth", block: "start" });
}

// =========================
// Chatbot Logic
// =========================
(function () {
    const logEl   = document.getElementById("chatLog");
    const inputEl = document.getElementById("chatMessage");
    const sendBtn = document.getElementById("chatSend");
    const attachEl = document.getElementById("chatAttachment");
    const voiceBtn = document.getElementById("chatVoice");
    let mediaRecorder = null;
    let voiceChunks = [];
    let pendingAudioBlob = null;

    const CHAT_ID_KEY = "hospital_chat_id_v2";
    function getStableChatId() {
        let id = localStorage.getItem(CHAT_ID_KEY);
        if (!id) {
            id = "web_" + Date.now() + "_" + Math.random().toString(36).slice(2);
            localStorage.setItem(CHAT_ID_KEY, id);
        }
        return id;
    }

    if (!logEl || !inputEl || !sendBtn) return;

    function addLine(who, text) {
        if (text === null || text === undefined || text === "") {
            text = " ";
        }

        const row = document.createElement("div");
        row.className = "chat-message " + (who === "me" ? "me" : "bot");
        row.textContent = text;
        logEl.appendChild(row);
        logEl.scrollTop = logEl.scrollHeight;
        return row;
    }


    async function sendChatFeedback(rating, userMessage, botReply, intent, statusEl, buttons) {
        if (buttons) buttons.forEach(btn => btn.disabled = true);
        if (statusEl) {
            statusEl.textContent = <?= json_encode($lang === 'ar' ? 'جاري حفظ التقييم...' : 'Saving feedback...') ?>;
        }

        let comment = "";
        if (rating === "down") {
            comment = prompt(<?= json_encode($lang === 'ar' ? 'ممكن تكتبي سبب بسيط عشان نحسن الرد؟' : 'Optional: tell us what was wrong so we can improve.') ?>) || "";
        }

        try {
            const form = new FormData();
            form.append("feedback_action", "1");
            form.append("chat_id", getStableChatId());
            form.append("user_message", userMessage || "");
            form.append("bot_reply", botReply || "");
            form.append("rating", rating);
            form.append("comment", comment);
            form.append("intent", intent || "");

            const res = await fetch("chat_api.php", {
                method: "POST",
                body: form,
                credentials: "same-origin"
            });
            const raw = await res.text();
            let data = null;
            try { data = JSON.parse(raw); } catch (e) {}

            if (res.ok && data && (data.ok === true || data.saved === true)) {
                if (statusEl) statusEl.textContent = <?= json_encode($lang === 'ar' ? 'تم حفظ التقييم، شكرًا لك ✅' : 'Feedback saved, thank you ✅') ?>;
            } else {
                if (statusEl) statusEl.textContent = <?= json_encode($lang === 'ar' ? 'لم يتم حفظ التقييم.' : 'Feedback was not saved.') ?>;
                if (buttons) buttons.forEach(btn => btn.disabled = false);
            }
        } catch (e) {
            if (statusEl) statusEl.textContent = <?= json_encode($lang === 'ar' ? 'حصل خطأ أثناء حفظ التقييم.' : 'Feedback save error.') ?>;
            if (buttons) buttons.forEach(btn => btn.disabled = false);
        }
    }

    function addFeedbackControls(botRow, userMessage, botReply, intent) {
        if (!botRow || !botReply) return;

        const wrap = document.createElement("div");
        wrap.className = "chat-feedback";

        const goodBtn = document.createElement("button");
        goodBtn.type = "button";
        goodBtn.className = "chat-feedback-btn";
        goodBtn.textContent = "👍 " + <?= json_encode($lang === 'ar' ? 'مفيد' : 'Helpful') ?>;

        const badBtn = document.createElement("button");
        badBtn.type = "button";
        badBtn.className = "chat-feedback-btn";
        badBtn.textContent = "👎 " + <?= json_encode($lang === 'ar' ? 'غير مفيد' : 'Not helpful') ?>;

        const status = document.createElement("span");
        status.className = "chat-feedback-status";

        const buttons = [goodBtn, badBtn];
        goodBtn.addEventListener("click", () => sendChatFeedback("up", userMessage, botReply, intent, status, buttons));
        badBtn.addEventListener("click", () => sendChatFeedback("down", userMessage, botReply, intent, status, buttons));

        wrap.appendChild(goodBtn);
        wrap.appendChild(badBtn);
        wrap.appendChild(status);
        botRow.appendChild(wrap);
    }

    function addSlotsArea(titleText) {
        const wrap = document.createElement("div");
        wrap.className = "chat-message bot";

        const inner = document.createElement("div");
        inner.className = "chat-slots-wrap";

        const title = document.createElement("div");
        title.className = "chat-slots-title";
        title.textContent = titleText;

        const group = document.createElement("div");
        group.className = "chat-slots-group";

        inner.appendChild(title);
        inner.appendChild(group);
        wrap.appendChild(inner);
        logEl.appendChild(wrap);
        logEl.scrollTop = logEl.scrollHeight;

        return group;
    }

    function extractSlots(responseData) {
        if (responseData && responseData.data && Array.isArray(responseData.data.slots)) {
            return responseData.data.slots;
        }
        if (responseData && Array.isArray(responseData.slots)) {
            return responseData.slots;
        }
        if (responseData && responseData.data && responseData.data.data && Array.isArray(responseData.data.data.slots)) {
            return responseData.data.data.slots;
        }
        return [];
    }

    function formatSlotLabel(slot, index) {
        const start = slot.start || slot.Start || "";
        const doctorName = slot.doctor_name || slot.doctorName || "";
        let datePart = "";
        let timePart = "";

        if (start) {
            datePart = String(start).slice(0, 10);
            timePart = String(start).slice(11, 16);
        }

        let label = (datePart && timePart)
            ? `${index + 1}) ${datePart} ${timePart}`
            : `${index + 1}) موعد`;

        if (doctorName) {
            label += ` - ${doctorName}`;
        }

        return label;
    }

    async function bookSelectedSlot(slot, btnRef) {
        if (!slot) return;

        btnRef.disabled = true;

        try {
            const res = await fetch("book_appointment.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({
                    doctor_id: slot.doctor_id ?? slot.doctorId ?? slot.DoctorID ?? null,
                    branch_id: slot.branch_id ?? slot.branchId ?? slot.BranchID ?? null,
                    start: slot.start ?? slot.Start ?? null
                })
            });

            const raw = await res.text();
            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (e) {}

            if (res.ok) {
                let msg = "تم تنفيذ الحجز.";
                if (data && typeof data.message === "string" && data.message.trim() !== "") {
                    msg = data.message;
                } else if (data && typeof data.reply === "string" && data.reply.trim() !== "") {
                    msg = data.reply;
                }
                addLine("bot", msg);
            } else {
                let msg = "فشل الحجز.";
                if (data && typeof data.message === "string" && data.message.trim() !== "") {
                    msg = data.message;
                } else if (data && typeof data.detail === "string" && data.detail.trim() !== "") {
                    msg = data.detail;
                } else if (raw) {
                    msg = raw;
                }
                addLine("bot", msg);
                btnRef.disabled = false;
            }
        } catch (e) {
            addLine(
                "bot",
                <?= json_encode(
                    $lang === 'ar'
                    ? 'حصل خطأ أثناء الحجز. تأكدي أن book_appointment.php موجود وأن سيرفر البايثون يعمل.'
                    : 'A booking error occurred. Make sure book_appointment.php exists and the Python server is running.'
                ) ?>
            );
            console.error(e);
            btnRef.disabled = false;
        }
    }

    function renderSlotButtons(slots) {
        if (!Array.isArray(slots) || slots.length === 0) return;

        const group = addSlotsArea(
            <?= json_encode($lang === 'ar' ? 'اختاري ميعاد للحجز:' : 'Choose a slot to book:') ?>
        );

        slots.forEach((slot, index) => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = "chat-slot-btn";
            btn.textContent = formatSlotLabel(slot, index);

            btn.addEventListener("click", function () {
                bookSelectedSlot(slot, btn);
            });

            group.appendChild(btn);
        });
    }

    async function sendMessage() {
        const text = (inputEl.value || "").trim();
        const attachment = attachEl && attachEl.files && attachEl.files.length ? attachEl.files[0] : null;
        const audioBlob = pendingAudioBlob;

        if (!text && !attachment && !audioBlob) return;

        let shownText = text || "";
        if (attachment) shownText += (shownText ? "\n" : "") + "📎 " + attachment.name;
        if (audioBlob) shownText += (shownText ? "\n" : "") + "🎙️ Voice message";
        addLine("me", shownText);

        inputEl.value = "";
        if (attachEl) attachEl.value = "";
        pendingAudioBlob = null;
        sendBtn.disabled = true;

        try {
            const form = new FormData();
            form.append("message", text);
            form.append("chat_id", getStableChatId());
            if (attachment) form.append("attachment", attachment);
            if (audioBlob) form.append("audio", audioBlob, "voice_message.webm");

            const res = await fetch("chat_api.php", {
                method: "POST",
                body: form,
                credentials: "same-origin"
            });

            const raw = await res.text();
            let data = null;

            try {
                data = JSON.parse(raw);
            } catch (e) {
                addLine("bot", raw || "Invalid response");
                return;
            }

            let reply = "";

            if (data && typeof data.reply === "string" && data.reply.trim() !== "") {
                reply = data.reply;
            } else if (data && typeof data.message === "string" && data.message.trim() !== "") {
                reply = data.message;
            } else if (data && typeof data.error === "string" && data.error.trim() !== "") {
                reply = data.error;
            } else {
                reply = <?= json_encode($lang === 'ar' ? 'تم استلام الرد بدون نص واضح.' : 'Response received without readable text.') ?>;
            }

            const botRow = addLine("bot", reply);
            addFeedbackControls(botRow, text, reply, data && data.intent ? data.intent : "");

            const slots = extractSlots(data);
            if (slots.length > 0) {
                renderSlotButtons(slots);
            }

        } catch (e) {
            addLine(
                "bot",
                <?= json_encode(
                    $lang === 'ar'
                    ? 'حصل خطأ في الاتصال بالشات. تأكدي أن chat_api.php موجود وأن سيرفر البايثون يعمل.'
                    : 'A chatbot connection error occurred. Make sure chat_api.php exists and the Python server is running.'
                ) ?>
            );
            console.error(e);
        } finally {
            sendBtn.disabled = false;
            inputEl.focus();
        }
    }

    sendBtn.addEventListener("click", sendMessage);


    if (attachEl) {
        attachEl.addEventListener("change", function () {
            if (attachEl.files && attachEl.files.length) {
                addLine("bot", <?= json_encode($lang === 'ar' ? 'تم اختيار الملف. اضغطي إرسال لرفعه.' : 'File selected. Press Send to upload it.') ?>);
            }
        });
    }

    if (voiceBtn && navigator.mediaDevices && window.MediaRecorder) {
        voiceBtn.addEventListener("click", async function () {
            if (mediaRecorder && mediaRecorder.state === "recording") {
                mediaRecorder.stop();
                voiceBtn.classList.remove("recording");
                voiceBtn.textContent = "🎙️";
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                voiceChunks = [];
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.ondataavailable = (event) => {
                    if (event.data && event.data.size > 0) voiceChunks.push(event.data);
                };
                mediaRecorder.onstop = () => {
                    pendingAudioBlob = new Blob(voiceChunks, { type: "audio/webm" });
                    stream.getTracks().forEach(track => track.stop());
                    addLine("bot", <?= json_encode($lang === 'ar' ? 'تم تسجيل الصوت. اضغطي إرسال لتحويله لنص والرد عليه.' : 'Voice recorded. Press Send to transcribe and answer it.') ?>);
                };
                mediaRecorder.start();
                voiceBtn.classList.add("recording");
                voiceBtn.textContent = "⏹️";
            } catch (err) {
                addLine("bot", <?= json_encode($lang === 'ar' ? 'المتصفح لم يسمح باستخدام الميكروفون.' : 'Microphone permission was blocked.') ?>);
            }
        });
    }

    inputEl.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            sendMessage();
        }
    });

    addLine("bot", <?= json_encode($text['chat_welcome']) ?>);
})();
</script>

</body>
</html>