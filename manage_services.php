<?php
session_start();
include("db_connect.php");

// Only admins can access
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'     => 'Manage Services',
        'heading'        => 'Service Management',
        'subheading'     => 'View, edit, and manage hospital medical service records.',
        'btn_dashboard'  => 'Dashboard',
        'btn_add'        => 'Add New Service',
        'col_id'         => 'ID',
        'col_name'       => 'SERVICE NAME',
        'col_desc'       => 'DESCRIPTION',
        'col_price'      => 'PRICE',
        'col_actions'    => 'ACTIONS',
        'lang_en'        => 'EN',
        'lang_ar'        => 'AR',
        'logout'         => 'Logout'
    ],
    'ar' => [
        'page_title'     => 'إدارة الخدمات',
        'heading'        => 'إدارة الخدمات الطبية',
        'subheading'     => 'عرض وتعديل وإدارة سجلات الخدمات الطبية بالمستشفى.',
        'btn_dashboard'  => 'لوحة التحكم',
        'btn_add'        => 'إضافة خدمة جديدة',
        'col_id'         => 'المعرف',
        'col_name'       => 'اسم الخدمة',
        'col_desc'       => 'الوصف',
        'col_price'      => 'السعر',
        'col_actions'    => 'الإجراءات',
        'lang_en'        => 'EN',
        'lang_ar'        => 'AR',
        'logout'         => 'تسجيل الخروج'
    ]
];
$t = $texts[$lang];

$sql = "SELECT SpecialtyID, Name, Description, Price FROM specialties ORDER BY Name ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($t['page_title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        /* Top Header Bar */
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
        
        .btn-logout { background: var(--accent-red); color: #fff; border: none; padding: 8px 20px; border-radius: 12px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Main Content Card */
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
        .btn-secondary { background: #334155; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent-blue); color: #0b141e; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            text-align: left;
            color: var(--accent-blue);
            font-size: 13px;
            padding: 15px;
            border-bottom: 1px solid #334155;
            letter-spacing: 1px;
        }
        
        html[dir="rtl"] th { text-align: right; }

        td {
            padding: 20px 15px;
            border-bottom: 1px solid #334155;
            font-size: 15px;
            vertical-align: middle;
        }

        .service-id { color: var(--text-muted); }
        .service-name { font-weight: 600; }
        .price-badge { color: #fff; font-weight: 400; }

        /* Action Buttons */
        .actions { display: flex; gap: 10px; }
        .btn-action {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid #334155;
            transition: 0.3s;
        }
        .btn-edit { color: var(--accent-blue); }
        .btn-edit:hover { background: var(--accent-blue); color: #000; }
        .btn-delete { color: var(--accent-red); }
        .btn-delete:hover { background: var(--accent-red); color: #fff; }

        .empty-row { text-align: center; color: var(--text-muted); padding: 40px; }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">
            <i class="fa-solid fa-hospital"></i>
            <div>
                <h2>Cairo Hospitals</h2>
                <p>Dashboard</p>
            </div>
        </div>
        <div class="nav-controls">
            <div class="lang-switch">
                <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>"><?= $t['lang_en'] ?></a>
                <a href="?lang=ar" class="<?= $lang === 'ar' ? 'active' : '' ?>"><?= $t['lang_ar'] ?></a>
            </div>
            <a href="logout.php" class="btn-logout">
                <i class="fa-solid fa-right-from-bracket"></i> <?= $t['logout'] ?>
            </a>
        </div>
    </div>

    <div class="main-card">
        <div class="content-header">
            <div class="title-area">
                <h1><?= $t['heading'] ?></h1>
                <p><?= $t['subheading'] ?></p>
            </div>
            <div class="header-btns">
                <a href="admin_dashboard.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> <?= $t['btn_dashboard'] ?>
                </a>
                <a href="add_service.php" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> <?= $t['btn_add'] ?>
                </a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th><?= $t['col_id'] ?></th>
                    <th><?= $t['col_name'] ?></th>
                    <th><?= $t['col_desc'] ?></th>
                    <th><?= $t['col_price'] ?></th>
                    <th><?= $t['col_actions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="service-id">#<?= $row['SpecialtyID'] ?></td>
                            <td class="service-name"><?= htmlspecialchars($row['Name']) ?></td>
                            <td style="color: var(--text-muted); font-size: 13px;">
                                <?= htmlspecialchars($row['Description'] ?? '') ?>
                            </td>
                            <td class="price-badge">
                                <?= number_format((float)$row['Price'], 2) ?> EGP
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="edit_service.php?id=<?= $row['SpecialtyID'] ?>" class="btn-action btn-edit" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <a href="delete_service.php?id=<?= $row['SpecialtyID'] ?>" 
                                       class="btn-action btn-delete" 
                                       onclick="return confirm('Are you sure?');" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-row">No services found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>