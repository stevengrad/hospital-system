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
    <link rel="icon" href="favicon.ico" type="image/x-icon">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($T['app_name'] ?? 'Hospital Login') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root{
    --navy:#071426;
    --navy-2:#0b2138;
    --teal:#0f5e78;
    --blue:#0b78d0;
    --green:#18b85a;
    --green-dark:#0f8d43;
    --text:#08233d;
    --muted:#5f728a;
    --line:#d7e3ef;
    --soft:#f6f9fc;
    --white:#ffffff;
    --shadow:0 26px 70px rgba(3, 19, 38, 0.36);
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
    font-family:'Inter',sans-serif;
    min-height:100vh;
    color:var(--text);
    background:
        linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px),
        radial-gradient(circle at 18% 18%, rgba(11,120,208,.25), transparent 28%),
        radial-gradient(circle at 88% 10%, rgba(24,184,90,.16), transparent 24%),
        linear-gradient(135deg, var(--navy) 0%, var(--navy-2) 48%, var(--teal) 100%);
    background-size:56px 56px,56px 56px,auto,auto,auto;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px 18px;
}
.page-shell{
    width:100%;
    max-width:1240px;
    display:grid;
    grid-template-columns:1.08fr .92fr;
    gap:42px;
    align-items:center;
}
.hero-panel{color:#fff;padding:12px 0}
.eyebrow{
    display:inline-flex;align-items:center;gap:10px;
    padding:10px 16px;margin-bottom:22px;border-radius:999px;
    background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.18);
    font-size:14px;font-weight:800;box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
}
.hero-title{
    max-width:690px;
    font-size:clamp(38px,4.75vw,64px);
    line-height:1.04;font-weight:900;letter-spacing:-2.2px;margin-bottom:18px;
}
.hero-text{
    max-width:675px;font-size:17px;line-height:1.75;color:rgba(255,255,255,.88);margin-bottom:24px;
}
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;max-width:675px;margin-bottom:18px}
.stat-card{
    min-height:104px;padding:18px 18px;border-radius:20px;
    background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.17);
    backdrop-filter:blur(10px);
}
.stat-card i{font-size:24px;margin-bottom:10px;color:#fff}
.stat-number{font-size:26px;font-weight:900;line-height:1;color:#fff;margin-bottom:7px}
.stat-label{font-size:14px;line-height:1.35;color:rgba(255,255,255,.86);font-weight:600}
.hero-services{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;max-width:675px}
.service-card{
    display:flex;gap:14px;align-items:flex-start;min-height:118px;
    background:rgba(255,255,255,.92);color:var(--text);
    border:1px solid rgba(255,255,255,.7);border-radius:22px;padding:18px 18px;
    box-shadow:0 12px 28px rgba(0,0,0,.08);
}
.service-icon{
    flex:0 0 48px;width:48px;height:48px;border-radius:16px;
    display:flex;align-items:center;justify-content:center;
    background:#eaf5ff;color:var(--blue);font-size:21px;
}
.service-title{font-size:16px;font-weight:900;margin-bottom:6px;color:#06213b}
.service-text{font-size:14px;line-height:1.55;color:#52667f}
.login-card{
    width:100%;max-width:500px;margin-inline:auto;position:relative;
    background:rgba(255,255,255,.94);border:1px solid rgba(255,255,255,.75);
    border-radius:30px;box-shadow:var(--shadow);padding:30px 28px 26px;
    backdrop-filter:blur(18px);
}
.lang-toggle{position:absolute;top:22px;<?= ($dir === 'rtl') ? 'left' : 'right' ?>:22px;display:flex;gap:8px;z-index:2}
.lang-toggle a{
    min-width:45px;text-align:center;padding:9px 11px;border-radius:13px;text-decoration:none;
    font-size:13px;font-weight:900;color:#0f5599;background:#f4f8fd;border:1px solid #b8d3ee;transition:.2s;
}
.lang-toggle a.active,.lang-toggle a:hover{background:#eaf4ff;border-color:#84bdf0;color:#0b65b8}
.brand-wrap{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:18px;margin-bottom:12px;width:100%;text-align:center;padding-inline:96px}
.brand-logo{width:46px;height:46px;border-radius:16px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e8f4ff,#effff5);border:1px solid #d8eafe;color:#0b78d0;font-size:22px;box-shadow:0 8px 22px rgba(11,120,208,.10);flex:0 0 46px}
.brand-badge{display:none}
.brand-text h1{font-size:24px;font-weight:900;color:#116d35;line-height:1.2;text-align:center;white-space:nowrap}
.login-title{text-align:center;font-size:28px;line-height:1.15;font-weight:900;color:#06213b;margin-top:8px;margin-bottom:10px}
.subtitle{text-align:center;color:var(--muted);font-size:14.5px;line-height:1.7;margin-bottom:22px}
.error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c;text-align:center;margin-bottom:14px;font-size:14px;border-radius:14px;padding:12px 14px;font-weight:700}
.form-group{margin-bottom:15px}.input-wrap{position:relative}.input-wrap .icon{position:absolute;top:50%;transform:translateY(-50%);<?= ($dir === 'rtl') ? 'right:18px;' : 'left:18px;' ?>font-size:17px;color:#0b70bd;pointer-events:none;width:18px;text-align:center}
.form-input{
    width:100%;height:58px;border:1px solid #cfe0f0;border-radius:18px;font-size:15px;background:#f8fbff;color:var(--text);transition:.2s;
    <?= ($dir === 'rtl') ? 'padding:0 52px 0 52px;' : 'padding:0 52px 0 52px;' ?>
}
.form-input:focus{outline:none;border-color:#78b6ec;box-shadow:0 0 0 4px rgba(11,120,208,.11);background:#fff}.form-input::placeholder{color:#8294aa}
.helper-line{font-size:12.5px;color:#71839a;margin-top:7px;padding-inline:4px;line-height:1.45}
.primary-btn,.secondary-btn,.face-action-btn{width:100%;border:none;border-radius:17px;min-height:56px;font-size:16px;font-weight:900;cursor:pointer;transition:.2s;display:flex;align-items:center;justify-content:center;gap:10px;text-align:center}
.primary-btn{margin-top:8px;background:linear-gradient(135deg,var(--green),#25c86b);color:#fff;box-shadow:0 14px 28px rgba(24,184,90,.24)}.primary-btn:hover{background:linear-gradient(135deg,var(--green-dark),#18b85a);transform:translateY(-1px)}
.secondary-btn{margin-top:12px;background:#fff;color:#076b35;border:2px solid rgba(24,184,90,.25)}.secondary-btn:hover{background:#f3fff8}
.links{display:flex;justify-content:center;gap:18px;flex-wrap:wrap;text-align:center;margin-top:20px;font-size:14px;line-height:1.8}.links a{color:#075da9;text-decoration:none;font-weight:900}.links a:hover{text-decoration:underline}
.face-panel{margin-top:16px;border-radius:22px;border:1px solid #d9e6f2;padding:16px;background:linear-gradient(180deg,#f9fcff,#f3f8fd);display:none}.face-panel h3{font-size:15px;margin-bottom:10px;text-align:center;color:#12375c;font-weight:900}.face-box{position:relative;width:100%;max-width:372px;height:270px;margin:10px auto;border-radius:18px;overflow:hidden;border:2px solid rgba(24,184,90,.45);background:#09111d;box-shadow:0 10px 30px rgba(0,0,0,.12)}.face-box video{width:100%;height:100%;object-fit:cover}.face-box canvas{position:absolute;top:0;left:0;width:100%;height:100%;z-index:10}.face-action-btn{margin-top:8px;background:linear-gradient(135deg,var(--blue),#35a2ff);color:#fff;box-shadow:0 10px 24px rgba(31,143,255,.18)}.face-status{font-size:13px;margin-top:10px;text-align:center;color:#41576f;min-height:20px;line-height:1.6}.small-note{font-size:12px;color:#73859a;margin-top:8px;text-align:center}.verified-banner{text-align:center;font-weight:900;color:#15803d;margin-top:10px;display:none;background:#ecfdf3;border:1px solid #bbf7d0;border-radius:12px;padding:10px;font-size:13px}
.toggle-eye{position:absolute;top:50%;transform:translateY(-50%);<?= ($dir === 'rtl') ? 'left:18px;' : 'right:18px;' ?>width:24px;height:24px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#61758e;z-index:5}.toggle-eye i{font-size:18px}.forgot-password-wrap{display:flex;justify-content:<?= ($dir === 'rtl') ? 'flex-start' : 'flex-end' ?>;margin-top:9px;margin-bottom:2px;padding-inline:4px}.forgot-password-link{text-decoration:none;font-size:14px;font-weight:900;color:#617b9a}.forgot-password-link:hover{color:#075da9;text-decoration:underline}
@media (max-width:1050px){.page-shell{grid-template-columns:1fr;gap:22px}.hero-panel{display:block}.hero-title{font-size:42px}.login-card{max-width:560px}.stats-row,.hero-services,.hero-text{max-width:100%}}
@media (max-width:760px){body{padding:18px 12px;align-items:flex-start}.page-shell{gap:18px}.hero-panel{padding-top:0}.hero-title{font-size:36px}.stats-row,.hero-services{grid-template-columns:1fr}.service-card{min-height:auto}.login-card{padding:26px 18px 22px;border-radius:24px}.brand-wrap{padding-inline:76px;margin-top:52px}.links{gap:12px}.face-box{height:230px}}
@media (max-width:520px){.hero-panel{display:none}.brand-wrap{padding-inline:0;margin-top:56px}.brand-logo{width:42px;height:42px;font-size:20px}.brand-text h1{font-size:21px}.login-title{font-size:24px}.form-input{height:56px}.primary-btn,.secondary-btn,.face-action-btn{min-height:54px}.lang-toggle{top:16px;<?= ($dir === 'rtl') ? 'left' : 'right' ?>:16px}}
</style>
</head>
<body>

<div class="page-shell">
    <section class="hero-panel">
        <div class="eyebrow">
            <i class="fa-solid fa-hospital"></i>
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

        <div class="stats-row">
            <div class="stat-card">
                <i class="fa-solid fa-clock"></i>
                <div class="stat-number">24/7</div>
                <div class="stat-label"><?= ($lang === 'ar') ? 'وصول سريع للخدمات' : 'Fast service access' ?></div>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-lock"></i>
                <div class="stat-number"><?= ($lang === 'ar') ? 'آمن' : 'Secure' ?></div>
                <div class="stat-label"><?= ($lang === 'ar') ? 'حماية بيانات المرضى' : 'Protected patient data' ?></div>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-user-doctor"></i>
                <div class="stat-number"><?= ($lang === 'ar') ? 'ذكي' : 'Smart' ?></div>
                <div class="stat-label"><?= ($lang === 'ar') ? 'حسابات أطباء ومرضى' : 'Doctor & patient accounts' ?></div>
            </div>
        </div>

        <div class="hero-services">
            <div class="service-card">
                <div class="service-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <div class="service-title"><?= ($lang === 'ar') ? 'إدارة المواعيد' : 'Appointment management' ?></div>
                    <div class="service-text"><?= ($lang === 'ar') ? 'تابع مواعيدك وخدمات المستشفى من لوحة واحدة.' : 'Track appointments and hospital services from one dashboard.' ?></div>
                </div>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fa-solid fa-notes-medical"></i></div>
                <div>
                    <div class="service-title"><?= ($lang === 'ar') ? 'السجل الطبي' : 'Medical history' ?></div>
                    <div class="service-text"><?= ($lang === 'ar') ? 'طريقة واضحة للوصول إلى معلوماتك الصحية.' : 'A clear way to access your health information.' ?></div>
                </div>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fa-solid fa-camera"></i></div>
                <div>
                    <div class="service-title"><?= ($lang === 'ar') ? 'التعرف على الوجه' : 'Face recognition' ?></div>
                    <div class="service-text"><?= ($lang === 'ar') ? 'تسجيل دخول ذكي عند توفر خدمة التعرف على الوجه.' : 'Smart sign-in when face recognition is available.' ?></div>
                </div>
            </div>
            <div class="service-card">
                <div class="service-icon"><i class="fa-solid fa-stethoscope"></i></div>
                <div>
                    <div class="service-title"><?= ($lang === 'ar') ? 'تجربة احترافية' : 'Professional experience' ?></div>
                    <div class="service-text"><?= ($lang === 'ar') ? 'تصميم واقعي مناسب لموقع مستشفى.' : 'A realistic design suitable for a hospital website.' ?></div>
                </div>
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
            <div class="brand-logo"><i class="fa-solid fa-hospital-user"></i></div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($T['app_name'] ?? 'Cairo Hospitals') ?></h1>
            </div>
        </div>

        <h2 class="login-title"><?= ($lang === 'ar') ? 'تسجيل الدخول' : 'Login' ?></h2>

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
                    <span class="icon"><i class="fa-solid fa-user"></i></span>
                    <input class="form-input" type="text" name="username"
                           placeholder="<?= ($lang === 'ar')
                                ? 'اسم المستخدم '
                                : 'Username ' ?>"
                           required>
                </div>
            </div>

            <div class="form-group" id="nid-group">
                <div class="input-wrap">
                    <span class="icon"><i class="fa-solid fa-id-card"></i></span>
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
                    <span class="icon"><i class="fa-solid fa-lock"></i></span>

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
                <i class="fa-solid fa-right-to-bracket"></i>
                <?= ($lang === 'ar') ? 'تسجيل الدخول' : 'Login' ?>
            </button>

            <button type="button" class="secondary-btn" id="face-login-btn">
                <i class="fa-solid fa-camera"></i>
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
            <div><a href="register.php"><i class="fa-solid fa-user-plus"></i> <?= ($lang === 'ar') ? 'إنشاء حساب جديد' : 'Create new account' ?></a></div>
            <div><a href="admin_login.php"><i class="fa-solid fa-user-gear"></i> <?= ($lang === 'ar') ? 'دخول المشرف' : 'Admin Login' ?></a></div>
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
// --------- Face Recognition Login ----------
const faceBtn = document.getElementById('face-login-btn');
const facePanel = document.getElementById('face-panel');
const video = document.getElementById('video');
const canvas = document.getElementById('overlay');
const statusEl = document.getElementById('face-status');
const bannerEl = document.getElementById('verified-banner');
const faceVerifiedField = document.getElementById('face_verified');
const faceIdentityField = document.getElementById('face_identity');

const ctx = canvas ? canvas.getContext('2d') : null;

let streamRef = null;

async function startCamera() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Camera API is not available. Use HTTPS.");
        }

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
        console.error("Camera error:", err);
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

if (faceBtn) {
    faceBtn.addEventListener('click', async () => {
        if (bannerEl) bannerEl.style.display = 'none';

        if (facePanel.style.display === 'none' || facePanel.style.display === '') {
            facePanel.style.display = 'block';
            await startCamera();
        } else {
            facePanel.style.display = 'none';
            stopCamera();
        }
    });
}

const captureFaceBtn = document.getElementById('capture-face');

if (captureFaceBtn) {
    captureFaceBtn.addEventListener('click', async () => {
        if (!video || !video.videoWidth || !ctx) {
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
    const response = await fetch("https://cairohospitals.click/face/verify_face", {
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

    console.log("Face verify response:", data);

    if (!response.ok || !data.success) {
        faceVerifiedField.value = "0";
        faceIdentityField.value = "";
        bannerEl.style.display = "none";
        statusEl.textContent = data.error || data.message || ((lang === 'ar') ? "فشل التحقق." : "Verification failed.");
        return;
    }

    if (data.verified && data.identity && data.identity !== "unknown") {
        faceVerifiedField.value = "1";
        faceIdentityField.value = data.identity;

        bannerEl.style.display = "block";
        statusEl.textContent = (lang === 'ar')
            ? `تم التعرف على: ${data.identity}`
            : `Recognized as: ${data.identity}`;

        stopCamera();

        setTimeout(() => {
            document.getElementById('face-login-form').submit();
        }, 700);

    } else {
        faceVerifiedField.value = "0";
        faceIdentityField.value = "";
        bannerEl.style.display = "none";

        statusEl.textContent = (lang === 'ar')
            ? `الوجه غير مطابق. Distance: ${data.distance}, threshold: ${data.threshold}`
            : `Face not matched. Distance: ${data.distance}, threshold: ${data.threshold}`;
    }

} catch (err) {
    console.error("Face verification connection/error:", err);
    faceVerifiedField.value = "0";
    faceIdentityField.value = "";
    bannerEl.style.display = "none";
    statusEl.textContent = (lang === 'ar')
        ? "تعذر الاتصال بخدمة التعرف على الوجه."
        : "Could not connect to face recognition service.";
}
    });
}
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