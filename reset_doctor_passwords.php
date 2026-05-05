<?php
session_start();
include 'db_connect.php';

function bounce_with_alert(string $msg, string $to) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access</title></head><body>';
    echo '<script>alert('.json_encode($msg).');window.location.href='.json_encode($to).';</script>';
    echo '</body></html>';
    exit();
}

/* ---- Admin check ---- */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    bounce_with_alert('Access denied: admin only.', 'admin_login.php');
}

/* ---- Dashboard-gate check (must have visited admin_dashboard recently) ---- */
$gateOk     = !empty($_SESSION['reset_pw_gate']);
$gateExpiry = $_SESSION['reset_pw_gate_expires'] ?? 0;

if (!$gateOk || $gateExpiry < time()) {
    bounce_with_alert('Access denied: please open the Admin Dashboard first.', 'admin_dashboard.php');
}

/* Optional extra: Referer check (not required, but extra protection)
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'admin_dashboard.php') === false) {
    bounce_with_alert('Access denied: open this page from the Admin Dashboard.', 'admin_dashboard.php');
}
*/

/* One-time use gate (consume it) */
unset($_SESSION['reset_pw_gate'], $_SESSION['reset_pw_gate_expires']);

/* ---- Logic: reset passwords ---- */
function generateDoctorPassword(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%&*';
    $rand   = '';
    for ($i = 0; $i < 6; $i++) {
        $rand .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'doc' . $rand;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$sql = "SELECT EmployeeID, DoctorUsername FROM employees WHERE Role = 'Doctor'";
$result = $conn->query($sql);

$upd = $conn->prepare("UPDATE employees SET PasswordHash = ? WHERE EmployeeID = ?");

$resetList = [];
while ($row = $result->fetch_assoc()) {
    $empId = (int)$row['EmployeeID'];
    $user  = $row['DoctorUsername'];
    if (!$user) continue;

    $plain = generateDoctorPassword();
    $hash  = password_hash($plain, PASSWORD_DEFAULT);

    $upd->bind_param("si", $hash, $empId);
    $upd->execute();

    $resetList[] = [
        'EmployeeID' => $empId,
        'username'   => $user,
        'password'   => $plain, // shown once
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Doctor Passwords</title>
<style>
    body{font-family:Arial, sans-serif;background:#f5f6fa;padding:20px;margin:0;}
    .bar{display:flex;gap:10px;margin-bottom:15px;}
    .btn{padding:8px 14px;border:none;border-radius:6px;cursor:pointer}
    .back{background:#007bff;color:#fff;}
    .back:hover{background:#0056b3;}
    .note{margin:10px 0;padding:10px;background:#fff3cd;border:1px solid #ffeeba;border-radius:6px;}
    table{border-collapse:collapse;width:100%;background:#fff;margin-top:10px;}
    th,td{border:1px solid #ccc;padding:8px;text-align:left;}
    th{background:#007bff;color:#fff;}
</style>
</head>
<body>

<div class="bar">
  <button class="btn back" onclick="location.href='admin_dashboard.php'">⬅ Back to Admin Dashboard</button>
</div>

<h2>Doctor Password Reset</h2>

<div class="note">
    <strong>Important:</strong> These passwords are shown only once. Copy them to a safe place.
</div>

<?php if (empty($resetList)): ?>
    <p>No doctors found or no passwords were updated.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Employee ID</th>
            <th>Doctor Username</th>
            <th>NEW Password</th>
        </tr>
        <?php foreach ($resetList as $d): ?>
        <tr>
            <td><?= (int)$d['EmployeeID'] ?></td>
            <td><?= htmlspecialchars($d['username']) ?></td>
            <td><?= htmlspecialchars($d['password']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

</body>
</html>
