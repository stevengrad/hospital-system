<?php
header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "error" => "POST only"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = trim($_POST["message"] ?? "");

if ($message === "") {
    echo json_encode([
        "error" => "Empty message"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// لو الـ dashboard أو أي صفحة بعتت patient_id في الفورم، خزّنه
if (isset($_POST["patient_id"]) && $_POST["patient_id"] !== "") {
    $_SESSION["patient_id"] = (int)$_POST["patient_id"];
}

// لو المستخدم كتب رقم فقط، اعتبريه PatientID وخزّنيه مباشرة
if (preg_match('/^\d{1,10}$/', $message)) {
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

// بيانات الهوية من الـ session
$username = $_SESSION["username"] ?? null;
$display_name = $_SESSION["first_name"] ?? ($_SESSION["name"] ?? null);

// chat_id ثابت نسبيًا لكل يوزر/سيشن
$chat_id = "dash_" . ($_SESSION["user_id"] ?? session_id());

// تجهيز الـ payload للـ FastAPI
$payloadArr = [
    "text" => $message,
    "username" => $username,
    "display_name" => $display_name,
    "chat_id" => $chat_id,
];

// ابعتي patient_id لو موجود
if (isset($_SESSION["patient_id"]) && $_SESSION["patient_id"] !== "") {
    $payloadArr["patient_id"] = (int)$_SESSION["patient_id"];
}

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

$chatbotUrl = getenv('CHATBOT_API_URL') ?: 'https://cairohospitals.click/chat/free';$ch = curl_init($chatbotUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode([
        "error" => "Failed to connect to Python API",
        "details" => curl_error($ch)
    ], JSON_UNESCAPED_UNICODE);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode([
        "error" => "Python API returned HTTP " . $httpCode,
        "raw" => $response
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($response, true);

// لو الـ backend رجع patient_id_set خزّنيه في الـ session
if (is_array($decoded)) {
    if (($decoded["intent"] ?? "") === "patient_id_set") {
        $pid = $decoded["data"]["patient_id"] ?? null;
        if ($pid !== null && $pid !== "") {
            $_SESSION["patient_id"] = (int)$pid;
        }
    }
}

echo $response;
?>