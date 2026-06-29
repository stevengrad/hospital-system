<?php
session_start();
include 'db_connect.php';

/* =========================
   Language Handling
========================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

function lang_link_myappts($code) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?lang=' . $code;
}

/* =========================
   Auth Check
========================= */
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$login_id = (int)$_SESSION['user_id'];
$error    = '';
$message  = '';

$username     = '';
$national_id  = '';
$patient_id   = 0;

/* =========================
   Get login data + direct patient_id if exists
========================= */
$stmt_user = $conn->prepare("SELECT username, national_id FROM login WHERE id = ?");
$stmt_user->bind_param("i", $login_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($row = $result_user->fetch_assoc()) {
    $username    = $row['username'] ?? '';
    $national_id = $row['national_id'] ?? '';
}
$stmt_user->close();

/* =========================
   Get PatientID from patients by NationalID
========================= */
if (!empty($national_id)) {
    $stmt_patient = $conn->prepare("SELECT PatientID FROM patients WHERE NationalID = ?");
    $stmt_patient->bind_param("s", $national_id);
    $stmt_patient->execute();
    $res_patient = $stmt_patient->get_result();

    if ($p = $res_patient->fetch_assoc()) {
        $patient_id = (int)$p['PatientID'];
    }

    $stmt_patient->close();
}

/* =========================
   Cancel Appointment
========================= */
if (isset($_GET['cancel_id']) && $patient_id > 0) {
    $cancel_id = intval($_GET['cancel_id']);
    $stmt = $conn->prepare("UPDATE appointments SET Status = 'Canceled' WHERE AppointmentID = ? AND PatientID = ? AND Status <> 'Canceled'");
    $stmt->bind_param("ii", $cancel_id, $patient_id);
    $stmt->execute();
    $stmt->close();
    header("Location: my_appointments.php");
    exit();
}

/* =========================
   Fetch Appointments
========================= */
$appointments = [];
if ($patient_id > 0) {
    $query = "SELECT a.AppointmentID, a.AppointmentDateTime, a.Status,
                     CONCAT(e.FirstName,' ',e.LastName) AS DoctorName,
                     d.SpecialtyID, s.Name AS SpecialtyName
              FROM appointments a
              INNER JOIN doctors d ON a.DoctorID = d.EmployeeID
              INNER JOIN employees e ON d.EmployeeID = e.EmployeeID
              LEFT JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
              WHERE a.PatientID = ?
              ORDER BY a.AppointmentDateTime DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
}

/* =========================
   Status Badge Styling
========================= */
function formatStatus($status, $lang='en') {
    $status_lower = strtolower($status);
    $label = $status;
    $color = "#94a3b8";

    if ($status_lower === 'pending') {
        $label = ($lang === 'ar' ? 'قيد الانتظار' : 'Pending');
        $color = "#facc15";
    } elseif ($status_lower === 'scheduled') {
        $label = ($lang === 'ar' ? 'مجدول' : 'Scheduled');
        $color = "#70d1f4";
    } elseif ($status_lower === 'completed') {
        $label = ($lang === 'ar' ? 'مكتمل' : 'Completed');
        $color = "#22c55e";
    } elseif ($status_lower === 'canceled') {
        $label = ($lang === 'ar' ? 'ملغى' : 'Canceled');
        $color = "#ef4444";
    }

    return "<span class='status-pill' style='border-color: $color; color: $color;'>$label</span>";
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang==='ar' ? 'مواعيدي' : 'My Appointments' ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">
    <style>
        :root {
            --bg-dark: #0b141e;
            --card-bg: #1c2a39;
            --accent-blue: #70d1f4;
            --accent-red: #ef4444;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            padding: 20px;
        }

        .navbar {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .brand { display: flex; align-items: center; gap: 15px; }
        .brand i { background: var(--accent-blue); color: #fff; padding: 10px; border-radius: 10px; font-size: 20px; }
        .brand h2 { margin: 0; font-size: 20px; }
        .brand p { margin: 0; font-size: 12px; color: var(--text-muted); }

        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .lang-switch { background: #334155; border-radius: 50px; padding: 5px; display: flex; gap: 5px; }
        .lang-switch a { text-decoration: none; color: #fff; padding: 5px 12px; border-radius: 50px; font-size: 12px; }
        .lang-switch a.active { background: var(--accent-blue); color: #000; font-weight: 600; }

        .btn-logout {
            background: var(--accent-red);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .main-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 40px;
            min-height: 80vh;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
        }

        .title-area h1 { margin: 0; font-size: 32px; font-weight: 600; }
        .title-area p { color: var(--text-muted); margin-top: 10px; }

        .header-btns { display: flex; gap: 15px; }
        .btn-dash {
            background: #334155;
            color: #fff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add {
            background: var(--accent-blue);
            color: #0b141e;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            color: var(--accent-blue);
            font-size: 13px;
            padding: 15px;
            border-bottom: 1px solid #334155;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        html[dir="rtl"] th { text-align: right; }

        td { padding: 20px 15px; border-bottom: 1px solid #334155; font-size: 15px; }

        .doctor-name { font-weight: 600; color: #fff; }
        .specialty-tag {
            background: rgba(112, 209, 244, 0.1);
            color: var(--accent-blue);
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
        }

        .status-pill {
            padding: 4px 15px;
            border-radius: 20px;
            border: 1px solid;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-cancel {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid #ef4444;
            color: #ef4444;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-cancel:hover { background: #ef4444; color: #fff; }

        .no-data { text-align: center; padding: 60px; color: var(--text-muted); }
        .logo-icon {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    background: #ffffff;
    border: 1px solid rgba(255,255,255,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 10px 24px rgba(0,0,0,0.16);
}

.logo-icon img {
    width: 58px;
    height: 58px;
    object-fit: contain;
    border-radius: 14px;
    display: block;
}
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
           <div class="logo-icon" id="brandLogo">
                 <img src="assets/Cairo_hospitals1.png?v=2" alt="Cairo Hospitals" onerror="document.getElementById('brandLogo').classList.add('logo-fallback');">
                 <i class="fa-solid fa-hospital"></i>
           </div>
            <div>
                <h2>Cairo Hospitals</h2>
                <p><?= $lang==='ar' ? 'إدارة المواعيد' : 'Manage Appointments' ?></p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                <a href="?lang=ar" class="<?= $lang === 'ar' ? 'active' : '' ?>">AR</a>
            </div>
            <a href="logout.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> <?= $lang==='ar' ? 'خروج' : 'Logout' ?>
            </a>
        </div>
    </div>

    <div class="main-card">
        <div class="content-header">
            <div class="title-area">
                <h1><?= $lang==='ar' ? 'مواعيد المريض' : 'Patient Appointments' ?></h1>
                <p><?= $lang==='ar' ? 'عرض وإلغاء مواعيدك الطبية بكل سهولة.' : 'View and manage your medical visits effortlessly.' ?></p>
            </div>
            <div class="header-btns">
                <a href="dashboard.php" class="btn-dash">
                    <i class="fa-solid fa-chevron-left"></i> <?= $lang==='ar' ? 'لوحة التحكم' : 'Dashboard' ?>
                </a>
                <a href="services.php" class="btn-add">
                    <i class="fa-solid fa-calendar-plus"></i> <?= $lang==='ar' ? 'حجز موعد' : 'Book New' ?>
                </a>
            </div>
        </div>
    <?php if (isset($_GET['booked']) && $_GET['booked'] == '1'): ?>
        <div style="background:#dcfce7;color:#166534;border:1px solid #86efac;padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:700;">
            <?= $lang === 'ar' ? 'تم حجز الموعد بنجاح.' : 'Appointment booked successfully.' ?>
        </div>
    <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?= $lang==='ar' ? 'الطبيب' : 'DOCTOR' ?></th>
                    <th><?= $lang==='ar' ? 'التخصص' : 'SPECIALTY' ?></th>
                    <th><?= $lang==='ar' ? 'التاريخ والوقت' : 'DATE & TIME' ?></th>
                    <th><?= $lang==='ar' ? 'الحالة' : 'STATUS' ?></th>
                    <th><?= $lang==='ar' ? 'إجراء' : 'ACTION' ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($appointments)): ?>
                    <?php foreach($appointments as $row):
                        $dt = strtotime($row['AppointmentDateTime']);
                    ?>
                        <tr>
                            <td style="color: var(--text-muted);">#<?= $row['AppointmentID'] ?></td>
                            <td><span class="doctor-name"><?= htmlspecialchars($row['DoctorName']) ?></span></td>
                            <td><span class="specialty-tag"><?= htmlspecialchars($row['SpecialtyName']) ?></span></td>
                            <td>
                                <div><?= date('Y-m-d', $dt) ?></div>
                                <div style="font-size: 12px; color: var(--text-muted);"><?= date('h:i A', $dt) ?></div>
                            </td>
                            <td><?= formatStatus($row['Status'], $lang) ?></td>
                            <td>
                                <?php if(strtolower($row['Status']) !== 'canceled' && strtolower($row['Status']) !== 'completed'): ?>
                                    <button class="btn-cancel" onclick="confirmCancel(<?= (int)$row['AppointmentID'] ?>)" title="Cancel">
                                        <i class="fa-solid fa-calendar-xmark"></i>
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 12px;">--</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="no-data"><?= $lang==='ar' ? 'لا توجد مواعيد مسجلة' : 'No appointments found.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    function confirmCancel(id){
        let msg = "<?= $lang==='ar' ? 'هل أنت متأكد من إلغاء هذا الموعد؟' : 'Are you sure you want to cancel this appointment?' ?>";
        if(confirm(msg)){
            window.location.href = "my_appointments.php?cancel_id=" + id;
        }
    }
    </script>
</body>
</html>