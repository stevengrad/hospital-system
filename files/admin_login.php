<?php
session_start();
include("db_connect.php");

/* 🌍 Handle language toggle */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

/* 📝 Language Text */
$text = [
    'en' => [
        'title' => 'Admin Login | Cairo Hospitals',
        'heading' => 'Admin Portal',
        'subheading' => 'Please enter your credentials to access the dashboard.',
        'username' => 'Username',
        'password' => 'Password',
        'login' => 'Sign In',
        'back' => '← Back to Patient Login',
        'error_fill' => 'Please fill in all fields.',
        'error_password' => 'Invalid password!',
        'error_credentials' => 'Invalid admin credentials!',
        'error_db' => 'Database error. Please try again later.',
    ],
    'ar' => [
        'title' => 'تسجيل دخول المسؤول | مستشفيات القاهرة',
        'heading' => 'بوابة المسؤول',
        'subheading' => 'يرجى إدخال بيانات الاعتماد للوصول إلى لوحة التحكم.',
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور',
        'login' => 'تسجيل الدخول',
        'back' => '← الرجوع لتسجيل دخول المرضى',
        'error_fill' => 'يرجى ملء جميع الحقول.',
        'error_password' => 'كلمة المرور غير صحيحة!',
        'error_credentials' => 'بيانات المسؤول غير صحيحة!',
        'error_db' => 'خطأ في قاعدة البيانات. يرجى المحاولة لاحقًا.',
    ]
];
$t = $text[$lang];

$error = "";

/* 🔐 Login Logic */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username == "" || $password == "") {
        $error = $t['error_fill'];
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_login WHERE username = ? LIMIT 1");

        if ($stmt === false) {
            $error = $t['error_db'];
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                if ($password === $admin['password']) {
                    $_SESSION['user_id']  = $admin['id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role']     = 'admin';
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error = $t['error_password'];
                }
            } else {
                $error = $t['error_credentials'];
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['title']) ?></title>
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
            --error-red: #ef4444;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at top right, #1e293b, var(--bg-dark));
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: var(--text-main);
        }

        .login-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .hospital-logo {
            background: var(--accent-blue);
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
            color: #0b141e;
            box-shadow: 0 10px 20px rgba(112, 209, 244, 0.3);
        }

        h1 { font-size: 24px; margin: 0; color: var(--text-main); }
        h2 { font-size: 18px; color: var(--accent-blue); margin-bottom: 10px; font-weight: 400; }
        .subheading { font-size: 13px; color: var(--text-muted); margin-bottom: 30px; }

        .form-group {
            position: relative;
            margin-bottom: 20px;
            text-align: <?= ($dir === 'rtl') ? 'right' : 'left' ?>;
        }

        label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent-blue);
            margin-bottom: 8px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 14px 15px;
            background: var(--input-bg);
            border: 1px solid #334155;
            border-radius: 12px;
            color: #fff;
            box-sizing: border-box;
            font-family: inherit;
            transition: 0.3s;
        }

        input:focus {
            border-color: var(--accent-blue);
            outline: none;
            box-shadow: 0 0 0 4px rgba(112, 209, 244, 0.1);
        }

        .password-wrapper { position: relative; }
        .toggle-eye {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            <?= ($dir === 'rtl') ? 'left:15px;' : 'right:15px;' ?>
            cursor: pointer;
            color: var(--text-muted);
            font-size: 18px;
        }

        button {
            width: 100%;
            padding: 15px;
            background: var(--accent-blue);
            border: none;
            color: #0b141e;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(112, 209, 244, 0.2);
            opacity: 0.9;
        }

        .error-box {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error-red);
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid var(--error-red);
        }

        .lang-switch {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .lang-switch a {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 15px;
            border-radius: 50px;
            background: #334155;
        }

        .lang-switch a.active {
            background: var(--accent-blue);
            color: #0b141e;
        }

        .back-link {
            margin-top: 25px;
            display: block;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 14px;
        }

        .back-link:hover { color: var(--accent-blue); }
    </style>
</head>
<body>

<div class="login-card">
    <div class="lang-switch">
        <a href="?lang=en" class="<?= ($lang=='en'?'active':'') ?>">EN</a>
        <a href="?lang=ar" class="<?= ($lang=='ar'?'active':'') ?>">AR</a>
    </div>

    <div class="hospital-logo">
        <i class="fa-solid fa-shield-halved"></i>
    </div>

    <h1>Cairo Hospitals</h1>
    <h2><?= htmlspecialchars($t['heading']) ?></h2>
    <p class="subheading"><?= htmlspecialchars($t['subheading']) ?></p>

    <?php if (!empty($error)): ?>
        <div class="error-box"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label><?= $t['username'] ?></label>
            <input type="text" name="username" placeholder="e.g. admin_01" required>
        </div>

        <div class="form-group">
            <label><?= $t['password'] ?></label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="••••••••" required>
                <span class="toggle-eye" onclick="togglePassword(this)">
                    <i class="fa-regular fa-eye"></i>
                </span>
            </div>
        </div>

        <button type="submit"><?= htmlspecialchars($t['login']) ?></button>
    </form>

    <a href="index.php" class="back-link"><?= htmlspecialchars($t['back']) ?></a>
</div>

<script>
function togglePassword(eyeElement) {
    const passwordInput = document.getElementById("password");
    const icon = eyeElement.querySelector("i");
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.className = "fa-regular fa-eye-slash";
    } else {
        passwordInput.type = "password";
        icon.className = "fa-regular fa-eye";
    }
}
</script>

</body>
</html>