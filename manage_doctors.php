<?php
session_start();
include("db_connect.php");

// Only admins can access
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

/* -------- Language handling -------- */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$texts = [
    'en' => [
        'hospital_name'        => 'Cairo Hospitals',
        'page_title'           => 'Manage Doctors',
        'heading'              => 'Doctor Management',
        'sub_heading'          => 'View, edit, and manage hospital medical staff records.',
        'back_dashboard'       => 'Dashboard',
        'add_doctor'           => 'Add New Doctor',
        'col_id'               => 'ID',
        'col_name'             => 'Full Name',
        'col_specialty'        => 'Specialty',
        'col_experience'       => 'Experience',
        'col_fee'              => 'Consultation Fee',
        'col_actions'          => 'Actions',
        'no_doctors'           => 'No doctors found.',
        'edit'                 => 'Edit',
        'delete'               => 'Delete',
        'delete_confirm'       => 'Are you sure you want to delete this doctor?',
        'unknown_doctor'       => 'Unknown Doctor',
        'na'                   => 'N/A',
        'yrs_suffix'           => ' yrs',
        'logout'               => 'Logout',
        'search_label'         => 'Search by Doctor ID',
        'search_placeholder'   => 'Enter doctor ID...',
        'search_btn'           => 'Search',
        'clear_btn'            => 'Clear',
        'search_results'       => 'Search results for Doctor ID',
    ],
    'ar' => [
        'hospital_name'        => 'مستشفيات القاهرة',
        'page_title'           => 'إدارة الأطباء',
        'heading'              => 'إدارة الأطباء',
        'sub_heading'          => 'عرض وتعديل وإدارة سجلات الطاقم الطبي بالمستشفى.',
        'back_dashboard'       => 'لوحة التحكم',
        'add_doctor'           => 'إضافة طبيب جديد',
        'col_id'               => 'الرقم',
        'col_name'             => 'الاسم الكامل',
        'col_specialty'        => 'التخصص',
        'col_experience'       => 'الخبرة',
        'col_fee'              => 'أتعاب الكشف',
        'col_actions'          => 'الإجراءات',
        'no_doctors'           => 'لا يوجد أطباء مسجلون.',
        'edit'                 => 'تعديل',
        'delete'               => 'حذف',
        'delete_confirm'       => 'هل أنت متأكد أنك تريد حذف هذا الطبيب؟',
        'unknown_doctor'       => 'طبيب غير معروف',
        'na'                   => 'غير متوفر',
        'yrs_suffix'           => ' سنة',
        'logout'               => 'تسجيل الخروج',
        'search_label'         => 'البحث برقم الطبيب',
        'search_placeholder'   => 'اكتب رقم الطبيب...',
        'search_btn'           => 'بحث',
        'clear_btn'            => 'مسح',
        'search_results'       => 'نتائج البحث عن رقم الطبيب',
    ],
];

$t = $texts[$lang];
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

/* -------- Fetch doctors with Doctor ID search -------- */
$doctorIdSearch = trim($_GET['doctor_id_search'] ?? '');

