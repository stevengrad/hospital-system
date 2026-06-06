<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

include 'db_connect.php';

// ✅ Total user accounts (from login table)
$users = 0;
$resUsers = $conn->query("SELECT COUNT(*) AS total FROM login");
if ($resUsers && $row = $resUsers->fetch_assoc()) {
    $users = (int)$row['total'];
}

// ✅ Total patients (from patients table)
$patients = 0;
$resPatients = $conn->query("SELECT COUNT(*) AS total FROM patients");
if ($resPatients && $row = $resPatients->fetch_assoc()) {
    $patients = (int)$row['total'];
}

// ✅ Total doctors (from doctors table)
$doctors = 0;
$resDoctors = $conn->query("SELECT COUNT(*) AS total FROM doctors");
if ($resDoctors && $row = $resDoctors->fetch_assoc()) {
    $doctors = (int)$row['total'];
}

// ✅ Total appointments
$appointments = 0;
$resAppointments = $conn->query("SELECT COUNT(*) AS total FROM appointments");
if ($resAppointments && $row = $resAppointments->fetch_assoc()) {
    $appointments = (int)$row['total'];
}

// ✅ Total services (only if services table exists)
$services = 0;
$checkServices = $conn->query("SHOW TABLES LIKE 'services'");
if ($checkServices && $checkServices->num_rows > 0) {
    $resServices = $conn->query("SELECT COUNT(*) AS total FROM services");
    if ($resServices && $row = $resServices->fetch_assoc()) {
        $services = (int)$row['total'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif;background:#f0f8ff;margin:0;text-align:center; }
header { background:#0077b6;padding:15px 40px;color:white;display:flex;justify-content:space-between;align-items:center; }
.container { display:flex;justify-content:center;gap:30px;margin-top:60px;flex-wrap:wrap; }
.card { background:white;padding:25px;width:220px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.2); }
.card h3 { color:#0077b6; margin-bottom:10px; }
.card p { font-size:24px;font-weight:bold;margin:0; }
a.header-link { color:white;text-decoration:none;font-weight:bold; }
</style>
</head>
<body>
<header>
  <h1>Reports Overview</h1>
  <a href="admin_dashboard.php" class="header-link">🏠 Dashboard</a>
</header>

<div class="container">
  <div class="card">
    <h3>Total Users</h3>
    <p><?= $users; ?></p>
  </div>
  <div class="card">
    <h3>Total Patients</h3>
    <p><?= $patients; ?></p>
  </div>
  <div class="card">
    <h3>Total Doctors</h3>
    <p><?= $doctors; ?></p>
  </div>
  <div class="card">
    <h3>Total Services</h3>
    <p><?= $services; ?></p>
  </div>
  <div class="card">
    <h3>Total Appointments</h3>
    <p><?= $appointments; ?></p>
  </div>
</div>
</body>
</html>
