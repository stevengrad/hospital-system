<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access denied. Admins only.");
}

include("db_connect.php");

// Language
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'en';
}
$lang = $_SESSION['lang'] ?? 'en';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $msg = ($lang === 'ar')
        ? "معرف التخصص غير صحيح."
        : "Invalid specialty ID.";
    header("Location: manage_services.php?msg=" . urlencode($msg) . "&type=error");
    exit();
}

// Check if any doctors are linked to this specialty
$stmtCheck = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM doctors
    WHERE SpecialtyID = ?
");
$stmtCheck->bind_param("i", $id);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
$rowCheck = $resCheck->fetch_assoc();
$stmtCheck->close();

if ((int)$rowCheck['total'] > 0) {
    $msg = ($lang === 'ar')
        ? "لا يمكن حذف هذا التخصص لأنه مرتبط بدكاترة في النظام."
        : "Cannot delete this specialty because it is linked to doctors in the system.";
    header("Location: manage_services.php?msg=" . urlencode($msg) . "&type=error");
    exit();
}

// Safe delete
$stmtDelete = $conn->prepare("
    DELETE FROM specialties
    WHERE SpecialtyID = ?
");
$stmtDelete->bind_param("i", $id);

if ($stmtDelete->execute()) {
    $msg = ($lang === 'ar')
        ? "تم حذف التخصص بنجاح."
        : "Specialty deleted successfully.";
    header("Location: manage_services.php?msg=" . urlencode($msg) . "&type=success");
    exit();
} else {
    $msg = ($lang === 'ar')
        ? "حدث خطأ أثناء الحذف."
        : "An error occurred while deleting.";
    header("Location: manage_services.php?msg=" . urlencode($msg) . "&type=error");
    exit();
}