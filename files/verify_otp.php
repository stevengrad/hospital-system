<?php
session_start();
require_once "db_connect.php";

$error = "";

if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["reset_username"])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp = trim($_POST["otp"] ?? "");

    if ($otp === "") {
        $error = "Please enter the OTP code.";
    } elseif (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error = "OTP must be 6 numbers.";
    } else {
        $stmt = $conn->prepare("
            SELECT *
            FROM password_resets
            WHERE email = ?
              AND otp = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "Invalid OTP code.";
        } else {
            $row = $result->fetch_assoc();

            if (strtotime($row["expires_at"]) < time()) {
                $error = "OTP has expired. Please request a new code.";
            } else {
                $update = $conn->prepare("
                    UPDATE password_resets
                    SET verified = 1
                    WHERE email = ?
                ");
                $update->bind_param("s", $email);
                $update->execute();

                $_SESSION["forgot_verified"] = true;

                header("Location: reset_password.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify OTP | Cairo Hospitals</title>
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png?v=2">

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #08111f, #063b2d);
    display: flex;
    align-items: center;
    justify-content: center;
}
.box {
    width: 620px;
    background: #f5f8fb;
    border-radius: 28px;
    padding: 45px 38px;
    text-align: center;
}
.logo img {
    width: 70px;
    height: 70px;
    object-fit: contain;
    border-radius: 18px;
}
h1 {
    color: #146c33;
}
.step {
    display: inline-block;
    padding: 13px 28px;
    border: 1px solid #bcd8ff;
    border-radius: 22px;
    color: #0b5eb8;
    font-weight: bold;
    margin: 20px 0;
}
input {
    width: 100%;
    padding: 20px;
    border-radius: 18px;
    border: 1px solid #d6dde8;
    font-size: 22px;
    text-align: center;
    letter-spacing: 6px;
    box-sizing: border-box;
    margin-bottom: 25px;
}
button {
    width: 100%;
    padding: 20px;
    border: none;
    border-radius: 18px;
    background: #15b947;
    color: white;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
}
.error {
    background: #fff0f2;
    color: #c51f3d;
    border: 1px solid #ffc2cc;
    padding: 17px;
    border-radius: 14px;
    font-weight: bold;
    margin-bottom: 20px;
}
a {
    display: block;
    margin-top: 25px;
    color: #0b5eb8;
    text-decoration: none;
    font-weight: bold;
}
</style>
</head>

<body>
<div class="box">
    <div class="logo">
        <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals">
    </div>

    <h1>Cairo Hospitals</h1>
    <p>Enter the 6-digit OTP sent to your registered email.</p>

    <div class="step">Step 2: Enter OTP</div>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="otp" maxlength="6" placeholder="000000" required>
        <button type="submit">Verify OTP</button>
    </form>

    <a href="forgot_password.php">Send another code</a>
</div>
</body>
</html>