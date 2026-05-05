<?php
session_start();
include('db_connect.php');

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   Language
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$text = [
    'en' => [
        'page_title'        => 'Forgot Password',
        'app_name'          => 'Cairo Hospitals',
        'hero_badge'        => 'Password Recovery',
        'hero_title'        => 'Recover your account securely',
        'hero_desc'         => 'Enter your email address to receive a one-time verification code, then set a new password safely.',
        'card_subtitle'     => 'Follow the steps below to reset your password.',
        'email'             => 'Email Address',
        'email_placeholder' => 'Enter your email address',
        'send_code'         => 'Send OTP Code',
        'otp'               => 'Verification Code',
        'otp_placeholder'   => 'Enter the code sent to your email',
        'verify_code'       => 'Verify Code',
        'new_password'      => 'New Password',
        'confirm_password'  => 'Confirm Password',
        'reset_password'    => 'Save New Password',
        'back_login'        => 'Back to Login',
        'step_1'            => 'Step 1: Verify Email',
        'step_2'            => 'Step 2: Verify OTP',
        'step_3'            => 'Step 3: Set New Password',
        'email_not_found'   => 'This email is not registered.',
        'code_sent'         => 'OTP code has been sent to your email.',
        'code_invalid'      => 'The code you entered is incorrect.',
        'code_verified'     => 'Code verified successfully. You can now set a new password.',
        'password_mismatch' => 'Passwords do not match.',
        'password_invalid'  => 'Password must be at least 8 characters and contain uppercase, lowercase, number, and special character.',
        'password_updated'  => 'Password updated successfully. You can now log in.',
        'email_send_fail'   => 'Could not send OTP email. Please try again.',
        'show_password'     => 'Show password',
        'hide_password'     => 'Hide password',
        'email_subject'     => 'Your Cairo Hospitals Password Reset Code',
        'email_greeting'    => 'Password Reset Request',
        'email_body_1'      => 'We received a request to reset your password.',
        'email_body_2'      => 'Use the following OTP code to continue:',
        'email_note'        => 'This code expires after 10 minutes. If you did not request this, please ignore this email.',
    ],
    'ar' => [
        'page_title'        => 'نسيت كلمة المرور',
        'app_name'          => 'مستشفيات القاهرة',
        'hero_badge'        => 'استعادة كلمة المرور',
        'hero_title'        => 'استعد الوصول إلى حسابك بأمان',
        'hero_desc'         => 'أدخل بريدك الإلكتروني لاستلام كود تحقق مؤقت، ثم قم بتعيين كلمة مرور جديدة بشكل آمن.',
        'card_subtitle'     => 'اتبع الخطوات التالية لإعادة تعيين كلمة المرور.',
        'email'             => 'البريد الإلكتروني',
        'email_placeholder' => 'أدخل بريدك الإلكتروني',
        'send_code'         => 'إرسال كود التحقق',
        'otp'               => 'كود التحقق',
        'otp_placeholder'   => 'أدخل الكود المرسل إلى بريدك الإلكتروني',
        'verify_code'       => 'تأكيد الكود',
        'new_password'      => 'كلمة المرور الجديدة',
        'confirm_password'  => 'تأكيد كلمة المرور',
        'reset_password'    => 'حفظ كلمة المرور الجديدة',
        'back_login'        => 'العودة لتسجيل الدخول',
        'step_1'            => 'الخطوة 1: التحقق من البريد الإلكتروني',
        'step_2'            => 'الخطوة 2: التحقق من الكود',
        'step_3'            => 'الخطوة 3: تعيين كلمة مرور جديدة',
        'email_not_found'   => 'هذا البريد الإلكتروني غير مسجل.',
        'code_sent'         => 'تم إرسال كود التحقق إلى بريدك الإلكتروني.',
        'code_invalid'      => 'الكود الذي أدخلته غير صحيح.',
        'code_verified'     => 'تم التحقق من الكود بنجاح. يمكنك الآن تعيين كلمة مرور جديدة.',
        'password_mismatch' => 'كلمتا المرور غير متطابقتين.',
        'password_invalid'  => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم ورمز خاص.',
        'password_updated'  => 'تم تحديث كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.',
        'email_send_fail'   => 'تعذر إرسال كود التحقق. حاول مرة أخرى.',
        'show_password'     => 'إظهار كلمة المرور',
        'hide_password'     => 'إخفاء كلمة المرور',
        'email_subject'     => 'كود إعادة تعيين كلمة المرور - مستشفيات القاهرة',
        'email_greeting'    => 'طلب إعادة تعيين كلمة المرور',
        'email_body_1'      => 'لقد استلمنا طلبًا لإعادة تعيين كلمة المرور الخاصة بك.',
        'email_body_2'      => 'استخدم كود التحقق التالي لإكمال العملية:',
        'email_note'        => 'تنتهي صلاحية هذا الكود خلال 10 دقائق. إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.',
    ]
];

