<?php

session_start();

require_once "db_connect.php";

/* language switch */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}

require_once __DIR__ . "/lang/init_lang.php";   // sets $T and $_SESSION['lang']

$lang = $_SESSION['lang'] ?? 'en';
$dir  = $T['dir'] ?? (($lang === 'ar') ? 'rtl' : 'ltr');

$error = "";

// Simple language toggle link builder
function lang_link_index($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

/**
 * Verify password for both:
 * 1) already hashed passwords
 * 2) old plain-text passwords
 *
 * If old plain-text password matches, it upgrades it automatically to hash.
 */
function verifyAndUpgradePassword(mysqli $conn, string $enteredPassword, array $userRow): bool {
    $storedPassword = $userRow['password'] ?? '';
    $userId = (int)($userRow['id'] ?? 0);

    // Case 1: already hashed
    if (password_get_info($storedPassword)['algo'] !== null) {
        return password_verify($enteredPassword, $storedPassword);
    }

    // Case 2: old plain-text password
    if ($enteredPassword === $storedPassword) {
        $newHash = password_hash($enteredPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE login SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();
        }

        return true;
    }

    return false;
}

/* =========================
   FACE LOGIN
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['face_login'])) {
    $face_verified = (isset($_POST['face_verified']) && $_POST['face_verified'] === '1');
    $face_identity = trim($_POST['face_identity'] ?? '');

    if (!$face_verified || $face_identity === '') {
        $error = ($lang === 'ar')
            ? "فشل التحقق بالوجه."
            : "Face verification failed.";
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, role
            FROM login
            WHERE username = ?
            LIMIT 1
        ");

        if ($stmt === false) {
            $error = ($lang === 'ar')
                ? "خطأ في قاعدة البيانات."
                : "Database error.";
        } else {
            $stmt->bind_param("s", $face_identity);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'] ?? 'patient';

                if (strtolower($user['role']) === 'doctor') {
                    header("Location: doctor_dashboard.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = ($lang === 'ar')
                    ? "الوجه معروف لكن لا يوجد حساب مطابق في جدول login."
                    : "Face recognized, but no matching account found in login table.";
            }

            $stmt->close();
        }
    }
}

/* =========================
   NORMAL LOGIN
========================= */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username    = trim($_POST['username'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = ($lang === 'ar')
            ? "من فضلك أدخل اسم المستخدم وكلمة المرور."
            : "Please enter Username and Password.";
    } else {

        // 1) first get account by username only
        $stmt = $conn->prepare("
            SELECT id, username, national_id, password, role
            FROM login
            WHERE username = ?
            LIMIT 1
        ");

        if ($stmt === false) {
            $error = ($lang === 'ar')
                ? "خطأ في قاعدة البيانات."
                : "Database error.";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $account = $result->fetch_assoc();
                $role = strtolower(trim($account['role'] ?? 'patient'));

                // -------------------------
                // Doctor Login
                // -------------------------
                if ($role === 'doctor') {
                    if (verifyAndUpgradePassword($conn, $password, $account)) {
                        $_SESSION['user_id']  = $account['id'];
                        $_SESSION['username'] = $account['username'];
                        $_SESSION['role']     = 'doctor';

                        header("Location: doctor_dashboard.php");
                        exit();
                    } else {
                        $error = ($lang === 'ar')
                            ? "كلمة المرور غير صحيحة."
                            : "Wrong password.";
                    }
                }

                // -------------------------
                // Patient Login
                // -------------------------
                else {
                    if (empty($national_id)) {
                        $error = ($lang === 'ar')
                            ? "الرقم القومي مطلوب لدخول المريض."
                            : "National ID is required for patient login.";
                    } elseif (!preg_match('/^[0-9]{14}$/', $national_id)) {
                        $error = ($lang === 'ar')
                            ? "يجب أن يتكون الرقم القومي من 14 رقمًا."
                            : "National ID must be exactly 14 digits.";
                    } elseif (($account['national_id'] ?? '') !== $national_id) {
                        $error = ($lang === 'ar')
                            ? "الرقم القومي غير صحيح."
                            : "Invalid National ID.";
                    } elseif (verifyAndUpgradePassword($conn, $password, $account)) {
                        $_SESSION['user_id']  = $account['id'];
                        $_SESSION['username'] = $account['username'];
                        $_SESSION['role']     = $role ?: 'patient';

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = ($lang === 'ar')
                            ? "كلمة المرور غير صحيحة!"
                            : "Wrong password!";
                    }
                }

            } else {
                $error = ($lang === 'ar')
                    ? "اسم المستخدم غير موجود."
                    : "Username not found.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png?v=2">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($T['app_name'] ?? 'Hospital Login') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root{
    --bg-1:#08111f;
    --bg-2:#0d1b2f;
    --bg-3:#12385f;
    --primary:#1f8fff;
    --primary-dark:#116ad0;
    --success:#17a34a;
    --success-dark:#12833c;
    --text:#0f172a;
    --muted:#64748b;
    --line:#dbe4ee;
    --card:rgba(255,255,255,0.92);
    --white:#ffffff;
    --shadow:0 20px 60px rgba(2, 12, 27, 0.35);
}

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Inter',sans-serif;
    min-height:100vh;
    background:
        radial-gradient(circle at 15% 20%, rgba(31,143,255,0.25), transparent 22%),
        radial-gradient(circle at 85% 18%, rgba(34,197,94,0.18), transparent 20%),
        radial-gradient(circle at 50% 85%, rgba(14,165,233,0.18), transparent 24%),
        linear-gradient(135deg, var(--bg-1), var(--bg-2) 45%, var(--bg-3));
    display:flex;
    align-items:center;
    justify-content:center;
    padding:28px 16px;
    color:var(--text);
}

.page-shell{
    width:100%;
    max-width:1180px;
    display:grid;
    grid-template-columns: 1.05fr 0.95fr;
    gap:32px;
    align-items:center;
}

.hero-panel{
    color:#fff;
    padding:20px 6px 20px 10px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:10px 14px;
    border:1px solid rgba(255,255,255,0.16);
    background:rgba(255,255,255,0.08);
    border-radius:999px;
    font-size:13px;
    font-weight:600;
    letter-spacing:0.2px;
    margin-bottom:20px;
    backdrop-filter: blur(10px);
}

.hero-title{
    font-size: clamp(34px, 5vw, 58px);
    line-height:1.04;
    font-weight:800;
    letter-spacing:-1.5px;
    margin-bottom:16px;
}

.hero-text{
    max-width:560px;
    font-size:16px;
    line-height:1.8;
    color:rgba(255,255,255,0.84);
    margin-bottom:28px;
}

.hero-points{
    display:grid;
    grid-template-columns:repeat(2, minmax(180px, 1fr));
    gap:14px;
    max-width:560px;
}

.point-card{
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:18px;
    padding:16px 16px;
    backdrop-filter: blur(10px);
}

.point-title{
    font-size:14px;
    font-weight:700;
    margin-bottom:5px;
    color:#fff;
}

.point-text{
    font-size:13px;
    line-height:1.6;
    color:rgba(255,255,255,0.75);
}

.login-card{
    width:100%;
    max-width:520px;
    margin-inline:auto;
    background:var(--card);
    border:1px solid rgba(255,255,255,0.7);
    backdrop-filter: blur(18px);
    border-radius:28px;
    box-shadow:var(--shadow);
    padding:28px 28px 24px;
    position:relative;
}

.lang-toggle{
    position:absolute;
    top:18px;
    <?= ($dir === 'rtl') ? 'left' : 'right' ?>: 18px;
    display:flex;
    gap:8px;
}

.lang-toggle a{
    min-width:44px;
    text-align:center;
    padding:8px 10px;
    border-radius:12px;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    border:1px solid #c7d5e4;
    color:#27527b;
    background:rgba(255,255,255,0.65);
    transition:all .2s ease;
}

.lang-toggle a:hover{
    background:#eef6ff;
    border-color:#9cc5f1;
}

.brand-wrap{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:14px;
    margin-top:6px;
    margin-bottom:10px;
}

.brand-badge{
    width:54px;
    height:54px;
    border-radius:18px;
    background:linear-gradient(135deg, #e8f3ff, #f0fff5);
    border:1px solid #dbeafe;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    box-shadow:0 8px 24px rgba(31,143,255,0.12);
}


.brand-badge img{
    width:50px;
    height:50px;
    object-fit:contain;
    border-radius:16px;
    display:block;
}

.brand-badge span{
    display:none;
}

.brand-badge.logo-fallback img{
    display:none;
}

.brand-badge.logo-fallback span{
    display:block;
}

.brand-text h1{
    font-size:20px;
    font-weight:800;
    line-height:1.2;
    color:#136f33;
    text-align:center;
}

.subtitle{
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-bottom:22px;
    line-height:1.7;
}

.error{
    background:#fff1f2;
    border:1px solid #fecdd3;
    color:#be123c;
    text-align:center;
    margin-bottom:14px;
    font-size:14px;
    border-radius:14px;
    padding:12px 14px;
    font-weight:600;
}

.form-group{
    margin-bottom:14px;
}

.input-wrap{
    position:relative;
}

.input-wrap .icon{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'right:14px;' : 'left:14px;' ?>
    font-size:15px;
    color:#7c90a8;
    pointer-events:none;
}

.form-input{
    width:100%;
    height:56px;
    border:1px solid var(--line);
    border-radius:16px;
    font-size:15px;
    background:#f8fbff;
    color:var(--text);
    transition:all .2s ease;
    <?= ($dir === 'rtl') ? 'padding:0 44px 0 48px;' : 'padding:0 48px 0 44px;' ?>
}

.form-input:focus{
    outline:none;
    border-color:#7ab6f7;
    box-shadow:0 0 0 4px rgba(31,143,255,0.10);
    background:#fff;
}

.form-input::placeholder{
    color:#8a9aae;
}

.helper-line{
    font-size:12px;
    color:#7f8ea3;
    margin-top:4px;
    padding-inline:4px;
}

.primary-btn,
.secondary-btn,
.face-action-btn{
    width:100%;
    border:none;
    border-radius:16px;
    height:54px;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    transition:all .2s ease;
}

.primary-btn{
    margin-top:8px;
    background:linear-gradient(135deg, var(--success), #22c55e);
    color:#fff;
    box-shadow:0 12px 24px rgba(23,163,74,0.20);
}

.primary-btn:hover{
    background:linear-gradient(135deg, var(--success-dark), #16a34a);
    transform:translateY(-1px);
}

.secondary-btn{
    margin-top:10px;
    background:#fff;
    color:#11713b;
    border:2px solid rgba(23,163,74,0.25);
}

.secondary-btn:hover{
    background:#f2fff6;
}

.links{
    text-align:center;
    margin-top:18px;
    font-size:14px;
    line-height:1.9;
}

.links a{
    color:#0f62b8;
    text-decoration:none;
    font-weight:600;
}

.links a:hover{
    text-decoration:underline;
}

.face-panel{
    margin-top:16px;
    border-radius:20px;
    border:1px solid #d9e3ee;
    padding:16px;
    background:linear-gradient(180deg, #f9fcff 0%, #f5f9fd 100%);
    display:none;
}

.face-panel h3{
    font-size:15px;
    margin-bottom:10px;
    text-align:center;
    color:#12375c;
    font-weight:800;
}

.face-box{
    position:relative;
    width:100%;
    max-width:372px;
    height:270px;
    margin:10px auto;
    border-radius:18px;
    overflow:hidden;
    border:2px solid rgba(23,163,74,0.45);
    background:#09111d;
    box-shadow:0 10px 30px rgba(0,0,0,0.12);
}

.face-box video{
    width:100%;
    height:100%;
    object-fit:cover;
}

.face-box canvas{
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    z-index:10;
}

.face-action-btn{
    margin-top:8px;
    background:linear-gradient(135deg, var(--primary), #35a2ff);
    color:#fff;
    box-shadow:0 10px 24px rgba(31,143,255,0.18);
}

.face-action-btn:hover{
    background:linear-gradient(135deg, var(--primary-dark), #1f8fff);
}

.face-status{
    font-size:13px;
    margin-top:10px;
    text-align:center;
    color:#41576f;
    min-height:20px;
    line-height:1.6;
}

.small-note{
    font-size:12px;
    color:#73859a;
    margin-top:8px;
    text-align:center;
}

.verified-banner{
    text-align:center;
    font-weight:800;
    color:#15803d;
    margin-top:10px;
    display:none;
    background:#ecfdf3;
    border:1px solid #bbf7d0;
    border-radius:12px;
    padding:10px;
    font-size:13px;
}

.lang-toggle a.active{
    background:#eef6ff;
    border-color:#9cc5f1;
    color:#0f62b8;
}

.toggle-eye{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'left:16px;' : 'right:16px;' ?>
    width:22px;
    height:22px;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    color:#6b7c93;
    z-index:5;
}

.toggle-eye i{
    font-size:18px;
}

/* NEW: Forgot password link */
.forgot-password-wrap{
    display:flex;
    justify-content:<?= ($dir === 'rtl') ? 'flex-start' : 'flex-end' ?>;
    margin-top:10px;
    margin-bottom:2px;
    padding-inline:4px;
}

.forgot-password-link{
    text-decoration:none;
    font-size:14px;
    font-weight:700;
    color:#6d84a3;
    transition:all .2s ease;
}

.forgot-password-link:hover{
    color:#0f62b8;
    text-decoration:underline;
}

@media (max-width: 980px){
    .page-shell{
        grid-template-columns:1fr;
        gap:18px;
    }

    .hero-panel{
        display:none;
    }

    .login-card{
        max-width:560px;
    }
}

@media (max-width: 560px){
    body{
        padding:18px 12px;
    }

    .login-card{
        padding:22px 18px 20px;
        border-radius:22px;
    }

    .lang-toggle{
        top:14px;
        <?= ($dir === 'rtl') ? 'left' : 'right' ?>: 14px;
    }

    .brand-wrap{
        flex-direction:column;
        gap:10px;
        margin-top:10px;
    }

    .brand-text h1{
        font-size:18px;
    }

    .forgot-password-link{
        font-size:13px;
    }
}
</style>
</head>
<body>

<div class="page-shell">
    <section class="hero-panel">
        <div class="eyebrow">
            <span>🏥</span>
            <span><?= ($lang === 'ar') ? 'بوابة رعاية رقمية متكاملة' : 'A premium digital care gateway' ?></span>
        </div>

        <h2 class="hero-title">
            <?= ($lang === 'ar')
                ? 'رعاية صحية أكثر سهولة وخصوصية واحترافية'
                : 'Healthcare access that feels private, seamless, and professional' ?>
        </h2>

        <p class="hero-text">
            <?= ($lang === 'ar')
                ? 'ادخل إلى حسابك لإدارة المواعيد، متابعة السجل الطبي، والوصول إلى خدمات المستشفى بسرعة وأمان من مكان واحد.'
                : 'Sign in to manage appointments, review medical history, and access hospital services securely from one refined experience.' ?>
        </p>

        <div class="hero-points">
            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'تجربة آمنة' : 'Secure access' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'تسجيل دخول عادي أو بالتعرف على الوجه مع تجربة دخول موثوقة.' : 'Use standard login or facial recognition with a trusted sign-in flow.' ?></div>
            </div>
            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'إدارة سهلة' : 'Simple management' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'الوصول السريع للمواعيد، السجل الطبي، والخدمات الطبية.' : 'Reach appointments, medical history, and hospital services with ease.' ?></div>
            </div>
            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'للمرضى والأطباء' : 'For patients and doctors' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'واجهة موحدة مع توجيه ذكي حسب نوع الحساب بعد تسجيل الدخول.' : 'One unified login with smart redirection based on the account type.' ?></div>
            </div>
            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'مظهر احترافي' : 'Professional experience' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'واجهة حديثة تعكس جودة مشروع مستشفى احترافي ومتكامل.' : 'A modern interface that reflects a polished hospital-grade experience.' ?></div>
            </div>
        </div>
    </section>

    <section class="login-card">
        <div class="lang-toggle">
            <a href="<?= htmlspecialchars(lang_link_index('en')) ?>"
               class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="<?= htmlspecialchars(lang_link_index('ar')) ?>"
               class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge" id="indexBrandLogo">
                <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals" onerror="document.getElementById('indexBrandLogo').classList.add('logo-fallback');">
                <span>👨‍⚕️</span>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($T['app_name'] ?? 'Cairo Hospitals') ?></h1>
            </div>
        </div>

        <p class="subtitle">
            <?= ($lang === 'ar')
                ? 'تسجيل الدخول كـ مريض أو طبيب للوصول إلى لوحة التحكم والخدمات المرتبطة بحسابك.'
                : 'Login as a patient or doctor to access your dashboard and account services.' ?>
        </p>

        <?php if (!empty($error)) : ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <!-- NORMAL LOGIN FORM -->
        <form id="login-form" method="POST" onsubmit="return validateNID();">
            <div class="form-group">
                <div class="input-wrap">
                    <span class="icon">👤</span>
                    <input class="form-input" type="text" name="username"
                           placeholder="<?= ($lang === 'ar')
                                ? 'اسم المستخدم (الأطباء يبدأون بـ dr)'
                                : 'Username (doctors start with dr)' ?>"
                           required>
                </div>
            </div>

            <div class="form-group" id="nid-group">
                <div class="input-wrap">
                    <span class="icon">🪪</span>
                    <input class="form-input"
                           type="text"
                           name="national_id"
                           id="national_id"
                           placeholder="<?= ($lang === 'ar')
                                ? 'الرقم القومي (للمرضى فقط)'
                                : 'National ID (patients only)' ?>"
                           maxlength="14">
                </div>

                <div class="helper-line">
                    <?= ($lang === 'ar')
                        ? 'هذه الخانة مطلوبة للمرضى فقط، وليست مطلوبة لحسابات الأطباء.'
                        : 'This field is required for patients only and not required for doctor accounts.' ?>
                </div>

                <p id="nid-error" class="error" style="display:none; margin-top:8px;"></p>
            </div>

            <div class="form-group">
                <div class="input-wrap">
                    <span class="icon">🔒</span>

                    <input class="form-input"
                           type="password"
                           name="password"
                           id="password"
                           placeholder="<?= ($lang === 'ar') ? 'كلمة المرور' : 'Password' ?>"
                           required>

                    <span class="toggle-eye" onclick="togglePassword(this)">
                        <i class="fa-regular fa-eye"></i>
                    </span>
                </div>

                <div class="forgot-password-wrap">
                    <a href="forgot_password.php" class="forgot-password-link">
                        <?= ($lang === 'ar') ? 'هل نسيت كلمة المرور؟' : 'Forgot Password?' ?>
                    </a>
                </div>
            </div>

            <button class="primary-btn" type="submit" name="login">
                <?= ($lang === 'ar') ? 'تسجيل الدخول' : 'Login' ?>
            </button>

            <button type="button" class="secondary-btn" id="face-login-btn">
                <?= ($lang === 'ar') ? 'تسجيل الدخول بالتعرف على الوجه' : 'Login with Face Recognition' ?>
            </button>

            <!-- Face recognition panel -->
            <div class="face-panel" id="face-panel">
                <h3><?= ($lang === 'ar') ? 'الدخول بالتعرف على الوجه' : 'Face Recognition Login' ?></h3>

                <div class="face-box">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="overlay"></canvas>
                </div>

                <button type="button" id="capture-face" class="face-action-btn">
                    <?= ($lang === 'ar') ? 'تحقق وسجل الدخول' : 'Verify & Login' ?>
                </button>

                <div class="face-status" id="face-status"></div>
                <div class="verified-banner" id="verified-banner">
                    <?= ($lang === 'ar') ? 'تم التحقق بنجاح، جاري تسجيل الدخول...' : 'Verified successfully. Logging you in...' ?>
                </div>
                <p class="small-note">
                    <?= ($lang === 'ar')
                        ? 'يرجى التأكد من وضوح الوجه ووجود إضاءة مناسبة قبل التحقق.'
                        : 'Please make sure your face is clearly visible and the lighting is good before verification.' ?>
                </p>
            </div>
        </form>

        <!-- HIDDEN FACE LOGIN FORM -->
        <form id="face-login-form" method="POST" style="display:none;">
            <input type="hidden" name="face_login" value="1">
            <input type="hidden" name="face_verified" id="face_verified" value="0">
            <input type="hidden" name="face_identity" id="face_identity" value="">
        </form>

        <div class="links">
            <div><a href="register.php"><?= ($lang === 'ar') ? 'إنشاء حساب جديد' : 'Create new account' ?></a></div>
            <div><a href="admin_login.php"><?= ($lang === 'ar') ? 'دخول المشرف' : 'Admin Login' ?></a></div>
        </div>
    </section>
</div>

<script>
const lang = '<?= $lang ?>';

// --------- National ID validation (for patients) ----------
document.getElementById("national_id").addEventListener("input", function () {
    this.value = this.value.replace(/[^0-9]/g, "");
});

function validateNID() {
    const nidInput = document.getElementById("national_id");
    const errorBox = document.getElementById("nid-error");
    const nid = nidInput.value.trim();

    // لو المستخدم سايب الرقم القومي فاضي، نسيب الـ PHP يحدد هل هو دكتور ولا مريض
    if (nid === "") {
        errorBox.style.display = "none";
        return true;
    }

    if (!/^[0-9]{14}$/.test(nid)) {
        errorBox.textContent = (lang === 'ar')
            ? "إذا أدخلت الرقم القومي، يجب أن يكون 14 رقمًا."
            : "If you enter National ID, it must be exactly 14 digits.";
        errorBox.style.display = "block";
        return false;
    }

    errorBox.style.display = "none";
    return true;
}

// --------- Face Recognition Login ----------
const faceBtn = document.getElementById('face-login-btn');
const facePanel = document.getElementById('face-panel');
const video = document.getElementById('video');
const canvas = document.getElementById('overlay');
const ctx = canvas.getContext('2d');
const statusEl = document.getElementById('face-status');
const bannerEl = document.getElementById('verified-banner');
const faceVerifiedField = document.getElementById('face_verified');
const faceIdentityField = document.getElementById('face_identity');

let streamRef = null;

async function startCamera() {
    try {
        streamRef = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: "user" },
            audio: false
        });

        video.srcObject = streamRef;

        video.onloadedmetadata = () => {
            video.play();
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
        };

        statusEl.textContent = (lang === 'ar')
            ? "الكاميرا جاهزة. اضغط تحقق وسجل الدخول."
            : "Camera ready. Click Verify & Login.";

    } catch (err) {
        console.error(err);
        statusEl.textContent = (lang === 'ar')
            ? "تعذر فتح الكاميرا."
            : "Could not open camera.";
    }
}

function stopCamera() {
    if (streamRef) {
        streamRef.getTracks().forEach(track => track.stop());
        streamRef = null;
    }
}

faceBtn.addEventListener('click', async () => {
    bannerEl.style.display = 'none';

    if (facePanel.style.display === 'none' || facePanel.style.display === '') {
        facePanel.style.display = 'block';
        await startCamera();
    } else {
        facePanel.style.display = 'none';
        stopCamera();
    }
});

document.getElementById('capture-face').addEventListener('click', async () => {
    if (!video.videoWidth) {
        statusEl.textContent = (lang === 'ar')
            ? "الكاميرا ليست جاهزة بعد."
            : "Camera not ready yet.";
        return;
    }

    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = canvas.toDataURL("image/jpeg", 0.9);

    statusEl.textContent = (lang === 'ar')
        ? "جاري التحقق من الوجه..."
        : "Verifying face...";

    try {
        const response = await fetch("/face/verify_face", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                image: imageData
            })
        });

        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Face API returned non-JSON:", text);
            throw new Error("Face API returned invalid response");
        }

        if (!response.ok || data.success === false) {
            faceVerifiedField.value = "0";
            faceIdentityField.value = "";
            bannerEl.style.display = "none";
            statusEl.textContent = data.error || data.message || ((lang === 'ar') ? "فشل التحقق." : "Verification failed.");
            return;
        }

        const faceMatched = Boolean(data.matched || data.verified);
        const identity = data.identity || data.username || data.patient_username || "";

        if (faceMatched && identity && identity !== "unknown") {
            faceVerifiedField.value = "1";
            faceIdentityField.value = identity;

            bannerEl.style.display = "block";
            statusEl.textContent = (lang === 'ar')
                ? `تم التعرف على: ${identity}`
                : `Recognized as: ${identity}`;

            stopCamera();

            setTimeout(() => {
                document.getElementById('face-login-form').submit();
            }, 700);

        } else {
            faceVerifiedField.value = "0";
            faceIdentityField.value = "";
            bannerEl.style.display = "none";
            const details = (data.distance !== undefined && data.threshold !== undefined)
                ? ` Distance: ${data.distance}, threshold: ${data.threshold}`
                : "";

            statusEl.textContent = (lang === 'ar')
                ? "الوجه غير مسجل أو غير مطابق في قاعدة البيانات." + details
                : "Face not found or not matched in database." + details;
        }

    } catch (err) {
        console.error(err);
        faceVerifiedField.value = "0";
        faceIdentityField.value = "";
        bannerEl.style.display = "none";
        statusEl.textContent = (lang === 'ar')
            ? "تعذر الاتصال بخدمة التعرف على الوجه."
            : "Could not connect to face recognition service.";
    }
});

const usernameInput = document.querySelector("input[name='username']");
const nidGroup = document.getElementById("nid-group");

usernameInput.addEventListener("input", function () {
    const username = this.value.toLowerCase();

    if (username.startsWith("dr")) {
        nidGroup.style.display = "none";
    } else {
        nidGroup.style.display = "block";
    }
});

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