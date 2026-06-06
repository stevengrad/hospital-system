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

// Use the correct table: login
$result = $conn->query("
    SELECT login_id AS id, username, national_id, role
    FROM login
    ORDER BY login_id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin - Manage Users</title>
<style>
body{font-family:Segoe UI;background:linear-gradient(135deg,#007bff,#00bcd4);color:#fff;margin:0}
.container{max-width:900px;margin:60px auto;background:#fff;color:#007bff;padding:25px;border-radius:15px;box-shadow:0 4px 20px rgba(0,0,0,0.2)}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
th{background:#007bff;color:#fff}
</style>
</head>
<body>
<div class="container">
<h2>👥 Manage Users</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>National ID</th>
        <th>Role</th>
    </tr>
    <?php if ($result && $result->num_rows > 0): ?>
        <?php while($u = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars($u['national_id']) ?></td>
            <td><?= htmlspecialchars($u['role']) ?></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="4">No users found.</td>
        </tr>
    <?php endif; ?>
</table>
<a href="admin_dashboard.php" style="color:#007bff;text-decoration:none;font-weight:bold;">⬅ Back</a>
</div>
</body>
</html>
