<?php
session_start();
include 'db_connect.php';

/* -------------------------------
   LANGUAGE SYSTEM
-------------------------------- */
$lang = $_GET['lang'] ?? ($_SESSION['lang'] ?? 'en');
$_SESSION['lang'] = $lang;
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$t = [
    'en' => [
        'header_title' => 'Contact Messages',
        'subheading' => 'View and manage inquiries sent via the contact form.',
        'dashboard' => 'Dashboard',
        'logout' => 'Logout',
        'sender' => 'Sender',
        'email' => 'Email',
        'message' => 'Message',
        'sent_at' => 'Sent At',
        'actions' => 'Actions',
        'delete' => 'Delete',
        'no_messages' => 'No messages yet.',
        'confirm_del' => 'Delete this message?'
    ],
    'ar' => [
        'header_title' => 'رسائل التواصل',
        'subheading' => 'عرض وإدارة الاستفسارات المرسلة عبر نموذج الاتصال.',
        'dashboard' => 'لوحة التحكم',
        'logout' => 'خروج',
        'sender' => 'المرسل',
        'email' => 'البريد الإلكتروني',
        'message' => 'الرسالة',
        'sent_at' => 'تاريخ الإرسال',
        'actions' => 'الإجراءات',
        'delete' => 'حذف',
        'no_messages' => 'لا توجد رسائل بعد.',
        'confirm_del' => 'هل تريد حذف الرسالة؟'
    ]
][$lang];

/* -----------------------------------
   ADMIN ACCESS CHECK
------------------------------------ */
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$info = "";
$info_type = "";

/* -------------------------
   DELETE MESSAGE
--------------------------- */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $info = ($lang == 'ar') ? "✅ تم حذف الرسالة بنجاح" : "✅ Message deleted successfully.";
                $info_type = "success";
            } else {
                $info = ($lang == 'ar') ? "⚠️ فشل حذف الرسالة" : "⚠️ Failed to delete message.";
                $info_type = "error";
            }
            $stmt->close();
        }
    }
}

$result = $conn->query("SELECT id, name, email, message, created_at FROM contact_messages ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($t['header_title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0b141e;
            --card-bg: #1c2a39;
            --accent-blue: #70d1f4;
            --accent-red: #ef4444;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: #253447;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            padding: 20px;
        }

        /* Navbar */
        .navbar {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .brand { display: flex; align-items: center; gap: 15px; }
        .brand i { background: var(--accent-blue); color: #fff; padding: 10px; border-radius: 10px; font-size: 20px; }
        .brand h2 { margin: 0; font-size: 20px; }

        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .lang-switch { background: #334155; border-radius: 50px; padding: 5px; display: flex; gap: 5px; }
        .lang-switch a { text-decoration: none; color: #fff; padding: 5px 12px; border-radius: 50px; font-size: 12px; }
        .lang-switch a.active { background: var(--accent-blue); color: #000; font-weight: 600; }
        
        .btn-logout { background: var(--accent-red); color: #fff; border: none; padding: 8px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; }

        /* Main Container */
        .glass-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .btn-dashboard { background: #334155; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px; }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: var(--accent-blue); font-size: 12px; padding: 15px; border-bottom: 1px solid #334155; text-transform: uppercase; letter-spacing: 1px; }
        html[dir="rtl"] th { text-align: right; }
        td { padding: 15px; border-bottom: 1px solid #334155; font-size: 14px; vertical-align: top; }

        .sender-name { font-weight: 600; color: var(--text-main); display: block; }
        .sender-email { font-size: 12px; color: var(--accent-blue); }
        .msg-text { color: var(--text-muted); line-height: 1.6; max-width: 450px; word-wrap: break-word; }
        
        .btn-del { color: var(--accent-red); text-decoration: none; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        .btn-del:hover { opacity: 0.8; }

        .status-msg { padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; text-align: center; font-weight: 600; }
        .success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid #4ade80; }
        .error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid #f87171; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-envelope-open-text"></i>
            <div>
                <h2>Cairo Hospitals</h2>
                <p style="margin:0; font-size:12px; color:var(--text-muted)">Inbox</p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $lang==='en'?'active':'' ?>">EN</a>
                <a href="?lang=ar" class="<?= $lang==='ar'?'active':'' ?>">AR</a>
            </div>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> <?= $t['logout'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <div class="header-row">
            <div>
                <h1 style="margin:0"><?= $t['header_title'] ?></h1>
                <p style="color:var(--text-muted); margin:5px 0 0 0"><?= $t['subheading'] ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> <?= $t['dashboard'] ?></a>
        </div>

        <?php if ($info): ?>
            <div class="status-msg <?= $info_type ?>"><?= htmlspecialchars($info) ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th><?= $t['sender'] ?></th>
                    <th><?= $t['message'] ?></th>
                    <th><?= $t['sent_at'] ?></th>
                    <th><?= $t['actions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--text-muted)">#<?= $row['id'] ?></td>
                        <td>
                            <span class="sender-name"><?= htmlspecialchars($row['name']) ?></span>
                            <span class="sender-email"><?= htmlspecialchars($row['email']) ?></span>
                        </td>
                        <td>
                            <div class="msg-text"><?= nl2br(htmlspecialchars($row['message'])) ?></div>
                        </td>
                        <td style="font-size: 12px; color: var(--text-muted);">
                            <?= date('M d, Y', strtotime($row['created_at'])) ?><br>
                            <?= date('h:i A', strtotime($row['created_at'])) ?>
                        </td>
                        <td>
                            <a href="?delete=<?= (int)$row['id'] ?>&lang=<?= $lang ?>" 
                               class="btn-del" 
                               onclick="return confirm('<?= $t['confirm_del'] ?>');">
                                <i class="fa-solid fa-trash-can"></i> <?= $t['delete'] ?>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">
                            <i class="fa-solid fa-folder-open" style="font-size: 30px; display: block; margin-bottom: 10px;"></i>
                            <?= $t['no_messages'] ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>