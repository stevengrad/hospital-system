<?php
session_start();
include('db_connect.php');

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$oldToken = $_POST['old_token'] ?? '';

if (!$oldToken) {
    die("Invalid request.");
}

/*
    This file sends a new verification email for the same pending registration.
    It does NOT create a real account.
    It only:
    - creates new token
    - sets expires_at to 3 minutes
    - sets status back to pending
    - sends another verification email
*/

function app_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function show_message_page($title, $message, $type = 'info') {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    $color = '#1478d4';
    if ($type === 'error') {
        $color = '#dc2626';
    } elseif ($type === 'success') {
        $color = '#16a34a';
    } elseif ($type === 'warning') {
        $color = '#d97706';
    }

    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$safeTitle}</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #08111f, #12385f);
                color: #0f172a;
            }
            .card {
                width: 92%;
                max-width: 520px;
                background: #ffffff;
                border-radius: 18px;
                padding: 34px;
                box-shadow: 0 18px 45px rgba(0,0,0,0.22);
                text-align: center;
            }
            .icon {
                width: 62px;
                height: 62px;
                border-radius: 50%;
                margin: 0 auto 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: {$color}22;
                color: {$color};
                font-size: 30px;
                font-weight: 800;
            }
            h2 {
                margin: 0 0 12px;
                color: {$color};
                font-size: 26px;
            }
            p {
                color: #475569;
                line-height: 1.7;
                font-size: 16px;
                margin-bottom: 24px;
            }
            .actions {
                display: flex;
                gap: 12px;
                justify-content: center;
                flex-wrap: wrap;
                margin-top: 10px;
            }
            a.btn {
                text-decoration: none;
                padding: 13px 20px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 700;
                display: inline-block;
            }
            .primary {
                background: #1478d4;
                color: white;
            }
            .primary:hover {
                background: #0f62b8;
            }
            .secondary {
                background: #e2e8f0;
                color: #0f172a;
            }
            .secondary:hover {
                background: #cbd5e1;
            }
        </style>
    </head>
    <body>
        <div class='card'>
            <div class='icon'>✓</div>
            <h2>{$safeTitle}</h2>
            <p>{$safeMessage}</p>
            <div class='actions'>
                <a href='register.php' class='btn secondary'>Back to Register</a>
                <a href='index.php' class='btn primary'>Back to Login</a>
            </div>
        </div>
    </body>
    </html>
    ";
    exit();
}

function sendVerificationEmailSMTP($toEmail, $username, $plainPassword, $verifyUrl, $expiresSeconds = 180) {
    /*
        الأفضل تخليهم Environment Variables في AWS:
        SMTP_USER=cairohospitals0@gmail.com
        SMTP_PASS=your_gmail_app_password
    */
    $smtpUser = getenv('SMTP_USER') ?: 'cairohospitals0@gmail.com';
    $smtpPass = getenv('SMTP_PASS') ?: 'ocafuqbtzleqtbgk';
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = intval(getenv('SMTP_PORT') ?: 587);
    $fromName = 'Cairo Hospitals';

    if (empty($toEmail) || empty($smtpUser) || empty($smtpPass) || $smtpPass === 'PUT_YOUR_GMAIL_APP_PASSWORD_HERE') {
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
        $mail->Subject = 'New verification email - Cairo Hospitals';

        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $minutesText = $expiresSeconds < 60 ? $expiresSeconds . ' seconds' : ceil($expiresSeconds / 60) . ' minutes';

        $mail->Body = '
        <html>
        <body style="font-family:Arial,sans-serif;line-height:1.8;color:#0f172a;background:#f8fafc;padding:24px;">
            <div style="max-width:620px;margin:auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:16px;padding:28px;">
                <h2 style="color:#116ad0;margin-top:0;">New Verification Link</h2>
                <p>Your old verification link expired. Please click the button below to verify your email and complete registration.</p>

                <p style="text-align:center;margin:30px 0;">
                    <a href="' . $safeUrl . '" style="background:#116ad0;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-weight:bold;display:inline-block;">Verify Email</a>
                </p>

                <div style="background:#f8fafc;border:1px solid #dbe4ee;border-radius:12px;padding:16px;">
                    <p><strong>Username:</strong> ' . $safeUser . '</p>
                    <p><strong>Password:</strong> ' . $safePass . '</p>
                </div>

                <p style="color:#dc2626;font-weight:bold;">This new verification link expires in ' . htmlspecialchars($minutesText, ENT_QUOTES, 'UTF-8') . '.</p>
                <p>If the button does not work, copy and open this link:</p>
                <p style="word-break:break-all;color:#116ad0;">' . $safeUrl . '</p>
            </div>
        </body>
        </html>';

        $mail->AltBody =
            "New verification link - Cairo Hospitals\n\n" .
            "Open this link to verify your email and complete registration:\n" .
            $verifyUrl . "\n\n" .
            "Username: " . $username . "\n" .
            "Password: " . $plainPassword . "\n\n" .
            "This verification link expires in " . $minutesText . ".";

        $mail->send();

        return ['ok' => true, 'reason' => 'sent'];

    } catch (Exception $e) {
        return ['ok' => false, 'reason' => $mail->ErrorInfo];
    }
}

