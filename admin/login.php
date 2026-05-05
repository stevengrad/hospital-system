<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../inc/db.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_id'] = $user['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $err = "Invalid username or password.";
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Admin Login</title></head><body>
  <h2>Admin Login</h2>
  <?php if ($err) echo "<p style='color:red;'>".htmlspecialchars($err)."</p>"; ?>
  <form method="post">
    <label>Username <input name="username"></label><br>
    <label>Password <input type="password" name="password"></label><br>
    <button>Login</button>
  </form>
</body></html>
