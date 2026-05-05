<?php
session_start();
include("db_connect.php");

// 🔐 Admin-only access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

/* ----------------- Language handling ----------------- */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'title'      => 'Manage Appointments - Admin',
        'heading'    => 'Appointment Management',
        'subheading' => 'Add, view, and update hospital appointment records.',
        'add_new'    => 'Add New Appointment',
        'patient'    => 'Patient',
        'patient_opt'=> '-- Select Patient --',
        'doctor'     => 'Doctor',
        'doctor_opt' => '-- Select Doctor --',
        'branch'     => 'Branch',
        'branch_opt' => '-- Select Branch --',
        'datetime'   => 'Appointment Date & Time',
        'status'     => 'Status',
        'status_pending'   => 'Pending',
        'status_scheduled' => 'Scheduled',
        'status_completed' => 'Completed',
        'status_canceled'  => 'Canceled',
        'status_rejected'  => 'Rejected',
        'status_noshow'    => 'NoShow',
        'btn_add'    => 'Add Appointment',
        'existing'   => 'Existing Appointments',
        'col_id'     => 'ID',
        'col_patient'=> 'Patient',
        'col_doctor' => 'Doctor',
        'col_branch' => 'Branch',
        'col_dt'     => 'Date & Time',
        'col_status' => 'Status',
        'col_actions'=> 'Actions',
        'btn_update' => 'Update',
        'btn_delete' => 'Delete',
        'confirm_del'=> 'Are you sure?',
        'back'       => 'Dashboard',
        'logout'     => 'Logout',
        'no_appts'   => 'No appointments found.'
    ],
    'ar' => [
        'title'      => 'إدارة المواعيد - المسؤول',
        'heading'    => 'إدارة المواعيد',
        'subheading' => 'إضافة وعرض وتحديث سجلات المواعيد.',
        'add_new'    => 'إضافة موعد جديد',
        'patient'    => 'المريض',
        'patient_opt'=> '-- اختر المريض --',
        'doctor'     => 'الطبيب',
        'doctor_opt' => '-- اختر الطبيب --',
        'branch'     => 'الفرع',
        'branch_opt' => '-- اختر الفرع --',
        'datetime'   => 'تاريخ ووقت الموعد',
        'status'     => 'الحالة',
        'status_pending'   => 'قيد الانتظار',
        'status_scheduled' => 'مجدول',
        'status_completed' => 'مكتمل',
        'status_canceled'  => 'ملغى',
        'status_rejected'  => 'مرفوض',
        'status_noshow'    => 'لم يحضر',
        'btn_add'    => 'إضافة الموعد',
        'existing'   => 'المواعيد الحالية',
        'col_id'     => 'الرقم',
        'col_patient'=> 'المريض',
        'col_doctor' => 'الطبيب',
        'col_branch' => 'الفرع',
        'col_dt'     => 'التاريخ والوقت',
        'col_status' => 'الحالة',
        'col_actions'=> 'العمليات',
        'btn_update' => 'تحديث',
        'btn_delete' => 'حذف',
        'confirm_del'=> 'هل أنت متأكد؟',
        'back'       => 'لوحة التحكم',
        'logout'     => 'خروج',
        'no_appts'   => 'لا توجد مواعيد.'
    ]
];

$t = $texts[$lang];
$msg = "";

/* -------- helper: status label -------- */
function status_label_local($status, $lang, $texts) {
    $status = trim($status);
    $key = 'status_' . strtolower($status);
    return $texts[$lang][$key] ?? $status;
}

/* ---------- Fetch dropdown data ---------- */
$patients = $conn->query("SELECT PatientID, FirstName, LastName FROM patients ORDER BY FirstName, LastName");
$doctors  = $conn->query("SELECT d.EmployeeID AS DoctorID, CONCAT(e.FirstName, ' ', e.LastName) AS DoctorName, s.Name AS SpecialtyName FROM doctors d INNER JOIN employees e ON d.EmployeeID = e.EmployeeID INNER JOIN specialties s ON d.SpecialtyID = s.SpecialtyID ORDER BY DoctorName");
$branches = $conn->query("SELECT BranchID, Name FROM branches ORDER BY Name");

/* ---------- Logic for Add / Update / Delete (remains same as your version) ---------- */
// ... [Your existing POST logic for add_appointment, update_appointment, and delete] ...

