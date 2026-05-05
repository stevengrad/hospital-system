<?php
session_start();

/* =========================
   Access Control
========================= */
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'doctor') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/lang/init_lang.php';

$lang = $_SESSION['lang'] ?? 'en';
$dir  = $T['dir'] ?? (($lang === 'ar') ? 'rtl' : 'ltr');

$doctorID = (int)$_SESSION['user_id'];

$successMsg = '';
$errorMsg   = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* =========================
   Update Appointment Status
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $appointmentID = (int)($_POST['appointment_id'] ?? 0);
    $status        = trim($_POST['status'] ?? '');

    if ($appointmentID > 0 && $status !== '') {

        $stmt = $conn->prepare("
            UPDATE appointments
            SET Status = ?
            WHERE AppointmentID = ?
            AND DoctorID = ?
        ");

        $stmt->bind_param(
            "sii",
            $status,
            $appointmentID,
            $doctorID
        );

        if ($stmt->execute()) {
            $successMsg =
                ($lang === 'ar')
                ? "تم تحديث الحالة بنجاح"
                : "Status updated successfully";
        }

        $stmt->close();
    }
}

/* =========================
   Fetch Appointments
========================= */

$stmt = $conn->prepare("
    SELECT
        a.AppointmentID,
        CONCAT(p.FirstName,' ',p.LastName) AS PatientName,
        a.AppointmentDateTime,
        a.Status
    FROM appointments a
    JOIN patients p
        ON a.PatientID = p.PatientID
    WHERE a.DoctorID = ?
    ORDER BY a.AppointmentDateTime DESC
");

$stmt->bind_param("i", $doctorID);
$stmt->execute();

$appointments = $stmt->get_result();

$stmt->close();

/* =========================
   Language Links
========================= */

function lang_link_appt($code) {
    return basename($_SERVER['PHP_SELF']) . '?lang=' . $code;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>"
      dir="<?= htmlspecialchars($dir) ?>">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
<?= ($lang === 'ar')
    ? 'مواعيد الطبيب'
    : 'Doctor Appointments'
?>
</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:"Segoe UI", Tahoma, Arial, sans-serif;
    color:white;
    min-height:100vh;
    background:
        radial-gradient(circle at top left, rgba(76,201,240,0.18), transparent 25%),
        radial-gradient(circle at top right, rgba(123,47,247,0.14), transparent 22%),
        linear-gradient(135deg, #07121d, #0c1c2d, #12314e);
    background-attachment: fixed;
}

/* Topbar */

.topbar{

    position:sticky;

    top:15px;

    margin:20px;

    padding:18px 25px;

    border-radius:24px;

    display:flex;

    justify-content:space-between;

    align-items:center;

    background:

    rgba(255,255,255,0.08);

    border:

    1px solid
    rgba(255,255,255,0.12);

    backdrop-filter:

    blur(16px);

    box-shadow:

    0 20px 50px
    rgba(0,0,0,0.25);
}

.brand{

    display:flex;

    gap:14px;

    align-items:center;
}

.brand-icon{

    width:56px;
    height:56px;

    border-radius:18px;

    display:grid;

    place-items:center;

    font-size:24px;

    background:

    linear-gradient(
        135deg,
        #4cc9f0,
        #4895ef
    );
}

.brand-text h1{

    margin:0;

    font-size:20px;

    font-weight:800;
}

.brand-text p{

    margin:4px 0 0;

    font-size:13px;

    color:rgba(255,255,255,0.8);
}

.menu{

    display:flex;

    gap:10px;

    align-items:center;
}

.btn{

    padding:11px 16px;

    border-radius:14px;

    color:white;

    font-weight:700;

    text-decoration:none;

    background:

    rgba(255,255,255,0.08);

    border:

    1px solid
    rgba(255,255,255,0.14);

    transition:0.25s;
}

.btn:hover{

    transform:translateY(-2px);
}

.logout{

    background:

    linear-gradient(
        135deg,
        #ef4444,
        #dc2626
    );
}

/* Container */

.container{
    width:min(1200px, calc(100% - 40px));
    margin:30px auto 50px;
}

/* Hero */

.hero{

    border-radius:28px;

    padding:30px;

    background:

    linear-gradient(
        135deg,
        #6d28d9,
        #2563eb,
        #38bdf8
    );

    margin-bottom:25px;
}

.hero h2{

    margin:0 0 10px;

    font-size:32px;

    font-weight:800;
}

.hero p{

    margin:0;

    font-size:16px;
}

/* Table */

.table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    background:rgba(255,255,255,0.08);
    border-radius:22px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,0.10);
    box-shadow:0 18px 40px rgba(0,0,0,0.20);
}

