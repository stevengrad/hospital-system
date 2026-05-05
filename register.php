<?php
// ====== KEEP YOUR BACKEND HERE ======
// مثال:
// if($_SERVER["REQUEST_METHOD"] == "POST"){ ... }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Register - Cairo Hospitals</title>

<link rel="icon" href="favicon.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family: 'Segoe UI', sans-serif;
}

body{
    background: linear-gradient(135deg,#0f2a3d,#1b4d6b);
    display:flex;
    height:100vh;
}

/* LEFT SIDE */
.left{
    flex:1;
    color:white;
    padding:60px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.left h1{
    font-size:48px;
    font-weight:700;
    line-height:1.2;
    margin-bottom:20px;
}

.left p{
    color:#cbd5e1;
    margin-bottom:40px;
}

.features{
    display:grid;
    grid-template-columns: repeat(2,1fr);
    gap:20px;
}

.feature{
    background:rgba(255,255,255,0.08);
    padding:20px;
    border-radius:15px;
}

.feature i{
    font-size:22px;
    margin-bottom:10px;
}

/* RIGHT SIDE */
.right{
    width:500px;
    background:white;
    border-radius:30px 0 0 30px;
    padding:40px;
    overflow-y:auto;
}

/* HEADER */
.brand{
    text-align:center;
    margin-bottom:20px;
}

.brand img{
    width:50px;
}

.brand h2{
    color:#1e7d4f;
    margin-top:10px;
}

/* FORM */
.form-group{
    margin-bottom:15px;
}

.label{
    font-size:14px;
    margin-bottom:5px;
    display:block;
}

.input-box{
    position:relative;
}

.input-box input,
.input-box select{
    width:100%;
    padding:12px 40px;
    border-radius:12px;
    border:1px solid #ddd;
    outline:none;
}

.input-box i{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#888;
}

/* BUTTON */
.btn{
    width:100%;
    padding:14px;
    border:none;
    border-radius:15px;
    background:#22c55e;
    color:white;
    font-weight:bold;
    cursor:pointer;
    margin-top:10px;
}

.btn:hover{
    background:#16a34a;
}

/* LINKS */
.links{
    text-align:center;
    margin-top:15px;
}

.links a{
    color:#1e7d4f;
    text-decoration:none;
    font-weight:500;
}
</style>
</head>

<body>

<!-- LEFT -->
<div class="left">
    <h1>Start your healthcare journey with a trusted account</h1>
    <p>Create your account to manage appointments and medical history easily.</p>

    <div class="features">
        <div class="feature">
            <i class="fa-solid fa-calendar-check"></i>
            <p>Appointment management</p>
        </div>

        <div class="feature">
            <i class="fa-solid fa-file-medical"></i>
            <p>Medical history access</p>
        </div>

        <div class="feature">
            <i class="fa-solid fa-user-doctor"></i>
            <p>Doctor & patient accounts</p>
        </div>

        <div class="feature">
            <i class="fa-solid fa-shield-halved"></i>
            <p>Secure system</p>
        </div>
    </div>
</div>

<!-- RIGHT -->
<div class="right">

    <div class="brand">
        <img src="favicon.ico">
        <h2>Cairo Hospitals</h2>
    </div>

    <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
            <label class="label">First Name</label>
            <div class="input-box">
                <i class="fa fa-user"></i>
                <input type="text" name="first_name" required>
            </div>
        </div>

        <div class="form-group">
            <label class="label">Last Name</label>
            <div class="input-box">
                <i class="fa fa-user"></i>
                <input type="text" name="last_name" required>
            </div>
        </div>

        <div class="form-group">
            <label class="label">Username</label>
            <div class="input-box">
                <i class="fa fa-user"></i>
                <input type="text" name="username" required>
            </div>
        </div>

        <div class="form-group">
            <label class="label">National ID</label>
            <div class="input-box">
                <i class="fa fa-id-card"></i>
                <input type="text" name="national_id">
            </div>
        </div>

        <div class="form-group">
            <label class="label">Password</label>
            <div class="input-box">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" required>
            </div>
        </div>

        <div class="form-group">
            <label class="label">Confirm Password</label>
            <div class="input-box">
                <i class="fa fa-lock"></i>
                <input type="password" name="confirm_password" required>
            </div>
        </div>

        <div class="form-group">
            <label class="label">National ID Photo</label>
            <input type="file" name="id_photo">
        </div>

        <div class="form-group">
            <label class="label">Gender</label>
            <div class="input-box">
                <i class="fa fa-venus-mars"></i>
                <select name="gender">
                    <option>Select Gender</option>
                    <option>Male</option>
                    <option>Female</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="label">Governorate</label>
            <div class="input-box">
                <i class="fa fa-location-dot"></i>
                <select name="governorate">
                    <option>Select Governorate</option>
                    <option>Cairo</option>
                    <option>Giza</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="label">Birthdate</label>
            <div class="input-box">
                <i class="fa fa-calendar"></i>
                <input type="date" name="birthdate">
            </div>
        </div>

        <div class="form-group">
            <label class="label">Phone Number</label>
            <div class="input-box">
                <i class="fa fa-phone"></i>
                <input type="text" name="phone">
            </div>
        </div>

        <button class="btn">Create Account</button>

        <div class="links">
            <a href="login.php">Already have an account? Login</a>
        </div>

    </form>
</div>

</body>
</html>