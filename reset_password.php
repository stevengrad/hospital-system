<?php
session_start();
require_once "db_connect.php";

$error = "";

if (
    !isset($_SESSION["reset_email"]) ||
    !isset($_SESSION["reset_username"]) ||
    !isset($_SESSION["forgot_verified"]) ||
    $_SESSION["forgot_verified"] !== true
) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];
$username = $_SESSION["reset_username"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($new_password === "" || $confirm_password === "") {
        $error = "Please fill all fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Use plain password if your login system currently uses plain text
        $password_to_save = $new_password;

        // If your login uses password_hash, use this instead:
        // $password_to_save = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE login
            SET password = ?
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $password_to_save, $username);

        if ($stmt->execute()) {
            $delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $delete->bind_param("s", $email);
            $delete->execute();

            unset($_SESSION["reset_email"]);
            unset($_SESSION["reset_username"]);
            unset($_SESSION["forgot_verified"]);

            header("Location: index.php?reset=success");
            exit();
        } else {
            $error = "Could not update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password | Cairo Hospitals</title>
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
label {
    display: block;
    text-align: left;
    margin-bottom: 10px;
    font-weight: bold;
    color: #243b53;
}
input {
    width: 100%;
    padding: 20px;
    border-radius: 18px;
    border: 1px solid #d6dde8;
    font-size: 17px;
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
</style>
</head>

<body>
<div class="box">
    <div class="logo">
        <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals">
    </div>

    <h1>Cairo Hospitals</h1>
    <p>Create a new password for your account.</p>

    <div class="step">Step 3: Reset Password</div>

    <?php if ($error !== ""): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>

        <button type="submit">Done</button>
    </form>
</div>
</body>
</html>