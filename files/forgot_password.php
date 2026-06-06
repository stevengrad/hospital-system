<?php
session_start();
include('db_connect.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

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
        'hero_desc'         => 'Enter your username to receive a one-time verification code on your registered email, then set a new password safely.',
        'card_subtitle'     => 'Follow the steps below to reset your password.',
        'username'          => 'Username',
        'username_placeholder' => 'Enter your username',
        'send_code'         => 'Send OTP Code',
        'back_login'        => 'Back to Login',
        'step_1'            => 'Step 1: Verify Username',
        'username_not_found'=> 'This username is not registered.',
        'no_email'          => 'This username does not have a registered email.',
        'email_send_fail'   => 'Could not send OTP email. Please try again.',
        'email_subject'     => 'Your Cairo Hospitals Password Reset Code',
        'email_greeting'    => 'Password Reset Request',
        'email_body_1'      => 'We received a request to reset your password.',
        'email_body_2'      => 'Use the following OTP code to continue:',
        'email_note'        => 'This code expires after 5 minutes. If you did not request this, please ignore this email.',
    ],
    'ar' => [
        'page_title'        => 'نسيت كلمة المرور',
        'app_name'          => 'مستشفيات القاهرة',
        'hero_badge'        => 'استعادة كلمة المرور',
        'hero_title'        => 'استعد الوصول إلى حسابك بأمان',
        'hero_desc'         => 'أدخل اسم المستخدم ليتم إرسال كود تحقق مؤقت إلى البريد الإلكتروني المسجل، ثم قم بتعيين كلمة مرور جديدة بأمان.',
        'card_subtitle'     => 'اتبع الخطوات التالية لإعادة تعيين كلمة المرور.',
        'username'          => 'اسم المستخدم',
        'username_placeholder' => 'أدخل اسم المستخدم',
        'send_code'         => 'إرسال كود التحقق',
        'back_login'        => 'العودة لتسجيل الدخول',
        'step_1'            => 'الخطوة 1: التحقق من اسم المستخدم',
        'username_not_found'=> 'اسم المستخدم غير مسجل.',
        'no_email'          => 'لا يوجد بريد إلكتروني مسجل لهذا المستخدم.',
        'email_send_fail'   => 'تعذر إرسال كود التحقق. حاول مرة أخرى.',
        'email_subject'     => 'كود إعادة تعيين كلمة المرور - مستشفيات القاهرة',
        'email_greeting'    => 'طلب إعادة تعيين كلمة المرور',
        'email_body_1'      => 'لقد استلمنا طلبًا لإعادة تعيين كلمة المرور الخاصة بك.',
        'email_body_2'      => 'استخدم كود التحقق التالي لإكمال العملية:',
        'email_note'        => 'تنتهي صلاحية هذا الكود خلال 5 دقائق. إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.',
    ]
];

$t = $text[$lang];
$message = '';
$messageType = '';
$username = '';

/* =========================
   Helper: send OTP email
========================= */
function sendResetOtpEmailSMTP($toEmail, $otp, $lang, $text) {
    $mail = new PHPMailer(true);

    try {
        /*
            Best: put these in docker-compose.yml under php environment:
            SMTP_USER: cairohospitals0@gmail.com
            SMTP_PASS: your_gmail_app_password
        */
        $smtpUser = getenv("SMTP_USER") ?: "cairohospitals0@gmail.com";
        $smtpPass = getenv("SMTP_PASS") ?: "PUT_YOUR_WORKING_GMAIL_APP_PASSWORD_HERE";

        if (empty($smtpUser) || empty($smtpPass) || $smtpPass === "PUT_YOUR_WORKING_GMAIL_APP_PASSWORD_HERE") {
            return [
                "ok" => false,
                "reason" => "SMTP credentials are not configured."
            ];
        }

        $mail->isSMTP();

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = "UTF-8";
        $mail->setFrom($smtpUser, "Cairo Hospitals");
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = ($lang === "ar")
            ? "رمز إعادة تعيين كلمة المرور"
            : "Password Reset OTP";

        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, "UTF-8");

        $mail->Body = '
        <div style="font-family:Arial,sans-serif;background:#f8fafc;padding:30px;">
            <div style="max-width:520px;margin:auto;background:#ffffff;border-radius:18px;padding:28px;border:1px solid #e5e7eb;">
                <h2 style="color:#146c33;text-align:center;">Cairo Hospitals</h2>

                <p style="font-size:16px;color:#334155;text-align:center;">
                    ' . htmlspecialchars($text[$lang]['email_body_1'], ENT_QUOTES, "UTF-8") . '
                </p>

                <p style="font-size:16px;color:#334155;text-align:center;">
                    ' . htmlspecialchars($text[$lang]['email_body_2'], ENT_QUOTES, "UTF-8") . '
                </p>

                <div style="font-size:34px;font-weight:bold;letter-spacing:8px;text-align:center;color:#0f62b8;background:#eef6ff;border-radius:14px;padding:18px;margin:24px 0;">
                    ' . $safeOtp . '
                </div>

                <p style="color:#dc2626;font-weight:bold;text-align:center;">
                    ' . htmlspecialchars($text[$lang]['email_note'], ENT_QUOTES, "UTF-8") . '
                </p>
            </div>
        </div>';

        $mail->AltBody =
            $text[$lang]['email_greeting'] . "\n\n" .
            $text[$lang]['email_body_1'] . "\n" .
            $text[$lang]['email_body_2'] . "\n\n" .
            "OTP Code: " . $otp . "\n\n" .
            $text[$lang]['email_note'];

        $mail->send();

        return [
            "ok" => true,
            "reason" => ""
        ];

    } catch (Exception $e) {
        return [
            "ok" => false,
            "reason" => $mail->ErrorInfo
        ];
    }
}

