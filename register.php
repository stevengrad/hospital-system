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
        'error_fill'=>'Please fill all fields and provide a National ID photo.',
        'error_birthdate'=>'Invalid birthdate.',
        'nid_photo'=>'National ID Photo',
        'face_scan'=>'Face Scan',
        'open_face_camera'=>'Open Face Camera',
        'start_face_scan'=>'Start Face Scan',
        'face_required'=>'Please complete the full face scan.',
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
        'error_fill'=>'يرجى ملء جميع الحقول وإضافة صورة البطاقة.',
        'error_birthdate'=>'تاريخ الميلاد غير صحيح.',
        'nid_photo'=>'صورة البطاقة',
        'face_scan'=>'مسح الوجه',
        'open_face_camera'=>'فتح كاميرا الوجه',
        'start_face_scan'=>'ابدأ مسح الوجه',
        'face_required'=>'يرجى إكمال مسح الوجه بالكامل.',
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
========================= */
function sendWelcomeCredentialsEmailSMTP($toEmail, $username, $plainPassword, $lang, $texts) {
    $smtpUser = 'cairohospitals0@gmail.com';
    $smtpPass = 'PUT_YOUR_GMAIL_APP_PASSWORD_HERE'; // put your same Gmail App Password here
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
        <html><body style="font-family:Arial,sans-serif;line-height:1.8;color:#0f172a;">
            <h2 style="color:#116ad0;">' . htmlspecialchars($greeting) . '</h2>
            <p>' . htmlspecialchars($body1) . '</p>
            <p>' . htmlspecialchars($body2) . '</p>
            <div style="background:#f8fafc;border:1px solid #dbe4ee;border-radius:12px;padding:16px;">
                <p><strong>' . htmlspecialchars($labelUser) . ':</strong> ' . htmlspecialchars($username) . '</p>
                <p><strong>' . htmlspecialchars($labelPass) . ':</strong> ' . htmlspecialchars($plainPassword) . '</p>
            </div>
            <p style="margin-top:16px;">' . htmlspecialchars($note) . '</p>
        </body></html>';

        $mail->AltBody = $greeting . "\n\n" . $body1 . "\n" . $body2 . "\n\n" . $labelUser . ': ' . $username . "\n" . $labelPass . ': ' . $plainPassword . "\n\n" . $note;
        $mail->send();
        return ['ok' => true, 'reason' => 'sent'];
    } catch (Exception $e) {
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

                $stmt_reg = $conn->prepare("INSERT INTO registration
                    (first_name, last_name, username, password, national_id, national_id_photo, face_image_path, face_samples_count, gender, government, birthdate, address, email, phone_number)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

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
                        $stmt_login = $conn->prepare("INSERT INTO login (username, password, national_id, role, face_image_path, face_samples_count) VALUES (?,?,?,?,?,?)");

                        if(!$stmt_login){
                            $error = $t['db_error'] . " (login): " . $conn->error;
                        } else {
                            $stmt_login->bind_param("sssssi", $username, $hashed, $national_id, $role, $face_relative_path, $face_samples_count);

                            if($stmt_login->execute()){
                                $bloodType = NULL;
                                $stmt_patient = $conn->prepare("INSERT INTO patients (NationalID, FirstName, LastName, DOB, Gender, BloodType, ContactPhone, Email, Address) VALUES (?,?,?,?,?,?,?,?,?)");

                                if(!$stmt_patient){
                                    $error = $t['db_error'] . " (patients): " . $conn->error;
                                } else {
                                    $stmt_patient->bind_param("sssssssss", $national_id, $first_name, $last_name, $birthdate, $gender, $bloodType, $contact_number, $email, $address);

                                    if($stmt_patient->execute()){
                                        $visit_date   = date('Y-m-d H:i:s');
                                        $doctor_name  = "System";
                                        $diagnosis    = "New patient registration";
                                        $treatment    = "Initial account created in patient portal.";

                                        $stmt_history = $conn->prepare("INSERT INTO patient_history (patient_username, visit_date, doctor_name, diagnosis, treatment) VALUES (?,?,?,?,?)");

                                        if(!$stmt_history){
                                            $error = $t['db_error'] . " (history): " . $conn->error;
                                        } else {
                                            $stmt_history->bind_param("sssss", $username, $visit_date, $doctor_name, $diagnosis, $treatment);

                                            if($stmt_history->execute()){
                                                $mailResult = sendWelcomeCredentialsEmailSMTP($email, $username, $plainPasswordForEmail, $lang, $texts);
                                                if (!$mailResult['ok']) {
                                                    $success_notice = 'Mail notice: ' . $mailResult['reason'];
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($t['title']) ?> - Cairo Hospitals</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
    --bg1:#0f2a3d;
    --bg2:#1b4d6b;
    --green:#1e7d4f;
    --green2:#22c55e;
    --blue:#0b69b7;
    --text:#0f172a;
    --muted:#64748b;
    --line:#d8e4f0;
    --soft:#f8fbff;
    --white:#ffffff;
    --danger:#dc2626;
    --warning:#9a3412;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter','Segoe UI',sans-serif;}
body{
    min-height:100vh;
    background:
        radial-gradient(circle at 15% 25%, rgba(31,143,255,.22), transparent 23%),
        radial-gradient(circle at 85% 20%, rgba(34,197,94,.16), transparent 22%),
        linear-gradient(135deg,var(--bg1),#0d2436 45%,var(--bg2));
    display:flex;
    color:var(--text);
}
.left{
    flex:1;
    color:white;
    padding:64px 7vw;
    display:flex;
    flex-direction:column;
    justify-content:center;
    min-height:100vh;
}
.badge{
    display:inline-flex;
    width:max-content;
    align-items:center;
    gap:10px;
    padding:12px 18px;
    border-radius:999px;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.16);
    color:#eaf6ff;
    font-size:14px;
    font-weight:700;
    margin-bottom:26px;
    backdrop-filter:blur(10px);
}
.left h1{
    max-width:760px;
    font-size:clamp(42px,5.4vw,76px);
    line-height:1.04;
    font-weight:900;
    letter-spacing:-2px;
    margin-bottom:20px;
}
.left .lead{
    max-width:680px;
    color:#d7e5f2;
    font-size:18px;
    line-height:1.8;
    margin-bottom:34px;
}
.features{
    display:grid;
    grid-template-columns:repeat(2,minmax(220px,1fr));
    gap:18px;
    max-width:720px;
}
.feature{
    background:rgba(255,255,255,.09);
    border:1px solid rgba(255,255,255,.15);
    border-radius:22px;
    padding:22px;
    min-height:122px;
    backdrop-filter:blur(10px);
}
.feature i{font-size:24px;margin-bottom:14px;color:#ffffff;}
.feature strong{display:block;font-size:16px;margin-bottom:8px;}
.feature p{font-size:14px;line-height:1.6;color:#d3e0eb;}
.right-wrap{
    width:620px;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:36px 34px 36px 0;
}
.right{
    width:100%;
    max-height:92vh;
    overflow-y:auto;
    background:rgba(255,255,255,.94);
    border:1px solid rgba(255,255,255,.75);
    border-radius:34px;
    padding:34px;
    box-shadow:0 28px 80px rgba(0,0,0,.32);
    backdrop-filter:blur(16px);
    position:relative;
}
.right::-webkit-scrollbar{width:8px;}
.right::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;}
.lang-toggle{
    position:absolute;
    top:20px;
    <?= ($dir === 'rtl') ? 'left' : 'right' ?>:22px;
    display:flex;
    gap:8px;
}
.lang-toggle a{
    text-decoration:none;
    color:#0b5fa5;
    font-weight:800;
    font-size:13px;
    border:1px solid #aed0f4;
    background:#f4f9ff;
    padding:9px 13px;
    border-radius:14px;
}
.lang-toggle a.active{background:#e9f4ff;border-color:#7bb9f3;}
.brand{
    text-align:center;
    margin:10px 0 22px;
}
.logo-img{
    width:82px;
    height:82px;
    object-fit:contain;
    display:block;
    margin:0 auto 10px;
    border-radius:20px;
}
.brand h2{
    color:var(--green);
    font-size:28px;
    font-weight:900;
    margin-bottom:8px;
}
.brand p{color:var(--muted);font-size:15px;line-height:1.7;max-width:420px;margin:auto;}
.error,.notice{
    padding:13px 14px;
    border-radius:16px;
    margin-bottom:16px;
    text-align:center;
    font-weight:700;
    font-size:14px;
}
.error{background:#fff1f2;border:1px solid #fecdd3;color:#be123c;}
.notice{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.form-group{margin-bottom:16px;}
.label{display:block;font-size:14px;font-weight:800;color:#243b53;margin-bottom:8px;}
.input-box{position:relative;}
.input-box i{
    position:absolute;
    <?= ($dir === 'rtl') ? 'right' : 'left' ?>:16px;
    top:50%;transform:translateY(-50%);
    color:var(--blue);
    font-size:16px;
    pointer-events:none;
}
.input-box input,.input-box select,.textarea{
    width:100%;
    border:1px solid var(--line);
    background:var(--soft);
    border-radius:17px;
    color:var(--text);
    font-size:15px;
    outline:none;
    transition:.2s;
}
.input-box input,.input-box select{height:58px;<?= ($dir === 'rtl') ? 'padding:0 48px 0 46px;' : 'padding:0 46px 0 48px;' ?>}
.textarea{min-height:106px;resize:vertical;padding:16px;}
.input-box input:focus,.input-box select:focus,.textarea:focus{
    border-color:#8ac2f7;background:white;box-shadow:0 0 0 4px rgba(11,105,183,.10);
}
.eye{
    position:absolute;
    <?= ($dir === 'rtl') ? 'left' : 'right' ?>:16px;
    top:50%;transform:translateY(-50%);
    color:#64748b;
    cursor:pointer;
    z-index:2;
}
.helper{font-size:12px;color:#73849a;margin-top:7px;line-height:1.55;}
.file-input{
    width:100%;
    border:1px solid var(--line);
    background:var(--soft);
    border-radius:17px;
    padding:15px;
    font-size:14px;
}
.face-card{
    border:1px solid #cdebd9;
    background:#fbfffd;
    border-radius:22px;
    padding:16px;
}
.face-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;}
.face-btn,.face-btn-soft{
    height:52px;
    border-radius:16px;
    font-size:14px;
    font-weight:900;
    cursor:pointer;
    border:none;
    transition:.2s;
}
.face-btn{background:linear-gradient(135deg,#0b69b7,#1495df);color:white;box-shadow:0 12px 26px rgba(11,105,183,.18);}
.face-btn-soft{background:white;color:#0f7a43;border:2px solid rgba(34,197,94,.22);}
.face-btn:hover{filter:brightness(.96);}
.face-btn-soft:hover{background:#f1fff6;}
.face-box{
    position:relative;
    width:100%;
    height:280px;
    margin-top:14px;
    border-radius:20px;
    overflow:hidden;
    border:2px solid rgba(34,197,94,.55);
    background:#08111f;
    display:none;
}
.face-box video,.face-box canvas{width:100%;height:100%;object-fit:cover;}
.face-box canvas{position:absolute;top:0;left:0;z-index:2;}
.face-guide{
    position:absolute;top:50%;left:50%;width:180px;height:220px;
    transform:translate(-50%,-50%);
    border:3px solid #22c55e;border-radius:26px;
    box-shadow:0 0 0 999px rgba(0,0,0,.18);
    z-index:3;pointer-events:none;
}
.angle-badges{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:12px;}
.angle-badge{padding:8px 12px;border-radius:999px;background:#eef2f7;color:#475569;border:1px solid #dbe4ee;font-size:12px;font-weight:900;}
.angle-badge.active{background:#dbeafe;color:#0b69b7;border-color:#93c5fd;}
.angle-badge.done{background:#dcfce7;color:#166534;border-color:#86efac;}
.face-status,.counter-box{text-align:center;font-size:13px;font-weight:800;margin-top:10px;color:#41576f;min-height:20px;}
.counter-box{color:#0b69b7;}
.btn{
    width:100%;height:58px;border:none;border-radius:18px;
    background:linear-gradient(135deg,#18a957,#24c76a);
    color:white;font-size:17px;font-weight:900;cursor:pointer;
    box-shadow:0 14px 30px rgba(34,197,94,.24);
    margin-top:6px;
}
.btn:hover{background:linear-gradient(135deg,#12833c,#20b85f);}
.links{text-align:center;margin-top:18px;font-size:14px;}
.links a{color:#0b69b7;text-decoration:none;font-weight:800;}
.links a:hover{text-decoration:underline;}
@media(max-width:1050px){body{display:block}.left{display:none}.right-wrap{width:100%;padding:24px}.right{max-width:680px;margin:auto;max-height:none}.grid-2{grid-template-columns:1fr 1fr}}
@media(max-width:650px){.right-wrap{padding:14px}.right{padding:26px 18px;border-radius:24px}.grid-2,.face-actions{grid-template-columns:1fr}.brand h2{font-size:24px}.lang-toggle{position:static;justify-content:center;margin-bottom:10px}}
</style>
</head>
<body>

<section class="left">
    <div class="badge"><i class="fa-solid fa-shield-heart"></i><span><?= ($lang === 'ar') ? 'تجربة تسجيل آمنة واحترافية' : 'A premium and secure registration experience' ?></span></div>
    <h1><?= ($lang === 'ar') ? 'ابدأ رحلتك الطبية بحساب موثوق وتجربة تسجيل مريحة' : 'Start your healthcare journey with a trusted account' ?></h1>
    <p class="lead"><?= ($lang === 'ar') ? 'أنشئ حسابك للوصول إلى خدمات المستشفى، إدارة المواعيد، ومراجعة السجل الطبي من خلال واجهة واضحة وآمنة.' : 'Create your account to access hospital services, manage appointments, review medical records, and use secure face recognition login.' ?></p>
    <div class="features">
        <div class="feature"><i class="fa-solid fa-calendar-check"></i><strong><?= ($lang === 'ar') ? 'إدارة المواعيد' : 'Appointments' ?></strong><p><?= ($lang === 'ar') ? 'احجز وتابع مواعيدك بسهولة.' : 'Book and manage your appointments easily.' ?></p></div>
        <div class="feature"><i class="fa-solid fa-file-medical"></i><strong><?= ($lang === 'ar') ? 'السجل الطبي' : 'Medical Records' ?></strong><p><?= ($lang === 'ar') ? 'وصول واضح ومنظم لبياناتك الطبية.' : 'Clear access to your medical information.' ?></p></div>
        <div class="feature"><i class="fa-solid fa-camera"></i><strong><?= ($lang === 'ar') ? 'مسح الوجه' : 'Face Recognition' ?></strong><p><?= ($lang === 'ar') ? 'تسجيل وجه موجه من عدة زوايا لتحسين الدقة.' : 'Guided multi-angle scan for better recognition.' ?></p></div>
        <div class="feature"><i class="fa-solid fa-lock"></i><strong><?= ($lang === 'ar') ? 'نظام آمن' : 'Secure System' ?></strong><p><?= ($lang === 'ar') ? 'تحقق من البيانات وحماية للحساب.' : 'Validated data and protected account access.' ?></p></div>
    </div>
</section>

<section class="right-wrap">
    <div class="right">
        <div class="lang-toggle">
            <a href="<?= htmlspecialchars(lang_link_register('en')) ?>" class="<?= ($lang === 'en') ? 'active' : '' ?>">EN</a>
            <a href="<?= htmlspecialchars(lang_link_register('ar')) ?>" class="<?= ($lang === 'ar') ? 'active' : '' ?>">AR</a>
        </div>

        <div class="brand">
            <img src="favicon.ico" alt="Cairo Hospitals" class="logo-img">
            <h2><?= ($lang === 'ar') ? 'مستشفيات القاهرة' : 'Cairo Hospitals' ?></h2>
            <p><?= ($lang === 'ar') ? 'أنشئ حسابك الطبي بأمان من خلال تجربة تسجيل حديثة.' : 'Create your secure hospital account in a clean registration experience.' ?></p>
        </div>

        <?php if(!empty($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if(!empty($success_notice)): ?><div class="notice"><?= htmlspecialchars($success_notice) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="registerForm">
            <div class="grid-2">
                <div class="form-group"><label class="label"><?= ($lang === 'ar') ? 'الاسم الأول' : 'First Name' ?></label><div class="input-box"><i class="fa-solid fa-user"></i><input type="text" name="first_name" required></div></div>
                <div class="form-group"><label class="label"><?= ($lang === 'ar') ? 'اسم العائلة' : 'Last Name' ?></label><div class="input-box"><i class="fa-solid fa-user"></i><input type="text" name="last_name" required></div></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['username']) ?></label><div class="input-box"><i class="fa-solid fa-user-check"></i><input type="text" name="username" required></div></div>
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['national_id']) ?></label><div class="input-box"><i class="fa-solid fa-id-card"></i><input type="text" name="national_id" maxlength="14" required></div></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['password']) ?></label><div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="password" id="password" required><span class="eye" onclick="togglePassword('password', this)"><i class="fa-regular fa-eye"></i></span></div></div>
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['confirm']) ?></label><div class="input-box"><i class="fa-solid fa-lock"></i><input type="password" name="confirm_password" id="confirm_password" required><span class="eye" onclick="togglePassword('confirm_password', this)"><i class="fa-regular fa-eye"></i></span></div></div>
            </div>

            <div class="form-group">
                <label class="label"><?= htmlspecialchars($t['nid_photo']) ?></label>
                <input class="file-input" type="file" name="nid_photo" accept="image/*" required>
                <div class="helper"><?= ($lang === 'ar') ? 'ارفعي صورة واضحة للبطاقة.' : 'Upload a clear image of your National ID.' ?></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['gender']) ?></label><div class="input-box"><i class="fa-solid fa-venus-mars"></i><select name="gender" required><option value=""><?= htmlspecialchars($t['select_gender']) ?></option><option value="Male"><?= ($lang === 'ar') ? 'ذكر' : 'Male' ?></option><option value="Female"><?= ($lang === 'ar') ? 'أنثى' : 'Female' ?></option></select></div></div>
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['governorate']) ?></label><div class="input-box"><i class="fa-solid fa-location-dot"></i><select name="governorate" required><option value=""><?= htmlspecialchars($t['select_governorate']) ?></option><option value="Cairo"><?= ($lang === 'ar') ? 'القاهرة' : 'Cairo' ?></option><option value="Giza"><?= ($lang === 'ar') ? 'الجيزة' : 'Giza' ?></option><option value="Alexandria"><?= ($lang === 'ar') ? 'الإسكندرية' : 'Alexandria' ?></option><option value="Qalyubia"><?= ($lang === 'ar') ? 'القليوبية' : 'Qalyubia' ?></option><option value="Dakahlia"><?= ($lang === 'ar') ? 'الدقهلية' : 'Dakahlia' ?></option></select></div></div>
            </div>

            <div class="grid-2">
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['birthdate']) ?></label><div class="input-box"><i class="fa-solid fa-calendar-days"></i><input type="date" name="birthdate" max="<?= date('Y-m-d') ?>" required></div></div>
                <div class="form-group"><label class="label"><?= htmlspecialchars($t['phone']) ?></label><div class="input-box"><i class="fa-solid fa-phone"></i><input type="text" name="contact_number" required></div></div>
            </div>

            <div class="form-group"><label class="label"><?= htmlspecialchars($t['email']) ?></label><div class="input-box"><i class="fa-solid fa-envelope"></i><input type="email" name="email" required></div></div>
            <div class="form-group"><label class="label"><?= htmlspecialchars($t['address']) ?></label><textarea class="textarea" name="address" required></textarea></div>

            <div class="form-group face-card">
                <label class="label"><?= htmlspecialchars($t['face_scan']) ?></label>
                <div class="face-actions">
                    <button type="button" class="face-btn" id="openFaceCameraBtn"><i class="fa-solid fa-video"></i> <?= htmlspecialchars($t['open_face_camera']) ?></button>
                    <button type="button" class="face-btn-soft" id="startFaceScanBtn" style="display:none;"><i class="fa-solid fa-camera"></i> <?= htmlspecialchars($t['start_face_scan']) ?></button>
                </div>
                <div class="helper"><?= ($lang === 'ar') ? 'ضعي الوجه داخل الإطار الأخضر. سيتم التقاط صورتين لكل زاوية: الأمام ثم اليمين ثم اليسار.' : 'Place your face inside the green frame. Two photos will be captured for each angle: front, then right, then left.' ?></div>
                <div class="face-box" id="faceBox"><video id="faceVideo" autoplay muted playsinline></video><canvas id="faceCanvas"></canvas><div class="face-guide"></div></div>
                <div class="angle-badges" id="angleBadges"><span class="angle-badge" data-angle="front"><?= htmlspecialchars($t['face_front']) ?></span><span class="angle-badge" data-angle="right"><?= htmlspecialchars($t['face_right']) ?></span><span class="angle-badge" data-angle="left"><?= htmlspecialchars($t['face_left']) ?></span></div>
                <div class="counter-box" id="faceCounter"></div>
                <div class="face-status" id="faceStatus"></div>
                <input type="hidden" name="face_images_json" id="face_images_json">
            </div>

            <button type="submit" class="btn"><i class="fa-solid fa-user-plus"></i> <?= htmlspecialchars($t['register']) ?></button>
            <div class="links"><a href="index.php"><?= htmlspecialchars($t['back']) ?></a></div>
        </form>
    </div>
</section>

<script>
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
function setActiveAngle(angleKey){angleBadges.forEach(b=>{b.classList.remove('active');if(b.dataset.angle===angleKey)b.classList.add('active');});}
function markAngleDone(angleKey){angleBadges.forEach(b=>{if(b.dataset.angle===angleKey){b.classList.remove('active');b.classList.add('done');}});}
function resetAngleBadges(){angleBadges.forEach(b=>b.classList.remove('active','done'));}
function sleep(ms){return new Promise(resolve=>setTimeout(resolve,ms));}
function captureFrame(angleKey){
    const ctx = faceCanvas.getContext('2d');
    ctx.drawImage(faceVideo,0,0,faceCanvas.width,faceCanvas.height);
    const imageData = faceCanvas.toDataURL('image/jpeg',0.9);
    capturedFaces.push({angle:angleKey,image:imageData});
    faceImagesInput.value = JSON.stringify(capturedFaces);
    faceCounter.textContent = '<?= addslashes($t['face_progress']) ?> ' + capturedFaces.length + '/' + TOTAL_REQUIRED;
}
async function countdownAndCapture(angleKey,angleLabel){
    for(let sec=3;sec>=1;sec--){faceStatus.textContent='<?= addslashes($t['face_step_move']) ?> '+angleLabel+' — '+sec;await sleep(900);}
    faceStatus.textContent='<?= addslashes($t['face_hold_still']) ?>: '+angleLabel;
    for(let i=0;i<PHOTOS_PER_ANGLE;i++){captureFrame(angleKey);await sleep(450);}
}
openFaceCameraBtn.addEventListener('click',async()=>{
    try{
        if(faceStream){faceStream.getTracks().forEach(track=>track.stop());faceStream=null;}
        capturedFaces=[];faceImagesInput.value='';faceStatus.textContent='';faceCounter.textContent='';resetAngleBadges();scanRunning=false;
        faceStream = await navigator.mediaDevices.getUserMedia({video:{width:640,height:480,facingMode:'user'},audio:false});
        faceVideo.srcObject=faceStream;faceBox.style.display='block';startFaceScanBtn.style.display='block';
        faceVideo.onloadedmetadata=async()=>{faceCanvas.width=faceVideo.videoWidth||640;faceCanvas.height=faceVideo.videoHeight||480;await faceVideo.play();faceStatus.textContent='<?= addslashes($t['face_scan_ready']) ?>';faceCounter.textContent='<?= addslashes($t['face_samples_target']) ?>';};
    }catch(err){faceStatus.textContent='<?= addslashes($t['face_camera_error']) ?>';console.error(err);}
});
startFaceScanBtn.addEventListener('click',async()=>{
    if(scanRunning)return;
    if(!faceStream || !faceVideo.videoWidth){faceStatus.textContent='<?= addslashes($t['camera_not_ready']) ?>';return;}
    scanRunning=true;capturedFaces=[];faceImagesInput.value='';faceCounter.textContent='0/'+TOTAL_REQUIRED;resetAngleBadges();
    for(const step of angleSteps){setActiveAngle(step.key);await sleep(800);await countdownAndCapture(step.key,step.label);markAngleDone(step.key);faceStatus.textContent=step.label+' - <?= addslashes($t['face_angle_done']) ?>';await sleep(800);}
    faceImagesInput.value=JSON.stringify(capturedFaces);faceCounter.textContent='<?= addslashes($t['face_progress']) ?> '+capturedFaces.length+'/'+TOTAL_REQUIRED;faceStatus.textContent='<?= addslashes($t['face_done']) ?>';
    if(faceStream){faceStream.getTracks().forEach(track=>track.stop());faceStream=null;}
    faceVideo.srcObject=null;faceBox.style.display='none';startFaceScanBtn.style.display='none';scanRunning=false;
});
document.getElementById('registerForm').addEventListener('submit',function(e){
    try{const parsed=JSON.parse(faceImagesInput.value||'[]');if(!Array.isArray(parsed)||parsed.length<TOTAL_REQUIRED){e.preventDefault();alert('<?= addslashes($t['face_required']) ?>');}}
    catch(err){e.preventDefault();alert('<?= addslashes($t['face_required']) ?>');}
});
function togglePassword(inputId,eyeElement){const input=document.getElementById(inputId);const icon=eyeElement.querySelector('i');if(input.type==='password'){input.type='text';icon.className='fa-regular fa-eye-slash';}else{input.type='password';icon.className='fa-regular fa-eye';}}
</script>
</body>
</html>
