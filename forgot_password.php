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
        'page_title'             => 'Forgot Password',
        'app_name'               => 'Cairo Hospitals',
        'hero_badge'             => 'Password Recovery',
        'hero_title'             => 'Recover your account securely',
        'hero_desc'              => 'Enter your username or email to receive a one-time verification code, then set a new password safely.',
        'card_subtitle'          => 'Follow the steps below to reset your password.',
        'identifier'             => 'Username or Email',
        'identifier_placeholder' => 'Enter your username or email address',
        'send_code'              => 'Send OTP Code',
        'otp'                    => 'Verification Code',
        'otp_placeholder'        => 'Enter the code sent to your email',
        'verify_code'            => 'Verify Code',
        'new_password'           => 'New Password',
        'confirm_password'       => 'Confirm Password',
        'reset_password'         => 'Save New Password',
        'back_login'             => 'Back to Login',
        'step_1'                 => 'Step 1: Verify Account',
        'step_2'                 => 'Step 2: Verify OTP',
        'step_3'                 => 'Step 3: Set New Password',
        'account_not_found'      => 'This username or email is not registered.',
        'no_email'               => 'This account does not have a registered email.',
        'code_sent'              => 'OTP code has been sent to your registered email.',
        'code_invalid'           => 'The code you entered is incorrect or expired.',
        'code_verified'          => 'Code verified successfully. You can now set a new password.',
        'password_mismatch'      => 'Passwords do not match.',
        'password_invalid'       => 'Password must be at least 8 characters and contain uppercase, lowercase, number, and special character.',
        'password_updated'       => 'Password updated successfully. You can now log in.',
        'email_send_fail'        => 'Could not send OTP email. Please try again.',
        'show_password'          => 'Show password',
        'hide_password'          => 'Hide password',
        'email_subject'          => 'Your Cairo Hospitals Password Reset Code',
        'email_greeting'         => 'Password Reset Request',
        'email_body_1'           => 'We received a request to reset your password.',
        'email_body_2'           => 'Use the following OTP code to continue:',
        'email_note'             => 'This code expires after 10 minutes. If you did not request this, please ignore this email.',
        'secure_note_title'      => 'Secure reset flow',
        'secure_note_text'       => 'For your privacy, the reset code is sent only to the email registered with your hospital account.',
        'smtp_not_configured'    => 'SMTP credentials are not configured in the server environment.',
    ],
    'ar' => [
        'page_title'             => 'نسيت كلمة المرور',
        'app_name'               => 'مستشفيات القاهرة',
        'hero_badge'             => 'استعادة كلمة المرور',
        'hero_title'             => 'استعد الوصول إلى حسابك بأمان',
        'hero_desc'              => 'أدخل اسم المستخدم أو البريد الإلكتروني لاستلام كود تحقق مؤقت، ثم قم بتعيين كلمة مرور جديدة بأمان.',
        'card_subtitle'          => 'اتبع الخطوات التالية لإعادة تعيين كلمة المرور.',
        'identifier'             => 'اسم المستخدم أو البريد الإلكتروني',
        'identifier_placeholder' => 'أدخل اسم المستخدم أو البريد الإلكتروني',
        'send_code'              => 'إرسال كود التحقق',
        'otp'                    => 'كود التحقق',
        'otp_placeholder'        => 'أدخل الكود المرسل إلى بريدك الإلكتروني',
        'verify_code'            => 'تأكيد الكود',
        'new_password'           => 'كلمة المرور الجديدة',
        'confirm_password'       => 'تأكيد كلمة المرور',
        'reset_password'         => 'حفظ كلمة المرور الجديدة',
        'back_login'             => 'العودة لتسجيل الدخول',
        'step_1'                 => 'الخطوة 1: التحقق من الحساب',
        'step_2'                 => 'الخطوة 2: التحقق من الكود',
        'step_3'                 => 'الخطوة 3: تعيين كلمة مرور جديدة',
        'account_not_found'      => 'اسم المستخدم أو البريد الإلكتروني غير مسجل.',
        'no_email'               => 'لا يوجد بريد إلكتروني مسجل لهذا الحساب.',
        'code_sent'              => 'تم إرسال كود التحقق إلى البريد الإلكتروني المسجل.',
        'code_invalid'           => 'الكود الذي أدخلته غير صحيح أو انتهت صلاحيته.',
        'code_verified'          => 'تم التحقق من الكود بنجاح. يمكنك الآن تعيين كلمة مرور جديدة.',
        'password_mismatch'      => 'كلمتا المرور غير متطابقتين.',
        'password_invalid'       => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم ورمز خاص.',
        'password_updated'       => 'تم تحديث كلمة المرور بنجاح. يمكنك الآن تسجيل الدخول.',
        'email_send_fail'        => 'تعذر إرسال كود التحقق. حاول مرة أخرى.',
        'show_password'          => 'إظهار كلمة المرور',
        'hide_password'          => 'إخفاء كلمة المرور',
        'email_subject'          => 'كود إعادة تعيين كلمة المرور - مستشفيات القاهرة',
        'email_greeting'         => 'طلب إعادة تعيين كلمة المرور',
        'email_body_1'           => 'لقد استلمنا طلبًا لإعادة تعيين كلمة المرور الخاصة بك.',
        'email_body_2'           => 'استخدم كود التحقق التالي لإكمال العملية:',
        'email_note'             => 'تنتهي صلاحية هذا الكود خلال 10 دقائق. إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة.',
        'secure_note_title'      => 'استعادة آمنة',
        'secure_note_text'       => 'لحماية خصوصيتك، يتم إرسال كود التحقق فقط إلى البريد الإلكتروني المسجل في حسابك بالمستشفى.',
        'smtp_not_configured'    => 'بيانات SMTP غير مضافة في إعدادات السيرفر.',
    ]
];

