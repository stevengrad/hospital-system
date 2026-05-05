<?php
session_start();

// Only admins can access
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit(); 
}
if (strtolower($_SESSION['role'] ?? '') !== 'admin') { 
    die("Access denied. Admins only."); 
}

include 'db_connect.php';

// Fetch all patients
$query = "
    SELECT id, first_name, last_name, dob, gender, contact
    FROM patients
    ORDER BY first_name ASC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Patients</title>
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
    <h2>🧑‍⚕️ Manage Patients</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>DOB</th>
            <th>Gender</th>
            <th>Contact</th>
        </tr>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($p = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                <td><?= htmlspecialchars($p['dob']) ?></td>
                <td><?= htmlspecialchars($p['gender']) ?></td>
                <td><?= htmlspecialchars($p['contact']) ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No patients found.</td></tr>
        <?php endif; ?>
    </table>

    <a href="admin_dashboard.php" style="color:#007bff;text-decoration:none;font-weight:bold;">⬅ Back</a>
</div>

</body>
</html>
