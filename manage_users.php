<?php
session_start();
include 'db_connect.php';

/* -------------------------
   1) ADMIN-ONLY ACCESS
-------------------------- */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strtolower($_SESSION['role']) !== 'admin'
) {
    header("Location: admin_login.php");
    exit();
}

/* -------------------------
   2) LANGUAGE HANDLING
-------------------------- */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';
$dir  = ($lang === 'ar') ? 'rtl' : 'ltr';

$texts = [
    'en' => [
        'page_title'     => 'Manage Users',
        'back_dashboard' => 'Back to Dashboard',
        'english'        => 'English',
        'arabic'         => 'العربية',
        'col_id'         => 'ID',
        'col_username'   => 'Username',
        'col_national'   => 'National ID',
        'col_role'       => 'Role',
        'col_actions'    => 'Actions',
        'btn_edit'       => 'Edit',
        'btn_delete'     => 'Delete',
        'role_admin'     => 'Admin',
        'role_doctor'    => 'Doctor',
        'role_patient'   => 'Patient',
        'title_icon'     => '👤',
        'search_label'   => 'Search by Username or National ID',
'search_placeholder' => 'Enter username or national ID...',
        'search_btn'     => 'Search',
        'clear_btn'      => 'Clear',
        'search_results' => 'Search results for',
    ],
    'ar' => [
        'page_title'     => 'إدارة المستخدمين',
        'back_dashboard' => 'العودة إلى لوحة التحكم',
        'english'        => 'English',
        'arabic'         => 'العربية',
        'col_id'         => 'المعرف',
        'col_username'   => 'اسم المستخدم',
        'col_national'   => 'الرقم القومي',
        'col_role'       => 'الدور',
        'col_actions'    => 'الإجراءات',
        'btn_edit'       => 'تعديل',
        'btn_delete'     => 'حذف',
        'role_admin'     => 'مسؤول',
        'role_doctor'    => 'طبيب',
        'role_patient'   => 'مريض',
        'title_icon'     => '👤',
        'search_label'   => 'البحث باسم المستخدم أو الرقم القومي',
'search_placeholder' => 'اكتب اسم المستخدم أو الرقم القومي...',
        'search_btn'     => 'بحث',
        'clear_btn'      => 'مسح',
        'search_results' => 'نتائج البحث عن',
    ]
];

$t = $texts[$lang];

/* -------------------------
   3) SMALL HELPER TO READ A COLUMN
-------------------------- */
function col($row, ...$names) {
    foreach ($names as $n) {
        if (array_key_exists($n, $row)) {
            return $row[$n];
        }
    }
    return null;
}

/* -------------------------
   4) FETCH USERS WITH USERNAME OR NATIONAL ID SEARCH
-------------------------- */
$userSearch = trim($_GET['user_search'] ?? '');

