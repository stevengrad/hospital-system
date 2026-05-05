<?php
session_start();
include 'db_connect.php';

// 🔐 Only admin can access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

// 🌐 Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'   => 'Add Service | Cairo Hospitals',
        'heading'      => 'Register New Specialty',
        'subheading'   => 'Expand the medical services offered by the hospital network.',
        'name'         => 'Specialty / Service Name',
        'desc'         => 'Detailed Description',
        'price'        => 'Consultation Price (EGP)',
        'save'         => 'Save Service',
        'back_manage'  => 'Back to Services',
        'msg_success'  => 'Service added successfully.',
        'msg_error'    => 'Please fill all required fields and ensure price is valid.',
        'dashboard'    => 'Dashboard',
        'logout'       => 'Logout'
    ],
    'ar' => [
        'page_title'   => 'إضافة خدمة | مستشفيات القاهرة',
        'heading'      => 'تسجيل تخصص جديد',
        'subheading'   => 'توسيع الخدمات الطبية المقدمة عبر شبكة المستشفيات.',
        'name'         => 'اسم التخصص / الخدمة',
        'desc'         => 'الوصف التفصيلي',
        'price'        => 'سعر الكشف (جنيه)',
        'save'         => 'حفظ الخدمة',
        'back_manage'  => 'العودة للخدمات',
        'msg_success'  => 'تمت إضافة الخدمة بنجاح.',
        'msg_error'    => 'يرجى ملء جميع الحقول المطلوبة والتأكد من أن السعر صحيح.',
        'dashboard'    => 'لوحة التحكم',
        'logout'       => 'خروج'
    ]
];

$t = $texts[$lang];

$success = '';
$error   = '';
$service_name = '';
$description  = '';
$price        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_name = trim($_POST['service_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = trim($_POST['price'] ?? '');

    if ($service_name === '' || $price === '' || !is_numeric($price)) {
        $error = $t['msg_error'];
    } else {
        $price_val = (float)$price;
        $stmt = $conn->prepare("INSERT INTO specialties (Name, Description, Price) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssd", $service_name, $description, $price_val);
            if ($stmt->execute()) {
                $success = $t['msg_success'];
                $service_name = ''; $description = ''; $price = '';
            } else {
                $error = "DB error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
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
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --input-bg: #253447;
            --success-green: #4ade80;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        /* Navbar */
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
        .brand i { background: var(--accent-blue); color: #0b141e; padding: 10px; border-radius: 10px; font-size: 20px; }
        
        .nav-controls { display: flex; align-items: center; gap: 15px; }
        .btn-nav { background: #334155; color: #fff; text-decoration: none; padding: 8px 18px; border-radius: 12px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Form Card */
        .glass-card {
            background: var(--card-bg);
            border-radius: 30px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.05);
        }

        h1 { font-size: 24px; margin: 0; }
        .subheading { color: var(--text-muted); font-size: 13px; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; text-align: left; }
        html[dir="rtl"] .form-group { text-align: right; }

        label {
            display: block;
            font-size: 11px;
            color: var(--accent-blue);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input, textarea {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 14px;
            color: #fff;
            font-family: inherit;
            box-sizing: border-box;
            transition: 0.3s;
        }

        textarea { height: 100px; resize: none; }

        input:focus, textarea:focus {
            border-color: var(--accent-blue);
            outline: none;
            box-shadow: 0 0 0 4px rgba(112, 209, 244, 0.1);
        }

        .btn-submit {
            width: 100%;
            background: var(--accent-blue);
            color: #0b141e;
            border: none;
            padding: 16px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(112, 209, 244, 0.2);
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
        .alert-success { background: rgba(74, 222, 128, 0.1); color: var(--success-green); border: 1px solid var(--success-green); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid #f87171; }

        .lang-switch { display: flex; gap: 5px; background: #334155; padding: 4px; border-radius: 50px; }
        .lang-switch a { text-decoration: none; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 50px; }
        .lang-switch a.active { background: var(--accent-blue); color: #0b141e; font-weight: bold; }

        .back-link { display: block; text-align: center; margin-top: 25px; color: var(--text-muted); text-decoration: none; font-size: 14px; }
        .back-link:hover { color: var(--accent-blue); }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-pills"></i>
            <div>
                <h2 style="margin:0; font-size:18px;">Cairo Hospitals</h2>
                <p style="margin:0; font-size:11px; color:var(--text-muted)">Management Portal</p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $lang=='en'?'active':'' ?>">EN</a>
                <a href="?lang=ar" class="<?= $lang=='ar'?'active':'' ?>">AR</a>
            </div>
            <a href="admin_dashboard.php" class="btn-nav"><i class="fa-solid fa-chart-line"></i> <?= $t['dashboard'] ?></a>
            <a href="logout.php" class="btn-nav" style="background:var(--accent-blue); color:#000;"><i class="fa-solid fa-power-off"></i> <?= $t['logout'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <h1><?= htmlspecialchars($t['heading']) ?></h1>
        <p class="subheading"><?= htmlspecialchars($t['subheading']) ?></p>

        <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>
        <?php if ($error): ?> <div class="alert alert-error"><?= $error ?></div> <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label><?= $t['name'] ?></label>
                <input type="text" id="service_name" name="service_name" value="<?= htmlspecialchars($service_name) ?>" placeholder="e.g. Cardiology" required>
            </div>

            <div class="form-group">
                <label><?= $t['desc'] ?></label>
                <textarea id="description" name="description" placeholder="Brief details about this specialty..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="form-group">
                <label><?= $t['price'] ?></label>
                <input type="text" id="price" name="price" value="<?= htmlspecialchars($price) ?>" placeholder="0.00" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-circle-plus"></i> <?= $t['save'] ?>
            </button>
        </form>

        <a href="manage_services.php" class="back-link"><?= $t['back_manage'] ?></a>
    </div>

    <script>
        // Price field validation (Numbers + Dot only)
        document.getElementById('price').addEventListener('input', function(){
            this.value = this.value.replace(/[^0-9.]/g,'');
        });
    </script>
</body>
</html>