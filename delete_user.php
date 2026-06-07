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
    Full user deletion flow:

    1) Read user data from registration first.
    2) Resolve PatientID from patients table using NationalID.
    3) Delete face data from:
       - S3 face images
       - representations.pkl
       through face-api /face/delete_user.
    4) Delete all related user information from RDS tables.
    5) Delete local uploaded national ID image if it exists in uploads/.

    IMPORTANT for AWS:
    In ECS web task -> php container, add:
    FACE_DELETE_URL=https://cairohospitals.click/face/delete_user

    Locally Docker Compose can use:
    FACE_DELETE_URL=http://face-api:5001/face/delete_user

    The face-api container/task must have S3 delete permission.
*/

function redirect_with_message($message, $type = 'success') {
    header("Location: manage_users.php?msg=" . urlencode($message) . "&type=" . urlencode($type));
    exit();
}

function table_exists($conn, $tableName) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $tableName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return intval($row['c'] ?? 0) > 0;
}

function column_exists($conn, $tableName, $columnName) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    return intval($row['c'] ?? 0) > 0;
}

function safe_delete_by_column($conn, $tableName, $columnName, $value) {
    if ($value === null || $value === '') {
        return 0;
    }

    if (!table_exists($conn, $tableName) || !column_exists($conn, $tableName, $columnName)) {
        return 0;
    }

    $sql = "DELETE FROM `$tableName` WHERE `$columnName` = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare delete failed for {$tableName}.{$columnName}: " . $conn->error);
    }

    $stmt->bind_param("s", $value);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return max(0, intval($affected));
}

function safe_delete_by_int_column($conn, $tableName, $columnName, $value) {
    if ($value === null || $value === '' || intval($value) <= 0) {
        return 0;
    }

    if (!table_exists($conn, $tableName) || !column_exists($conn, $tableName, $columnName)) {
        return 0;
    }

    $sql = "DELETE FROM `$tableName` WHERE `$columnName` = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare delete failed for {$tableName}.{$columnName}: " . $conn->error);
    }

    $v = intval($value);
    $stmt->bind_param("i", $v);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return max(0, intval($affected));
}

function call_face_delete_api($username, $patientId = null, $nationalId = null) {
    $faceDeleteUrl = getenv('FACE_DELETE_URL') ?: 'http://face-api:5001/face/delete_user';

    $payload = [
        'username' => $username
    ];

    if ($patientId !== null && $patientId !== '') {
        $payload['patient_id'] = $patientId;
    }

    if ($nationalId !== null && $nationalId !== '') {
        $payload['national_id'] = $nationalId;
    }

    $ch = curl_init($faceDeleteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError) {
        return [
            'ok' => false,
            'error' => 'Face delete API curl error: ' . $curlError
        ];
    }

    $json = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => false,
            'error' => 'Face delete API failed. HTTP ' . $httpCode . ' Response: ' . $response
        ];
    }

    if (!is_array($json)) {
        return [
            'ok' => false,
            'error' => 'Face delete API returned invalid JSON. Response: ' . $response
        ];
    }

    if (empty($json['success'])) {
        return [
            'ok' => false,
            'error' => 'Face delete API did not confirm success. Response: ' . $response
        ];
    }

    return [
        'ok' => true,
        'data' => $json
    ];
}

function delete_local_upload_file($relativePath) {
    if (empty($relativePath)) {
        return false;
    }

    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
    $uploadsDir = realpath(__DIR__ . '/uploads');
    $realFile = realpath($fullPath);

    if ($uploadsDir && $realFile && str_starts_with($realFile, $uploadsDir) && is_file($realFile)) {
        return @unlink($realFile);
    }

    return false;
}

/* ---------------------------------------------------------
   1) Get user data before deleting anything
--------------------------------------------------------- */