/* ---------------------------------------------------------
   1) Get old pending registration
--------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT *
    FROM pending_registrations
    WHERE token = ?
    LIMIT 1
");

if (!$stmt) {
    show_message_page("Database Error", "Could not load verification request.", "error");
}

$stmt->bind_param("s", $oldToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    show_message_page("Verification request not found", "Please go back to register and create a new account.", "error");
}

$data = $result->fetch_assoc();
$stmt->close();

if (($data['status'] ?? '') === 'verified') {
    show_message_page("Already Verified", "Your account is already verified. You can login now.", "success");
}

$email = trim($data['email'] ?? '');
$username = trim($data['username'] ?? '');
$passwordHash = $data['password_hash'] ?? '';

if ($email === '' || $username === '') {
    show_message_page("Invalid Data", "Email or username is missing. Please go back to register.", "error");
}

/*
    Important:
    The password stored in pending_registrations is hashed.
    If you want to show password in resend email, you must store plain password temporarily,
    but this is not recommended.
    So here we send password as hidden/unchanged.
*/
$plainPasswordForEmail = 'The same password you used during registration';

/* ---------------------------------------------------------
   2) Create new token and 3-minute expiry
--------------------------------------------------------- */

$newToken = bin2hex(random_bytes(32));
$expiresSeconds = 180;
$newExpiresAt = date('Y-m-d H:i:s', time() + $expiresSeconds);

$stmtUpdate = $conn->prepare("
    UPDATE pending_registrations
    SET token = ?,
        expires_at = ?,
        status = 'pending'
    WHERE token = ?
");

if (!$stmtUpdate) {
    show_message_page("Database Error", "Could not update verification token.", "error");
}

$stmtUpdate->bind_param("sss", $newToken, $newExpiresAt, $oldToken);
$stmtUpdate->execute();

if ($stmtUpdate->affected_rows <= 0) {
    $stmtUpdate->close();
    show_message_page("Update Failed", "Could not create a new verification link.", "error");
}

$stmtUpdate->close();

/* ---------------------------------------------------------
   3) Send new verification email
--------------------------------------------------------- */

$verifyUrl = app_base_url() . "/verify_email.php?token=" . urlencode($newToken);

$mailResult = sendVerificationEmailSMTP(
    $email,
    $username,
    $plainPasswordForEmail,
    $verifyUrl,
    $expiresSeconds
);

if (!$mailResult['ok']) {
    show_message_page(
        "Email sending failed",
        "A new verification link was created, but the email could not be sent. Mail error: " . $mailResult['reason'],
        "error"
    );
}

show_message_page(
    "New verification email sent",
    "We sent another verification email. Please open it within 3 minutes.",
    "success"
);
?>