$t = $text[$lang];
$message = '';
$messageType = '';
$step = 1;

/* =========================
   Helper Functions
========================= */
function safe_trim($value) {
    return trim((string)($value ?? ''));
}

function findResetUser(mysqli $conn, string $identifier) {
    $identifier = safe_trim($identifier);
    if ($identifier === '') {
        return null;
    }

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("
            SELECT id, username, email, national_id
            FROM registration
            WHERE email = ?
            LIMIT 1
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, email, national_id
            FROM registration
            WHERE username = ?
            LIMIT 1
        ");
    }

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function trySaveOtpInPasswordResets(mysqli $conn, string $email, string $otp, int $expiresTs) {
    /*
        Optional support for your friend's password_resets table.
        If the table does not exist, the session OTP still works.
    */
    try {
        $expiresAt = date("Y-m-d H:i:s", $expiresTs);

        $deleteOld = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        if ($deleteOld) {
            $deleteOld->bind_param("s", $email);
            $deleteOld->execute();
            $deleteOld->close();
        }

        $insertOtp = $conn->prepare("
            INSERT INTO password_resets (email, otp, expires_at, verified)
            VALUES (?, ?, ?, 0)
        ");

        if ($insertOtp) {
            $insertOtp->bind_param("sss", $email, $otp, $expiresAt);
            $insertOtp->execute();
            $insertOtp->close();
        }
    } catch (Throwable $e) {
        // Do not break the reset flow if the optional table does not exist.
    }
}

function tryMarkOtpVerified(mysqli $conn, string $email) {
    try {
        $stmt = $conn->prepare("
            UPDATE password_resets
            SET verified = 1
            WHERE email = ?
        ");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Optional table only.
    }
}

function clearForgotSessions() {
    unset(
        $_SESSION['forgot_email'],
        $_SESSION['forgot_username'],
        $_SESSION['forgot_national_id'],
        $_SESSION['forgot_otp'],
        $_SESSION['forgot_otp_expires'],
        $_SESSION['forgot_verified'],
        $_SESSION['reset_email'],
        $_SESSION['reset_username']
    );
}

/* =========================
   Helper: send OTP email
   AWS-safe: SMTP password is NOT hardcoded.
   Add SMTP_USER and SMTP_PASS to ECS hospital-web-service environment variables.
========================= */
function sendResetOtpEmailSMTP($toEmail, $otpCode, $lang, $text) {
    $smtpUser = getenv("SMTP_USER") ?: "cairohospitals0@gmail.com";
    $smtpPass = getenv("SMTP_PASS") ?: "";
    $smtpHost = getenv("SMTP_HOST") ?: "smtp.gmail.com";
    $smtpPort = (int)(getenv("SMTP_PORT") ?: 587);
    $fromName = getenv("SMTP_FROM_NAME") ?: "Cairo Hospitals";

    if (
        empty($toEmail) ||
        empty($smtpUser) ||
        empty($smtpPass) ||
        $smtpPass === "PUT_YOUR_WORKING_GMAIL_APP_PASSWORD_HERE" ||
        $smtpPass === "YOUR_GMAIL_APP_PASSWORD_HERE"
    ) {
        return [
            "ok" => false,
            "reason" => "SMTP credentials are not configured."
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = "UTF-8";

        $mail->setFrom($smtpUser, $fromName);
        $mail->addAddress($toEmail);

        $safeOtp = htmlspecialchars($otpCode, ENT_QUOTES, "UTF-8");

        $mail->isHTML(true);
        $mail->Subject = $text[$lang]['email_subject'];

        $mail->Body = '
        <html>
        <body style="font-family:Arial,sans-serif;line-height:1.9;color:#0f172a;background:#f8fafc;padding:24px;">
            <div style="max-width:560px;margin:auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:18px;padding:28px;">
                <h2 style="color:#116ad0;text-align:center;margin-top:0;">' . htmlspecialchars($text[$lang]['email_greeting'], ENT_QUOTES, "UTF-8") . '</h2>
                <p style="font-size:16px;color:#334155;text-align:center;">' . htmlspecialchars($text[$lang]['email_body_1'], ENT_QUOTES, "UTF-8") . '</p>
                <p style="font-size:16px;color:#334155;text-align:center;">' . htmlspecialchars($text[$lang]['email_body_2'], ENT_QUOTES, "UTF-8") . '</p>

                <div style="background:#eef6ff;border:1px solid #bfdbfe;border-radius:14px;padding:18px;text-align:center;margin:22px 0;">
                    <div style="font-size:13px;color:#64748b;margin-bottom:6px;">OTP Code</div>
                    <div style="font-size:34px;font-weight:800;letter-spacing:6px;color:#0f62b8;">' . $safeOtp . '</div>
                </div>

                <p style="color:#dc2626;font-weight:bold;text-align:center;margin-bottom:0;">' . htmlspecialchars($text[$lang]['email_note'], ENT_QUOTES, "UTF-8") . '</p>
            </div>
        </body>
        </html>';

        $mail->AltBody =
            $text[$lang]['email_greeting'] . "\n\n" .
            $text[$lang]['email_body_1'] . "\n" .
            $text[$lang]['email_body_2'] . "\n\n" .
            "OTP Code: " . $otpCode . "\n\n" .
            $text[$lang]['email_note'];

        $mail->send();

        return ["ok" => true, "reason" => ""];
    } catch (Exception $e) {
        return ["ok" => false, "reason" => $mail->ErrorInfo];
    }
}

/* =========================
   Determine current step
========================= */
if (isset($_SESSION['forgot_verified']) && $_SESSION['forgot_verified'] === true) {
    $step = 3;
} elseif (isset($_SESSION['forgot_email']) && isset($_SESSION['forgot_otp'])) {
    $step = 2;
}

/* =========================
   Step 1: Send OTP
   Supports BOTH:
   - your version: username
   - friend version: email
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_code'])) {
    $identifier = safe_trim($_POST['identifier'] ?? ($_POST['username'] ?? ($_POST['email'] ?? '')));

    if ($identifier === '') {
        $message = $t['account_not_found'];
        $messageType = 'error';
        $step = 1;
    } else {
        $userRow = findResetUser($conn, $identifier);

        if (!$userRow) {
            $message = $t['account_not_found'];
            $messageType = 'error';
            $step = 1;
        } else {
            $email = safe_trim($userRow['email'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = $t['no_email'];
                $messageType = 'error';
                $step = 1;
            } else {
                $otp = (string) random_int(100000, 999999);
                $expiresTs = time() + 600; // 10 minutes

                $_SESSION['forgot_email']       = $email;
                $_SESSION['forgot_username']    = safe_trim($userRow['username'] ?? '');
                $_SESSION['forgot_national_id'] = safe_trim($userRow['national_id'] ?? '');
                $_SESSION['forgot_otp']         = $otp;
                $_SESSION['forgot_otp_expires'] = $expiresTs;

                // Compatibility with your older separate verify_otp/reset_password flow.
                $_SESSION['reset_email']    = $email;
                $_SESSION['reset_username'] = safe_trim($userRow['username'] ?? '');

                unset($_SESSION['forgot_verified']);

                trySaveOtpInPasswordResets($conn, $email, $otp, $expiresTs);

                $mailResult = sendResetOtpEmailSMTP($email, $otp, $lang, $text);

                if ($mailResult['ok']) {
                    $message = $t['code_sent'];
                    $messageType = 'success';
                    $step = 2;
                } else {
                    $message = $t['email_send_fail'];
                    if (!empty($mailResult['reason'])) {
                        $message .= ' ' . htmlspecialchars($mailResult['reason']);
                    }
                    $messageType = 'error';
                    $step = 1;
                }
            }
        }
    }
}

/* =========================
   Step 2: Verify OTP
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    $enteredOtp = safe_trim($_POST['otp'] ?? '');

    if (
        !isset($_SESSION['forgot_otp']) ||
        !isset($_SESSION['forgot_otp_expires']) ||
        time() > (int)$_SESSION['forgot_otp_expires']
    ) {
        $message = $t['code_invalid'];
        $messageType = 'error';
        $step = 1;
        clearForgotSessions();
    } elseif ($enteredOtp !== (string)$_SESSION['forgot_otp']) {
        $message = $t['code_invalid'];
        $messageType = 'error';
        $step = 2;
    } else {
        $_SESSION['forgot_verified'] = true;

        if (!empty($_SESSION['forgot_email'])) {
            tryMarkOtpVerified($conn, $_SESSION['forgot_email']);
        }

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

        $stmtUser = $conn->prepare("
            SELECT id, username, national_id
            FROM registration
            WHERE email = ?
            LIMIT 1
        ");
        $stmtUser->bind_param("s", $email);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        $userRow = $resUser ? $resUser->fetch_assoc() : null;
        $stmtUser->close();

        if (!$userRow) {
            $message = $t['account_not_found'];
            $messageType = 'error';
            $step = 1;
        } else {
            $username   = safe_trim($userRow['username'] ?? '');
            $nationalId = safe_trim($userRow['national_id'] ?? '');

            // Update registration.
            $stmt1 = $conn->prepare("
                UPDATE registration
                SET password = ?
                WHERE id = ?
            ");
            $stmt1->bind_param("si", $hashed, $userRow['id']);
            $stmt1->execute();
            $stmt1->close();

            // Update login by username first.
            $affectedByUsername = 0;
            if ($username !== '') {
                $stmt2 = $conn->prepare("
                    UPDATE login
                    SET password = ?
                    WHERE username = ?
                ");
                $stmt2->bind_param("ss", $hashed, $username);
                $stmt2->execute();
                $affectedByUsername = $stmt2->affected_rows;
                $stmt2->close();
            }

            // If not found by username, update by national_id.
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

            clearForgotSessions();

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
    --danger:#dc2626;
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
    width:52px;
    height:52px;
    object-fit:contain;
    border-radius:16px;
    display:block;
}

.brand-badge i{
    display:none;
    color:var(--primary);
    font-size:26px;
}

.brand-badge.logo-fallback img{ display:none; }
.brand-badge.logo-fallback i{ display:block; }

.brand-title{
    text-align:center;
    font-size:24px;
    font-weight:800;
    color:#0f2747;
    margin-bottom:6px;
}

.card-subtitle{
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-bottom:22px;
    line-height:1.6;
}

.steps{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
    margin-bottom:22px;
}

.step-pill{
    text-align:center;
    border:1px solid var(--line);
    border-radius:14px;
    padding:10px 8px;
    font-size:12px;
    font-weight:700;
    color:#64748b;
    background:#f8fafc;
}

.step-pill.active{
    background:#eef6ff;
    border-color:#9cc5f1;
    color:#0f62b8;
}

.alert{
    padding:12px 14px;
    border-radius:14px;
    font-size:14px;
    line-height:1.6;
    margin-bottom:18px;
}

.alert.success{
    color:#14532d;
    background:#dcfce7;
    border:1px solid #86efac;
}

.alert.error{
    color:#7f1d1d;
    background:#fee2e2;
    border:1px solid #fecaca;
}

.form-group{
    margin-bottom:16px;
}

label{
    display:block;
    font-size:13px;
    font-weight:700;
    color:#334155;
    margin-bottom:8px;
}

.input-wrap{
    position:relative;
}

.input-wrap i{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'right' : 'left' ?>: 14px;
    color:#64748b;
}

.input-wrap input{
    width:100%;
    border:1px solid var(--line);
    border-radius:16px;
    padding:15px 46px;
    font-size:15px;
    color:var(--text);
    background:#ffffff;
    outline:none;
    transition:all .2s ease;
    direction:<?= ($dir === 'rtl') ? 'rtl' : 'ltr' ?>;
}

.input-wrap input:focus{
    border-color:#8cc8ff;
    box-shadow:0 0 0 4px rgba(31,143,255,0.12);
}

.toggle-password{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'left' : 'right' ?>: 12px;
    border:0;
    background:transparent;
    color:#64748b;
    cursor:pointer;
    padding:6px;
}

.btn-submit{
    width:100%;
    border:0;
    border-radius:16px;
    padding:15px 18px;
    font-size:15px;
    font-weight:800;
    color:#fff;
    background:linear-gradient(135deg,var(--primary),var(--primary-dark));
    cursor:pointer;
    transition:transform .18s ease, box-shadow .18s ease;
    box-shadow:0 14px 28px rgba(31,143,255,0.28);
}

.btn-submit:hover{
    transform:translateY(-1px);
    box-shadow:0 18px 34px rgba(31,143,255,0.35);
}

.back-link{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:16px;
    text-decoration:none;
    color:#27527b;
    font-size:14px;
    font-weight:700;
}

.back-link:hover{
    color:#0f62b8;
}

@media(max-width:900px){
    .page-shell{
        grid-template-columns:1fr;
        gap:22px;
    }
    .hero-panel{
        text-align:center;
    }
    .hero-text,.point-card{
        margin-inline:auto;
    }
}

@media(max-width:520px){
    .reset-card{
        padding:28px 18px 22px;
    }
    .steps{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>
<div class="page-shell">

    <section class="hero-panel">
        <div class="eyebrow">
            <i class="fa-solid fa-shield-heart"></i>
            <?= htmlspecialchars($t['hero_badge']) ?>
        </div>

        <h1 class="hero-title"><?= htmlspecialchars($t['hero_title']) ?></h1>
        <p class="hero-text"><?= htmlspecialchars($t['hero_desc']) ?></p>

        <div class="point-card">
            <div class="point-title">
                <i class="fa-solid fa-lock"></i>
                <?= htmlspecialchars($t['secure_note_title']) ?>
            </div>
            <div class="point-text"><?= htmlspecialchars($t['secure_note_text']) ?></div>
        </div>
    </section>

    <main class="reset-card">
        <div class="lang-toggle">
            <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
            <a href="?lang=ar" class="<?= $lang === 'ar' ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge" id="forgotBrandLogo">
                <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals" onerror="document.getElementById('forgotBrandLogo').classList.add('logo-fallback');">
                <i class="fa-solid fa-hospital"></i>
            </div>
        </div>

        <h2 class="brand-title"><?= htmlspecialchars($t['app_name']) ?></h2>
        <p class="card-subtitle"><?= htmlspecialchars($t['card_subtitle']) ?></p>

        <div class="steps">
            <div class="step-pill <?= $step === 1 ? 'active' : '' ?>"><?= htmlspecialchars($t['step_1']) ?></div>
            <div class="step-pill <?= $step === 2 ? 'active' : '' ?>"><?= htmlspecialchars($t['step_2']) ?></div>
            <div class="step-pill <?= $step === 3 ? 'active' : '' ?>"><?= htmlspecialchars($t['step_3']) ?></div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?= htmlspecialchars($messageType) ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="identifier"><?= htmlspecialchars($t['identifier']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input
                            type="text"
                            id="identifier"
                            name="identifier"
                            value="<?= htmlspecialchars($_POST['identifier'] ?? ($_POST['username'] ?? ($_POST['email'] ?? ''))) ?>"
                            placeholder="<?= htmlspecialchars($t['identifier_placeholder']) ?>"
                            required
                        >
                    </div>
                </div>

                <button type="submit" name="send_code" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i>
                    <?= htmlspecialchars($t['send_code']) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="otp"><?= htmlspecialchars($t['otp']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-key"></i>
                        <input
                            type="text"
                            id="otp"
                            name="otp"
                            placeholder="<?= htmlspecialchars($t['otp_placeholder']) ?>"
                            inputmode="numeric"
                            maxlength="6"
                            required
                        >
                    </div>
                </div>

                <button type="submit" name="verify_code" class="btn-submit">
                    <i class="fa-solid fa-check"></i>
                    <?= htmlspecialchars($t['verify_code']) ?>
                </button>
            </form>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <form method="post" autocomplete="off">
                <div class="form-group">
                    <label for="password"><?= htmlspecialchars($t['new_password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="<?= htmlspecialchars($t['new_password']) ?>"
                            required
                        >
                        <button class="toggle-password" type="button" onclick="togglePassword('password', this)" title="<?= htmlspecialchars($t['show_password']) ?>">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><?= htmlspecialchars($t['confirm_password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="<?= htmlspecialchars($t['confirm_password']) ?>"
                            required
                        >
                        <button class="toggle-password" type="button" onclick="togglePassword('confirm_password', this)" title="<?= htmlspecialchars($t['show_password']) ?>">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="reset_password" class="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= htmlspecialchars($t['reset_password']) ?>
                </button>
            </form>
        <?php endif; ?>

        <a href="index.php?lang=<?= urlencode($lang) ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            <?= htmlspecialchars($t['back_login']) ?>
        </a>
    </main>

</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>