$t = $text[$lang];
$message = '';
$messageType = '';
$step = 1;

/* =========================
   Helper: send OTP email
========================= */
function sendResetOtpEmailSMTP($toEmail, $otpCode, $lang, $text) {
    $smtpUser = 'cairohospitals0@gmail.com';
    $smtpPass = 'nyqt rjbf pyoo qsfx';
    $fromName = 'Cairo Hospitals';

    if (empty($toEmail) || empty($smtpUser) || empty($smtpPass)) {
        return ['ok' => false, 'reason' => 'smtp_not_configured'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtpUser, $fromName);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $text[$lang]['email_subject'];

        $mail->Body = '
        <html>
        <body style="font-family:Arial,sans-serif;line-height:1.9;color:#0f172a;">
            <h2 style="color:#116ad0;">' . htmlspecialchars($text[$lang]['email_greeting']) . '</h2>
            <p>' . htmlspecialchars($text[$lang]['email_body_1']) . '</p>
            <p>' . htmlspecialchars($text[$lang]['email_body_2']) . '</p>
            <div style="background:#f8fafc;border:1px solid #dbe4ee;border-radius:14px;padding:18px;text-align:center;margin:18px 0;">
                <div style="font-size:13px;color:#64748b;margin-bottom:6px;">OTP Code</div>
                <div style="font-size:34px;font-weight:800;letter-spacing:4px;color:#dc2626;">' . htmlspecialchars($otpCode) . '</div>
            </div>
            <p>' . htmlspecialchars($text[$lang]['email_note']) . '</p>
        </body>
        </html>';

        $mail->AltBody =
            $text[$lang]['email_greeting'] . "\n\n" .
            $text[$lang]['email_body_1'] . "\n" .
            $text[$lang]['email_body_2'] . "\n\n" .
            'OTP Code: ' . $otpCode . "\n\n" .
            $text[$lang]['email_note'];

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'reason' => $mail->ErrorInfo];
    }
}

/* =========================
   Determine current step
========================= */
if (isset($_SESSION['forgot_verified']) && $_SESSION['forgot_verified'] === true) {
    $step = 3;
} elseif (isset($_SESSION['forgot_email'])) {
    $step = 2;
}

