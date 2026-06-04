<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

@set_time_limit(0);
@ini_set("max_execution_time", "0");
@ini_set("default_socket_timeout", "600");

// =========================
// AWS / Container URLs
// =========================
// Do NOT use localhost in AWS/ECS.
// The PHP container should call the chatbot through the deployed ALB route.
// You can override these from the ECS task definition environment variables.
$chatbotUrl = getenv("CHATBOT_API_URL") ?: "https://cairohospitals.click/chat/free";
$feedbackUrl = getenv("CHATBOT_FEEDBACK_URL") ?: "https://cairohospitals.click/chat/feedback";

// =========================
// Only POST Requests
// =========================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "error" => "POST only"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================
// Helpers
// =========================
function stable_chat_id(): string {
    $incoming_chat_id = trim($_POST["chat_id"] ?? "");

    if ($incoming_chat_id !== "") {
        // Sanitize browser/localStorage chat_id before forwarding it to Python.
        return preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $incoming_chat_id);
    }

    return "dash_" . ($_SESSION["user_id"] ?? session_id());
}

function has_uploaded_file_any(array $names): bool {
    foreach ($names as $key) {
        if (isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]["tmp_name"])) {
            return true;
        }
    }
    return false;
}

function attach_uploaded_file(array &$payloadArr, array $fieldNames, string $targetName): bool {
    foreach ($fieldNames as $key) {
        if (isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]["tmp_name"])) {
            $mime = $_FILES[$key]["type"] ?: "application/octet-stream";
            $name = $_FILES[$key]["name"] ?: $targetName;
            $payloadArr[$targetName] = new CURLFile($_FILES[$key]["tmp_name"], $mime, $name);
            return true;
        }
    }

    return false;
}

function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// =========================
// Feedback Proxy
// =========================
// The dashboard sends feedback_action=1 after each chatbot reply.
if (isset($_POST["feedback_action"]) && $_POST["feedback_action"] === "1") {
    $chat_id = stable_chat_id();

    $feedbackPayload = [
        "chat_id" => $chat_id,
        "user_message" => $_POST["user_message"] ?? "",
        "bot_reply" => $_POST["bot_reply"] ?? "",
        "rating" => $_POST["rating"] ?? "",
        "comment" => $_POST["comment"] ?? null,
        "intent" => $_POST["intent"] ?? null,
        "source" => "chatbot_ui"
    ];

    $ch = curl_init($feedbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($feedbackPayload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $response === null || $response === "") {
        json_response([
            "ok" => false,
            "error" => "Failed to connect to Python feedback API",
            "details" => $curlErr ?: "Empty response from Python feedback API",
            "feedback_url" => $feedbackUrl,
            "chat_id" => $chat_id
        ]);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        json_response([
            "ok" => false,
            "error" => "Python feedback API returned HTTP " . $httpCode,
            "raw" => $response,
            "feedback_url" => $feedbackUrl,
            "chat_id" => $chat_id
        ]);
    }

    echo $response;
    exit;
}

// =========================
// Main Chat Proxy
// =========================
$message = trim($_POST["message"] ?? $_POST["text"] ?? "");

$hasAttachment = has_uploaded_file_any(["attachment", "attachment_file", "prescription"]);
$hasAudio = has_uploaded_file_any(["audio", "voice", "audio_file"]);

if ($message === "" && !$hasAttachment && !$hasAudio) {
    json_response([
        "error" => "Empty message"
    ]);
}

// If dashboard or any page sends patient_id, store it in the session.
if (isset($_POST["patient_id"]) && $_POST["patient_id"] !== "") {
    $_SESSION["patient_id"] = (int)$_POST["patient_id"];
}

// If the user types only a number, treat it as PatientID and store it directly.
// This keeps your original patient_id shortcut.
if ($message !== "" && preg_match('/^\d{1,10}$/', $message) && !$hasAttachment && !$hasAudio) {
    $_SESSION["patient_id"] = (int)$message;

    echo json_encode([
        "intent" => "patient_id_set",
        "reply"  => "تم تسجيل PatientID = " . $_SESSION["patient_id"] . " ✅",
        "data"   => [
            "patient_id" => $_SESSION["patient_id"]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Session identity data
$username = $_SESSION["username"] ?? null;
$display_name = $_SESSION["first_name"] ?? ($_SESSION["name"] ?? null);
$chat_id = stable_chat_id();

$payloadArr = [
    // Send both keys to support both old and updated Python chatbot versions.
    "text" => $message,
    "message" => $message,
    "username" => $username,
    "display_name" => $display_name,
    "chat_id" => $chat_id
];

if (isset($_SESSION["patient_id"]) && $_SESSION["patient_id"] !== "") {
    $payloadArr["patient_id"] = (int)$_SESSION["patient_id"];
}

$hasMultipart = false;
$hasMultipart = attach_uploaded_file($payloadArr, ["attachment", "attachment_file", "prescription"], "attachment_file") || $hasMultipart;
$hasMultipart = attach_uploaded_file($payloadArr, ["audio", "voice", "audio_file"], "audio_file") || $hasMultipart;

$ch = curl_init($chatbotUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);

// If there is a file/audio, send multipart/form-data.
// If there is text only, send JSON to keep compatibility with your deployed chatbot.
if ($hasMultipart) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadArr);
} else {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadArr, JSON_UNESCAPED_UNICODE));
}

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $response === null || $response === "") {
    json_response([
        "error" => "Failed to connect to Python API",
        "details" => $curlErr ?: "Empty response from Python API",
        "chatbot_url" => $chatbotUrl,
        "chat_id" => $chat_id,
        "hint" => "In AWS, make sure CHATBOT_API_URL points to https://cairohospitals.click/chat/free and the /chat/* ALB rule targets the chatbot ECS service."
    ]);
}

if ($httpCode < 200 || $httpCode >= 300) {
    json_response([
        "error" => "Python API returned HTTP " . $httpCode,
        "raw" => $response,
        "chatbot_url" => $chatbotUrl,
        "chat_id" => $chat_id
    ]);
}

$decoded = json_decode($response, true);

// If backend returns patient_id_set, keep it in the PHP session.
if (is_array($decoded) && (($decoded["intent"] ?? "") === "patient_id_set")) {
    $pid = $decoded["data"]["patient_id"] ?? null;
    if ($pid !== null && $pid !== "") {
        $_SESSION["patient_id"] = (int)$pid;
    }
}

echo $response;
?>