if ($userSearch !== '') {
    $likeSearch = '%' . $userSearch . '%';

    $stmtUsers = $conn->prepare("
        SELECT *
        FROM registration
        WHERE username LIKE ?
           OR national_id LIKE ?
        ORDER BY id ASC
    ");

    if (!$stmtUsers) {
        die("Database prepare error: " . $conn->error);
    }

    $stmtUsers->bind_param("ss", $likeSearch, $likeSearch);
    $stmtUsers->execute();
    $result = $stmtUsers->get_result();
} else {
    $sql = "SELECT * FROM registration ORDER BY id ASC";
    $result = $conn->query($sql);
}

/* -------------------------
   5) MAP DB ROLE TO DISPLAY TEXT
-------------------------- */
function translateRole($dbRole, $lang, $texts) {
    $r = strtolower(trim((string)$dbRole));

    if ($r === 'admin') {
        return $texts[$lang]['role_admin'];
    } elseif ($r === 'doctor') {
        return $texts[$lang]['role_doctor'];
    } else {
        return $texts[$lang]['role_patient'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['page_title']) ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --bg-1:#07121d;
            --bg-2:#0c1c2d;
            --bg-3:#12314e;
            --white:#ffffff;
            --text-soft:rgba(255,255,255,0.82);
            --text-faint:rgba(255,255,255,0.62);
            --card:rgba(255,255,255,0.10);
            --stroke:rgba(255,255,255,0.14);
            --primary:#4cc9f0;
            --primary-2:#4895ef;
            --danger-1:#ef4444;
            --danger-2:#dc2626;
            --success-1:#16a34a;
            --success-2:#22c55e;
            --warning-1:#f59e0b;
            --warning-2:#f97316;
            --shadow:0 20px 50px rgba(0, 0, 0, 0.28);
            --shadow-strong:0 24px 55px rgba(0,0,0,0.34);
            --radius-xl:30px;
            --radius-lg:24px;
            --radius-md:18px;
        }

        *{
            box-sizing:border-box;
        }

        html, body{
            margin:0;
            padding:0;
        }

        body{
            font-family:'Inter', Arial, sans-serif;
            color:var(--white);
            min-height:100vh;
            background:
                radial-gradient(circle at top left, rgba(76,201,240,0.16), transparent 24%),
                radial-gradient(circle at top right, rgba(239,68,68,0.12), transparent 22%),
                radial-gradient(circle at bottom center, rgba(72,149,239,0.12), transparent 28%),
                linear-gradient(135deg, var(--bg-1), var(--bg-2) 46%, var(--bg-3));
        }

        a{
            text-decoration:none;
        }

        .page-shell{
            width:min(1320px, calc(100% - 32px));
            margin:18px auto 30px;
        }

        .glass{
            background:var(--card);
            border:1px solid var(--stroke);
            backdrop-filter:blur(16px);
            -webkit-backdrop-filter:blur(16px);
            box-shadow:var(--shadow);
        }

        .topbar{
            position:sticky;
            top:14px;
            z-index:999;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:16px;
            padding:16px 22px;
            margin-bottom:22px;
            border-radius:28px;
            background:rgba(255,255,255,0.10);
            border:1px solid rgba(255,255,255,0.12);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            box-shadow:var(--shadow-strong);
        }

        .brand{
            display:flex;
            align-items:center;
            gap:14px;
            min-width:0;
        }

        .brand-icon{
            width:56px;
            height:56px;
            border-radius:18px;
            display:grid;
            place-items:center;
            font-size:24px;
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow:0 15px 30px rgba(72,149,239,0.34);
            flex-shrink:0;
        }

        .brand-text h1{
            margin:0;
            font-size:22px;
            font-weight:800;
            line-height:1.2;
        }

        .brand-text p{
            margin:4px 0 0;
            color:var(--text-soft);
            font-size:13px;
        }

        .topbar-right{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            justify-content:flex-end;
        }

        .back-btn{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:12px 16px;
            border-radius:14px;
            color:#fff;
            font-weight:700;
            transition:0.25s ease;
            border:none;
            cursor:pointer;
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow:0 10px 25px rgba(72,149,239,0.30);
        }

        .back-btn:hover{
            transform:translateY(-2px);
        }

        .lang-toggle{
            display:flex;
            align-items:center;
            gap:8px;
        }

        .lang-toggle form{
            margin:0;
        }

        .lang-toggle button{
            border:none;
            padding:10px 14px;
            border-radius:12px;
            cursor:pointer;
            font-weight:700;
            transition:0.25s ease;
            box-shadow:0 8px 18px rgba(0,0,0,0.16);
        }

        .lang-active{
            background:linear-gradient(135deg, #ffd60a, #f59e0b);
            color:#111827;
        }

        .lang-inactive{
            background:rgba(255,255,255,0.90);
            color:#0f3d68;
        }

        .lang-toggle button:hover{
            transform:translateY(-1px);
        }

        .hero{
            display:grid;
            grid-template-columns:1.1fr 0.9fr;
            gap:22px;
            margin-bottom:22px;
        }

        .hero-main{
            position:relative;
            overflow:hidden;
            border-radius:var(--radius-xl);
            padding:32px;
            background:
                radial-gradient(circle at top right, rgba(255,255,255,0.13), transparent 26%),
                linear-gradient(135deg, rgba(17,24,39,0.92), rgba(30,41,59,0.92), rgba(59,130,246,0.72));
            border:1px solid rgba(255,255,255,0.12);
            box-shadow:var(--shadow-strong);
        }

        .hero-main::after{
            content:"";
            position:absolute;
            width:220px;
            height:220px;
            border-radius:50%;
            background:radial-gradient(circle, rgba(255,255,255,0.12), transparent 62%);
            bottom:-90px;
            right:-70px;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:9px 14px;
            border-radius:999px;
            background:rgba(255,255,255,0.14);
            border:1px solid rgba(255,255,255,0.16);
            font-size:13px;
            font-weight:700;
            margin-bottom:16px;
        }

        .hero-main h2{
            margin:0 0 12px;
            font-size:clamp(30px, 4vw, 42px);
            line-height:1.12;
            font-weight:800;
            letter-spacing:-1px;
        }

        .hero-main p{
            margin:0;
            max-width:720px;
            font-size:16px;
            line-height:1.8;
            color:var(--text-soft);
        }

        .hero-side{
            padding:22px;
            border-radius:var(--radius-xl);
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .mini-card{
            border-radius:20px;
            padding:18px;
            background:rgba(255,255,255,0.08);
            border:1px solid rgba(255,255,255,0.10);
        }

        .mini-card .label{
            font-size:13px;
            color:var(--text-faint);
            margin-bottom:8px;
        }

        .mini-card .value{
            font-size:18px;
            font-weight:800;
            line-height:1.6;
        }

        .msg-box{
            width:fit-content;
            max-width:100%;
            margin:0 auto 18px;
            padding:10px 18px;
            border-radius:14px;
            font-weight:700;
            box-shadow:0 12px 24px rgba(0,0,0,0.18);
            color:#fff;
        }

        .msg-success{
            background:linear-gradient(135deg, var(--success-1), var(--success-2));
        }

        .msg-error{
            background:linear-gradient(135deg, var(--danger-1), var(--danger-2));
        }


        .search-panel{
            border-radius:var(--radius-xl);
            padding:20px 22px;
            margin-bottom:22px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:14px;
            flex-wrap:wrap;
        }

        .search-title{
            display:flex;
            align-items:center;
            gap:10px;
            font-weight:800;
            color:#fff;
            font-size:16px;
        }

        .search-form{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            flex:1;
            justify-content:flex-end;
        }

        .search-input-wrap{
            position:relative;
            min-width:260px;
            flex:0 1 380px;
        }

        .search-input-wrap i{
            position:absolute;
            top:50%;
            transform:translateY(-50%);
            left:14px;
            color:rgba(255,255,255,0.65);
            pointer-events:none;
        }

        html[dir="rtl"] .search-input-wrap i{
            left:auto;
            right:14px;
        }

        .search-input{
            width:100%;
            border:none;
            outline:none;
            padding:13px 16px 13px 42px;
            border-radius:15px;
            background:rgba(255,255,255,0.14);
            border:1px solid rgba(255,255,255,0.14);
            color:#fff;
            font-size:14px;
            font-weight:600;
        }

        html[dir="rtl"] .search-input{
            padding:13px 42px 13px 16px;
        }

        .search-input::placeholder{
            color:rgba(255,255,255,0.55);
        }

        .search-btn,
        .clear-search-btn{
            border:none;
            padding:13px 17px;
            border-radius:14px;
            cursor:pointer;
            font-weight:800;
            transition:0.25s ease;
            display:inline-flex;
            align-items:center;
            gap:8px;
            white-space:nowrap;
        }

        .search-btn{
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            color:#fff;
            box-shadow:0 10px 22px rgba(72,149,239,0.24);
        }

        .clear-search-btn{
            background:rgba(255,255,255,0.90);
            color:#0f3d68;
        }

        .search-btn:hover,
        .clear-search-btn:hover{
            transform:translateY(-2px);
        }

        .search-info{
            width:100%;
            color:var(--text-soft);
            font-size:13px;
            font-weight:700;
        }

        .table-panel{
            border-radius:var(--radius-xl);
            padding:22px;
            overflow:hidden;
        }

        .table-wrap{
            width:100%;
            overflow-x:auto;
            border-radius:22px;
            background:rgba(255,255,255,0.08);
            border:1px solid rgba(255,255,255,0.10);
        }

        table{
            width:100%;
            border-collapse:collapse;
            min-width:760px;
            color:#fff;
        }

        th, td{
            padding:15px 14px;
            text-align:center;
            border-bottom:1px solid rgba(255,255,255,0.10);
            font-size:14px;
        }

        th{
            background:linear-gradient(135deg, rgba(76,201,240,0.95), rgba(72,149,239,0.95));
            color:#fff;
            font-weight:800;
            white-space:nowrap;
        }

        tr{
            background:rgba(255,255,255,0.02);
            transition:0.2s ease;
        }

        tr:nth-child(even){
            background:rgba(255,255,255,0.05);
        }

        tr:hover{
            background:rgba(255,255,255,0.10);
        }

        td{
            color:rgba(255,255,255,0.92);
        }

        .role-badge{
            display:inline-block;
            padding:6px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:800;
            min-width:88px;
        }

        .role-admin{
            background:linear-gradient(135deg, #facc15, #f59e0b);
            color:#111827;
        }

        .role-doctor{
            background:linear-gradient(135deg, #16a34a, #22c55e);
            color:#fff;
        }

        .role-patient{
            background:linear-gradient(135deg, #2563eb, #3b82f6);
            color:#fff;
        }

        .actions{
            display:flex;
            justify-content:center;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
        }

        .btn-action{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            padding:9px 13px;
            border-radius:12px;
            text-decoration:none;
            font-size:13px;
            font-weight:700;
            color:#fff;
            transition:0.25s ease;
            white-space:nowrap;
        }

        .btn-edit{
            background:linear-gradient(135deg, var(--primary), var(--primary-2));
            box-shadow:0 10px 22px rgba(72,149,239,0.22);
        }

        .btn-delete{
            background:linear-gradient(135deg, var(--danger-1), var(--danger-2));
            box-shadow:0 10px 22px rgba(220,38,38,0.22);
        }

        .btn-edit:hover,
        .btn-delete:hover{
            transform:translateY(-2px);
        }

        .empty-row td{
            padding:22px;
            color:var(--text-soft);
            font-weight:600;
        }

        .footer-note{
            text-align:center;
            color:rgba(255,255,255,0.74);
            font-size:14px;
            padding:20px 10px 28px;
        }

        @media (max-width: 980px){
            .hero{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 760px){
            .topbar{
                flex-direction:column;
                align-items:stretch;
            }

            .topbar-right{
                justify-content:space-between;
            }

            .back-btn{
                justify-content:center;
                width:100%;
            }

            .hero-main h2{
                font-size:26px;
            }
        }

        @media (max-width: 640px){
            .page-shell{
                width:min(100% - 18px, 1320px);
                margin:12px auto 22px;
            }

            .topbar,
            .hero-main,
            .hero-side,
            .table-panel{
                padding:18px;
            }

            .brand-text h1{
                font-size:18px;
            }

            .hero-main h2{
                font-size:24px;
            }
        }
    </style>
</head>
<body>

<div class="page-shell">

    <header class="topbar glass">
        <div class="brand">
            <div class="brand-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="brand-text">
                <h1><?= htmlspecialchars($t['page_title']) ?></h1>
                <p><?= ($lang === 'ar') ? 'إدارة حسابات المرضى والمستخدمين' : 'Manage patient and user accounts' ?></p>
            </div>
        </div>

        <div class="topbar-right">
            <a class="back-btn" href="admin_dashboard.php">
                <i class="fa-solid fa-arrow-left"></i>
                <span><?= htmlspecialchars($t['back_dashboard']) ?></span>
            </a>

            <div class="lang-toggle">
                <form method="get" style="display:inline;">
                    <button type="submit" name="lang" value="en"
                            class="<?= $lang === 'en' ? 'lang-active' : 'lang-inactive' ?>">
                        <?= htmlspecialchars($t['english']) ?>
                    </button>
                </form>
                <form method="get" style="display:inline;">
                    <button type="submit" name="lang" value="ar"
                            class="<?= $lang === 'ar' ? 'lang-active' : 'lang-inactive' ?>">
                        <?= htmlspecialchars($t['arabic']) ?>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-main">
            <div class="hero-badge">
                <i class="fa-solid fa-user-gear"></i>
                <span><?= ($lang === 'ar') ? 'لوحة إدارة المستخدمين' : 'User Management Panel' ?></span>
            </div>

            <h2><?= htmlspecialchars($t['page_title']) ?> <?= $t['title_icon']; ?></h2>
            <p>
                <?= ($lang === 'ar')
                    ? 'من هذه الصفحة يمكنك مراجعة بيانات المستخدمين، تعديل الحسابات، أو حذفها مع واجهة احترافية متناسقة مع باقي صفحات النظام.'
                    : 'From this page, you can review user data, edit accounts, or delete them through a professional interface consistent with the rest of the system.' ?>
            </p>
        </div>

        <div class="hero-side glass">
            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'المسؤول الحالي' : 'Current Admin' ?></div>
                <div class="value"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'نوع الصفحة' : 'Page Type' ?></div>
                <div class="value"><?= htmlspecialchars($t['page_title']) ?></div>
            </div>

            <div class="mini-card">
                <div class="label"><?= ($lang === 'ar') ? 'إجراء سريع' : 'Quick Action' ?></div>
                <div class="value" style="font-size:15px;">
                    <?= ($lang === 'ar')
                        ? 'يمكنك تعديل أو حذف أي مستخدم مباشرة من الجدول بالأسفل.'
                        : 'You can edit or delete any user directly from the table below.' ?>
                </div>
            </div>
        </div>
    </section>

    <?php if (isset($_GET['msg'])): ?>
        <div class="msg-box <?= (($_GET['type'] ?? '') === 'success') ? 'msg-success' : 'msg-error' ?>">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <section class="search-panel glass">
        <div class="search-title">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span><?= htmlspecialchars($t['search_label']) ?></span>
        </div>

        <form class="search-form" method="get" action="">
            <input type="hidden" name="lang" value="<?= htmlspecialchars($lang) ?>">

            <div class="search-input-wrap">
    <i class="fa-solid fa-id-card"></i>
    <input
        class="search-input"
        type="text"
        name="user_search"
        value="<?= htmlspecialchars($userSearch) ?>"
        placeholder="<?= htmlspecialchars($t['search_placeholder']) ?>"
        autocomplete="off"
    >
</div>

            <button class="search-btn" type="submit">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span><?= htmlspecialchars($t['search_btn']) ?></span>
            </button>

           <?php if ($userSearch !== ''): ?>
    <a class="clear-search-btn" href="?lang=<?= urlencode($lang) ?>">
        <i class="fa-solid fa-xmark"></i>
        <span><?= htmlspecialchars($t['clear_btn']) ?></span>
    </a>
<?php endif; ?>

<?php if ($userSearch !== ''): ?>
    <div class="search-info">
        <?= htmlspecialchars($t['search_results']) ?>:
        <strong><?= htmlspecialchars($userSearch) ?></strong>
    </div>
<?php endif; ?>
        </form>
    </section>

    <section class="table-panel glass">
        <div class="table-wrap">
            <table>
                <tr>
                    <th><?= htmlspecialchars($t['col_id']) ?></th>
                    <th><?= htmlspecialchars($t['col_username']) ?></th>
                    <th><?= htmlspecialchars($t['col_national']) ?></th>
                    <th><?= htmlspecialchars($t['col_role']) ?></th>
                    <th><?= htmlspecialchars($t['col_actions']) ?></th>
                </tr>

                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $id        = col($row, 'id', 'ID', 'user_id', 'UserID');
                            $username  = col($row, 'username', 'Username', 'user_name', 'UserName');
                            $national  = col($row, 'national_id', 'NationalID', 'national', 'National', 'nationalid', 'NationalId');
                            $dbRole    = col($row, 'role', 'Role', 'user_role', 'UserRole', 'type', 'UserType');

                            $roleText  = translateRole($dbRole, $lang, $texts);
                            $roleLower = strtolower(trim((string)$dbRole));

                            $roleClass = 'role-patient';
                            if ($roleLower === 'admin') $roleClass = 'role-admin';
                            elseif ($roleLower === 'doctor') $roleClass = 'role-doctor';
                        ?>

                        <tr>
                            <td><?= htmlspecialchars((string)$id); ?></td>
                            <td><?= htmlspecialchars((string)$username); ?></td>
                            <td><?= htmlspecialchars((string)$national); ?></td>
                            <td>
                                <span class="role-badge <?= $roleClass ?>">
                                    <?= htmlspecialchars($roleText); ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn-action btn-edit"
                                       href="edit_user.php?id=<?= urlencode((string)$id); ?>">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span><?= htmlspecialchars($t['btn_edit']) ?></span>
                                    </a>

                                    <a class="btn-action btn-delete"
                                       href="delete_user.php?id=<?= urlencode((string)$id); ?>"
                                       onclick="return confirm('Are you sure you want to delete this user?');">
                                        <i class="fa-solid fa-trash"></i>
                                        <span><?= htmlspecialchars($t['btn_delete']) ?></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="5">No users found.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </section>

    <div class="footer-note">
        © <?= date('Y') ?> <?= htmlspecialchars($t['page_title']) ?>
    </div>
</div>

</body>
</html>