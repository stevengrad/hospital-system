<?php
session_start();
include 'db_connect.php';

// 🔐 Admin only
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

/* ---------- Language handling ---------- */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'     => 'Manage Reports',
        'heading'        => 'Patient History',
        'subheading'     => 'Record medical diagnosis and treatment history.',
        'back'           => 'Dashboard',
        'logout'         => 'Logout',
        'select_patient' => 'Select Patient',
        'choose_patient' => '-- Choose Patient --',
        'visit_date'     => 'Visit Date & Time',
        'doctor_name'    => 'Doctor Name',
        'diagnosis'      => 'Diagnosis',
        'treatment'      => 'Treatment',
        'save'           => 'Save Medical Report',
        'msg_success'    => '✅ Patient history added successfully!',
        'msg_fill'       => '⚠️ Please fill all fields!',
        'msg_db'         => '⚠️ Database error: ',
    ],
    'ar' => [
        'page_title'     => 'إدارة التقارير',
        'heading'        => 'سجل المريض',
        'subheading'     => 'تسجيل التشخيص الطبي وتاريخ العلاج.',
        'back'           => 'لوحة التحكم',
        'logout'         => 'خروج',
        'select_patient' => 'اختر المريض',
        'choose_patient' => '-- اختر المريض --',
        'visit_date'     => 'تاريخ ووقت الزيارة',
        'doctor_name'    => 'اسم الطبيب',
        'diagnosis'      => 'التشخيص',
        'treatment'      => 'العلاج',
        'save'           => 'حفظ التقرير الطبي',
        'msg_success'    => '✅ تم إضافة سجل المريض بنجاح!',
        'msg_fill'       => '⚠️ يرجى ملء جميع الحقول!',
        'msg_db'         => '⚠️ خطأ في قاعدة البيانات: ',
    ]
];

$t = $texts[$lang];
$success = $error = "";

/* ---- SAVE NEW REPORT ---- */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_username = trim($_POST['patient_username'] ?? '');
    $visit_date       = $_POST['visit_date'] ?? date('Y-m-d H:i:s');
    $doctor_name      = trim($_POST['doctor_name'] ?? '');
    $diagnosis        = trim($_POST['diagnosis'] ?? '');
    $treatment        = trim($_POST['treatment'] ?? '');

    if ($patient_username !== '' && $doctor_name !== '' && $diagnosis !== '' && $treatment !== '') {
        $stmt = $conn->prepare("INSERT INTO patient_history (patient_username, visit_date, doctor_name, diagnosis, treatment) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssss", $patient_username, $visit_date, $doctor_name, $diagnosis, $treatment);
            if ($stmt->execute()) { $success = $t['msg_success']; } 
            else { $error = $t['msg_db'] . $stmt->error; }
            $stmt->close();
        }
    } else { $error = $t['msg_fill']; }
}

/* ---- GET PATIENT LIST ---- */
$patients = $conn->query("SELECT PatientID, NationalID, FirstName, LastName FROM patients ORDER BY FirstName ASC");
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($t['page_title']) ?></title>
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

        /* Top Navbar Customization */
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

        /* Content Layout */
        .glass-card {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        .btn-dashboard { background: #334155; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px; }

        /* Form Styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group { display: flex; flex-direction: column; gap: 10px; }
        label { font-size: 13px; color: var(--accent-blue); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        
        input, select, textarea {
            background: var(--input-bg);
            border: 1px solid #334155;
            padding: 14px;
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            font-size: 14px;
        }
        textarea { min-height: 120px; resize: none; }

        .btn-save { 
            background: var(--accent-blue); 
            color: #0b141e; 
            border: none; 
            padding: 15px 40px; 
            border-radius: 12px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.3s; 
            width: 100%;
            font-size: 16px;
        }
        .btn-save:hover { opacity: 0.9; transform: translateY(-2px); }

        .status-msg { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; text-align: center; }
        .success { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid #4ade80; }
        .error { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid #f87171; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-file-medical"></i>
            <div>
                <h2>Cairo Hospitals</h2>
                <p style="margin:0; font-size:12px; color:var(--text-muted)">Medical Records</p>
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
                <h1 style="margin:0; color:var(--text-main);"><?= $t['heading'] ?> 🩺</h1>
                <p style="color:var(--text-muted); margin:5px 0 0 0"><?= $t['subheading'] ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn-dashboard"><i class="fa-solid fa-house"></i> <?= $t['back'] ?></a>
        </div>

        <?php if ($success): ?>
            <div class="status-msg success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="status-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label><?= $t['select_patient'] ?></label>
                    <select name="patient_username" required>
                        <option value=""><?= $t['choose_patient'] ?></option>
                        <?php while($p = $patients->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($p['NationalID']) ?>">
                                <?= htmlspecialchars($p['FirstName'].' '.$p['LastName']) ?> (<?= htmlspecialchars($p['NationalID']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= $t['visit_date'] ?></label>
                    <input type="datetime-local" name="visit_date" required>
                </div>

                <div class="form-group">
                    <label><?= $t['doctor_name'] ?></label>
                    <input type="text" name="doctor_name" placeholder="Dr. Name..." required>
                </div>
            </div>

            <div class="form-group" style="margin-top:20px;">
                <label><?= $t['diagnosis'] ?></label>
                <textarea name="diagnosis" placeholder="Describe the medical condition..." required></textarea>
            </div>

            <div class="form-group" style="margin-top:20px; margin-bottom:30px;">
                <label><?= $t['treatment'] ?></label>
                <textarea name="treatment" placeholder="Prescribed medicine or procedures..." required></textarea>
            </div>

            <button type="submit" class="btn-save"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $t['save'] ?></button>
        </form>
    </div>

</body>
</html>