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

$doctorId   = (int)($_SESSION['user_id'] ?? 0);
$username   = $_SESSION['username'] ?? 'doctor';
$doctorName = $username;

$messages   = [];
$errorMsg   = '';
$infoMsg    = '';

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
    // fallback to username
}

/* =========================
   Text
========================= */
$text = [
    'app_name'        => $T['app_name'] ?? 'Cairo Hospitals',
    'page_title'      => ($lang === 'ar') ? 'رسائل الطبيب' : 'Doctor Messages',
    'dashboard'       => ($lang === 'ar') ? 'لوحة الطبيب' : 'Doctor Dashboard',
    'logout'          => $T['logout'] ?? (($lang === 'ar') ? 'تسجيل الخروج' : 'Logout'),
    'hero_badge'      => ($lang === 'ar') ? 'التواصل والإشعارات' : 'Communication & Notifications',
    'hero_title'      => ($lang === 'ar') ? 'رسائل د. ' . $doctorName : 'Messages of Dr. ' . $doctorName,
    'hero_desc'       => ($lang === 'ar')
        ? 'تابع الرسائل والإشعارات المرتبطة بحسابك من خلال واجهة احترافية ومنظمة.'
        : 'Track account-related messages and notifications through a clean professional interface.',
    'inbox'           => ($lang === 'ar') ? 'صندوق الرسائل' : 'Inbox',
    'inbox_desc'      => ($lang === 'ar')
        ? 'أحدث الرسائل والإشعارات المتاحة للطبيب.'
        : 'Latest messages and notifications available for the doctor.',
    'summary'         => ($lang === 'ar') ? 'ملخص سريع' : 'Quick Summary',
    'summary_desc'    => ($lang === 'ar')
        ? 'إحصائيات مبسطة عن رسائلك الحالية.'
        : 'Simple statistics about your current messages.',
    'total_messages'  => ($lang === 'ar') ? 'إجمالي الرسائل' : 'Total Messages',
    'unread'          => ($lang === 'ar') ? 'غير المقروءة' : 'Unread',
    'latest_update'   => ($lang === 'ar') ? 'آخر تحديث' : 'Latest Update',
    'from'            => ($lang === 'ar') ? 'من' : 'From',
    'subject'         => ($lang === 'ar') ? 'العنوان' : 'Subject',
    'message'         => ($lang === 'ar') ? 'الرسالة' : 'Message',
    'date'            => ($lang === 'ar') ? 'التاريخ' : 'Date',
    'status'          => ($lang === 'ar') ? 'الحالة' : 'Status',
    'read'            => ($lang === 'ar') ? 'مقروءة' : 'Read',
    'unread_status'   => ($lang === 'ar') ? 'غير مقروءة' : 'Unread',
    'empty'           => ($lang === 'ar') ? 'لا توجد رسائل متاحة حاليًا.' : 'No messages are available right now.',
    'note'            => ($lang === 'ar') ? 'ملاحظة' : 'Note',
    'note_text'       => ($lang === 'ar')
        ? 'إذا لم تظهر رسائل، فقد لا يكون جدول الرسائل موجودًا أو لم تتم إضافة بيانات بعد.'
        : 'If no messages appear, the messages table may not exist yet or no records have been inserted.',
    'fallback_sender' => ($lang === 'ar') ? 'النظام' : 'System',
    'fallback_subj'   => ($lang === 'ar') ? 'إشعار عام' : 'General Notification',
];

/* =========================
   Detect available message table
========================= */
function table_exists(mysqli $conn, string $tableName): bool {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

/* =========================
   Fetch messages safely
========================= */
try {
    if (table_exists($conn, 'doctor_messages')) {

        $query = "
            SELECT
                id,
                sender_name,
                subject,
                message_body,
                status,
                created_at
            FROM doctor_messages
            WHERE doctor_id = ?
            ORDER BY created_at DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $messages[] = [
                'sender'     => $row['sender_name'] ?? $text['fallback_sender'],
                'subject'    => $row['subject'] ?? $text['fallback_subj'],
                'body'       => $row['message_body'] ?? '',
                'status'     => strtolower((string)($row['status'] ?? 'unread')),
                'created_at' => $row['created_at'] ?? '',
            ];
        }

        $stmt->close();

    } elseif (table_exists($conn, 'messages')) {

        $query = "
            SELECT
                id,
                sender_name,
                subject,
                message_body,
                status,
                created_at
            FROM messages
            WHERE recipient_role = 'doctor' OR recipient_id = ?
            ORDER BY created_at DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $doctorId);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $messages[] = [
                'sender'     => $row['sender_name'] ?? $text['fallback_sender'],
                'subject'    => $row['subject'] ?? $text['fallback_subj'],
                'body'       => $row['message_body'] ?? '',
                'status'     => strtolower((string)($row['status'] ?? 'unread')),
                'created_at' => $row['created_at'] ?? '',
            ];
        }

        $stmt->close();

    } else {
        $infoMsg = $text['note_text'];
    }

} catch (Exception $e) {
    $errorMsg = ($lang === 'ar')
        ? 'تعذر تحميل الرسائل من قاعدة البيانات.'
        : 'Could not load messages from the database.';
}

$totalMessages = count($messages);
$unreadCount = 0;
$latestUpdate = '-';

