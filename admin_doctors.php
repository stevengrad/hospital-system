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

// Fetch doctors + their department names
$query = "
    SELECT d.id, d.name, d.specialty, d.photo, 
           dep.name AS department
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.id
    ORDER BY d.name ASC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Doctors</title>
<style>
body{font-family:Segoe UI;background:linear-gradient(135deg,#007bff,#00bcd4);color:#fff;margin:0}
.container{max-width:1000px;margin:60px auto;background:#fff;color:#007bff;padding:25px;border-radius:15px;box-shadow:0 4px 20px rgba(0,0,0,0.2)}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
th{background:#007bff;color:#fff}
img{width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid #007bff}
</style>
</head>
<body>

<div class="container">
    <h2>👨‍⚕️ Manage Doctors</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Photo</th>
            <th>Name</th>
            <th>Specialty</th>
            <th>Department</th>
        </tr>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($d = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $d['id'] ?></td>

                <td>
                    <?php if ($d['photo'] && file_exists("uploads/doctors/" . $d['photo'])): ?>
                        <img src="uploads/doctors/<?= $d['photo'] ?>">
                    <?php else: ?>
                        <img src="assets/img/default-doctor.png">
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($d['name']) ?></td>
                <td><?= htmlspecialchars($d['specialty']) ?></td>
                <td><?= htmlspecialchars($d['department'] ?? 'N/A') ?></td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No doctors found.</td></tr>
        <?php endif; ?>
    </table>

    <a href="admin_dashboard.php" style="color:#007bff;text-decoration:none;font-weight:bold;">⬅ Back</a>
</div>

</body>
</html>