/* =========================
   Step 1: Send OTP
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = $t['email_not_found'];
        $messageType = 'error';
        $step = 1;
    } else {
        $stmt = $conn->prepare("SELECT id, email, national_id FROM registration WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRow = $result->fetch_assoc();
        $stmt->close();

        if (!$userRow) {
            $message = $t['email_not_found'];
            $messageType = 'error';
            $step = 1;
        } else {
            $otp = (string)random_int(100000, 999999);

            $_SESSION['forgot_email'] = $email;
            $_SESSION['forgot_otp'] = $otp;
            $_SESSION['forgot_national_id'] = $userRow['national_id'] ?? '';
            $_SESSION['forgot_otp_expires'] = time() + 600;
            unset($_SESSION['forgot_verified']);

            $mailResult = sendResetOtpEmailSMTP($email, $otp, $lang, $text);

            if ($mailResult['ok']) {
                $message = $t['code_sent'];
                $messageType = 'success';
                $step = 2;
            } else {
                $message = $t['email_send_fail'];
                $messageType = 'error';
                $step = 1;
            }
        }
    }
}

/* =========================
   Step 2: Verify OTP
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $enteredOtp = trim($_POST['otp'] ?? '');

    if (
        !isset($_SESSION['forgot_otp']) ||
        !isset($_SESSION['forgot_otp_expires']) ||
        time() > (int)$_SESSION['forgot_otp_expires']
    ) {
        $message = $t['code_invalid'];
        $messageType = 'error';
        $step = 1;
        unset($_SESSION['forgot_email'], $_SESSION['forgot_otp'], $_SESSION['forgot_otp_expires'], $_SESSION['forgot_verified'], $_SESSION['forgot_national_id']);
    } elseif ($enteredOtp !== (string)$_SESSION['forgot_otp']) {
        $message = $t['code_invalid'];
        $messageType = 'error';
        $step = 2;
    } else {
        $_SESSION['forgot_verified'] = true;
        $message = $t['code_verified'];
        $messageType = 'success';
        $step = 3;
    }
}

/* =========================
   Step 3: Reset Password
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    $validPassword = preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);

    if (!isset($_SESSION['forgot_verified']) || $_SESSION['forgot_verified'] !== true || empty($_SESSION['forgot_email'])) {
        $message = $t['code_invalid'];
        $messageType = 'error';
        $step = 1;

    } elseif ($password !== $confirm) {
        $message = $t['password_mismatch'];
        $messageType = 'error';
        $step = 3;

    } elseif (!$validPassword) {
        $message = $t['password_invalid'];
        $messageType = 'error';
        $step = 3;

    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $email  = $_SESSION['forgot_email'];

        // 1) get the exact user from registration
        $stmtUser = $conn->prepare("
            SELECT id, username, national_id
            FROM registration
            WHERE email = ?
            LIMIT 1
        ");
        $stmtUser->bind_param("s", $email);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        $userRow = $resUser->fetch_assoc();
        $stmtUser->close();

        if (!$userRow) {
            $message = $t['email_not_found'];
            $messageType = 'error';
            $step = 1;
        } else {
            $username   = trim((string)($userRow['username'] ?? ''));
            $nationalId = trim((string)($userRow['national_id'] ?? ''));

            // 2) update registration
            $stmt1 = $conn->prepare("
                UPDATE registration
                SET password = ?
                WHERE id = ?
            ");
            $stmt1->bind_param("si", $hashed, $userRow['id']);
            $stmt1->execute();
            $stmt1->close();

            // 3) update login by username first
            $stmt2 = $conn->prepare("
                UPDATE login
                SET password = ?
                WHERE username = ?
            ");
            $stmt2->bind_param("ss", $hashed, $username);
            $stmt2->execute();
            $affectedByUsername = $stmt2->affected_rows;
            $stmt2->close();

            // 4) if not found by username, update by national_id
            if ($affectedByUsername <= 0 && $nationalId !== '') {
                $stmt3 = $conn->prepare("
                    UPDATE login
                    SET password = ?
                    WHERE national_id = ?
                ");
                $stmt3->bind_param("ss", $hashed, $nationalId);
                $stmt3->execute();
                $stmt3->close();
            }

            unset(
                $_SESSION['forgot_email'],
                $_SESSION['forgot_otp'],
                $_SESSION['forgot_otp_expires'],
                $_SESSION['forgot_verified'],
                $_SESSION['forgot_national_id']
            );

            $message = $t['password_updated'];
            $messageType = 'success';
            $step = 1;
        }
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($t['page_title']) ?></title>
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
    --card:rgba(255,255,255,0.94);
    --white:#ffffff;
    --shadow:0 20px 60px rgba(2, 12, 27, 0.35);
}
*{ box-sizing:border-box; margin:0; padding:0; }

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
    grid-template-columns:1.05fr 0.95fr;
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
    margin-bottom:20px;
    backdrop-filter:blur(10px);
}

.hero-title{
    font-size:clamp(34px, 5vw, 56px);
    line-height:1.04;
    font-weight:800;
    letter-spacing:-1.4px;
    margin-bottom:16px;
}

.hero-text{
    max-width:560px;
    font-size:16px;
    line-height:1.8;
    color:rgba(255,255,255,0.84);
    margin-bottom:28px;
}

.point-card{
    max-width:560px;
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:18px;
    padding:16px;
    backdrop-filter:blur(10px);
}

.point-title{
    font-size:14px;
    font-weight:700;
    margin-bottom:6px;
    color:#fff;
}

.point-text{
    font-size:13px;
    line-height:1.6;
    color:rgba(255,255,255,0.76);
}

.reset-card{
    width:100%;
    max-width:560px;
    margin-inline:auto;
    background:var(--card);
    border:1px solid rgba(255,255,255,0.7);
    backdrop-filter:blur(18px);
    border-radius:28px;
    box-shadow:var(--shadow);
    padding:30px 28px 24px;
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
.lang-toggle a.active,
.lang-toggle a:hover{
    background:#eef6ff;
    border-color:#9cc5f1;
    color:#0f62b8;
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
    width:56px;
    height:56px;
    border-radius:18px;
    background:linear-gradient(135deg, #e8f3ff, #f0fff5);
    border:1px solid #dbeafe;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:28px;
    box-shadow:0 8px 24px rgba(31,143,255,0.12);
}

.brand-text h1{
    font-size:22px;
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

.step-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:100%;
    margin-bottom:16px;
}

.step-badge span{
    display:inline-block;
    background:#eef6ff;
    color:#0f62b8;
    border:1px solid #bfdbfe;
    padding:10px 16px;
    border-radius:999px;
    font-size:13px;
    font-weight:800;
}

.message{
    text-align:center;
    margin-bottom:14px;
    font-size:14px;
    border-radius:14px;
    padding:12px 14px;
    font-weight:700;
}
.message.success{
    background:#ecfdf3;
    border:1px solid #bbf7d0;
    color:#15803d;
}
.message.error{
    background:#fff1f2;
    border:1px solid #fecdd3;
    color:#be123c;
}

.form-group{
    margin-bottom:14px;
}

.field-label{
    font-size:13px;
    font-weight:700;
    color:#27425d;
    margin-bottom:8px;
    display:block;
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

.toggle-eye{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'left:14px;' : 'right:14px;' ?>
    width:22px;
    height:22px;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    color:#6b7c93;
    user-select:none;
}
.toggle-eye i{
    font-size:18px;
}

.btn-submit{
    width:100%;
    border:none;
    border-radius:16px;
    height:54px;
    font-size:15px;
    font-weight:800;
    cursor:pointer;
    transition:all .2s ease;
    background:linear-gradient(135deg, var(--success), #22c55e);
    color:#fff;
    box-shadow:0 12px 24px rgba(23,163,74,0.20);
    margin-top:6px;
}
.btn-submit:hover{
    background:linear-gradient(135deg, var(--success-dark), #16a34a);
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

@media (max-width: 980px){
    .page-shell{
        grid-template-columns:1fr;
        gap:18px;
    }
    .hero-panel{
        display:none;
    }
}
@media (max-width: 560px){
    body{ padding:18px 12px; }
    .reset-card{
        padding:22px 18px 20px;
        border-radius:22px;
    }
}
</style>
</head>
<body>
<div class="page-shell">
    <section class="hero-panel">
        <div class="eyebrow">
            <span>🔐</span>
            <span><?= htmlspecialchars($t['hero_badge']) ?></span>
        </div>

        <h2 class="hero-title"><?= htmlspecialchars($t['hero_title']) ?></h2>
        <p class="hero-text"><?= htmlspecialchars($t['hero_desc']) ?></p>

        <div class="point-card">
            <div class="point-title"><?= ($lang === 'ar') ? 'استعادة آمنة' : 'Secure Recovery' ?></div>
            <div class="point-text">
                <?= ($lang === 'ar')
                    ? 'سيتم إرسال كود تحقق مؤقت إلى بريدك الإلكتروني، وبعد التحقق يمكنك تعيين كلمة مرور جديدة بأمان.'
                    : 'A temporary OTP code will be sent to your email, and after verification you can safely set a new password.' ?>
            </div>
        </div>
    </section>

    <section class="reset-card">
        <div class="lang-toggle">
            <a href="?lang=en" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="?lang=ar" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge">🏥</div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($t['app_name']) ?></h1>
            </div>
        </div>

        <p class="subtitle"><?= htmlspecialchars($t['card_subtitle']) ?></p>

        <div class="step-badge">
            <span>
                <?php
                    if ($step === 1) echo htmlspecialchars($t['step_1']);
                    elseif ($step === 2) echo htmlspecialchars($t['step_2']);
                    else echo htmlspecialchars($t['step_3']);
                ?>
            </span>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= $messageType === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['email']) ?></label>
                    <div class="input-wrap">
                        <span class="icon">✉️</span>
                        <input
                            class="form-input"
                            type="email"
                            name="email"
                            placeholder="<?= htmlspecialchars($t['email_placeholder']) ?>"
                            required
                        >
                    </div>
                </div>

                <button type="submit" name="send_code" class="btn-submit">
                    <?= htmlspecialchars($t['send_code']) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['otp']) ?></label>
                    <div class="input-wrap">
                        <span class="icon">🔢</span>
                        <input
                            class="form-input"
                            type="text"
                            name="otp"
                            placeholder="<?= htmlspecialchars($t['otp_placeholder']) ?>"
                            required
                        >
                    </div>
                </div>

                <button type="submit" name="verify_code" class="btn-submit">
                    <?= htmlspecialchars($t['verify_code']) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['new_password']) ?></label>
                    <div class="input-wrap">
                        <span class="icon">🔒</span>
                        <input
                            class="form-input"
                            type="password"
                            name="password"
                            id="password"
                            placeholder="<?= htmlspecialchars($t['new_password']) ?>"
                            required
                        >
                        <span class="toggle-eye" onclick="togglePassword('password', this)" title="<?= htmlspecialchars($t['show_password']) ?>">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['confirm_password']) ?></label>
                    <div class="input-wrap">
                        <span class="icon">✅</span>
                        <input
                            class="form-input"
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            placeholder="<?= htmlspecialchars($t['confirm_password']) ?>"
                            required
                        >
                        <span class="toggle-eye" onclick="togglePassword('confirm_password', this)" title="<?= htmlspecialchars($t['show_password']) ?>">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" name="reset_password" class="btn-submit">
                    <?= htmlspecialchars($t['reset_password']) ?>
                </button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="index.php"><?= htmlspecialchars($t['back_login']) ?></a>
        </div>
    </section>
</div>

<script>
function togglePassword(inputId, eyeElement) {
    const input = document.getElementById(inputId);
    const icon = eyeElement.querySelector('i');

    if (input.type === "password") {
        input.type = "text";
        icon.className = "fa-regular fa-eye-slash";
        eyeElement.title = "<?= addslashes($t['hide_password']) ?>";
    } else {
        input.type = "password";
        icon.className = "fa-regular fa-eye";
        eyeElement.title = "<?= addslashes($t['show_password']) ?>";
    }
}
</script>
</body>
</html>