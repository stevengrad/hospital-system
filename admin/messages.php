<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../inc/db.php';

// Delete message if requested
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
}

$stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
$messages = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Contact Messages - Admin</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
  <h2>Contact Messages</h2>
  <a href="dashboard.php" class="btn">⬅ Back to Dashboard</a>

  <table border="1" cellpadding="6" cellspacing="0" style="width:100%; margin-top:15px;">
    <tr style="background:#f0f0f0;">
      <th>ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Message</th>
      <th>Date</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($messages as $m): ?>
    <tr>
      <td><?= $m['id'] ?></td>
      <td><?= htmlspecialchars($m['name']) ?></td>
      <td><?= htmlspecialchars($m['email']) ?></td>
      <td><?= nl2br(htmlspecialchars($m['message'])) ?></td>
      <td><?= $m['created_at'] ?></td>
      <td><a href="?delete=<?= $m['id'] ?>" class="btn" style="background:#dc3545;" onclick="return confirm('Delete this message?');">Delete</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</body>
</html>
