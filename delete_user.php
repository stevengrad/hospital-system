<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_users.php?msg=Invalid user ID&type=error");
    exit();
}

/*
    This file deletes a patient from:
    1) S3 face images through face-api /face/delete_user
    2) representations.pkl through face-api
    3) RDS tables: patient_history, patients, login, registration

    Important:
    - The face-api container must be running.
    - The face-api task/container must have permission to delete from the S3 bucket.
*/

function redirect_with_message($message, $type = 'success') {
    header("Location: manage_users.php?msg=" . urlencode($message) . "&type=" . urlencode($type));
    exit();
}

function call_face_delete_api($username, $patientId = null) {
    $faceDeleteUrl = getenv('FACE_DELETE_URL') ?: 'http://face-api:5001/face/delete_user';

    $payload = [
        'username' => $username
    ];

    if ($patientId !== null && $patientId !== '') {
        $payload['patient_id'] = $patientId;
    }

    $ch = curl_init($faceDeleteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'error' => 'Face delete API curl error: ' . $curlError];
    }

    $json = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300 || empty($json['success'])) {
        return [
            'ok' => false,
            'error' => 'Face delete API failed. HTTP ' . $httpCode . ' Response: ' . $response
        ];
    }

    return ['ok' => true, 'data' => $json];
}

/* Get user data first before deleting it from RDS */
$stmt = $conn->prepare("SELECT id, username, national_id, national_id_photo FROM registration WHERE id = ? LIMIT 1");
if (!$stmt) {
    redirect_with_message('Database prepare error: ' . $conn->error, 'error');
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    redirect_with_message('User not found', 'error');
}

$username = $user['username'];
$nationalId = $user['national_id'];
$nidPhotoPath = $user['national_id_photo'] ?? '';

/* 1) Delete face data from S3 + representations.pkl first */
$faceResult = call_face_delete_api($username, $id);
if (!$faceResult['ok']) {
    redirect_with_message($faceResult['error'], 'error');
}

/* 2) Delete user data from RDS in a transaction */
$conn->begin_transaction();

try {
    // patient_history was inserted by patient_username in register.php
    $stmt = $conn->prepare("DELETE FROM patient_history WHERE patient_username = ?");
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
    }

    // patients table was inserted by NationalID in register.php
    $stmt = $conn->prepare("DELETE FROM patients WHERE NationalID = ?");
    if ($stmt) {
        $stmt->bind_param("s", $nationalId);
        $stmt->execute();
        $stmt->close();
    }

    // login table was inserted by username + national_id in register.php
    $stmt = $conn->prepare("DELETE FROM login WHERE username = ? OR national_id = ?");
    if (!$stmt) {
        throw new Exception('Prepare login delete failed: ' . $conn->error);
    }
    $stmt->bind_param("ss", $username, $nationalId);
    $stmt->execute();
    $stmt->close();

    // registration main row
    $stmt = $conn->prepare("DELETE FROM registration WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare registration delete failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Optional: remove local national ID image if it is stored in uploads/
    if (!empty($nidPhotoPath)) {
        $fullPath = __DIR__ . '/' . ltrim($nidPhotoPath, '/');
        $uploadsDir = realpath(__DIR__ . '/uploads');
        $realFile = realpath($fullPath);

        if ($uploadsDir && $realFile && str_starts_with($realFile, $uploadsDir) && is_file($realFile)) {
            @unlink($realFile);
        }
    }

    $removed = intval($faceResult['data']['representations_removed'] ?? 0);
    $deletedImages = intval($faceResult['data']['images_deleted_from_pkl_keys'] ?? 0) + intval($faceResult['data']['images_deleted_by_prefix'] ?? 0);

    redirect_with_message("User deleted successfully. Face records removed: {$removed}. S3 images deleted: {$deletedImages}.", 'success');

} catch (Exception $e) {
    $conn->rollback();
    redirect_with_message('RDS delete failed: ' . $e->getMessage(), 'error');
}
?>
