<?php
session_start();
include('db_connect.php');

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   Language handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$hero = [
    'badge' => [
        'en' => 'Trusted digital healthcare platform',
        'ar' => 'منصة رعاية صحية رقمية موثوقة'
    ],
    'title' => [
        'en' => 'Create your account and access smarter healthcare services.',
        'ar' => 'أنشئ حسابك واحصل على خدمات رعاية صحية أكثر ذكاءً.'
    ],
    'desc' => [
        'en' => 'Join Cairo Hospitals and experience a modern healthcare system designed to simplify your medical journey. From secure registration to smart face login, everything is built to save your time and protect your data.',
        'ar' => 'انضم إلى مستشفيات القاهرة واستمتع بنظام رعاية صحية حديث مصمم لتسهيل رحلتك الطبية. من التسجيل الآمن إلى تسجيل الدخول الذكي بالوجه، كل شيء مصمم لتوفير وقتك وحماية بياناتك.'
    ],
    'features' => [
        [
            'icon' => 'fa-user-shield',
            'title_en' => 'Secure Registration',
            'title_ar' => 'تسجيل آمن',
            'desc_en' => 'Your personal and medical data are protected with advanced security standards.',
            'desc_ar' => 'بياناتك الشخصية والطبية محمية بمعايير أمان متقدمة.'
        ],
        [
            'icon' => 'fa-calendar-check',
            'title_en' => 'Appointment Management',
            'title_ar' => 'إدارة المواعيد',
            'desc_en' => 'Easily book, manage, and track all your hospital visits in one place.',
            'desc_ar' => 'احجز وتابع وأدر زياراتك الطبية بسهولة من مكان واحد.'
        ],
        [
            'icon' => 'fa-notes-medical',
            'title_en' => 'Medical Records',
            'title_ar' => 'السجلات الطبية',
            'desc_en' => 'Access your medical history, diagnoses, and treatments anytime.',
            'desc_ar' => 'يمكنك الوصول إلى تاريخك الطبي والتشخيصات والعلاجات في أي وقت.'
        ],
        [
            'icon' => 'fa-camera',
            'title_en' => 'Face Recognition Login',
            'title_ar' => 'تسجيل الدخول بالوجه',
            'desc_en' => 'Register your face securely for faster and smarter login experience.',
            'desc_ar' => 'سجل وجهك بأمان لتجربة دخول أسرع وأكثر ذكاءً.'
        ],
        [
            'icon' => 'fa-envelope',
            'title_en' => 'Email Notifications',
            'title_ar' => 'إشعارات البريد الإلكتروني',
            'desc_en' => 'Receive your account credentials and updates directly via email.',
            'desc_ar' => 'استلم بيانات حسابك والتحديثات مباشرة عبر بريدك الإلكتروني.'
        ],
        [
            'icon' => 'fa-clock',
            'title_en' => '24/7 Access',
            'title_ar' => 'وصول على مدار الساعة',
            'desc_en' => 'Use the system anytime, anywhere without limitations.',
            'desc_ar' => 'استخدم النظام في أي وقت ومن أي مكان بدون قيود.'
        ],
    ]
];
$texts = [
    'en'=>[
        'heading'=>'Patient Registration',
        'title'=>'Register Account',
        'username'=>'Username',
        'national_id'=>'National ID',
        'password'=>'Password',
        'confirm'=>'Confirm Password',
        'register'=>'Create Account',
        'back'=>'Back to Login',
        'welcome'=>'Create your hospital account',
        'error_match'=>'Passwords do not match.',
        'error_invalid'=>'Password must be at least 8 characters and contain uppercase, lowercase, number, and special character.',
        'gender'=>'Gender',
        'select_gender'=>'Select Gender',
        'governorate'=>'Governorate',
        'select_governorate'=>'Select Governorate',
        'address'=>'Address',
        'birthdate'=>'Birthdate',
        'email'=>'Email',
        'phone'=>'Phone Number',
        'error_email'=>'Invalid email format.',
        'error_nid'=>'National ID must be exactly 14 digits.',
        'error_fill'=>'Please fill all required fields.',
        'error_birthdate'=>'Invalid birthdate.',
        'nid_photo'=>'National ID Photo',
        'face_scan'=>'Face Scan',
        'open_face_camera'=>'Open Face Camera',
        'start_face_scan'=>'Start Face Scan',
        'face_required'=>'Please complete the full face scan.',
        'face_note'=>'Capture face images from front, right, and left angles for better recognition.',
        'face_progress'=>'Captured',
        'face_done'=>'Face scan completed successfully.',
        'face_camera_error'=>'Could not open face camera.',
        'camera_not_ready'=>'Camera not ready yet.',
        'username_exists'=>'Username already exists.',
        'nid_exists'=>'National ID already exists.',
        'db_error'=>'Database error.',
        'email_subject'=>'Your Hospital Account Credentials',
        'email_greeting'=>'Welcome to Cairo Hospitals.',
        'email_body_1'=>'Your account has been created successfully.',
        'email_body_2'=>'Here are your login credentials:',
        'email_user'=>'Username',
        'email_pass'=>'Password',
        'email_note'=>'Please keep this email in a safe place.',
        'email_fail'=>'Account created, but email could not be sent.',
        'face_front'=>'Front',
        'face_right'=>'Right',
        'face_left'=>'Left',
        'face_step_move'=>'Please position your face:',
        'face_hold_still'=>'Hold still... capturing',
        'face_angle_done'=>'Angle captured successfully.',
        'face_scan_ready'=>'Camera ready. Start the guided scan.',
        'face_samples_target'=>'Two photos will be captured for each angle.',
        'smtp_not_ready'=>'Account created, but email service is not configured correctly.',
        'first_name'=>'First Name',
        'last_name'=>'Last Name',
    ],
    'ar'=>[
        'heading'=>'تسجيل مريض',
        'title'=>'تسجيل حساب جديد',
        'username'=>'اسم المستخدم',
        'national_id'=>'الرقم القومي',
        'password'=>'كلمة المرور',
        'confirm'=>'تأكيد كلمة المرور',
        'register'=>'إنشاء الحساب',
        'back'=>'العودة لتسجيل الدخول',
        'welcome'=>'إنشاء حساب جديد في المستشفى',
        'error_match'=>'كلمتا المرور غير متطابقتين.',
        'error_invalid'=>'يجب أن تكون كلمة المرور 8 أحرف على الأقل وتحتوي على حرف كبير وصغير ورقم ورمز خاص.',
        'gender'=>'الجنس',
        'select_gender'=>'اختر الجنس',
        'governorate'=>'المحافظة',
        'select_governorate'=>'اختر المحافظة',
        'address'=>'العنوان',
        'birthdate'=>'تاريخ الميلاد',
        'email'=>'البريد الإلكتروني',
        'phone'=>'رقم الهاتف',
        'error_email'=>'صيغة البريد الإلكتروني غير صحيحة.',
        'error_nid'=>'يجب أن يكون الرقم القومي 14 رقمًا.',
        'error_fill'=>'يرجى ملء جميع الحقول المطلوبة.',
        'error_birthdate'=>'تاريخ الميلاد غير صحيح.',
        'nid_photo'=>'صورة البطاقة',
        'face_scan'=>'مسح الوجه',
        'open_face_camera'=>'فتح كاميرا الوجه',
        'start_face_scan'=>'ابدأ مسح الوجه',
        'face_required'=>'يرجى إكمال مسح الوجه بالكامل.',
        'face_note'=>'يجب التقاط صور واضحة للوجه من الأمام واليمين واليسار لتحسين التعرف.',
        'face_progress'=>'تم التقاط',
        'face_done'=>'تم الانتهاء من مسح الوجه بنجاح.',
        'face_camera_error'=>'تعذر فتح كاميرا الوجه.',
        'camera_not_ready'=>'الكاميرا ليست جاهزة بعد.',
        'username_exists'=>'اسم المستخدم موجود بالفعل.',
        'nid_exists'=>'الرقم القومي موجود بالفعل.',
        'db_error'=>'خطأ في قاعدة البيانات.',
        'email_subject'=>'بيانات حسابك في المستشفى',
        'email_greeting'=>'مرحبًا بك في مستشفيات القاهرة.',
        'email_body_1'=>'تم إنشاء حسابك بنجاح.',
        'email_body_2'=>'هذه هي بيانات تسجيل الدخول الخاصة بك:',
        'email_user'=>'اسم المستخدم',
        'email_pass'=>'كلمة المرور',
        'email_note'=>'يرجى الاحتفاظ بهذه الرسالة في مكان آمن.',
        'email_fail'=>'تم إنشاء الحساب ولكن تعذر إرسال البريد الإلكتروني.',
        'face_front'=>'الأمام',
        'face_right'=>'اليمين',
        'face_left'=>'اليسار',
        'face_step_move'=>'يرجى توجيه وجهك إلى:',
        'face_hold_still'=>'اثبت مكانك... جارٍ الالتقاط',
        'face_angle_done'=>'تم التقاط هذه الزاوية بنجاح.',
        'face_scan_ready'=>'الكاميرا جاهزة. ابدأ المسح الموجّه.',
        'face_samples_target'=>'سيتم التقاط صورتين لكل زاوية.',
        'smtp_not_ready'=>'تم إنشاء الحساب ولكن خدمة البريد غير مضبوطة بشكل صحيح.',
        'first_name'=>'الاسم الأول',
        'last_name'=>'اسم العائلة',
    ]
];