th{

    background:#2563eb;

    padding:14px;

    font-size:15px;
}

td{
    padding:16px 14px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.10);
    background:rgba(255,255,255,0.03);
}
tr:last-child td{
    border-bottom:none;
}


select{

    padding:7px 12px;

    border-radius:10px;

    border:none;
}

.save{

    padding:8px 14px;

    border:none;

    border-radius:10px;

    background:#2563eb;

    color:white;

    cursor:pointer;
}

.save:hover{

    background:#1d4ed8;
}

.alert{

    padding:12px;

    border-radius:12px;

    margin-bottom:15px;
}

.success{

    background:#22c55e;
}

.error{

    background:#ef4444;
}

</style>

</head>

<body>
    <div class="topbar">

<div class="brand">

<div class="brand-icon">
<i class="fa-solid fa-calendar-check"></i>
</div>

<div class="brand-text">

<h1>
<?= $T['app_name']
?? 'Cairo Hospitals' ?>
</h1>

<p>
<?= ($lang === 'ar')
? 'مواعيد الطبيب'
: 'Doctor Appointments' ?>
</p>

</div>

</div>

<div class="menu">

<a class="btn"
href="doctor_dashboard.php">

<?= ($lang === 'ar')
? 'لوحة الطبيب'
: 'Dashboard' ?>

</a>

<a class="btn logout"
href="logout.php">

<?= $T['logout']
?? 'Logout' ?>

</a>

<a class="btn"
href="<?= lang_link_appt('en') ?>">
EN
</a>

<a class="btn"
href="<?= lang_link_appt('ar') ?>">
AR
</a>

</div>

</div>


<div class="container">

<div class="hero">

<h2>

<?= ($lang === 'ar')
? 'إدارة المواعيد'
: 'Manage Appointments' ?>

</h2>

<p>

<?= ($lang === 'ar')
? 'عرض المواعيد وتحديث الحالة بسهولة.'
: 'View appointments and update status easily.' ?>

</p>

</div>


<?php if ($successMsg): ?>

<div class="alert success">
<?= $successMsg ?>
</div>

<?php endif; ?>


<?php if ($errorMsg): ?>

<div class="alert error">
<?= $errorMsg ?>
</div>

<?php endif; ?>


<table class="table">

<tr>

<th>#</th>

<th>
<?= ($lang === 'ar')
? 'اسم المريض'
: 'Patient' ?>
</th>

<th>
<?= ($lang === 'ar')
? 'موعد الكشف'
: 'Date / Time' ?>
</th>

<th>
<?= ($lang === 'ar')
? 'الحالة'
: 'Status' ?>
</th>

<th>
<?= ($lang === 'ar')
? 'تحديث'
: 'Update' ?>
</th>

</tr>
<?php while ($row = $appointments->fetch_assoc()): ?>

<tr>

<td>

<?= (int)$row['AppointmentID'] ?>

</td>

<td>

<?= htmlspecialchars(
$row['PatientName']
) ?>

</td>

<td>

<?= htmlspecialchars(
$row['AppointmentDateTime']
) ?>

</td>

<td>

<form method="post">

<input
type="hidden"
name="appointment_id"
value="<?= (int)$row['AppointmentID'] ?>">

<select name="status">

<option
value="Pending"
<?= $row['Status']=='Pending'
? 'selected'
: '' ?>>

<?= ($lang==='ar')
? 'قيد الانتظار'
: 'Pending' ?>

</option>

<option
value="Completed"
<?= $row['Status']=='Completed'
? 'selected'
: '' ?>>

<?= ($lang==='ar')
? 'تمت'
: 'Completed' ?>

</option>

<option
value="Cancelled"
<?= $row['Status']=='Cancelled'
? 'selected'
: '' ?>>

<?= ($lang==='ar')
? 'ألغيت'
: 'Cancelled' ?>

</option>

</select>

</td>

<td>

<button
type="submit"
name="update_status"
class="save">

<?= ($lang==='ar')
? 'حفظ'
: 'Save' ?>

</button>

</form>

</td>

</tr>

<?php endwhile; ?>

</table>
<div style="
    text-align:center;
    color:rgba(255,255,255,0.62);
    font-size:13px;
    margin-top:18px;
    padding-bottom:20px;
">
    © <?= date('Y') ?> <?= $T['app_name'] ?? 'Graduation Hospital' ?> —
    <?= ($lang === 'ar') ? 'واجهة مواعيد احترافية للطبيب' : 'Professional Doctor Appointments Interface' ?>
</div>

</div>

</body>

</html>
