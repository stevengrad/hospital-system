<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

@set_time_limit(0);
@ini_set("max_execution_time", "0");
@ini_set("default_socket_timeout", "600");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "POST only"], JSON_UNESCAPED_UNICODE);
    exit;
}


// Feedback proxy: the UI sends rating after each bot reply.
if (isset($_POST["feedback_action"]) && $_POST["feedback_action"] === "1") {
    $incoming_chat_id = trim($_POST["chat_id"] ?? "");
    if ($incoming_chat_id !== "") {
        $chat_id = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $incoming_chat_id);
    } else {
        $chat_id = "dash_" . ($_SESSION["user_id"] ?? session_id());
    }

    $feedbackPayload = [
        "chat_id" => $chat_id,
        "user_message" => $_POST["user_message"] ?? "",
        "bot_reply" => $_POST["bot_reply"] ?? "",
        "rating" => $_POST["rating"] ?? "",
        "comment" => $_POST["comment"] ?? null,
        "intent" => $_POST["intent"] ?? null,
        "source" => "chatbot_ui"
    ];

    $feedbackUrl = getenv("CHATBOT_FEEDBACK_URL") ?: "https://cairohospitals.click/chat/feedback";
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
        echo json_encode([
            "ok" => false,
            "error" => "Failed to connect to Python feedback API",
            "details" => $curlErr ?: "Empty response from Python feedback API"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        echo json_encode([
            "ok" => false,
            "error" => "Python feedback API returned HTTP " . $httpCode,
            "raw" => $response
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo $response;
    exit;
}

$message = trim($_POST["message"] ?? $_POST["text"] ?? "");

function has_uploaded_file_any($names) {
    foreach ($names as $key) {
        if (isset($_FILES[$key]) && is_uploaded_file($_FILES[$key]["tmp_name"])) {
            return true;
        }
    }
    return false;
}

$hasAttachment = has_uploaded_file_any(["attachment", "attachment_file", "prescription"]);
$hasAudio = has_uploaded_file_any(["audio", "voice", "audio_file"]);

if ($message === "" && !$hasAttachment && !$hasAudio) {
    echo json_encode(["error" => "Empty message"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_POST["patient_id"]) && $_POST["patient_id"] !== "") {
    $_SESSION["patient_id"] = (int)$_POST["patient_id"];
}

$username = $_SESSION["username"] ?? null;
$display_name = $_SESSION["first_name"] ?? ($_SESSION["name"] ?? null);

// IMPORTANT: chat_id must be stable between messages.
// Prefer browser localStorage chat_id, fallback to PHP session id.
$incoming_chat_id = trim($_POST["chat_id"] ?? "");
if ($incoming_chat_id !== "") {
    $chat_id = preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', $incoming_chat_id);
} else {
    $chat_id = "dash_" . ($_SESSION["user_id"] ?? session_id());
}

$payloadArr = [
    "text" => $message,
    "message" => $message,
    "username" => $username,
    "display_name" => $display_name,
    "chat_id" => $chat_id,
];

if (isset($_SESSION["patient_id"]) && $_SESSION["patient_id"] !== "") {
    $payloadArr["patient_id"] = (int)$_SESSION["patient_id"];
}

function attach_uploaded_file(&$payloadArr, $fieldNames, $targetName) {
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

attach_uploaded_file($payloadArr, ["attachment", "attachment_file", "prescription"], "attachment_file");
attach_uploaded_file($payloadArr, ["audio", "voice", "audio_file"], "audio_file");

$pythonUrl = getenv("CHATBOT_API_URL") ?: "https://cairohospitals.click/chat/free";
$ch = curl_init($pythonUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadArr);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 600);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $response === null || $response === "") {
    echo json_encode([
        "error" => "Failed to connect to Python API",
        "details" => $curlErr ?: "Empty response from Python API",
        "python_url" => $pythonUrl,
        "chat_id" => $chat_id,
        "hint" => "In AWS, make sure CHATBOT_API_URL points to https://cairohospitals.click/chat/free and /chat/* ALB rule targets the chatbot service."    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        "error" => "Python API returned HTTP " . $httpCode,
        "raw" => $response,
        "python_url" => $pythonUrl,
        "chat_id" => $chat_id
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);
if (is_array($decoded) && (($decoded["intent"] ?? "") === "patient_id_set")) {
    $pid = $decoded["data"]["patient_id"] ?? null;
    if ($pid !== null && $pid !== "") {
        $_SESSION["patient_id"] = (int)$pid;
    }
}

echo $response;
?>
