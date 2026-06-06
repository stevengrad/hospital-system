<?php
session_start();

// Detect language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // default
}

$lang = $_SESSION['lang'];
$translations = [
    'en' => [
        'home' => 'Home',
        'services' => 'Services',
        'appointment' => 'Appointment',
        'contact' => 'Contact',
        'login' => 'Login',
        'logout' => 'Logout',
        'dashboard' => 'Dashboard',
        'language' => 'عربي'
    ],
    'ar' => [
        'home' => 'الرئيسية',
        'services' => 'الخدمات',
        'appointment' => 'المواعيد',
        'contact' => 'اتصل بنا',
        'login' => 'تسجيل الدخول',
        'logout' => 'تسجيل الخروج',
        'dashboard' => 'لوحة التحكم',
        'language' => 'English'
    ]
];

// Handle language toggle
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$isArabic = ($_SESSION['lang'] == 'ar');

// Identity coming from login session (used by chatbot JS)
$username = $_SESSION['username'] ?? null;

// Choose a display name from whatever you store in session.
// If you store full name in another key, add it here.
$displayName = $_SESSION['first_name'] ?? ($_SESSION['name'] ?? null);
?>
<!DOCTYPE html>
<html lang="<?= $isArabic ? 'ar' : 'en'; ?>" dir="<?= $isArabic ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Website</title>
    <link rel="icon" type="image/png" href="assets/Cairo_hospitals1.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Chatbot identity bootstrap: MUST be defined before chat widget JS runs -->
    <script>
      window.__USER__ = {
        username: <?php echo json_encode($username); ?>,
        display_name: <?php echo json_encode($displayName); ?>
      };
    </script>

    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', 'Cairo', sans-serif;
        }
        .navbar {
            background-color: #0d6efd;
        }
        .navbar-brand, .nav-link, .dropdown-toggle {
            color: white !important;
        }
        .lang-toggle {
            border: none;
            background: transparent;
            color: white;
            font-weight: 600;
        }
        .lang-toggle:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php"><i class="fa-solid fa-hospital"></i> Hospital</a>
        <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="collapse navbar-collapse <?= $isArabic ? 'justify-content-end' : ''; ?>" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 <?= $isArabic ? 'text-end' : ''; ?>">
                <li class="nav-item"><a class="nav-link" href="index.php"><?= $translations[$lang]['home']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="services.php"><?= $translations[$lang]['services']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="appointment.php"><?= $translations[$lang]['appointment']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="contact.php"><?= $translations[$lang]['contact']; ?></a></li>

                <?php if (isset($_SESSION['user_role'])): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><?= $translations[$lang]['dashboard']; ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><?= $translations[$lang]['logout']; ?></a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php"><?= $translations[$lang]['login']; ?></a></li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link" href="?lang=<?= $lang === 'en' ? 'ar' : 'en'; ?>">
                        <i class="fa-solid fa-globe"></i> <?= $translations[$lang]['language']; ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">