/* =========================
   Step 1: Send OTP by username
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $username = trim($_POST["username"] ?? "");

    if ($username === "") {
        $message = ($lang === 'ar') ? "يرجى إدخال اسم المستخدم." : "Please enter your username.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, email
            FROM registration
            WHERE username = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $message = ($lang === 'ar') ? "خطأ في قاعدة البيانات." : "Database error.";
            $messageType = "error";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $message = $t['username_not_found'];
                $messageType = "error";
            } else {
                $reset_username = trim((string)$user["username"]);
                $reset_email = trim((string)$user["email"]);

                if ($reset_email === "") {
                    $message = $t['no_email'];
                    $messageType = "error";
                } else {
                    unset($_SESSION['forgot_verified']);

                    $otp = (string) random_int(100000, 999999);
                    $expires_at = date("Y-m-d H:i:s", time() + 300); // 5 minutes

                    /*
                        These sessions are used by verify_otp.php and reset_password.php
                    */
                    $_SESSION["reset_email"] = $reset_email;
                    $_SESSION["reset_username"] = $reset_username;

                    /*
                        Also keep these for compatibility if any old logic still reads them
                    */
                    $_SESSION["forgot_email"] = $reset_email;
                    $_SESSION["forgot_username"] = $reset_username;
                    $_SESSION["forgot_otp"] = $otp;
                    $_SESSION["forgot_otp_expires"] = time() + 300;

                    $deleteOld = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    $deleteOld->bind_param("s", $reset_email);
                    $deleteOld->execute();
                    $deleteOld->close();

                    $insertOtp = $conn->prepare("
                        INSERT INTO password_resets (email, otp, expires_at, verified)
                        VALUES (?, ?, ?, 0)
                    ");

                    if (!$insertOtp) {
                        $message = ($lang === 'ar') ? "خطأ في حفظ كود التحقق." : "Could not save OTP code.";
                        $messageType = "error";
                    } else {
                        $insertOtp->bind_param("sss", $reset_email, $otp, $expires_at);
                        $insertOtp->execute();
                        $insertOtp->close();

                        $mailResult = sendResetOtpEmailSMTP($reset_email, $otp, $lang, $text);

                        if ($mailResult["ok"]) {
                            header("Location: verify_otp.php");
                            exit();
                        } else {
                            $message = $t['email_send_fail'] . " Reason: " . ($mailResult["reason"] ?? "Unknown error");
                            $messageType = "error";
                        }
                    }
                }
            }
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
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png?v=2">
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
    background:#ffffff;
    border:1px solid #dbeafe;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 8px 24px rgba(31,143,255,0.12);
    overflow:hidden;
}

.brand-badge img{
    width:58px;
    height:58px;
    object-fit:contain;
    display:block;
    border-radius:14px;
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
                    ? 'سيتم إرسال كود تحقق مؤقت إلى البريد الإلكتروني المسجل لحسابك، وبعد التحقق يمكنك تعيين كلمة مرور جديدة بأمان.'
                    : 'A temporary OTP code will be sent to the email registered with your username, and after verification you can safely set a new password.' ?>
            </div>
        </div>
    </section>

    <section class="reset-card">
        <div class="lang-toggle">
            <a href="?lang=en" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="?lang=ar" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge">
                <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals">
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($t['app_name']) ?></h1>
            </div>
        </div>

        <p class="subtitle"><?= htmlspecialchars($t['card_subtitle']) ?></p>

        <div class="step-badge">
            <span><?= htmlspecialchars($t['step_1']) ?></span>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= $messageType === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="field-label"><?= htmlspecialchars($t['username']) ?></label>
                <div class="input-wrap">
                    <span class="icon"><i class="fa-solid fa-user"></i></span>
                    <input
                        class="form-input"
                        type="text"
                        name="username"
                        id="username"
                        value="<?= htmlspecialchars($username ?? '') ?>"
                        placeholder="<?= htmlspecialchars($t['username_placeholder']) ?>"
                        required
                    >
                </div>
            </div>

            <button type="submit" name="send_code" class="btn-submit">
                <?= htmlspecialchars($t['send_code']) ?>
            </button>
        </form>

        <div class="links">
            <a href="index.php"><?= htmlspecialchars($t['back_login']) ?></a>
        </div>
    </section>
</div>
</body>
</html>