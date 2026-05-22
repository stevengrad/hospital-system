<?php
session_start();
include('db_connect.php');

$token = $_GET['token'] ?? '';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Waiting for Email Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family:Arial,sans-serif;
            background:#f4f8fb;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0;
        }
        .card{
            background:white;
            max-width:520px;
            padding:35px;
            border-radius:18px;
            box-shadow:0 15px 40px rgba(0,0,0,.12);
            text-align:center;
        }
        h1{color:#116ad0;}
        p{color:#475569;line-height:1.7;}
        .loader{
            margin:24px auto;
            width:48px;
            height:48px;
            border:5px solid #dbeafe;
            border-top-color:#116ad0;
            border-radius:50%;
            animation:spin 1s linear infinite;
        }
        @keyframes spin{to{transform:rotate(360deg);}}
    </style>
</head>
<body>
<link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">
<div class="card">
    <h1>Check your email</h1>
    <p>We sent a verification link to your email.</p>
    <p>Please click the verify button to complete your account registration.</p>
    <div class="loader"></div>
    <p>This page will automatically continue after verification.</p>
</div>

<script>
const token = <?= json_encode($token) ?>;

setInterval(async () => {
    const res = await fetch("check_verification_status.php?token=" + encodeURIComponent(token));
    const data = await res.json();

    if (data.status === "verified") {
        window.location.href = "index.php?verified=1";
    }

    if (data.status === "expired") {
        window.location.href = "register.php?expired=1";
    }
}, 3000);
</script>
</body>
</html>