foreach ($messages as $msg) {
    if (($msg['status'] ?? '') !== 'read') {
        $unreadCount++;
    }
}
if (!empty($messages) && !empty($messages[0]['created_at'])) {
    $latestUpdate = $messages[0]['created_at'];
}

function lang_link_doctor_messages($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
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
            --danger: #ef4444;
            --warning: #f59e0b;
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

        .alert-info {
            background: rgba(245,158,11,0.14);
            border: 1px solid rgba(245,158,11,0.28);
            color: #fff0cc;
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

        .content-grid {
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 22px;
            align-items: start;
        }

        .messages-list {
            display: grid;
            gap: 16px;
        }

        .message-card {
            border-radius: 24px;
            padding: 20px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.10);
            transition: 0.25s ease;
        }

        .message-card:hover {
            transform: translateY(-3px);
            border-color: rgba(76,201,240,0.28);
        }

        .message-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .message-subject {
            font-size: 19px;
            font-weight: 800;
            line-height: 1.5;
        }

        .message-from {
            color: var(--text-soft);
            font-size: 13px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .status-read {
            background: rgba(34,197,94,0.16);
            border: 1px solid rgba(34,197,94,0.30);
            color: #dcffe5;
        }

        .status-unread {
            background: rgba(245,158,11,0.16);
            border: 1px solid rgba(245,158,11,0.30);
            color: #fff2cf;
        }

        .message-body {
            color: rgba(255,255,255,0.92);
            line-height: 1.9;
            font-size: 14px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message-date {
            margin-top: 14px;
            color: rgba(255,255,255,0.60);
            font-size: 12px;
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
            .hero,
            .content-grid {
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
                <i class="fa-solid fa-envelope-open-text"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($text['app_name']) ?></h1>
                <p><?= htmlspecialchars($text['page_title']) ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= htmlspecialchars(lang_link_doctor_messages('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
                <a href="<?= htmlspecialchars(lang_link_doctor_messages('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
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
                <i class="fa-solid fa-bell"></i>
                <span><?= htmlspecialchars($text['hero_badge']) ?></span>
            </div>

            <h2><?= htmlspecialchars($text['hero_title']) ?></h2>
            <p><?= htmlspecialchars($text['hero_desc']) ?></p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['total_messages']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$totalMessages) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['unread']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$unreadCount) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= htmlspecialchars($text['latest_update']) ?></div>
                <div class="value"><?= htmlspecialchars((string)$latestUpdate) ?></div>
            </div>
        </div>
    </section>

    <?php if ($errorMsg): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <?php if ($infoMsg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($infoMsg) ?></div>
    <?php endif; ?>

    <div class="content-grid">
        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['inbox']) ?></h3>
                    <p><?= htmlspecialchars($text['inbox_desc']) ?></p>
                </div>
            </div>
                        <?php if (!empty($messages)): ?>
                <div class="messages-list">
                    <?php foreach ($messages as $msg): ?>
                        <?php
                            $isRead = (($msg['status'] ?? '') === 'read');
                            $statusClass = $isRead ? 'status-read' : 'status-unread';
                            $statusText  = $isRead ? $text['read'] : $text['unread_status'];
                        ?>
                        <div class="message-card">
                            <div class="message-head">
                                <div class="message-meta">
                                    <div class="message-subject">
                                        <?= htmlspecialchars($msg['subject'] ?? $text['fallback_subj']) ?>
                                    </div>
                                    <div class="message-from">
                                        <?= htmlspecialchars($text['from']) ?>:
                                        <strong><?= htmlspecialchars($msg['sender'] ?? $text['fallback_sender']) ?></strong>
                                    </div>
                                </div>

                                <div class="status-badge <?= $statusClass ?>">
                                    <i class="fa-solid fa-circle"></i>
                                    <?= htmlspecialchars($statusText) ?>
                                </div>
                            </div>

                            <div class="message-body">
                                <?= nl2br(htmlspecialchars($msg['body'] ?? '')) ?>
                            </div>

                            <div class="message-date">
                                <i class="fa-regular fa-calendar"></i>
                                <?= htmlspecialchars($text['date']) ?>:
                                <?= htmlspecialchars($msg['created_at'] ?? '-') ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">
                    <i class="fa-regular fa-envelope" style="font-size:34px; margin-bottom:12px;"></i><br>
                    <?= htmlspecialchars($text['empty']) ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel glass">
            <div class="panel-title-row">
                <div class="icon">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
                <div>
                    <h3><?= htmlspecialchars($text['summary']) ?></h3>
                    <p><?= htmlspecialchars($text['summary_desc']) ?></p>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px;">
                <div class="mini-card">
                    <div class="label"><?= htmlspecialchars($text['total_messages']) ?></div>
                    <div class="value"><?= htmlspecialchars((string)$totalMessages) ?></div>
                </div>

                <div class="mini-card">
                    <div class="label"><?= htmlspecialchars($text['unread']) ?></div>
                    <div class="value"><?= htmlspecialchars((string)$unreadCount) ?></div>
                </div>

                <div class="mini-card">
                    <div class="label"><?= htmlspecialchars($text['note']) ?></div>
                    <div class="value" style="font-size:15px; line-height:1.7;">
                        <?= htmlspecialchars($text['note_text']) ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($text['app_name']) ?> — <?= ($lang === 'ar') ? 'واجهة رسائل احترافية للطبيب' : 'Professional Doctor Messages Interface' ?>
    </div>
</div>

</body>
</html>