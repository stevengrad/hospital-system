<?php
session_start();

// 🔐 Admin-only access
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

include 'db_connect.php';

// Get appointments with REAL tables
$sql = "
    SELECT 
        a.AppointmentID,
        a.AppointmentDate,
        a.AppointmentTime,
        a.Status,
        
        CONCAT(p.FirstName, ' ', p.LastName) AS PatientName,
        
        CONCAT(e.FirstName, ' ', e.LastName) AS DoctorName
        
    FROM appointments a
    LEFT JOIN patients p 
        ON a.PatientID = p.PatientID
    LEFT JOIN employees e 
        ON a.DoctorID = e.EmployeeID
    ORDER BY a.AppointmentDate DESC, a.AppointmentTime DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Appointments</title>
<style>
body{font-family:Segoe UI;background:linear-gradient(135deg,#007bff,#00bcd4);color:#fff;margin:0}
.container{max-width:1000px;margin:60px auto;background:#fff;color:#007bff;padding:25px;border-radius:15px;box-shadow:0 4px 20px rgba(0,0,0,0.2)}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
th{background:#007bff;color:#fff}
</style>
</head>
<body>
<div class="container">
<h2>📋 Manage Appointments</h2>

<table>
<tr>
    <th>ID</th>
    <th>Patient</th>
    <th>Doctor</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
</tr>

<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['AppointmentID'] ?></td>
    <td><?= htmlspecialchars($row['PatientName'] ?? 'Unknown') ?></td>
    <td><?= htmlspecialchars($row['DoctorName'] ?? 'Unknown') ?></td>
    <td><?= htmlspecialchars($row['AppointmentDate']) ?></td>
    <td><?= htmlspecialchars($row['AppointmentTime']) ?></td>
    <td><?= htmlspecialchars($row['Status']) ?></td>
</tr>
<?php endwhile; ?>

</table>

<a href="dashboard.php" style="color:#007bff;text-decoration:none;font-weight:bold;">⬅ Back</a>
</div>
</body>
</html>
