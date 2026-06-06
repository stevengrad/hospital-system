<?php
session_start();
include 'db_connect.php';

// 🔐 Only admin can access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

// 🌐 Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

// Texts
$texts = [
    'en' => [
        'page_title'   => 'Edit User | Cairo Hospitals',
        'heading'      => 'Modify User Profile',
        'subheading'   => 'Update patient account details and identification.',
        'username'     => 'Username',
        'nid'          => 'National ID (14 digits)',
        'role_label'   => 'Account Role',
        'role_patient' => 'Patient',
        'save'         => 'Update Account',
        'back'         => 'Back to Users',
        'error_fill'   => '⚠️ Please fill in both Username and National ID.',
        'error_nid'    => '⚠️ National ID must be exactly 14 digits.',
        'invalid_id'   => 'Invalid user ID.',
        'not_found'    => 'User not found.',
        'dashboard'    => 'Dashboard',
        'lang_en'      => 'EN',
        'lang_ar'      => 'AR',
    ],
    'ar' => [
        'page_title'   => 'تعديل مستخدم | مستشفيات القاهرة',
        'heading'      => 'تعديل ملف المستخدم',
        'subheading'   => 'تحديث بيانات حساب المريض والرقم القومي.',
        'username'     => 'اسم المستخدم',
        'nid'          => 'الرقم القومي (14 رقمًا)',
        'role_label'   => 'نوع الحساب',
        'role_patient' => 'مريض',
        'save'         => 'تحديث الحساب',
        'back'         => 'العودة للمستخدمين',
        'error_fill'   => '⚠️ يرجى إدخال اسم المستخدم والرقم القومي.',
        'error_nid'    => '⚠️ يجب أن يكون الرقم القومي 14 رقمًا بالضبط.',
        'invalid_id'   => 'معرّف مستخدم غير صالح.',
        'not_found'    => 'المستخدم غير موجود.',
        'dashboard'    => 'الرئيسية',
        'lang_en'      => 'EN',
        'lang_ar'      => 'AR',
    ]
];
$t = $texts[$lang];

// 🔧 FIX: Removed the "shakes" identifier that caused the syntax error
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) die($t['invalid_id']);

$error = "";

// Helper for toggle URL
function lang_link_edit_user($code, $userId) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?id=' . urlencode($userId) . '&lang=' . $code;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');

    if ($username === '' || $national_id === '') {
        $error = $t['error_fill'];
    } elseif (!preg_match('/^[0-9]{14}$/', $national_id)) {
        $error = $t['error_nid'];
    } else {
        $stmt = $conn->prepare("UPDATE registration SET username = ?, national_id = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $national_id, $user_id);
        if ($stmt->execute()) {
            header("Location: manage_users.php?msg=user_updated&type=success");
            exit();
        } else {
            $error = "Database error: " . $stmt->error;
        }
    }
}

// Load user
$stmt = $conn->prepare("SELECT id, username, national_id FROM registration WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die($t['not_found']);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['page_title'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0b141e;
            --card-bg: #1c2a39;
            --accent-blue: #70d1f4;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: #253447;
            --error-red: #f87171;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            margin: 0; padding: 20px;
        }

        /* Navbar */
        .navbar {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .brand { display: flex; align-items: center; gap: 15px; }
        .brand i { background: var(--accent-blue); color: #0b141e; padding: 10px; border-radius: 10px; font-size: 20px; }
        
        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .btn-nav { background: #334155; color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Form Card */
        .glass-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 40px;
            max-width: 500px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
        }

        h1 { font-size: 24px; margin: 0; }
        .subheading { color: var(--text-muted); font-size: 13px; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; text-align: left; }
        html[dir="rtl"] .form-group { text-align: right; }

        label {
            display: block; font-size: 11px; color: var(--accent-blue);
            text-transform: uppercase; letter-spacing: 1.5px;
            margin-bottom: 8px; font-weight: 600;
        }

        input {
            width: 100%; background: var(--input-bg);
            border: 1px solid #334155; border-radius: 12px;
            padding: 14px; color: #fff; box-sizing: border-box;
            transition: 0.3s; font-family: inherit;
        }

        input:focus { border-color: var(--accent-blue); outline: none; }
        input.readonly { background: rgba(15, 23, 42, 0.4); border-color: transparent; color: var(--text-muted); cursor: default; }

        .btn-submit {
            width: 100%; background: var(--accent-blue);
            color: #0b141e; border: none; padding: 16px;
            border-radius: 15px; font-weight: 600; font-size: 16px;
            cursor: pointer; transition: 0.3s; margin-top: 10px;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(112, 209, 244, 0.2); }

        .alert-error {
            background: rgba(239, 68, 68, 0.1); color: var(--error-red);
            padding: 15px; border-radius: 12px; margin-bottom: 20px;
            font-size: 13px; text-align: center; border: 1px solid var(--error-red);
        }

        .lang-switch { display: flex; gap: 5px; background: #334155; padding: 4px; border-radius: 50px; }
        .lang-switch a { text-decoration: none; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 50px; }
        .lang-switch a.active { background: var(--accent-blue); color: #0b141e; font-weight: bold; }

        .back-link { display: block; text-align: center; margin-top: 25px; color: var(--text-muted); text-decoration: none; font-size: 14px; }
        .back-link:hover { color: var(--accent-blue); }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-user-gear"></i>
            <div>
                <h2 style="margin:0; font-size:18px;">Cairo Hospitals</h2>
                <p style="margin:0; font-size:11px; color:var(--text-muted)">System Admin Area</p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="<?= lang_link_edit_user('en', $user_id) ?>" class="<?= $lang=='en'?'active':'' ?>"><?= $t['lang_en'] ?></a>
                <a href="<?= lang_link_edit_user('ar', $user_id) ?>" class="<?= $lang=='ar'?'active':'' ?>"><?= $t['lang_ar'] ?></a>
            </div>
            <a href="admin_dashboard.php" class="btn-nav"><i class="fa-solid fa-chart-line"></i> <?= $t['dashboard'] ?></a>
            <a href="manage_users.php" class="btn-nav" style="background:var(--accent-blue); color:#000;"><i class="fa-solid fa-arrow-left"></i> <?= $t['back'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <h1><?= htmlspecialchars($t['heading']) ?></h1>
        <p class="subheading"><?= htmlspecialchars($t['subheading']) ?></p>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="username"><?= htmlspecialchars($t['username']) ?></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
                <label for="national_id"><?= htmlspecialchars($t['nid']) ?></label>
                <input type="text" id="national_id" name="national_id" maxlength="14" value="<?= htmlspecialchars($user['national_id']) ?>" required>
            </div>

            <div class="form-group">
                <label><?= htmlspecialchars($t['role_label']) ?></label>
                <input type="text" class="readonly" value="<?= htmlspecialchars($t['role_patient']) ?>" readonly>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($t['save']) ?>
            </button>
        </form>

        <a href="manage_users.php" class="back-link"><?= $t['back'] ?></a>
    </div>

    <script>
    // Validation: Only numbers in National ID field
    document.getElementById('national_id').addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g,'');
    });
    </script>

</body>
</html>