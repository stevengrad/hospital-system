<?php
session_start();

// Only admins can access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

include("db_connect.php");

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'   => 'Edit Service | Cairo Hospitals',
        'heading'      => 'Modify Medical Service',
        'subheading'   => 'Update specialty details, descriptions, and pricing.',
        'back'         => 'Back to Services',
        'name'         => 'Service / Specialty Name',
        'desc'         => 'Detailed Description',
        'price'        => 'Consultation Price (EGP)',
        'save'         => 'Save Changes',
        'not_found'    => 'Service not found.',
        'fill_all'     => 'Please fill all fields correctly.',
        'success'      => 'Service updated successfully.',
        'dashboard'    => 'Dashboard',
        'logout'       => 'Logout'
    ],
    'ar' => [
        'page_title'   => 'تعديل خدمة | مستشفيات القاهرة',
        'heading'      => 'تعديل الخدمة الطبية',
        'subheading'   => 'تحديث تفاصيل التخصص، الوصف، والأسعار.',
        'back'         => 'العودة للخدمات',
        'name'         => 'اسم الخدمة / التخصص',
        'desc'         => 'الوصف التفصيلي',
        'price'        => 'سعر الكشف (جنيه)',
        'save'         => 'حفظ التعديلات',
        'not_found'    => 'الخدمة غير موجودة.',
        'fill_all'     => 'يرجى ملء كل الحقول بشكل صحيح.',
        'success'      => 'تم تحديث الخدمة بنجاح.',
        'dashboard'    => 'لوحة التحكم',
        'logout'       => 'خروج'
    ]
];

$t = $texts[$lang];
$error = '';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die($t['not_found']);

// helper for toggle link
function lang_link_edit_service($code, $id) {
    $self = basename($_SERVER['PHP_SELF']);
    return $self . '?id=' . urlencode($id) . '&lang=' . $code;
}

// Load specialty
$stmt = $conn->prepare("SELECT SpecialtyID, Name, Description, Price FROM specialties WHERE SpecialtyID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) die($t['not_found']);

// Update logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');

    if ($name === '' || $description === '' || $price === '' || !is_numeric($price)) {
        $error = $t['fill_all'];
    } else {
        $stmt = $conn->prepare("UPDATE specialties SET Name = ?, Description = ?, Price = ? WHERE SpecialtyID = ?");
        $stmt->bind_param("ssdi", $name, $description, $price, $id);
        if ($stmt->execute()) {
            header("Location: manage_services.php?msg=" . urlencode($t['success']) . "&type=success");
            exit();
        } else {
            $error = $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['page_title'] ?></title>
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
            max-width: 650px;
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

        textarea { height: 120px; resize: none; }

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

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
            border: 1px solid #f87171;
        }

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
            <i class="fa-solid fa-pen-to-square"></i>
            <div>
                <h2 style="margin:0; font-size:18px;">Cairo Hospitals</h2>
                <p style="margin:0; font-size:11px; color:var(--text-muted)">Service ID: #<?= $id ?></p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="<?= lang_link_edit_service('en', $id) ?>" class="<?= $lang=='en'?'active':'' ?>">EN</a>
                <a href="<?= lang_link_edit_service('ar', $id) ?>" class="<?= $lang=='ar'?'active':'' ?>">AR</a>
            </div>
            <a href="admin_dashboard.php" class="btn-nav"><i class="fa-solid fa-chart-line"></i> <?= $t['dashboard'] ?></a>
            <a href="manage_services.php" class="btn-nav" style="background:var(--accent-blue); color:#000;"><i class="fa-solid fa-arrow-left"></i> <?= $t['back'] ?></a>
        </div>
    </div>

    <div class="glass-card">
        <h1><?= htmlspecialchars($t['heading']) ?></h1>
        <p class="subheading"><?= htmlspecialchars($t['subheading']) ?></p>

        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><?= $t['name'] ?></label>
                <input type="text" name="name" value="<?= htmlspecialchars($service['Name']) ?>" required>
            </div>

            <div class="form-group">
                <label><?= $t['desc'] ?></label>
                <textarea name="description" required><?= htmlspecialchars($service['Description']) ?></textarea>
            </div>

            <div class="form-group">
                <label><?= $t['price'] ?></label>
                <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($service['Price']) ?>" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> <?= $t['save'] ?>
            </button>
        </form>

        <a href="manage_services.php" class="back-link"><?= $t['back'] ?></a>
    </div>

</body>
</html>