<?php
session_start();
include('db_connect.php');

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid verification link.");
}

$stmt = $conn->prepare("SELECT * FROM pending_registrations WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Verification link not found.");
}

$data = $result->fetch_assoc();

if ($data['status'] === 'verified') {
    header("Location:index.php?verified=1");
    exit();
}

if (strtotime($data['expires_at']) < time()) {
    $update = $conn->prepare("UPDATE pending_registrations SET status='expired' WHERE token=?");
    $update->bind_param("s", $token);
    $update->execute();

    die("Verification link expired. Please register again.");
}

$username = $data['username'];
$face_images_json = $data['face_images_json'];

$decodedFaceImages = json_decode($face_images_json, true);
if (!is_array($decodedFaceImages)) {
    die("Invalid face data.");
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
    die("Face scan data is incomplete.");
}

$faceApiUrl = getenv('FACE_REGISTER_URL') ?: 'https://cairohospitals.click/face/register_face';

$payload = json_encode([
    'username' => $username,
    'images' => $faceImagesForApi
]);

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

$faceResult = json_decode($response, true);

if ($curlError || $httpCode < 200 || $httpCode >= 300 || empty($faceResult['success'])) {
    die("Face registration failed. Please try again.");
}

$face_samples_count = intval($faceResult['saved'] ?? 0);

if ($face_samples_count < 6) {
    die("Face registration incomplete. Saved images: " . $face_samples_count);
}

$face_relative_path = $faceResult['first_image_key'] ?? ("face-api/" . $username);

$conn->begin_transaction();

try {
    $role = 'patient';

    $stmt_reg = $conn->prepare("
        INSERT INTO registration
        (first_name, last_name, username, password, national_id, national_id_photo, face_image_path, face_samples_count, gender, government, birthdate, address, email, phone_number)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

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

    $stmt_login = $conn->prepare("
        INSERT INTO login (username, password, national_id, role, face_image_path, face_samples_count)
        VALUES (?,?,?,?,?,?)
    ");

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

    $bloodType = NULL;

    $stmt_patient = $conn->prepare("
        INSERT INTO patients
        (NationalID, FirstName, LastName, DOB, Gender, BloodType, ContactPhone, Email, Address)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

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

    $visit_date = date('Y-m-d H:i:s');
    $doctor_name = "System";
    $diagnosis = "New patient registration";
    $treatment = "Initial account created after email verification.";

    $stmt_history = $conn->prepare("
        INSERT INTO patient_history
        (patient_username, visit_date, doctor_name, diagnosis, treatment)
        VALUES (?,?,?,?,?)
    ");

    $stmt_history->bind_param(
        "sssss",
        $data['username'],
        $visit_date,
        $doctor_name,
        $diagnosis,
        $treatment
    );

    $stmt_history->execute();

    $update = $conn->prepare("UPDATE pending_registrations SET status='verified' WHERE token=?");
    $update->bind_param("s", $token);
    $update->execute();

    $conn->commit();

    header("Location:index.php?verified=1");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Registration failed: " . $e->getMessage());
}