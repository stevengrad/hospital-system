<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../inc/db.php';

// Approve or delete actions
if (isset($_GET['approve'])) {
    $id = (int) $_GET['approve'];
    $pdo->prepare("UPDATE appointments SET status='approved' WHERE id=?")->execute([$id]);
}
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM appointments WHERE id=?")->execute([$id]);
}

$stmt = $pdo->query("SELECT a.*, d.name AS doctor_name 
                     FROM appointments a 
                     LEFT JOIN doctors d ON a.doctor_id = d.id 
                     ORDER BY a.created_at DESC");
$appointments = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Appointments - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
  <h2>Manage Appointments</h2>
  <a href="dashboard.php" class="btn">⬅ Back to Dashboard</a>
  <table border="1" cellpadding="6" cellspacing="0" style="width:100%; margin-top:15px;">
    <tr style="background:#f0f0f0;">
      <th>ID</th>
      <th>Patient</th>
      <th>Phone</th>
      <th>Email</th>
      <th>Doctor</th>
      <th>Date</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($appointments as $a): ?>
    <tr>
      <td><?= $a['id'] ?></td>
      <td><?= htmlspecialchars($a['patient_name']) ?></td>
      <td><?= htmlspecialchars($a['phone']) ?></td>
      <td><?= htmlspecialchars($a['email']) ?></td>
      <td><?= htmlspecialchars($a['doctor_name']) ?></td>
      <td><?= htmlspecialchars($a['appointment_date']) ?></td>
      <td><?= htmlspecialchars($a['status']) ?></td>
      <td>
        <?php if ($a['status'] !== 'approved'): ?>
          <a href="?approve=<?= $a['id'] ?>" class="btn" style="background:#28a745;">Approve</a>
        <?php endif; ?>
        <a href="?delete=<?= $a['id'] ?>" class="btn" style="background:#dc3545;" onclick="return confirm('Delete this appointment?');">Delete</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</body>
</html>