if ($doctorIdSearch !== '') {
    $stmtDoctors = $conn->prepare("
        SELECT 
            d.EmployeeID,
            e.FirstName,
            e.LastName,
            e.HireDate,
            s.Name AS SpecialtyName,
            d.ConsultationFee
        FROM doctors d
        INNER JOIN employees e ON d.EmployeeID = e.EmployeeID
        INNER JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
        WHERE d.EmployeeID = ?
        ORDER BY e.FirstName, e.LastName
    ");

    if (!$stmtDoctors) {
        die("Database prepare error: " . $conn->error);
    }

    $doctorIdValue = intval($doctorIdSearch);
    $stmtDoctors->bind_param("i", $doctorIdValue);
    $stmtDoctors->execute();
    $result = $stmtDoctors->get_result();
} else {
    $sql = "
        SELECT 
            d.EmployeeID,
            e.FirstName,
            e.LastName,
            e.HireDate,
            s.Name AS SpecialtyName,
            d.ConsultationFee
        FROM doctors d
        INNER JOIN employees e ON d.EmployeeID = e.EmployeeID
        INNER JOIN specialties s ON d.SpecialtyID = s.SpecialtyID
        ORDER BY e.FirstName, e.LastName
    ";
    $result = $conn->query($sql);
}

function lang_link($code) {
    $doctorIdSearch = trim($_GET['doctor_id_search'] ?? '');
    $url = basename($_SERVER['PHP_SELF']) . '?lang=' . urlencode($code);

    if ($doctorIdSearch !== '') {
        $url .= '&doctor_id_search=' . urlencode($doctorIdSearch);
    }

    return $url;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['page_title']) ?> - <?= htmlspecialchars($t['hospital_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg-1: #06131f; --bg-2: #0d2233;
            --card: rgba(255,255,255,0.08);
            --stroke: rgba(255,255,255,0.12);
            --white: #ffffff;
            --text-soft: rgba(255,255,255,0.70);
            --primary: #4cc9f0; --primary-2: #4895ef;
            --danger: #ef4444;
            --radius-xl: 24px; --radius-lg: 16px;
        }

        body {
            margin: 0; font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(135deg, var(--bg-1), var(--bg-2));
            color: var(--white); min-height: 100vh; padding-bottom: 40px;
        }

        .page-shell { width: min(1200px, calc(100% - 32px)); margin: 20px auto; }
        
        .glass {
            background: var(--card); border: 1px solid var(--stroke);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            border-radius: var(--radius-xl);
        }

        .topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; margin-bottom: 24px;
        }

        .brand { display: flex; align-items: center; gap: 12px; }

        .brand-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary), var(--primary-2));
            border-radius: 12px; display: grid; place-items: center; font-size: 20px;
        }

        .topbar-right { display: flex; gap: 12px; align-items: center; }

        .btn {
            padding: 10px 18px; border-radius: 12px; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; font-size: 14px;
            border: none;
            cursor: pointer;
        }

        .btn-primary { background: var(--primary); color: #06131f; }
        .btn-outline { border: 1px solid var(--stroke); color: #fff; background: transparent; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }

        .content-panel { padding: 30px; }

        .panel-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
        }

        .panel-header h2 { margin: 0; font-size: 28px; }
        .panel-header p { margin: 5px 0 0; color: var(--text-soft); }

        .search-box {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 22px;
            padding: 18px;
            border: 1px solid var(--stroke);
            border-radius: var(--radius-lg);
            background: rgba(255,255,255,0.04);
        }

        .search-box label {
            font-size: 14px;
            color: var(--text-soft);
            font-weight: 600;
        }

        .search-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input-wrap {
            flex: 1;
            min-width: 240px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--stroke);
            background: rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 0 14px;
        }

        .search-input-wrap i {
            color: var(--primary);
        }

        .search-input {
            width: 100%;
            padding: 12px 0;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 15px;
        }

        .search-input::placeholder {
            color: rgba(255,255,255,0.45);
        }

        .search-info {
            color: var(--text-soft);
            font-size: 14px;
            margin-top: -5px;
            margin-bottom: 15px;
        }

        .search-info strong {
            color: var(--primary);
        }

        .table-container { overflow-x: auto; margin-top: 10px; }

        table { width: 100%; border-collapse: collapse; min-width: 800px; }

        th { 
            text-align: inherit; padding: 16px; color: var(--primary); 
            border-bottom: 2px solid var(--stroke); font-size: 14px; text-transform: uppercase;
        }

        td { padding: 16px; border-bottom: 1px solid var(--stroke); font-size: 15px; }
        tr:hover td { background: rgba(255,255,255,0.03); }

        .lang-toggle a {
            color: #fff; text-decoration: none; font-weight: bold; padding: 5px 10px;
            border-radius: 5px; border: 1px solid var(--stroke); font-size: 12px;
        }

        .lang-toggle a.active { background: var(--primary); color: #000; }

        @media (max-width: 768px) {
            .panel-header { flex-direction: column; align-items: flex-start; }
            .topbar { flex-direction: column; gap: 15px; }
            .search-row { flex-direction: column; align-items: stretch; }
            .search-input-wrap { min-width: unset; }
        }
    </style>
</head>
<body>

<div class="page-shell">
    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon"><i class="fa-solid fa-user-doctor"></i></div>
            <div>
                <div style="font-weight: 800; font-size: 18px;"><?= htmlspecialchars($t['hospital_name']) ?></div>
                <div style="font-size: 12px; color: var(--text-soft);"><?= htmlspecialchars($t['page_title']) ?></div>
            </div>
        </div>

        <div class="topbar-right">
            <div class="lang-toggle">
                <a href="<?= lang_link('en') ?>" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
                <a href="<?= lang_link('ar') ?>" class="<?= $lang === 'ar' ? 'active' : '' ?>">AR</a>
            </div>

            <a href="logout.php" class="btn btn-danger" style="padding: 8px 14px; font-size: 12px;">
                <i class="fa-solid fa-power-off"></i> <?= htmlspecialchars($t['logout']) ?>
            </a>
        </div>
    </header>

    <main class="content-panel glass">
        <div class="panel-header">
            <div>
                <h2><?= htmlspecialchars($t['heading']) ?></h2>
                <p><?= htmlspecialchars($t['sub_heading']) ?></p>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="admin_dashboard.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars($t['back_dashboard']) ?>
                </a>

                <a href="add_doctor.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t['add_doctor']) ?>
                </a>
            </div>
        </div>

        <form method="GET" class="search-box">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">

            <label><?= htmlspecialchars($t['search_label']) ?></label>

            <div class="search-row">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-id-badge"></i>
                    <input
                        class="search-input"
                        type="number"
                        name="doctor_id_search"
                        value="<?= htmlspecialchars($doctorIdSearch) ?>"
                        placeholder="<?= htmlspecialchars($t['search_placeholder']) ?>"
                        autocomplete="off"
                        min="1"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <?= htmlspecialchars($t['search_btn']) ?>
                </button>

                <?php if ($doctorIdSearch !== ''): ?>
                    <a href="?lang=<?= urlencode($lang) ?>" class="btn btn-outline">
                        <i class="fa-solid fa-xmark"></i>
                        <?= htmlspecialchars($t['clear_btn']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($doctorIdSearch !== ''): ?>
            <div class="search-info">
                <?= htmlspecialchars($t['search_results']) ?>:
                <strong>#<?= htmlspecialchars($doctorIdSearch) ?></strong>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($t['col_id']) ?></th>
                        <th><?= htmlspecialchars($t['col_name']) ?></th>
                        <th><?= htmlspecialchars($t['col_specialty']) ?></th>
                        <th><?= htmlspecialchars($t['col_experience']) ?></th>
                        <th><?= htmlspecialchars($t['col_fee']) ?></th>
                        <th style="text-align: center;"><?= htmlspecialchars($t['col_actions']) ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $fullName = trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? ''));

                            $experience = "0" . $t['yrs_suffix'];
                            if (!empty($row['HireDate'])) {
                                $diff = (new DateTime())->diff(new DateTime($row['HireDate']))->y;
                                $experience = $diff . $t['yrs_suffix'];
                            }
                        ?>
                            <tr>
                                <td>#<?= (int)$row['EmployeeID'] ?></td>

                                <td style="font-weight: 600;">
                                    <?= htmlspecialchars($fullName ?: $t['unknown_doctor']) ?>
                                </td>

                                <td>
                                    <span style="background: rgba(76,201,240,0.1); padding: 4px 10px; border-radius: 8px; color: var(--primary); font-size: 13px;">
                                        <?= htmlspecialchars($row['SpecialtyName'] ?? $t['na']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($experience) ?></td>

                                <td><?= number_format((float)$row['ConsultationFee'], 2) ?> EGP</td>

                                <td style="text-align: center;">
                                    <a href="edit_doctor.php?id=<?= (int)$row['EmployeeID'] ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-color: var(--primary); color: var(--primary);">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <a href="delete_doctor.php?id=<?= (int)$row['EmployeeID'] ?>" class="btn btn-outline" style="padding: 6px 12px; font-size: 12px; border-color: var(--danger); color: var(--danger);" onclick="return confirm('<?= htmlspecialchars($t['delete_confirm'], ENT_QUOTES) ?>');">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; color: var(--text-soft); text-align: center;">
                                <?= htmlspecialchars($t['no_doctors']) ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>