$t = $texts[$lang];
$error = '';
$success_notice = '';
$fieldErrors = [];
$old = [];
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_register($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

function old_value($key, $old) {
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

function field_error($key, $fieldErrors) {
    if (!empty($fieldErrors[$key])) {
        return '<div class="field-error">' . htmlspecialchars($fieldErrors[$key], ENT_QUOTES, 'UTF-8') . '</div>';
    }
    return '';
}

/* =========================
   SMTP Mail helper
   Put your Gmail App Password below
========================= */
function sendWelcomeCredentialsEmailSMTP($toEmail, $username, $plainPassword, $lang, $texts) {
    $smtpUser = 'cairohospitals0@gmail.com';
    $smtpPass = 'dnpoxjybarrdwhxd';
    $fromName = 'Cairo Hospitals';

    if (empty($toEmail) || empty($smtpUser) || empty($smtpPass) || $smtpPass === 'PUT_YOUR_GMAIL_APP_PASSWORD_HERE') {
        return ['ok' => false, 'reason' => 'smtp_not_configured'];
    }

    $mail = new PHPMailer(true);

    try {
        $subject   = $texts[$lang]['email_subject'];
        $greeting  = $texts[$lang]['email_greeting'];
        $body1     = $texts[$lang]['email_body_1'];
        $body2     = $texts[$lang]['email_body_2'];
        $labelUser = $texts[$lang]['email_user'];
        $labelPass = $texts[$lang]['email_pass'];
        $note      = $texts[$lang]['email_note'];
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
        $mail->Subject = $subject;

        $mail->Body = '
        <html>
        <body style="font-family:Arial,sans-serif;line-height:1.8;color:#0f172a;">
            <h2 style="color:#116ad0;">' . htmlspecialchars($greeting) . '</h2>
            <p>' . htmlspecialchars($body1) . '</p>
            <p>' . htmlspecialchars($body2) . '</p>
            <div style="background:#f8fafc;border:1px solid #dbe4ee;border-radius:12px;padding:16px;">
                <p><strong>' . htmlspecialchars($labelUser) . ':</strong> ' . htmlspecialchars($username) . '</p>
                <p><strong>' . htmlspecialchars($labelPass) . ':</strong> ' . htmlspecialchars($plainPassword) . '</p>
            </div>
            <p style="margin-top:16px;">' . htmlspecialchars($note) . '</p>
        </body>
        </html>';

        $mail->AltBody =
            $greeting . "\n\n" .
            $body1 . "\n" .
            $body2 . "\n\n" .
            $labelUser . ': ' . $username . "\n" .
            $labelPass . ': ' . $plainPassword . "\n\n" .
            $note;

        $mail->send();
        return ['ok' => true, 'reason' => 'sent'];
    } catch (Exception $e) {
        return ['ok' => false, 'reason' => $mail->ErrorInfo];
    }
}


function app_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function sendVerificationEmailSMTP($toEmail, $username, $plainPassword, $verifyUrl, $expiresSeconds =600) {
    $smtpUser = 'cairohospitals0@gmail.com';
    $smtpPass = 'dnpoxjybarrdwhxd';
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
        $mail->Subject = 'Verify your Cairo Hospitals account';

        $safeUser = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
        $minutesText = $expiresSeconds < 60 ? $expiresSeconds . ' seconds' : ceil($expiresSeconds / 60) . ' minutes';

        $mail->Body = '
        <html>
        <body style="font-family:Arial,sans-serif;line-height:1.8;color:#0f172a;background:#f8fafc;padding:24px;">
            <div style="max-width:620px;margin:auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:16px;padding:28px;">
                <h2 style="color:#116ad0;margin-top:0;">Verify your email address</h2>
                <p>Welcome to Cairo Hospitals.</p>
                <p>Your account registration is not completed yet. Please click the button below to verify your email and complete the registration.</p>
                <p style="text-align:center;margin:30px 0;">
                    <a href="' . $safeUrl . '" style="background:#116ad0;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-weight:bold;display:inline-block;">Verify Email</a>
                </p>
                <div style="background:#f8fafc;border:1px solid #dbe4ee;border-radius:12px;padding:16px;">
                    <p><strong>Username:</strong> ' . $safeUser . '</p>
                    <p><strong>Password:</strong> ' . $safePass . '</p>
                </div>
                <p style="color:#dc2626;font-weight:bold;">This verification link expires in ' . htmlspecialchars($minutesText, ENT_QUOTES, 'UTF-8') . '.</p>
                <p>If the button does not work, copy and open this link:</p>
                <p style="word-break:break-all;color:#116ad0;">' . $safeUrl . '</p>
            </div>
        </body>
        </html>';

        $mail->AltBody =
            "Verify your Cairo Hospitals account\n\n" .
            "Your account registration is not completed yet. Open this link to verify your email and complete registration:\n" .
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

/* =========================
   Backend registration
========================= */
if($_SERVER['REQUEST_METHOD']==='POST'){
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $national_id      = trim($_POST['national_id'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm          = trim($_POST['confirm_password'] ?? '');
    $gender           = $_POST['gender'] ?? '';
    $governorate      = $_POST['governorate'] ?? '';
    $birthdate        = $_POST['birthdate'] ?? '';
    $address          = trim($_POST['address'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $contact_number   = trim($_POST['contact_number'] ?? '');
    $face_images_json = $_POST['face_images_json'] ?? '[]';
    $old_national_id_photo = trim($_POST['old_national_id_photo'] ?? '');

    $old = [
        'first_name'=>$first_name,
        'last_name'=>$last_name,
        'username'=>$username,
        'national_id'=>$national_id,
        'gender'=>$gender,
        'governorate'=>$governorate,
        'birthdate'=>$birthdate,
        'address'=>$address,
        'email'=>$email,
        'contact_number'=>$contact_number,
        'national_id_photo'=>$old_national_id_photo,
    ];

    $plainPasswordForEmail = $password;

    $validPassword = preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
    $validEmail    = filter_var($email, FILTER_VALIDATE_EMAIL);
    $validNID      = preg_match('/^\d{14}$/', $national_id);

    $nid_photo_name = '';
    $nid_photo_path = $old_national_id_photo;
    $has_new_nid_photo = isset($_FILES['nid_photo']) && $_FILES['nid_photo']['error'] === UPLOAD_ERR_OK && !empty($_FILES['nid_photo']['name']);
    $has_old_nid_photo = !empty($old_national_id_photo);

    if($has_new_nid_photo){
        $original = basename($_FILES['nid_photo']['name']);
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]/','_',substr(pathinfo($original, PATHINFO_FILENAME),0,50));
        $nid_photo_name = "nid_".time()."_".$safeBase.".".$ext;

        $uploadDir = __DIR__."/uploads";
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir,0755,true);
        }

        $target = $uploadDir."/".$nid_photo_name;

        if(move_uploaded_file($_FILES['nid_photo']['tmp_name'],$target)){
            $nid_photo_path = "/uploads/".$nid_photo_name;
            $old['national_id_photo'] = $nid_photo_path;
            $has_old_nid_photo = true;
        } else {
            $nid_photo_name = '';
            $nid_photo_path = $old_national_id_photo;
            $old['national_id_photo'] = $old_national_id_photo;
        }
    }


$decodedFaceImages = json_decode($face_images_json, true);

if (!is_array($decodedFaceImages)) {
    $decodedFaceImages = [];
}

/*
The browser may send face images in one of these formats:

1) [
     "data:image/jpeg;base64,...",
     "data:image/jpeg;base64,..."
   ]

2) [
     {"angle":"front", "image":"data:image/jpeg;base64,..."},
     {"angle":"left", "image":"data:image/jpeg;base64,..."}
   ]

This code supports both. The real face registration will happen only after
email verification inside verify_email.php, not before verification.
*/
$faceImagesForApi = [];

foreach ($decodedFaceImages as $imgItem) {
    if (is_array($imgItem) && !empty($imgItem['image'])) {
        $faceImagesForApi[] = $imgItem['image'];
    } elseif (is_string($imgItem) && !empty($imgItem)) {
        $faceImagesForApi[] = $imgItem;
    }
}

$face_saved_ok = count($faceImagesForApi) >= 6;
    if(!$first_name){ $fieldErrors['first_name'] = $t['error_fill']; }
    if(!$last_name){ $fieldErrors['last_name'] = $t['error_fill']; }
    if(!$username){ $fieldErrors['username'] = $t['error_fill']; }
    if(!$national_id){ $fieldErrors['national_id'] = $t['error_fill']; }
    elseif(!$validNID){ $fieldErrors['national_id'] = $t['error_nid']; }
    if(!$password){ $fieldErrors['password'] = $t['error_fill']; }
    elseif(!$validPassword){ $fieldErrors['password'] = $t['error_invalid']; }
    if(!$confirm){ $fieldErrors['confirm_password'] = $t['error_fill']; }
    elseif($password !== $confirm){ $fieldErrors['confirm_password'] = $t['error_match']; }
    if(!$gender){ $fieldErrors['gender'] = $t['error_fill']; }
    if(!$governorate){ $fieldErrors['governorate'] = $t['error_fill']; }
    if(!$address){ $fieldErrors['address'] = $t['error_fill']; }
    if(!$email){ $fieldErrors['email'] = $t['error_fill']; }
    elseif(!$validEmail){ $fieldErrors['email'] = $t['error_email']; }
    if(!$birthdate){ $fieldErrors['birthdate'] = $t['error_fill']; }
    elseif(strtotime($birthdate) > strtotime(date('Y-m-d'))){ $fieldErrors['birthdate'] = $t['error_birthdate']; }
    if(!$contact_number){ $fieldErrors['contact_number'] = $t['error_fill']; }
    if(!$has_new_nid_photo && !$has_old_nid_photo){ $fieldErrors['nid_photo'] = $t['error_fill']; }
    if(!$face_saved_ok){ $fieldErrors['face_images_json'] = $t['face_required']; }

    if (empty($fieldErrors)) {
        $stmtCheckUser = $conn->prepare("SELECT id FROM registration WHERE username = ? LIMIT 1");
        $stmtCheckUser->bind_param("s", $username);
        $stmtCheckUser->execute();
        $stmtCheckUser->store_result();

        if($stmtCheckUser->num_rows > 0){
            $fieldErrors['username'] = $t['username_exists'];
        } else {
            $stmtCheckNID = $conn->prepare("SELECT id FROM registration WHERE national_id = ? LIMIT 1");
            $stmtCheckNID->bind_param("s", $national_id);
            $stmtCheckNID->execute();
            $stmtCheckNID->store_result();

            if($stmtCheckNID->num_rows > 0){
                $fieldErrors['national_id'] = $t['nid_exists'];
            } else {
                $stmtPending = $conn->prepare("SELECT id, status, expires_at FROM pending_registrations WHERE (username = ? OR national_id = ? OR email = ?) AND status = 'pending' LIMIT 1");

                if(!$stmtPending){
                    $error = $t['db_error'] . " (pending check): " . $conn->error;
                } else {
                    $stmtPending->bind_param("sss", $username, $national_id, $email);
                    $stmtPending->execute();
                    $pendingResult = $stmtPending->get_result();
                    $pendingRow = $pendingResult ? $pendingResult->fetch_assoc() : null;

                    if($pendingRow && strtotime($pendingRow['expires_at']) >= time()){
                        $error = "A verification email was already sent. Please check your email or wait until the link expires.";
                    } else {
                        $expireOld = $conn->prepare("UPDATE pending_registrations SET status='expired' WHERE (username = ? OR national_id = ? OR email = ?) AND status='pending'");
                        if($expireOld){
                            $expireOld->bind_param("sss", $username, $national_id, $email);
                            $expireOld->execute();
                        }

                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $token = bin2hex(random_bytes(32));
                        $expiresSeconds = 600;
                        $expiresAt = date('Y-m-d H:i:s', time() + $expiresSeconds);

                        $stmtInsertPending = $conn->prepare("
                            INSERT INTO pending_registrations
                            (token, first_name, last_name, username, password_hash, national_id, national_id_photo, gender, government, birthdate, address, email, phone_number, face_images_json, status, expires_at)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending', ?)
                        ");

                        if(!$stmtInsertPending){
                            $error = $t['db_error'] . " (pending insert): " . $conn->error;
                        } else {
                            $stmtInsertPending->bind_param(
                                "sssssssssssssss",
                                $token,
                                $first_name,
                                $last_name,
                                $username,
                                $hashed,
                                $national_id,
                                $nid_photo_path,
                                $gender,
                                $governorate,
                                $birthdate,
                                $address,
                                $email,
                                $contact_number,
                                $face_images_json,
                                $expiresAt
                            );

                            if($stmtInsertPending->execute()){
                                $verifyUrl = app_base_url() . "/verify_email.php?token=" . urlencode($token);

                                $mailResult = sendVerificationEmailSMTP(
                                    $email,
                                    $username,
                                    $plainPasswordForEmail,
                                    $verifyUrl,
                                    $expiresSeconds
                                );

                                if (!$mailResult['ok']) {
                                    $error = 'Mail error: ' . $mailResult['reason'];
                                } else {
                                    header("Location:waiting_verification.php?token=" . urlencode($token));
                                    exit();
                                }
                            } else {
                                $error = $t['db_error'] . ": Failed to save pending registration: " . $stmtInsertPending->error;
                            }
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
<title><?= htmlspecialchars($t['title']) ?></title>
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --bg-1:#08111f;
    --bg-2:#0d1b2f;
    --bg-3:#12385f;
    --primary:#1478d4;
    --primary-dark:#0f62b8;
    --success:#18b957;
    --success-dark:#159447;
    --text:#0f172a;
    --muted:#64748b;
    --line:#dbe4ee;
    --card:rgba(255,255,255,0.96);
    --white:#ffffff;
    --shadow:0 24px 70px rgba(2, 12, 27, 0.38);
    --danger:#dc2626;
}

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

html{
    min-height:100%;
    overflow-x:hidden;
}

body{
    font-family:'Inter',sans-serif;
    min-height:100vh;
    background:
        radial-gradient(circle at 15% 20%, rgba(31,143,255,0.25), transparent 22%),
        radial-gradient(circle at 85% 18%, rgba(34,197,94,0.18), transparent 20%),
        linear-gradient(135deg, var(--bg-1), var(--bg-2) 45%, var(--bg-3));
    color:var(--text);
    overflow-x:hidden;
    overflow-y:auto;
    margin:0;
    padding:34px 18px 34px 56px;
}

.page-shell{
    width:100%;
    max-width:none;
    display:grid;
    grid-template-columns:minmax(0, 1fr) 590px;
    gap:34px;
    align-items:flex-start;
}

.hero-panel{
    color:#fff;
    padding:35px 0 40px 0;
    display:flex;
    flex-direction:column;
    justify-content:flex-start;
    gap:20px;
    overflow:visible;
}

.hero-content{
    width:100%;
    max-width:850px;
    display:flex;
    flex-direction:column;
    gap:18px;
}

.eyebrow,
.badge{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:10px 14px;
    border:1px solid rgba(255,255,255,0.16);
    background:rgba(255,255,255,0.08);
    border-radius:999px;
    font-size:13px;
    font-weight:600;
    width:fit-content;
    margin-bottom:20px;
    backdrop-filter:blur(10px);
}

.hero-title{
    font-size:clamp(42px, 4.6vw, 68px);
    line-height:1.06;
    font-weight:800;
    letter-spacing:-1.5px;
    margin-bottom:16px;
    max-width:780px;
}

.hero-text,
.hero-desc{
    max-width:760px;
    font-size:16px;
    line-height:1.8;
    color:rgba(255,255,255,0.84);
    margin-bottom:24px;
}

.hero-points{
    display:grid;
    grid-template-columns:1fr;
    gap:14px;
    max-width:560px;
}

.point-card{
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.12);
    border-radius:18px;
    padding:16px;
    backdrop-filter:blur(10px);
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

.hero-features{
    display:grid;
    grid-template-columns:repeat(2, minmax(250px, 1fr));
    gap:18px;
    margin-top:20px;
    width:100%;
    max-width:820px;
}
.feature-item{
    display:flex;
    gap:14px;
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.14);
    padding:18px;
    border-radius:18px;
    backdrop-filter:blur(10px);
    min-height:126px;
}

.feature-item i{
    width:40px;
    height:40px;
    border-radius:12px;
    background:rgba(255,255,255,0.12);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#22c55e;
    font-size:16px;
    flex-shrink:0;
}

.feature-item h4{
    font-size:15px;
    margin-bottom:4px;
    color:#fff;
}

.feature-item p{
    font-size:13px;
    color:#cbd5e1;
    line-height:1.6;
}

.hero-cards{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
    margin-top:10px;
}

.hero-card{
    display:flex;
    gap:12px;
    background:rgba(255,255,255,0.08);
    padding:14px;
    border-radius:14px;
    align-items:flex-start;
}

.hero-card i{
    font-size:18px;
    color:#22c55e;
    margin-top:4px;
}

.hero-card h4{
    font-size:14px;
    margin-bottom:3px;
    color:#fff;
}

.hero-card p{
    font-size:12px;
    color:#cbd5e1;
}

.register-card{
    width:100%;
    max-width:590px;
    justify-self:end;
    margin-left:0;
    margin-right:6px;
    background:var(--card);
    border:1px solid rgba(255,255,255,0.7);
    backdrop-filter:blur(18px);
    border-radius:28px;
    box-shadow:var(--shadow);
    padding:28px 28px 24px;
    position:relative;
}

.lang-toggle{
    position:absolute;
    top:18px;
    <?= ($dir === 'rtl') ? 'left' : 'right' ?>:18px;
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
}

.lang-toggle a.active{
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
    width:64px;
    height:64px;
    border-radius:20px;
    background:#fff;
    border:1px solid #dbeafe;
    display:flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 8px 24px rgba(31,143,255,0.12);
    overflow:hidden;
    flex-shrink:0;
}

.brand-badge img{
    width:58px;
    height:58px;
    object-fit:contain;
    display:block;
}

.brand-text h1{
    font-size:22px;
    font-weight:800;
    line-height:1.2;
    color:#136f33;
    text-align:center;
    white-space:nowrap;
}

.subtitle{
    text-align:center;
    color:var(--muted);
    font-size:14px;
    margin-bottom:22px;
    line-height:1.7;
}

.error,
.notice{
    text-align:center;
    margin-bottom:14px;
    font-size:14px;
    border-radius:14px;
    padding:12px 14px;
    font-weight:600;
}

.error{
    background:#fff1f2;
    border:1px solid #fecdd3;
    color:#be123c;
}

.notice{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
}

.grid-2{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14px;
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
    color:#1478d4;
    pointer-events:none;
    z-index:2;
}

.form-input,
.form-select,
.form-textarea{
    width:100%;
    border:1px solid var(--line);
    border-radius:16px;
    font-size:15px;
    background:#f8fbff;
    color:var(--text);
    transition:all .2s ease;
}

.form-input,
.form-select{
    height:56px;
    <?= ($dir === 'rtl') ? 'padding:0 44px 0 48px;' : 'padding:0 48px 0 44px;' ?>
}

.form-textarea{
    min-height:112px;
    resize:vertical;
    padding:14px 16px;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus{
    outline:none;
    border-color:#7ab6f7;
    box-shadow:0 0 0 4px rgba(31,143,255,0.10);
    background:#fff;
}

.toggle-eye{
    position:absolute;
    top:50%;
    transform:translateY(-50%);
    <?= ($dir === 'rtl') ? 'left:14px;' : 'right:14px;' ?>
    width:28px;
    height:28px;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    color:#1478d4;
    z-index:5;
    border-radius:50%;
}

.toggle-eye:hover{
    background:#e8f3ff;
}

.toggle-eye i{
    font-size:17px;
}

.password-hint,
.helper-line{
    font-size:12px;
    color:#64748b;
    margin-top:6px;
    padding-inline:4px;
    line-height:1.5;
}

.field-error{
    color:var(--danger);
    font-size:12px;
    font-weight:700;
    margin-top:6px;
    line-height:1.5;
    padding-inline:4px;
}

.has-error .form-input,
.has-error .form-select,
.has-error .form-textarea,
.form-input.invalid,
.form-select.invalid,
.form-textarea.invalid{
    border-color:#fca5a5;
    background:#fff7f7;
}

.file-group input[type=file]{
    width:100%;
    border:1px solid var(--line);
    border-radius:14px;
    padding:14px 12px;
    background:#f8fbff;
}

.face-actions{
    display:flex;
    flex-direction:column;
    gap:10px;
    margin-top:8px;
}

.btn-main,
.btn-soft,
.btn-submit{
    width:100%;
    border:none;
    border-radius:16px;
    height:54px;
    font-size:15px;
    font-weight:700;
    cursor:pointer;
    transition:all .2s ease;
}

.btn-main{
    background:linear-gradient(135deg, var(--primary), #35a2ff);
    color:#fff;
    box-shadow:0 12px 24px rgba(31,143,255,0.18);
}

.btn-main:hover{
    background:linear-gradient(135deg, var(--primary-dark), #1f8fff);
}

.btn-soft{
    background:#ffffff;
    color:#11713b;
    border:2px solid rgba(23,163,74,0.20);
}

.btn-soft:hover{
    background:#f2fff6;
}

.btn-submit{
    margin-top:6px;
    background:linear-gradient(135deg, var(--success), #22c55e);
    color:#fff;
    box-shadow:0 12px 24px rgba(23,163,74,0.20);
}

.btn-submit:hover{
    background:linear-gradient(135deg, var(--success-dark), #16a34a);
}

.face-box{
    position:relative;
    width:100%;
    max-width:380px;
    height:270px;
    margin:12px auto 0;
    border-radius:18px;
    overflow:hidden;
    border:2px solid rgba(23,163,74,0.45);
    background:#09111d;
    display:none;
    box-shadow:0 10px 30px rgba(0,0,0,0.12);
}

.face-box video,
.face-box canvas{
    width:100%;
    height:100%;
    object-fit:cover;
}

.face-box canvas{
    position:absolute;
    top:0;
    left:0;
    z-index:2;
}

.face-guide{
    position:absolute;
    top:50%;
    left:50%;
    width:180px;
    height:215px;
    transform:translate(-50%, -50%);
    border:3px solid #22c55e;
    border-radius:20px;
    box-shadow:0 0 0 9999px rgba(0,0,0,0.16);
    z-index:3;
    pointer-events:none;
}

.face-status{
    font-size:13px;
    text-align:center;
    margin-top:10px;
    color:#41576f;
    min-height:20px;
    line-height:1.6;
    font-weight:600;
}

.counter-box{
    text-align:center;
    font-weight:800;
    color:#0f62b8;
    margin-top:10px;
    min-height:22px;
}

.angle-badges{
    display:flex;
    justify-content:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:10px;
}

.angle-badge{
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    background:#eef2f7;
    color:#475569;
    border:1px solid #dbe4ee;
}

.angle-badge.active{
    background:#dbeafe;
    color:#0f62b8;
    border-color:#93c5fd;
}

.angle-badge.done{
    background:#dcfce7;
    color:#166534;
    border-color:#86efac;
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

@media(max-width:980px){
    body{
        padding:20px 12px;
    }

    .page-shell{
        grid-template-columns:1fr;
        gap:18px;
        max-width:620px;
        margin:0 auto;
    }

    .hero-panel{
        display:none;
    }

    .register-card{
        max-width:620px;
        justify-self:center;
        margin:0 auto;
    }

    .hero-features{
        grid-template-columns:1fr;
    }
}
@media(max-width:700px){
    .grid-2{
        grid-template-columns:1fr;
    }
}

@media(max-width:560px){
    body{
        padding:18px 12px;
    }

    .register-card{
        padding:22px 18px 20px;
        border-radius:22px;
    }

    .brand-wrap{
        flex-direction:column;
        gap:10px;
        margin-top:10px;
    }

    .brand-text h1{
        white-space:normal;
        font-size:22px;
    }

    .face-box{
        height:230px;
    }
}
<?php if ($lang === 'ar'): ?>
.hero-panel,
.hero-content,
.hero-title,
.hero-text,
.feature-item,
.feature-item h4,
.feature-item p {
    direction: rtl;
    text-align: right;
}

.feature-item {
    flex-direction: row-reverse;
}

.eyebrow {
    margin-left: 0;
    margin-right: auto;
}
<?php endif; ?>
</style>
</head>
<body>
<div class="page-shell">
<section class="hero-panel">
    <div class="hero-content">

        <div class="eyebrow">
            <i class="fa-solid fa-shield-heart"></i>
            <?= htmlspecialchars($hero['badge'][$lang], ENT_QUOTES, 'UTF-8') ?>
        </div>

        <h2 class="hero-title">
            <?= htmlspecialchars($hero['title'][$lang], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p class="hero-text">
            <?= htmlspecialchars($hero['desc'][$lang], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <div class="hero-features">
            <?php foreach ($hero['features'] as $feature): ?>
                <div class="feature-item">
                    <i class="fa-solid <?= htmlspecialchars($feature['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <div>
                        <h4>
                            <?= htmlspecialchars($lang === 'ar' ? $feature['title_ar'] : $feature['title_en'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p>
                            <?= htmlspecialchars($lang === 'ar' ? $feature['desc_ar'] : $feature['desc_en'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>

    <section class="register-card">
        <div class="lang-toggle">
            <a href="<?= htmlspecialchars(lang_link_register('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="<?= htmlspecialchars(lang_link_register('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge">
                <img src="assets/Cairo_hospitals1.png" alt="Cairo Hospitals">
            </div>
            <div class="brand-text">
                <h1><?= ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals' ?></h1>
            </div>
        </div>

        <p class="subtitle"><?= ($lang === 'ar') ? 'أنشئ حسابك الطبي بأمان من خلال تجربة تسجيل حديثة واحترافية.' : 'Create your secure hospital account in a premium registration experience.' ?></p>

        <?php if(!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if(!empty($success_notice)): ?>
            <div class="notice"><?= htmlspecialchars($success_notice) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="registerForm" novalidate>
            <div class="grid-2">
                <div class="form-group <?= !empty($fieldErrors['first_name']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['first_name']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user icon"></i>
                        <input class="form-input" type="text" name="first_name" id="first_name" value="<?= old_value('first_name', $old) ?>" required>
                    </div>
                    <?= field_error('first_name', $fieldErrors) ?>
                    <div class="field-error client-error" id="first_name_error"></div>
                </div>

                <div class="form-group <?= !empty($fieldErrors['last_name']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['last_name']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user icon"></i>
                        <input class="form-input" type="text" name="last_name" id="last_name" value="<?= old_value('last_name', $old) ?>" required>
                    </div>
                    <?= field_error('last_name', $fieldErrors) ?>
                    <div class="field-error client-error" id="last_name_error"></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group <?= !empty($fieldErrors['username']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['username']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user-shield icon"></i>
                        <input class="form-input" type="text" name="username" id="username" value="<?= old_value('username', $old) ?>" required>
                    </div>
                    <?= field_error('username', $fieldErrors) ?>
                    <div class="field-error client-error" id="username_error"></div>
                </div>

                <div class="form-group <?= !empty($fieldErrors['national_id']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['national_id']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-id-card icon"></i>
                       <input class="form-input" type="text" name="national_id" id="national_id" maxlength="14" value="<?= old_value('national_id', $old) ?>" required>
                    </div>
                    <?= field_error('national_id', $fieldErrors) ?>
                    <div class="field-error client-error" id="national_id_error"></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group <?= !empty($fieldErrors['password']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['password']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock icon"></i>
                        <input class="form-input" type="password" name="password" id="password" required minlength="8">
                        <span class="toggle-eye" onclick="togglePassword('password', this)">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-hint">
                        <?= ($lang === 'ar') ? '8 أحرف على الأقل، حرف كبير، حرف صغير، رقم ورمز خاص.' : 'Minimum 8 characters, uppercase, lowercase, number and special character.' ?>
                    </div>
                    <?= field_error('password', $fieldErrors) ?>
                    <div class="field-error client-error" id="password_error"></div>
                </div>

                <div class="form-group <?= !empty($fieldErrors['confirm_password']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['confirm']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock icon"></i>
                        <input class="form-input" type="password" name="confirm_password" id="confirm_password" required>
                        <span class="toggle-eye" onclick="togglePassword('confirm_password', this)">
                            <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <?= field_error('confirm_password', $fieldErrors) ?>
                    <div class="field-error client-error" id="confirm_password_error"></div>
                </div>
            </div>

            <div class="form-group <?= !empty($fieldErrors['nid_photo']) ? 'has-error' : '' ?>">
                <label class="field-label"><?= htmlspecialchars($t['nid_photo']) ?></label>
                <div class="file-group">
                    <input type="file" name="nid_photo" id="nid_photo" accept="image/*" <?= empty($old['national_id_photo'] ?? '') ? 'required' : '' ?>>
                    <input type="hidden" name="old_national_id_photo" id="old_national_id_photo" value="<?= htmlspecialchars($old['national_id_photo'] ?? '') ?>">
                    <?php if (!empty($old['national_id_photo'] ?? '')): ?>
                        <small style="color: green; font-weight: 600; display: block; margin-top: 8px;"><?= ($lang === 'ar') ? 'تم رفع صورة البطاقة بالفعل.' : 'National ID photo already uploaded.' ?></small>
                    <?php endif; ?>
                </div>
                <div class="helper-line"><?= ($lang === 'ar') ? 'ارفعي صورة بطاقة واضحة.' : 'Upload a clear image of your National ID.' ?></div>
                <?= field_error('nid_photo', $fieldErrors) ?>
                <div class="field-error client-error" id="nid_photo_error"></div>
            </div>

            <div class="grid-2">
                <div class="form-group <?= !empty($fieldErrors['gender']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['gender']) ?></label>
                    <select class="form-select" name="gender" id="gender" required>
                        <option value=""><?= htmlspecialchars($t['select_gender']) ?></option>
                        <option value="Male" <?= (($old['gender'] ?? '') === 'Male') ? 'selected' : '' ?>><?= ($lang === 'ar') ? 'ذكر' : 'Male' ?></option>
                        <option value="Female" <?= (($old['gender'] ?? '') === 'Female') ? 'selected' : '' ?>><?= ($lang === 'ar') ? 'أنثى' : 'Female' ?></option>
                    </select>
                    <?= field_error('gender', $fieldErrors) ?>
                    <div class="field-error client-error" id="gender_error"></div>
                </div>

                <div class="form-group <?= !empty($fieldErrors['governorate']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['governorate']) ?></label>
                    <select class="form-select" name="governorate" id="governorate" required>
                        <option value=""><?= htmlspecialchars($t['select_governorate']) ?></option>
                        <?php
                        $govs = ['Cairo','Giza','Alexandria','Qalyubia','Dakahlia'];
                        foreach ($govs as $gov):
                        ?>
                            <option value="<?= $gov ?>" <?= (($old['governorate'] ?? '') === $gov) ? 'selected' : '' ?>><?= $gov ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error('governorate', $fieldErrors) ?>
                    <div class="field-error client-error" id="governorate_error"></div>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group <?= !empty($fieldErrors['birthdate']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['birthdate']) ?></label>
                    <input class="form-input" type="date" name="birthdate" id="birthdate" max="<?= date('Y-m-d') ?>" value="<?= old_value('birthdate', $old) ?>" required style="padding:0 16px;">
                    <?= field_error('birthdate', $fieldErrors) ?>
                    <div class="field-error client-error" id="birthdate_error"></div>
                </div>

                <div class="form-group <?= !empty($fieldErrors['contact_number']) ? 'has-error' : '' ?>">
                    <label class="field-label"><?= htmlspecialchars($t['phone']) ?></label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-phone icon"></i>
                        <input class="form-input" type="text" name="contact_number" id="contact_number" value="<?= old_value('contact_number', $old) ?>" required>
                    </div>
                    <?= field_error('contact_number', $fieldErrors) ?>
                    <div class="field-error client-error" id="contact_number_error"></div>
                </div>
            </div>

            <div class="form-group <?= !empty($fieldErrors['email']) ? 'has-error' : '' ?>">
                <label class="field-label"><?= htmlspecialchars($t['email']) ?></label>
                <div class="input-wrap">
                    <i class="fa-solid fa-envelope icon"></i>
                    <input class="form-input" type="email" name="email" id="email" value="<?= old_value('email', $old) ?>" required>
                </div>
                <?= field_error('email', $fieldErrors) ?>
                <div class="field-error client-error" id="email_error"></div>
            </div>

            <div class="form-group <?= !empty($fieldErrors['address']) ? 'has-error' : '' ?>">
                <label class="field-label"><?= htmlspecialchars($t['address']) ?></label>
                <textarea class="form-textarea" name="address" id="address" required><?= old_value('address', $old) ?></textarea>
                <?= field_error('address', $fieldErrors) ?>
                <div class="field-error client-error" id="address_error"></div>
            </div>

            <div class="form-group <?= !empty($fieldErrors['face_images_json']) ? 'has-error' : '' ?>">
                <label class="field-label"><?= htmlspecialchars($t['face_scan']) ?></label>

                <div class="face-actions">
                    <button type="button" class="btn-main" id="openFaceCameraBtn">
                        <i class="fa-solid fa-camera"></i> <?= htmlspecialchars($t['open_face_camera']) ?>
                    </button>
                    <button type="button" class="btn-soft" id="startFaceScanBtn" style="display:none;">
                        <i class="fa-solid fa-face-smile"></i> <?= htmlspecialchars($t['start_face_scan']) ?>
                    </button>
                </div>

                <div class="helper-line">
                    <?= ($lang === 'ar') ? 'ضعي الوجه داخل الإطار الأخضر. سيتم التقاط صورتين لكل زاوية: الأمام ثم اليمين ثم اليسار.' : 'Place your face inside the green frame. Two photos will be captured for each angle: front, then right, then left.' ?>
                </div>

                <div class="face-box" id="faceBox">
                    <video id="faceVideo" autoplay muted playsinline></video>
                    <canvas id="faceCanvas"></canvas>
                    <div class="face-guide"></div>
                </div>

                <div class="angle-badges" id="angleBadges">
                    <span class="angle-badge" data-angle="front"><?= htmlspecialchars($t['face_front']) ?></span>
                    <span class="angle-badge" data-angle="right"><?= htmlspecialchars($t['face_right']) ?></span>
                    <span class="angle-badge" data-angle="left"><?= htmlspecialchars($t['face_left']) ?></span>
                </div>

                <div class="counter-box" id="faceCounter"></div>
                <div class="face-status" id="faceStatus"></div>
                <input type="hidden" name="face_images_json" id="face_images_json">
                <?= field_error('face_images_json', $fieldErrors) ?>
                <div class="field-error client-error" id="face_images_json_error"></div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-user-plus"></i> <?= htmlspecialchars($t['register']) ?>
            </button>

            <div class="links">
                <a href="index.php"><?= htmlspecialchars($t['back']) ?></a>
            </div>
        </form>
    </section>
</div>

<script>
/* =========================
   Inline field errors
========================= */
const messages = {
    required: <?= json_encode($t['error_fill']) ?>,
    password: <?= json_encode($t['error_invalid']) ?>,
    confirm: <?= json_encode($t['error_match']) ?>,
    nid: <?= json_encode($t['error_nid']) ?>,
    email: <?= json_encode($t['error_email']) ?>,
    birthdate: <?= json_encode($t['error_birthdate']) ?>,
    face: <?= json_encode($t['face_required']) ?>
};

function setError(fieldId, message){
    const field = document.getElementById(fieldId);
    const errorBox = document.getElementById(fieldId + '_error');
    if(field){ field.classList.add('invalid'); }
    if(errorBox){ errorBox.textContent = message; }
}

function clearError(fieldId){
    const field = document.getElementById(fieldId);
    const errorBox = document.getElementById(fieldId + '_error');
    if(field){ field.classList.remove('invalid'); }
    if(errorBox){ errorBox.textContent = ''; }
}

function togglePassword(inputId, el){
    const input = document.getElementById(inputId);
    const icon = el.querySelector("i");

    if(input.type === "password"){
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    }else{
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

['first_name','last_name','username','national_id','password','confirm_password','gender','governorate','birthdate','contact_number','email','address','nid_photo'].forEach(id => {
    const el = document.getElementById(id);
    if(el){
        el.addEventListener('input', () => clearError(id));
        el.addEventListener('change', () => clearError(id));
    }
});

/* =========================
   FACE CAMERA - Guided Angles
========================= */
const openFaceCameraBtn = document.getElementById('openFaceCameraBtn');
const startFaceScanBtn = document.getElementById('startFaceScanBtn');
const faceBox = document.getElementById('faceBox');
const faceVideo = document.getElementById('faceVideo');
const faceCanvas = document.getElementById('faceCanvas');
const faceStatus = document.getElementById('faceStatus');
const faceCounter = document.getElementById('faceCounter');
const faceImagesInput = document.getElementById('face_images_json');
const angleBadges = document.querySelectorAll('.angle-badge');

let faceStream = null;
let capturedFaces = [];
let scanRunning = false;

const angleSteps = [
    { key: 'front', label: <?= json_encode($t['face_front']) ?> },
    { key: 'right', label: <?= json_encode($t['face_right']) ?> },
    { key: 'left',  label: <?= json_encode($t['face_left']) ?> }
];

const PHOTOS_PER_ANGLE = 2;
const TOTAL_REQUIRED = angleSteps.length * PHOTOS_PER_ANGLE;

function setActiveAngle(angleKey) {
    angleBadges.forEach(badge => {
        badge.classList.remove('active');
        if (badge.dataset.angle === angleKey) badge.classList.add('active');
    });
}

function markAngleDone(angleKey) {
    angleBadges.forEach(badge => {
        if (badge.dataset.angle === angleKey) {
            badge.classList.remove('active');
            badge.classList.add('done');
        }
    });
}

function resetAngleBadges() {
    angleBadges.forEach(badge => badge.classList.remove('active', 'done'));
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function captureFrame(angleKey) {
    const ctx = faceCanvas.getContext('2d');
    ctx.drawImage(faceVideo, 0, 0, faceCanvas.width, faceCanvas.height);
    const imageData = faceCanvas.toDataURL('image/jpeg', 0.9);
    capturedFaces.push({ angle: angleKey, image: imageData });
    faceImagesInput.value = JSON.stringify(capturedFaces);
    faceCounter.textContent = <?= json_encode($t['face_progress']) ?> + ' ' + capturedFaces.length + '/' + TOTAL_REQUIRED;
    clearError('face_images_json');
}

async function countdownAndCapture(angleKey, angleLabel) {
    for (let sec = 3; sec >= 1; sec--) {
        faceStatus.textContent = <?= json_encode($t['face_step_move']) ?> + ' ' + angleLabel + ' — ' + sec;
        await sleep(900);
    }

    faceStatus.textContent = <?= json_encode($t['face_hold_still']) ?> + ': ' + angleLabel;

    for (let i = 0; i < PHOTOS_PER_ANGLE; i++) {
        captureFrame(angleKey);
        await sleep(450);
    }
}

openFaceCameraBtn.addEventListener('click', async () => {
    try {
        if (faceStream) {
            faceStream.getTracks().forEach(track => track.stop());
            faceStream = null;
        }

        capturedFaces = [];
        faceImagesInput.value = '';
        faceStatus.textContent = '';
        faceCounter.textContent = '';
        resetAngleBadges();
        scanRunning = false;
        clearError('face_images_json');

        faceStream = await navigator.mediaDevices.getUserMedia({
            video: { width: 640, height: 480, facingMode: "user" },
            audio: false
        });

        faceVideo.srcObject = faceStream;
        faceBox.style.display = 'block';
        startFaceScanBtn.style.display = 'block';

        faceVideo.onloadedmetadata = async () => {
            faceCanvas.width = faceVideo.videoWidth || 640;
            faceCanvas.height = faceVideo.videoHeight || 480;
            await faceVideo.play();
            faceStatus.textContent = <?= json_encode($t['face_scan_ready']) ?>;
            faceCounter.textContent = <?= json_encode($t['face_samples_target']) ?>;
        };
    } catch (err) {
        setError('face_images_json', <?= json_encode($t['face_camera_error']) ?>);
        console.error(err);
    }
});

startFaceScanBtn.addEventListener('click', async () => {
    if (scanRunning) return;

    if (!faceStream || !faceVideo.videoWidth) {
        setError('face_images_json', <?= json_encode($t['camera_not_ready']) ?>);
        return;
    }

    scanRunning = true;
    capturedFaces = [];
    faceImagesInput.value = '';
    faceCounter.textContent = '0/' + TOTAL_REQUIRED;
    resetAngleBadges();
    clearError('face_images_json');

    for (const step of angleSteps) {
        setActiveAngle(step.key);
        await sleep(800);
        await countdownAndCapture(step.key, step.label);
        markAngleDone(step.key);
        faceStatus.textContent = step.label + ' - ' + <?= json_encode($t['face_angle_done']) ?>;
        await sleep(800);
    }

    faceImagesInput.value = JSON.stringify(capturedFaces);
    faceCounter.textContent = <?= json_encode($t['face_progress']) ?> + ' ' + capturedFaces.length + '/' + TOTAL_REQUIRED;
    faceStatus.textContent = <?= json_encode($t['face_done']) ?>;

    if (faceStream) {
        faceStream.getTracks().forEach(track => track.stop());
        faceStream = null;
    }

    faceVideo.srcObject = null;
    faceBox.style.display = 'none';
    startFaceScanBtn.style.display = 'none';
    scanRunning = false;
});


/* =========================
   Submit validation with inline errors only
========================= */
document.getElementById('registerForm').addEventListener('submit', function(e) {
    let hasError = false;
    const requiredFields = ['first_name','last_name','username','national_id','password','confirm_password','gender','governorate','birthdate','contact_number','email','address'];
    requiredFields.forEach(id => {
        const el = document.getElementById(id);
        clearError(id);
        if (!el || !String(el.value || '').trim()) {
            setError(id, messages.required);
            hasError = true;
        }
    });

    const nid = document.getElementById('national_id').value.trim();
    if (nid && !/^\d{14}$/.test(nid)) {
        setError('national_id', messages.nid);
        hasError = true;
    }

    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const passRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;

    if (password && !passRegex.test(password)) {
        setError('password', messages.password);
        hasError = true;
    }

    if (password && confirm && password !== confirm) {
        setError('confirm_password', messages.confirm);
        hasError = true;
    }

    const email = document.getElementById('email').value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        setError('email', messages.email);
        hasError = true;
    }

    const birthdate = document.getElementById('birthdate').value;
    if (birthdate) {
        const selected = new Date(birthdate);
        const today = new Date();
        today.setHours(0,0,0,0);
        if (selected > today) {
            setError('birthdate', messages.birthdate);
            hasError = true;
        }
    }

    const nidFile = document.getElementById('nid_photo');
    const oldNidPhoto = document.getElementById('old_national_id_photo');
    clearError('nid_photo');
    if ((!nidFile.files || nidFile.files.length === 0) && (!oldNidPhoto || oldNidPhoto.value.trim() === '')) {
        setError('nid_photo', messages.required);
        hasError = true;
    }

    clearError('face_images_json');
    try {
        const parsed = JSON.parse(faceImagesInput.value || '[]');
        if (!Array.isArray(parsed) || parsed.length < TOTAL_REQUIRED) {
            setError('face_images_json', messages.face);
            hasError = true;
        }
    } catch (err) {
        setError('face_images_json', messages.face);
        hasError = true;
    }

    if (hasError) {
        e.preventDefault();
        const firstError = document.querySelector('.invalid, .field-error:not(:empty)');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>
</body>
</html>