<?php
include('db_connect.php');
header('Content-Type: application/json');

$token = $_GET['token'] ?? '';

if (!$token) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

$stmt = $conn->prepare("SELECT status, expires_at FROM pending_registrations WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

$row = $result->fetch_assoc();

if ($row['status'] === 'verified') {
    echo json_encode(['status' => 'verified']);
    exit;
}

if (strtotime($row['expires_at']) < time()) {
    $update = $conn->prepare("UPDATE pending_registrations SET status='expired' WHERE token=?");
    $update->bind_param("s", $token);
    $update->execute();

    echo json_encode(['status' => 'expired']);
    exit;
}

echo json_encode(['status' => 'pending']);