<?php
// lang/init_lang.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
   We assume some other page already did:
   if (isset($_GET['lang'])) { $_SESSION['lang'] = $_GET['lang'] === 'ar' ? 'ar' : 'en'; }

   But if not, we still default to 'en' and accept session value.
*/
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$lang = ($_SESSION['lang'] === 'ar') ? 'ar' : 'en';

/* --------------------------
   TRANSLATION DICTIONARY
   -------------------------- */

$LANG = [
    'en' => [
        'dir'  => 'ltr',
       'app_name' => 'Cairo Hospitals',

        'logout' => 'Logout',

        'doctor_dashboard_title' => 'Doctor Dashboard',
        'hello_doctor' => 'Hello, Dr. :name',

        // Cards – Doctor dashboard
        'card_profile_badge' => 'Profile',
        'card_profile_title' => 'My Profile',
        'card_profile_text'  => 'View and update your personal data.',

        'card_appointments_badge' => 'Appointments',
        'card_appointments_title' => 'My Appointments',
        'card_appointments_text'  => "View today's and upcoming appointments.",

        'card_patients_badge' => 'Patients',
        'card_patients_title' => 'My Patients',
        'card_patients_text'  => 'Browse your patients and records.',

        'card_reports_badge' => 'Reports',
        'card_reports_title' => 'Reports',
        'card_reports_text'  => 'Create and edit patient reports.',
    ],

    'ar' => [
        'dir'  => 'rtl',
        'app_name' => 'مستشفيات القاهرة',

        'logout' => 'تسجيل الخروج',

        'doctor_dashboard_title' => 'لوحة تحكم الطبيب',
        'hello_doctor' => 'مرحبًا د. :name',

        // البطاقات – لوحة تحكم الطبيب
        'card_profile_badge' => 'الملف الشخصي',
        'card_profile_title' => 'ملفي الشخصي',
        'card_profile_text'  => 'عرض وتحديث بياناتك الشخصية.',

        'card_appointments_badge' => 'المواعيد',
        'card_appointments_title' => 'مواعيدي',
        'card_appointments_text'  => 'عرض مواعيد اليوم والمواعيد القادمة.',

        'card_patients_badge' => 'المرضى',
        'card_patients_title' => 'مرضاي',
        'card_patients_text'  => 'استعراض مرضاك وسجلاتهم الطبية.',

        'card_reports_badge' => 'التقارير',
        'card_reports_title' => 'التقارير',
        'card_reports_text'  => 'إنشاء وتعديل تقارير المرضى.',
    ]
];

/* --------------------------
   Expose translations in $T
   -------------------------- */

$T = $LANG[$lang];