$result = $conn->query("SELECT a.AppointmentID, a.AppointmentDateTime, a.Status, CONCAT(p.FirstName, ' ', p.LastName) AS PatientName, CONCAT(e.FirstName, ' ', e.LastName) AS DoctorName, b.Name AS BranchName FROM appointments a INNER JOIN patients p ON a.PatientID = p.PatientID INNER JOIN employees e ON a.DoctorID = e.EmployeeID INNER JOIN branches b ON a.BranchID = b.BranchID ORDER BY a.AppointmentDateTime DESC");
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($t['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0b141e;
            --card-bg: #1c2a39;
            --accent-blue: #70d1f4;
            --accent-red: #ef4444;
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: #253447;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            padding: 20px;
        }

        /* Top Navbar */
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

        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .lang-switch { background: #334155; border-radius: 50px; padding: 5px; display: flex; gap: 5px; }
        .lang-switch a { text-decoration: none; color: #fff; padding: 5px 12px; border-radius: 50px; font-size: 12px; }
        .lang-switch a.active { background: var(--accent-blue); color: #000; font-weight: 600; }
        
        .btn-logout { background: var(--accent-red); color: #fff; border: none; padding: 8px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 14px; }

        /* Main Content */
        .glass-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .btn-dashboard { background: #334155; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px; }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        label { font-size: 13px; color: var(--text-muted); }
        
        input, select {
            background: var(--input-bg);
            border: 1px solid #334155;
            padding: 12px;
            border-radius: 10px;
            color: #fff;
            font-family: inherit;
        }

        .btn-add { background: var(--accent-blue); color: #0b141e; border: none; padding: 12px 30px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; }

        /* Table Styling */
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: var(--accent-blue); font-size: 12px; padding: 15px; border-bottom: 1px solid #334155; text-transform: uppercase; letter-spacing: 1px; }
        html[dir="rtl"] th { text-align: right; }
        td { padding: 15px; border-bottom: 1px solid #334155; font-size: 14px; }

        .status-badge { color: var(--accent-blue); font-weight: 600; }
        
        .action-container { display: flex; align-items: center; gap: 10px; }
        .status-select-small { padding: 5px; font-size: 12px; width: auto; }
        .btn-upd { background: transparent; border: 1px solid var(--accent-blue); color: var(--accent-blue); padding: 5px 10px; border-radius: 8px; font-size: 12px; cursor: pointer; }
        .btn-del { color: var(--accent-red); text-decoration: none; font-size: 18px; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-hospital"></i>
            <div>
                <h2>Cairo Hospitals</h2>
                <p style="margin:0; font-size:12px; color:var(--text-muted)">Dashboard</p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $lang==='en'?'active':'' ?>">EN</a>
                <a href="?lang=ar" class="<?= $lang==='ar'?'active':'' ?>">AR</a>
            </div>
            <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> <?= $t['logout'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <div class="header-row">
            <div>
                <h1 style="margin:0"><?= $t['heading'] ?></h1>
                <p style="color:var(--text-muted); margin:5px 0 0 0"><?= $t['subheading'] ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> <?= $t['back'] ?></a>
        </div>

        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= $t['patient'] ?></label>
                    <select name="patient_id" required>
                        <option value=""><?= $t['patient_opt'] ?></option>
                        <?php while($p = $patients->fetch_assoc()): ?>
                            <option value="<?= $p['PatientID'] ?>"><?= $p['FirstName'].' '.$p['LastName'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['doctor'] ?></label>
                    <select name="doctor_id" required>
                        <option value=""><?= $t['doctor_opt'] ?></option>
                        <?php while($d = $doctors->fetch_assoc()): ?>
                            <option value="<?= $d['DoctorID'] ?>"><?= $d['DoctorName'] ?> (<?= $d['SpecialtyName'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['datetime'] ?></label>
                    <input type="datetime-local" name="appointment_datetime" required>
                </div>
                <div class="form-group">
                    <label><?= $t['status'] ?></label>
                    <select name="status" required>
                        <option value="Pending"><?= $t['status_pending'] ?></option>
                        <option value="Scheduled"><?= $t['status_scheduled'] ?></option>
                        <option value="Completed"><?= $t['status_completed'] ?></option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_appointment" class="btn-add">+ <?= $t['btn_add'] ?></button>
        </form>
    </div>

    <div class="glass-card">
        <h3 style="color:var(--accent-blue); margin-top:0"><?= $t['existing'] ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?= $t['col_id'] ?></th>
                    <th><?= $t['col_patient'] ?></th>
                    <th><?= $t['col_doctor'] ?></th>
                    <th><?= $t['col_dt'] ?></th>
                    <th><?= $t['col_status'] ?></th>
                    <th><?= $t['col_actions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="color:var(--text-muted)">#<?= $row['AppointmentID'] ?></td>
                        <td><b><?= htmlspecialchars($row['PatientName']) ?></b></td>
                        <td><?= htmlspecialchars($row['DoctorName']) ?></td>
                        <td><?= date('M d, Y - H:i', strtotime($row['AppointmentDateTime'])) ?></td>
                        <td><span class="status-badge"><?= status_label_local($row['Status'], $lang, $texts) ?></span></td>
                        <td>
                            <form method="POST" class="action-container">
                                <input type="hidden" name="appointment_id" value="<?= $row['AppointmentID'] ?>">
                                <select name="status" class="status-select-small">
                                    <option value="Pending" <?= $row['Status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="Scheduled" <?= $row['Status']=='Scheduled'?'selected':'' ?>>Scheduled</option>
                                    <option value="Completed" <?= $row['Status']=='Completed'?'selected':'' ?>>Completed</option>
                                </select>
                                <button type="submit" name="update_appointment" class="btn-upd"><?= $t['btn_update'] ?></button>
                                <a href="?delete=<?= $row['AppointmentID'] ?>" class="btn-del" onclick="return confirm('<?= $t['confirm_del'] ?>')"><i class="fa-solid fa-trash-can"></i></a>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;"><?= $t['no_appts'] ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>