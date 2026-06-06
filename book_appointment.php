<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "booked" => false,
        "message" => "Not logged in"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$raw = file_get_contents("php://input");
$body = json_decode($raw, true);

$doctor_id = isset($body["doctor_id"]) ? (int)$body["doctor_id"] : 0;
$branch_id = isset($body["branch_id"]) ? (int)$body["branch_id"] : 0;
$start     = trim($body["start"] ?? "");

if ($doctor_id <= 0 || $branch_id <= 0 || $start === "") {
    http_response_code(400);
    echo json_encode([
        "booked" => false,
        "message" => "Missing fields",
        "debug" => [
            "doctor_id" => $doctor_id,
            "branch_id" => $branch_id,
            "start" => $start
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}
$login_id = (int)$_SESSION['user_id'];
$patient_id = 0;
$national_id = "";

/* =========================
   Get national_id from login
========================= */
$stmt = $conn->prepare("SELECT national_id FROM login WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $login_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $national_id = $row["national_id"] ?? "";
    }

    $stmt->close();
}

/* =========================
   Get PatientID from patients using NationalID
========================= */
if (!empty($national_id)) {
    $stmt2 = $conn->prepare("SELECT PatientID FROM patients WHERE NationalID = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("s", $national_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($row2 = $res2->fetch_assoc()) {
            $patient_id = (int)$row2["PatientID"];
        }

        $stmt2->close();
    }
}
/* =========================
   Fallback from patients table using NationalID
========================= */
if ($patient_id <= 0 && !empty($national_id)) {
    $stmt2 = $conn->prepare("SELECT PatientID FROM patients WHERE NationalID = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("s", $national_id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($row2 = $res2->fetch_assoc()) {
            $patient_id = (int)$row2["PatientID"];
        }

        $stmt2->close();
    }
}

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "booked" => false,
        "message" => "No PatientID linked to this account",
        "debug" => [
            "login_id" => $login_id,
            "national_id" => $national_id
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// keep correct patient_id in session
$_SESSION["patient_id"] = $patient_id;

$payloadArr = [
    "patient_id" => $patient_id,
    "doctor_id"  => $doctor_id,
    "branch_id"  => $branch_id,
    "start"      => $start
];

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

$bookUrl = getenv("CHATBOT_BOOK_URL") ?: "https://cairohospitals.click/chat/book";

$ch = curl_init($bookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);

http_response_code($http ?: 500);
echo json_encode([
    "booked" => false,
    "message" => "FastAPI booking failed",
    "book_url" => $bookUrl,
    "http_code" => $http,
    "fastapi_response" => $decoded ?: $response,
    "debug" => [
        "patient_id" => $patient_id,
        "doctor_id" => $doctor_id,
        "branch_id" => $branch_id,
        "start" => $start
    ]
], JSON_UNESCAPED_UNICODE);
    exit();
}

$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($response, true);

if ($http >= 200 && $http < 300) {
    echo json_encode([
        "booked" => $decoded["booked"] ?? true,
        "message" => $decoded["message"] ?? ($decoded["reply"] ?? "Appointment booked successfully."),
        "fastapi_response" => $decoded,
        "debug" => [
            "patient_id" => $patient_id,
            "doctor_id" => $doctor_id,
            "branch_id" => $branch_id,
            "start" => $start
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code($http ?: 500);
echo json_encode([
    "booked" => false,
    "message" => "FastAPI booking failed",
    "fastapi_response" => $decoded ?: $response,
    "debug" => [
        "patient_id" => $patient_id,
        "doctor_id" => $doctor_id,
        "branch_id" => $branch_id,
        "start" => $start
    ]
], JSON_UNESCAPED_UNICODE);