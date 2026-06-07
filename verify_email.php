<?php
session_start();
include('db_connect.php');

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid verification link.");
}

/*
    Correct verification flow:
    - Pending data stays in pending_registrations.
    - Real account is created only after email verification.
    - If verification expires, show a page with:
        1) Send another verification email
        2) Back to Register
    - Do not redirect to register.php?error=verification_expired
*/

function app_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    return $scheme . '://' . $host . ($dir ? $dir : '');
}

function show_message_page($title, $message, $type = 'info', $showButtons = true, $token = '') {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

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
            button, a.btn {
                border: none;
                text-decoration: none;
                padding: 13px 20px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
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
            <div class='icon'>!</div>
            <h2>{$safeTitle}</h2>
            <p>{$safeMessage}</p>
    ";

    if ($showButtons) {
        echo "
            <div class='actions'>
                <form method='POST' action='resend_verification.php' style='margin:0;'>
                    <input type='hidden' name='old_token' value='{$safeToken}'>
                    <button type='submit' class='primary'>Send another verification email</button>
                </form>

                <a href='register.php' class='btn secondary'>Back to Register</a>
            </div>
        ";
    }

    echo "
        </div>
    </body>
    </html>
    ";
    exit();
}

function expire_pending_token($conn, $token) {
    $stmt = $conn->prepare("
        UPDATE pending_registrations
        SET status = 'expired'
        WHERE token = ?
    ");

    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->close();
    }
}

function mark_pending_verified($conn, $token) {
    $stmt = $conn->prepare("
        UPDATE pending_registrations
        SET status = 'verified'
        WHERE token = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare pending verification update failed: " . $conn->error);
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->close();
}

function table_has_existing_national_id($conn, $national_id) {
    $stmt = $conn->prepare("
        SELECT national_id FROM (
            SELECT national_id FROM registration WHERE national_id = ?
            UNION
            SELECT national_id FROM login WHERE national_id = ?
            UNION
            SELECT NationalID AS national_id FROM patients WHERE NationalID = ?
        ) AS existing_accounts
        LIMIT 1
    ");

    if (!$stmt) {
        return ['ok' => false, 'exists' => false, 'error' => $conn->error];
    }

    $stmt->bind_param("sss", $national_id, $national_id, $national_id);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return ['ok' => true, 'exists' => $exists, 'error' => null];
}

function call_face_register_api($username, $face_images_json) {
    $decodedFaceImages = json_decode($face_images_json, true);

    if (!is_array($decodedFaceImages)) {
        return [
            'ok' => false,
            'error' => "Invalid face data."
        ];
    }

    $faceImagesForApi = [];

    foreach ($decodedFaceImages as $imgItem) {
        if (is_array($imgItem) && !empty($imgItem['image'])) {
            $faceImagesForApi[] = $imgItem['image'];
        } elseif (is_string($imgItem) && !empty($imgItem)) {
            $faceImagesForApi[] = $imgItem;
        }
    }

    if (count($faceImagesForApi) < 6) {
        return [
            'ok' => false,
            'error' => "Face scan data is incomplete."
        ];
    }

    $faceApiUrl = getenv('FACE_REGISTER_URL') ?: 'https://cairohospitals.click/face/register_face';

    $payload = json_encode([
        'username' => $username,
        'images' => $faceImagesForApi
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($faceApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return [
            'ok' => false,
            'error' => "Face API curl error: " . $curlError
        ];
    }

    $faceResult = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300 || !is_array($faceResult) || empty($faceResult['success'])) {
        return [
            'ok' => false,
            'error' => "Face registration failed. HTTP {$httpCode}. Response: " . $response
        ];
    }

    $saved = intval($faceResult['saved'] ?? 0);

    if ($saved < 6) {
        return [
            'ok' => false,
            'error' => "Face registration incomplete. Saved images: " . $saved
        ];
    }

    return [
        'ok' => true,
        'data' => $faceResult
    ];
}

/* ---------------------------------------------------------
   1) Get pending registration by token
--------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT *
    FROM pending_registrations
    WHERE token = ?
    LIMIT 1
");

if (!$stmt) {
    show_message_page(
        "Database Error",
        "Could not load verification request. Please try again.",
        "error",
        true,
        $token
    );
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    show_message_page(
        "Verification link not found",
        "This verification link is invalid or no longer exists.",
        "error",
        true,
        $token
    );
}

$data = $result->fetch_assoc();
$stmt->close();

/* ---------------------------------------------------------
   2) Validate pending status
--------------------------------------------------------- */

if (($data['status'] ?? '') === 'verified') {
    header("Location: index.php?verified=1");
    exit();
}

if (($data['status'] ?? '') === 'expired') {
    show_message_page(
        "Verification time expired",
        "Your verification link has expired. You can send another verification email or go back to register.",
        "warning",
        true,
        $token
    );
}

if (strtotime($data['expires_at']) < time()) {
    expire_pending_token($conn, $token);

    show_message_page(
        "Verification time expired",
        "Your verification link expired after 3 minutes. You can send another verification email or go back to register.",
        "warning",
        true,
        $token
    );
}

$username = trim($data['username'] ?? '');
$national_id = trim($data['national_id'] ?? '');

if ($username === '' || $national_id === '') {
    show_message_page(
        "Verification failed",
        "The saved registration data is incomplete. Please go back to register.",
        "error",
        true,
        $token
    );
}

/* ---------------------------------------------------------
   3) Check duplicate National ID before inserting final data
--------------------------------------------------------- */

$check = table_has_existing_national_id($conn, $national_id);

if (!$check['ok']) {
    show_message_page(
        "Database Error",
        "Could not check existing account. Please try again.",
        "error",
        true,
        $token
    );
}

if ($check['exists']) {
    expire_pending_token($conn, $token);

    show_message_page(
        "National ID already exists",
        "This National ID is already registered. Please go back to register or login.",
        "error",
        true,
        $token
    );
}

/* ---------------------------------------------------------
   4) Register face AFTER email verification only
--------------------------------------------------------- */

$face_images_json = $data['face_images_json'] ?? '';

$faceApiResult = call_face_register_api($username, $face_images_json);

if (!$faceApiResult['ok']) {
    show_message_page(
        "Face registration failed",
        $faceApiResult['error'],
        "error",
        true,
        $token
    );
}

$faceResult = $faceApiResult['data'];

$face_samples_count = intval($faceResult['saved'] ?? 0);
$face_relative_path = $faceResult['first_image_key'] ?? ("faces/" . $username);

/* ---------------------------------------------------------
   5) Insert final user data into real tables
--------------------------------------------------------- */

$conn->begin_transaction();

try {
    $role = 'patient';

    $stmt_reg = $conn->prepare("
        INSERT INTO registration
        (
            first_name,
            last_name,
            username,
            password,
            national_id,
            national_id_photo,
            face_image_path,
            face_samples_count,
            gender,
            government,
            birthdate,
            address,
            email,
            phone_number
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt_reg) {
        throw new Exception("Prepare registration insert failed: " . $conn->error);
    }

    $stmt_reg->bind_param(
        "sssssssissssss",
        $data['first_name'],
        $data['last_name'],
        $data['username'],
        $data['password_hash'],
        $data['national_id'],
        $data['national_id_photo'],
        $face_relative_path,
        $face_samples_count,
        $data['gender'],
        $data['government'],
        $data['birthdate'],
        $data['address'],
        $data['email'],
        $data['phone_number']
    );

    $stmt_reg->execute();
    $stmt_reg->close();

    $stmt_login = $conn->prepare("
        INSERT INTO login
        (
            username,
            password,
            national_id,
            role,
            face_image_path,
            face_samples_count
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt_login) {
        throw new Exception("Prepare login insert failed: " . $conn->error);
    }

    $stmt_login->bind_param(
        "sssssi",
        $data['username'],
        $data['password_hash'],
        $data['national_id'],
        $role,
        $face_relative_path,
        $face_samples_count
    );

    $stmt_login->execute();
    $stmt_login->close();

    $bloodType = null;

    $stmt_patient = $conn->prepare("
        INSERT INTO patients
        (
            NationalID,
            FirstName,
            LastName,
            DOB,
            Gender,
            BloodType,
            ContactPhone,
            Email,
            Address
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt_patient) {
        throw new Exception("Prepare patient insert failed: " . $conn->error);
    }

    $stmt_patient->bind_param(
        "sssssssss",
        $data['national_id'],
        $data['first_name'],
        $data['last_name'],
        $data['birthdate'],
        $data['gender'],
        $bloodType,
        $data['phone_number'],
        $data['email'],
        $data['address']
    );

    $stmt_patient->execute();
    $stmt_patient->close();

    $visit_date = date('Y-m-d H:i:s');
    $doctor_name = "System";
    $diagnosis = "New patient registration";
    $treatment = "Initial account created after email verification.";

    $stmt_history = $conn->prepare("
        INSERT INTO patient_history
        (
            patient_username,
            visit_date,
            doctor_name,
            diagnosis,
            treatment
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt_history) {
        throw new Exception("Prepare patient history insert failed: " . $conn->error);
    }

    $stmt_history->bind_param(
        "sssss",
        $data['username'],
        $visit_date,
        $doctor_name,
        $diagnosis,
        $treatment
    );

    $stmt_history->execute();
    $stmt_history->close();

    mark_pending_verified($conn, $token);

    $conn->commit();

    header("Location: index.php?verified=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();

    show_message_page(
        "Verification failed",
        "Could not complete registration. Please send another verification email or go back to register.",
        "error",
        true,
        $token
    );
}
?>