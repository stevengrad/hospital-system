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

$texts = [
    'en'=>[
        'heading'=>'Patient Registration',
        'title'=>'Register Account',
        'username'=>'Username',
        'national_id'=>'National ID',
        'password'=>'Password',
        'confirm'=>'Confirm Password',
        'register'=>'Register',
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
        'error_fill'=>'Please fill all fields and provide a National ID photo.',
        'error_birthdate'=>'Invalid birthdate.',
        'nid_photo'=>'National ID Photo',
        'take_photo'=>'📸',
        'face_scan'=>'Face Scan',
        'open_face_camera'=>'Open Face Camera',
        'start_face_scan'=>'Start Face Scan',
        'face_required'=>'Please complete the full face scan.',
        'face_note'=>'Capture face images from front, right, and left angles for better recognition.',
        'face_progress'=>'Captured',
        'face_done'=>'Face scan completed successfully.',
        'face_camera_error'=>'Could not open face camera.',
        'camera_not_ready'=>'Camera not ready yet.',
        'camera_capture'=>'Capture',
        'camera_not_available'=>'Camera not available',
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
    ],

    'ar'=>[
        'heading'=>'تسجيل مريض',
        'title'=>'تسجيل حساب جديد',
        'username'=>'اسم المستخدم',
        'national_id'=>'الرقم القومي',
        'password'=>'كلمة المرور',
        'confirm'=>'تأكيد كلمة المرور',
        'register'=>'تسجيل',
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
        'error_fill'=>'يرجى ملء جميع الحقول وإضافة صورة البطاقة.',
        'error_birthdate'=>'تاريخ الميلاد غير صحيح.',
        'nid_photo'=>'صورة البطاقة',
        'take_photo'=>'📸',
        'face_scan'=>'مسح الوجه',
        'open_face_camera'=>'فتح كاميرا الوجه',
        'start_face_scan'=>'ابدأ مسح الوجه',
        'face_required'=>'يرجى إكمال مسح الوجه بالكامل.',
        'face_note'=>'يجب التقاط صور واضحة للوجه من الأمام واليمين واليسار لتحسين التعرف.',
        'face_progress'=>'تم التقاط',
        'face_done'=>'تم الانتهاء من مسح الوجه بنجاح.',
        'face_camera_error'=>'تعذر فتح كاميرا الوجه.',
        'camera_not_ready'=>'الكاميرا ليست جاهزة بعد.',
        'camera_capture'=>'التقاط',
        'camera_not_available'=>'الكاميرا غير متاحة',
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
    ]
];