$stmt = $conn->prepare("
    SELECT id, username, national_id, national_id_photo
    FROM registration
    WHERE id = ?
    LIMIT 1
");

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

$username = trim($user['username'] ?? '');
$nationalId = trim($user['national_id'] ?? '');
$nidPhotoPath = $user['national_id_photo'] ?? '';

if ($username === '' && $nationalId === '') {
    redirect_with_message('Cannot delete user because username and national ID are missing.', 'error');
}

/* ---------------------------------------------------------
   2) Resolve PatientID from patients table
--------------------------------------------------------- */

$patientId = null;

if ($nationalId !== '' && table_exists($conn, 'patients') && column_exists($conn, 'patients', 'NationalID')) {
    $stmt = $conn->prepare("SELECT PatientID FROM patients WHERE NationalID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $nationalId);
        $stmt->execute();
        $res = $stmt->get_result();
        $patient = $res->fetch_assoc();
        $stmt->close();

        if ($patient && isset($patient['PatientID'])) {
            $patientId = intval($patient['PatientID']);
        }
    }
}

/* ---------------------------------------------------------
   3) Delete face data from S3 + representations.pkl first
--------------------------------------------------------- */

$faceResult = call_face_delete_api($username, $patientId, $nationalId);

if (!$faceResult['ok']) {
    redirect_with_message($faceResult['error'], 'error');
}

/* ---------------------------------------------------------
   4) Delete all user-related data from RDS
--------------------------------------------------------- */

$deletedCounts = [];

$conn->begin_transaction();

try {
    /*
        Delete child/dependent rows first to avoid foreign key issues.
        These calls are safe:
        - If the table does not exist, it skips.
        - If the column does not exist, it skips.
    */

    // Appointment / booking related tables
    $deletedCounts['appointments_by_patient'] = safe_delete_by_int_column($conn, 'appointments', 'PatientID', $patientId);
    $deletedCounts['appointments_by_username'] = safe_delete_by_column($conn, 'appointments', 'patient_username', $username);

    $deletedCounts['bookings_by_patient'] = safe_delete_by_int_column($conn, 'bookings', 'PatientID', $patientId);
    $deletedCounts['bookings_by_username'] = safe_delete_by_column($conn, 'bookings', 'patient_username', $username);

    // Patient medical data
    $deletedCounts['patient_history_by_username'] = safe_delete_by_column($conn, 'patient_history', 'patient_username', $username);
    $deletedCounts['patient_history_by_patient'] = safe_delete_by_int_column($conn, 'patient_history', 'PatientID', $patientId);

    $deletedCounts['medical_records'] = safe_delete_by_int_column($conn, 'medical_records', 'PatientID', $patientId);
    $deletedCounts['prescriptions'] = safe_delete_by_int_column($conn, 'prescriptions', 'PatientID', $patientId);
    $deletedCounts['lab_results'] = safe_delete_by_int_column($conn, 'lab_results', 'PatientID', $patientId);

    // Emergency / reports / messages if your DB has them
    $deletedCounts['emergency_by_patient'] = safe_delete_by_int_column($conn, 'emergency', 'PatientID', $patientId);
    $deletedCounts['emergency_by_username'] = safe_delete_by_column($conn, 'emergency', 'patient_username', $username);

    $deletedCounts['reports_by_patient'] = safe_delete_by_int_column($conn, 'reports', 'PatientID', $patientId);
    $deletedCounts['reports_by_username'] = safe_delete_by_column($conn, 'reports', 'patient_username', $username);

    $deletedCounts['messages_by_sender'] = safe_delete_by_column($conn, 'messages', 'sender_username', $username);
    $deletedCounts['messages_by_receiver'] = safe_delete_by_column($conn, 'messages', 'receiver_username', $username);

    // Pending registrations with same username / national id
    $deletedCounts['pending_registrations_by_username'] = safe_delete_by_column($conn, 'pending_registrations', 'username', $username);
    $deletedCounts['pending_registrations_by_national_id'] = safe_delete_by_column($conn, 'pending_registrations', 'national_id', $nationalId);

    // Main patient table
    $deletedCounts['patients_by_patient_id'] = safe_delete_by_int_column($conn, 'patients', 'PatientID', $patientId);
    $deletedCounts['patients_by_national_id'] = safe_delete_by_column($conn, 'patients', 'NationalID', $nationalId);

    // Login table
    $deletedCounts['login_by_username'] = safe_delete_by_column($conn, 'login', 'username', $username);
    $deletedCounts['login_by_national_id'] = safe_delete_by_column($conn, 'login', 'national_id', $nationalId);

    // Registration table
    $stmt = $conn->prepare("DELETE FROM registration WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare registration delete failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $deletedCounts['registration_by_id'] = max(0, intval($stmt->affected_rows));
    $stmt->close();

    // Extra safety: delete registration rows with same username / national_id if duplicated
    $deletedCounts['registration_by_username'] = safe_delete_by_column($conn, 'registration', 'username', $username);
    $deletedCounts['registration_by_national_id'] = safe_delete_by_column($conn, 'registration', 'national_id', $nationalId);

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    redirect_with_message('RDS delete failed: ' . $e->getMessage(), 'error');
}

/* ---------------------------------------------------------
   5) Delete local uploaded national ID photo after DB commit
--------------------------------------------------------- */

$localPhotoDeleted = delete_local_upload_file($nidPhotoPath);

/* ---------------------------------------------------------
   6) Build success message
--------------------------------------------------------- */

$removedEmbeddings = intval($faceResult['data']['representations_removed'] ?? 0);

$s3Deleted = 0;
$s3Deleted += intval($faceResult['data']['images_deleted_from_pkl_keys'] ?? 0);
$s3Deleted += intval($faceResult['data']['images_deleted_by_prefix'] ?? 0);
$s3Deleted += intval($faceResult['data']['s3_deleted_count'] ?? 0);

if (isset($faceResult['data']['deleted_s3_keys']) && is_array($faceResult['data']['deleted_s3_keys'])) {
    $s3Deleted = max($s3Deleted, count($faceResult['data']['deleted_s3_keys']));
}

$totalDbDeleted = array_sum($deletedCounts);

$message = "User deleted successfully. ";
$message .= "DB rows deleted: {$totalDbDeleted}. ";
$message .= "Face embeddings removed: {$removedEmbeddings}. ";
$message .= "S3 images deleted: {$s3Deleted}. ";

if ($localPhotoDeleted) {
    $message .= "Local national ID photo deleted.";
} else {
    $message .= "No local national ID photo deleted.";
}

redirect_with_message($message, 'success');
?>