$t = $texts[$lang];
$error = '';
$success_notice = '';
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_register($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

/* =========================
   SMTP Mail helper
   CHANGE THESE VALUES
========================= */
function sendWelcomeCredentialsEmailSMTP($toEmail, $username, $plainPassword, $lang, $texts) {
   $smtpUser = 'cairohospitals0@gmail.com';
$smtpPass = 'nyqt rjbf pyoo qsfx';
    $fromName = 'Cairo Hospitals';

    if (empty($toEmail) || empty($smtpUser) || empty($smtpPass) || $smtpUser === 'YOUR_GMAIL@gmail.com') {
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
    }catch (Exception $e) {
    return ['ok' => false, 'reason' => $mail->ErrorInfo];
}
}

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

    $plainPasswordForEmail = $password;

   

    $validPassword = preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password);
    $validEmail    = filter_var($email, FILTER_VALIDATE_EMAIL);
    $validNID      = preg_match('/^\d{14}$/', $national_id);

    $nid_photo_name = '';
    $nid_photo_path = '';

    if(!empty($_FILES['nid_photo']['name'])){
        $original = basename($_FILES['nid_photo']['name']);
        $ext      = pathinfo($original, PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-\.]/','_',substr(pathinfo($original, PATHINFO_FILENAME),0,50));
        $nid_photo_name = "nid_".time()."_".$safeBase.".".$ext;

        $uploadDir = __DIR__."/uploads";
        if(!is_dir($uploadDir)) {
            mkdir($uploadDir,0755,true);
        }

        $target = $uploadDir."/".$nid_photo_name;

        if(move_uploaded_file($_FILES['nid_photo']['tmp_name'],$target)){
            $nid_photo_path = "/uploads/".$nid_photo_name;
        } else {
            $nid_photo_name = '';
            $nid_photo_path = '';
        }
    }

    $face_saved_ok = false;
    $face_relative_path = '';
    $face_samples_count = 0;
    $saved_face_paths = [];

    $decodedFaceImages = json_decode($face_images_json, true);
    if (!is_array($decodedFaceImages)) {
        $decodedFaceImages = [];
    }

    if (!empty($decodedFaceImages) && !empty($username)) {
        $faceDbRoot = "C:/xampp/htdocs/hospital/FFace_Recognition_System _Depi_stud/build_database/db_folder";
        $safeUsername = preg_replace('/[^A-Za-z0-9_\-]/', '_', $username);
        $personDir = $faceDbRoot . "/" . $safeUsername;

        if (!is_dir($personDir)) {
            mkdir($personDir, 0755, true);
        }

        $counter = 1;
        foreach ($decodedFaceImages as $imgItem) {
            $angleName = 'face';
            $imgData = '';

            if (is_array($imgItem)) {
                $angleName = preg_replace('/[^A-Za-z0-9_\-]/', '_', strtolower($imgItem['angle'] ?? 'face'));
                $imgData = $imgItem['image'] ?? '';
            } else {
                $imgData = $imgItem;
            }

            if (!preg_match('/^data:image\/(\w+);base64,/', $imgData, $type)) {
                continue;
            }

            $imageDataClean = substr($imgData, strpos($imgData, ',') + 1);
            $imageDataClean = str_replace(' ', '+', $imageDataClean);
            $imageBinary = base64_decode($imageDataClean);

            if ($imageBinary === false) {
                continue;
            }

            $imageExt = strtolower($type[1]);
            if (!in_array($imageExt, ['jpg', 'jpeg', 'png'])) {
                $imageExt = 'jpg';
            }

            $faceFileName = sprintf("%s_%s_%03d_%d.%s", $safeUsername, $angleName, $counter, time(), $imageExt);
            $faceFullPath = $personDir . "/" . $faceFileName;

            if (file_put_contents($faceFullPath, $imageBinary) !== false) {
                $saved_face_paths[] = "build_database/db_folder/" . $safeUsername . "/" . $faceFileName;
                $counter++;
            }
        }

        $face_samples_count = count($saved_face_paths);
        if ($face_samples_count >= 6) {
            $face_saved_ok = true;
            $face_relative_path = $saved_face_paths[0];
        }
    }

    if(!$username || !$national_id || !$password || !$confirm || !$gender || !$governorate || !$address || !$email || !$birthdate || !$contact_number){
        $error = $t['error_fill'];
    }elseif(!$validNID){
        $error = $t['error_nid'];
    }elseif(!$validPassword){
        $error = $t['error_invalid'];
    }elseif($password !== $confirm){
        $error = $t['error_match'];
    }elseif(!$validEmail){
        $error = $t['error_email'];
    }elseif(strtotime($birthdate) > strtotime(date('Y-m-d'))){
        $error = $t['error_birthdate'];
    }elseif(!$nid_photo_path){
        $error = $t['error_fill'];
    }elseif(!$face_saved_ok){
        $error = $t['face_required'];
    }else{

        $stmtCheckUser = $conn->prepare("SELECT id FROM registration WHERE username = ? LIMIT 1");
        $stmtCheckUser->bind_param("s", $username);
        $stmtCheckUser->execute();
        $stmtCheckUser->store_result();

        if($stmtCheckUser->num_rows > 0){
            $error = $t['username_exists'];
        } else {
            $stmtCheckNID = $conn->prepare("SELECT id FROM registration WHERE national_id = ? LIMIT 1");
            $stmtCheckNID->bind_param("s", $national_id);
            $stmtCheckNID->execute();
            $stmtCheckNID->store_result();

            if($stmtCheckNID->num_rows > 0){
                $error = $t['nid_exists'];
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt_reg = $conn->prepare("
                    INSERT INTO registration
                    (first_name, last_name, username, password, national_id, national_id_photo, face_image_path, face_samples_count, gender, government, birthdate, address, email, phone_number)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");

                if(!$stmt_reg){
                    $error = $t['db_error'] . ": " . $conn->error;
                } else {
                    $stmt_reg->bind_param(
                        "ssssssssssssss",
                        $first_name,
                        $last_name,
                        $username,
                        $hashed,
                        $national_id,
                        $nid_photo_path,
                        $face_relative_path,
                        $face_samples_count,
                        $gender,
                        $governorate,
                        $birthdate,
                        $address,
                        $email,
                        $contact_number
                    );

                    if($stmt_reg->execute()){

                        $role = 'patient';
                        $stmt_login = $conn->prepare("
                            INSERT INTO login (username, password, national_id, role, face_image_path, face_samples_count)
                            VALUES (?,?,?,?,?,?)
                        ");

                        if(!$stmt_login){
                            $error = $t['db_error'] . " (login): " . $conn->error;
                        } else {
                            $stmt_login->bind_param(
                                "sssssi",
                                $username,
                                $hashed,
                                $national_id,
                                $role,
                                $face_relative_path,
                                $face_samples_count
                            );

                            if($stmt_login->execute()){

                                $bloodType = NULL;
                                $stmt_patient = $conn->prepare("
                                    INSERT INTO patients
                                    (NationalID, FirstName, LastName, DOB, Gender, BloodType, ContactPhone, Email, Address)
                                    VALUES (?,?,?,?,?,?,?,?,?)
                                ");

                                if(!$stmt_patient){
                                    $error = $t['db_error'] . " (patients): " . $conn->error;
                                } else {
                                    $stmt_patient->bind_param(
                                        "sssssssss",
                                        $national_id,
                                        $first_name,
                                        $last_name,
                                        $birthdate,
                                        $gender,
                                        $bloodType,
                                        $contact_number,
                                        $email,
                                        $address
                                    );

                                    if($stmt_patient->execute()){

                                        $visit_date   = date('Y-m-d H:i:s');
                                        $doctor_name  = "System";
                                        $diagnosis    = "New patient registration";
                                        $treatment    = "Initial account created in patient portal.";

                                        $stmt_history = $conn->prepare("
                                            INSERT INTO patient_history
                                            (patient_username, visit_date, doctor_name, diagnosis, treatment)
                                            VALUES (?,?,?,?,?)
                                        ");

                                        if(!$stmt_history){
                                            $error = $t['db_error'] . " (history): " . $conn->error;
                                        } else {
                                            $stmt_history->bind_param(
                                                "sssss",
                                                $username,
                                                $visit_date,
                                                $doctor_name,
                                                $diagnosis,
                                                $treatment
                                            );

                                            if($stmt_history->execute()){

                                                $mailResult = sendWelcomeCredentialsEmailSMTP(
                                                    $email,
                                                    $username,
                                                    $plainPasswordForEmail,
                                                    $lang,
                                                    $texts
                                                );

                                                if (!$mailResult['ok']) {
                                                   $success_notice = 'Mail error: ' . $mailResult['reason'];
                                                }

                                                $reloadUrl = "http://127.0.0.1:5001/reload_db";
                                                $context = stream_context_create([
                                                    'http' => [
                                                        'method'  => 'POST',
                                                        'timeout' => 3,
                                                        'header'  => "Content-Type: application/json\r\n"
                                                    ]
                                                ]);
                                                @file_get_contents($reloadUrl, false, $context);

                                                if (empty($success_notice)) {
                                                    header("Location:index.php");
                                                    exit();
                                                }
                                            } else {
                                                $error = $t['db_error'] . ": Failed to create initial history: " . $stmt_history->error;
                                            }
                                        }

                                    } else {
                                        $error = $t['db_error'] . ": Failed to create patient record: " . $stmt_patient->error;
                                    }
                                }

                            } else {
                                $error = $t['db_error'] . ": Failed to create user login: " . $stmt_login->error;
                            }
                        }

                    } else {
                        $error = $t['db_error'] . ": Failed to create registration: " . $stmt_reg->error;
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($t['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
    --warning:#f59e0b;
    --danger:#dc2626;
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
    max-width:1220px;
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
    grid-template-columns:1fr;
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

.register-card{
    width:100%;
    max-width:560px;
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

.lang-toggle a.active{
    background:#eef6ff;
    border-color:#9cc5f1;
    color:#0f62b8;
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

.notice{
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    text-align:center;
    margin-bottom:14px;
    font-size:14px;
    border-radius:14px;
    padding:12px 14px;
    font-weight:600;
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
    color:#7c90a8;
    pointer-events:none;
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

.form-input::placeholder,
.form-textarea::placeholder{
    color:#8a9aae;
}

.helper-line{
    font-size:12px;
    color:#7f8ea3;
    margin-top:6px;
    padding-inline:4px;
}

.file-group{
    display:flex;
    align-items:center;
    gap:8px;
}

.file-group input[type=file]{
    flex:1;
    min-width:0;
    border:1px solid var(--line);
    border-radius:14px;
    padding:14px 12px;
    background:#f8fbff;
}

.small-camera-btn{
    min-width:56px;
    height:56px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg, var(--primary), #35a2ff);
    color:#fff;
    font-size:18px;
    cursor:pointer;
    box-shadow:0 10px 20px rgba(31,143,255,0.18);
}

.small-camera-btn:hover{
    background:linear-gradient(135deg, var(--primary-dark), #1f8fff);
}

.preview-img{
    width:100%;
    max-width:380px;
    height:240px;
    object-fit:cover;
    border-radius:16px;
    border:2px solid #60a5fa;
    display:none;
    margin:12px auto 0;
    box-shadow:0 10px 24px rgba(31,143,255,0.10);
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

    .register-card{
        max-width:620px;
    }
}

@media (max-width: 700px){
    .grid-2{
        grid-template-columns:1fr;
    }
}

@media (max-width: 560px){
    body{
        padding:18px 12px;
    }

    .register-card{
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

    .face-actions{
        flex-direction:column;
    }
}
</style>
</head>
<body>
<div class="page-shell">
    <section class="hero-panel">
        <div class="eyebrow">
            <span>🩺</span>
            <span><?= ($lang === 'ar') ? 'تجربة تسجيل احترافية وآمنة' : 'A premium and secure registration experience' ?></span>
        </div>

        <h2 class="hero-title">
            <?= ($lang === 'ar')
                ? 'ابدأ رحلتك الطبية بحساب موثوق وتجربة تسجيل أنيقة'
                : 'Start your healthcare journey with a trusted account and a refined registration flow' ?>
        </h2>

        <p class="hero-text">
            <?= ($lang === 'ar')
                ? 'أنشئ حسابك للوصول إلى خدمات المستشفى، إدارة المواعيد، والاستفادة من تسجيل الدخول بالتعرف على الوجه في تجربة حديثة وآمنة.'
                : 'Create your account to access hospital services, manage appointments, and benefit from face recognition login in a modern secure flow.' ?>
        </p>

        <div class="hero-points">
            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'تسجيل آمن' : 'Secure onboarding' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'تجربة تسجيل أكثر أمانًا مع التحقق من كلمة المرور ودعم مسح الوجه الموجّه.' : 'A safer registration flow with password validation and guided face scan support.' ?></div>
            </div>

            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'وصول أسرع' : 'Smart access' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'يتم التقاط الوجه من عدة زوايا لتحسين دقة تسجيل الدخول لاحقًا.' : 'Multiple face angles are captured to improve recognition accuracy later.' ?></div>
            </div>

            <div class="point-card">
                <div class="point-title"><?= ($lang === 'ar') ? 'إرسال بيانات الحساب' : 'Credential email' ?></div>
                <div class="point-text"><?= ($lang === 'ar') ? 'بعد إنشاء الحساب سيتم إرسال اسم المستخدم وكلمة المرور إلى البريد الإلكتروني المُدخل.' : 'After registration, the username and password will be sent to the entered email.' ?></div>
            </div>
        </div>
    </section>

    <section class="register-card">
        <div class="lang-toggle">
            <a href="<?= htmlspecialchars(lang_link_register('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="<?= htmlspecialchars(lang_link_register('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand-wrap">
            <div class="brand-badge">🏥</div>
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

       <form method="POST" enctype="multipart/form-data" id="registerForm">

    <div class="grid-2">
        <div class="form-group">
            <label class="field-label"><?= ($lang === 'ar') ? 'الاسم الأول' : 'First Name' ?></label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input class="form-input" type="text" name="first_name" required>
            </div>
        </div>

        <div class="form-group">
            <label class="field-label"><?= ($lang === 'ar') ? 'اسم العائلة' : 'Last Name' ?></label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input class="form-input" type="text" name="last_name" required>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <div class="form-group">
            <label class="field-label"><?= htmlspecialchars($t['username']) ?></label>
            <div class="input-wrap">
                <span class="icon">👤</span>
                <input class="form-input" type="text" name="username" required>
            </div>
        </div>

        <div class="form-group">
            <label class="field-label"><?= htmlspecialchars($t['national_id']) ?></label>
            <div class="input-wrap">
                <span class="icon">🪪</span>
                <input class="form-input" type="text" name="national_id" maxlength="14" required>
            </div>
        </div>
    </div>

    <div class="grid-2">
       <div class="form-group">
    <label class="field-label"><?= htmlspecialchars($t['password']) ?></label>
    <div class="input-wrap">
        <span class="icon">🔒</span>
        <input class="form-input" type="password" name="password" id="password" required>
        <span class="toggle-eye" onclick="togglePassword('password', this)">
            <i class="fa-regular fa-eye"></i>
        </span>
    </div>
</div>
       <div class="form-group">
    <label class="field-label"><?= htmlspecialchars($t['confirm']) ?></label>
    <div class="input-wrap">
        <span class="icon">✅</span>
        <input class="form-input" type="password" name="confirm_password" id="confirm_password" required>
        <span class="toggle-eye" onclick="togglePassword('confirm_password', this)">
            <i class="fa-regular fa-eye"></i>
        </span>
    </div>
</div>
    </div>

            <div class="form-group">
    <label class="field-label"><?= htmlspecialchars($t['nid_photo']) ?></label>

    <div class="file-group">
        <input type="file" name="nid_photo" id="nidFile" accept="image/*" required>
    </div>

    <div class="helper-line">
        <?= ($lang === 'ar') ? 'ارفعي صورة بطاقة واضحة.' : 'Upload a clear image of your National ID.' ?>
    </div>
</div>
            <div class="grid-2">
                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['gender']) ?></label>
                    <select class="form-select" name="gender" required>
                        <option value=""><?= htmlspecialchars($t['select_gender']) ?></option>
                        <option value="Male"><?= ($lang === 'ar') ? 'ذكر' : 'Male' ?></option>
                        <option value="Female"><?= ($lang === 'ar') ? 'أنثى' : 'Female' ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['governorate']) ?></label>
                    <select class="form-select" name="governorate" required>
                        <option value=""><?= htmlspecialchars($t['select_governorate']) ?></option>
                        <option value="Cairo"><?= ($lang === 'ar') ? 'القاهرة' : 'Cairo' ?></option>
                        <option value="Giza"><?= ($lang === 'ar') ? 'الجيزة' : 'Giza' ?></option>
                        <option value="Alexandria"><?= ($lang === 'ar') ? 'الإسكندرية' : 'Alexandria' ?></option>
                        <option value="Qalyubia"><?= ($lang === 'ar') ? 'القليوبية' : 'Qalyubia' ?></option>
                        <option value="Dakahlia"><?= ($lang === 'ar') ? 'الدقهلية' : 'Dakahlia' ?></option>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['birthdate']) ?></label>
                    <input class="form-input" type="date" name="birthdate" max="<?= date('Y-m-d') ?>" required style="padding:0 16px;">
                </div>

                <div class="form-group">
                    <label class="field-label"><?= htmlspecialchars($t['phone']) ?></label>
                    <div class="input-wrap">
                        <span class="icon">📞</span>
                        <input class="form-input" type="text" name="contact_number" required>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label"><?= htmlspecialchars($t['email']) ?></label>
                <div class="input-wrap">
                    <span class="icon">✉️</span>
                    <input class="form-input" type="email" name="email" required>
                </div>
            </div>

            <div class="form-group">
                <label class="field-label"><?= htmlspecialchars($t['address']) ?></label>
                <textarea class="form-textarea" name="address" required></textarea>
            </div>

            <div class="form-group">
                <label class="field-label"><?= htmlspecialchars($t['face_scan']) ?></label>

                <div class="face-actions">
                    <button type="button" class="btn-main" id="openFaceCameraBtn"><?= htmlspecialchars($t['open_face_camera']) ?></button>
                    <button type="button" class="btn-soft" id="startFaceScanBtn" style="display:none;"><?= htmlspecialchars($t['start_face_scan']) ?></button>
                </div>

                <div class="helper-line">
                    <?= ($lang === 'ar')
                        ? 'ضعي الوجه داخل الإطار الأخضر. سيتم التقاط صورتين لكل زاوية: الأمام ثم اليمين ثم اليسار.'
                        : 'Place your face inside the green frame. Two photos will be captured for each angle: front, then right, then left.' ?>
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
            </div>

            <button type="submit" class="btn-submit"><?= htmlspecialchars($t['register']) ?></button>

            <div class="links">
                <a href="index.php"><?= htmlspecialchars($t['back']) ?></a>
            </div>
        </form>
    </section>
</div>

<script>

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
    { key: 'front', label: '<?= addslashes($t['face_front']) ?>' },
    { key: 'right', label: '<?= addslashes($t['face_right']) ?>' },
    { key: 'left',  label: '<?= addslashes($t['face_left']) ?>' }
];

const PHOTOS_PER_ANGLE = 2;
const TOTAL_REQUIRED = angleSteps.length * PHOTOS_PER_ANGLE;

function setActiveAngle(angleKey) {
    angleBadges.forEach(badge => {
        badge.classList.remove('active');
        if (badge.dataset.angle === angleKey) {
            badge.classList.add('active');
        }
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
    angleBadges.forEach(badge => {
        badge.classList.remove('active', 'done');
    });
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function captureFrame(angleKey) {
    const ctx = faceCanvas.getContext('2d');
    ctx.drawImage(faceVideo, 0, 0, faceCanvas.width, faceCanvas.height);
    const imageData = faceCanvas.toDataURL('image/jpeg', 0.9);
    capturedFaces.push({
        angle: angleKey,
        image: imageData
    });
    faceImagesInput.value = JSON.stringify(capturedFaces);
    faceCounter.textContent = '<?= addslashes($t['face_progress']) ?> ' + capturedFaces.length + '/' + TOTAL_REQUIRED;
}

async function countdownAndCapture(angleKey, angleLabel) {
    for (let sec = 3; sec >= 1; sec--) {
        faceStatus.textContent = '<?= addslashes($t['face_step_move']) ?> ' + angleLabel + ' — ' + sec;
        await sleep(900);
    }

    faceStatus.textContent = '<?= addslashes($t['face_hold_still']) ?>: ' + angleLabel;

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
            faceStatus.textContent = '<?= addslashes($t['face_scan_ready']) ?>';
            faceCounter.textContent = '<?= addslashes($t['face_samples_target']) ?>';
        };
    } catch (err) {
        faceStatus.textContent = '<?= addslashes($t['face_camera_error']) ?>';
        console.error(err);
    }
});

startFaceScanBtn.addEventListener('click', async () => {
    if (scanRunning) return;

    if (!faceStream || !faceVideo.videoWidth) {
        faceStatus.textContent = '<?= addslashes($t['camera_not_ready']) ?>';
        return;
    }

    scanRunning = true;
    capturedFaces = [];
    faceImagesInput.value = '';
    faceCounter.textContent = '0/' + TOTAL_REQUIRED;
    resetAngleBadges();

    for (const step of angleSteps) {
        setActiveAngle(step.key);
        await sleep(800);
        await countdownAndCapture(step.key, step.label);
        markAngleDone(step.key);
        faceStatus.textContent = step.label + ' - <?= addslashes($t['face_angle_done']) ?>';
        await sleep(800);
    }

    faceImagesInput.value = JSON.stringify(capturedFaces);
    faceCounter.textContent = '<?= addslashes($t['face_progress']) ?> ' + capturedFaces.length + '/' + TOTAL_REQUIRED;
    faceStatus.textContent = '<?= addslashes($t['face_done']) ?>';

    if (faceStream) {
        faceStream.getTracks().forEach(track => track.stop());
        faceStream = null;
    }

    faceVideo.srcObject = null;
    faceBox.style.display = 'none';
    startFaceScanBtn.style.display = 'none';
    scanRunning = false;
});

document.getElementById('registerForm').addEventListener('submit', function(e) {
    try {
        const parsed = JSON.parse(faceImagesInput.value || '[]');
        if (!Array.isArray(parsed) || parsed.length < TOTAL_REQUIRED) {
            e.preventDefault();
            alert('<?= addslashes($t['face_required']) ?>');
        }
    } catch (err) {
        e.preventDefault();
        alert('<?= addslashes($t['face_required']) ?>');
    }
});
function togglePassword(inputId, eyeElement) {
    const input = document.getElementById(inputId);
    const icon = eyeElement.querySelector('i');

    if (input.type === "password") {
        input.type = "text";
        icon.className = "fa-regular fa-eye-slash";
    } else {
        input.type = "password";
        icon.className = "fa-regular fa-eye";
    }
}
</script>

</body>
</html>