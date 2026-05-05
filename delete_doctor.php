<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_doctors.php?msg=Invalid doctor ID&type=error");
    exit();
}

try {

    /* 1) حذف المواعيد المرتبطة بالدكتور */
    $stmt1 = $conn->prepare("
        DELETE FROM appointments
        WHERE DoctorID = ?
    ");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    $stmt1->close();

    /* 2) حذف من جدول doctors */
    $stmt2 = $conn->prepare("
        DELETE FROM doctors
        WHERE EmployeeID = ?
    ");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->close();

    /* 3) حذف من جدول employees */
    $stmt3 = $conn->prepare("
        DELETE FROM employees
        WHERE EmployeeID = ?
    ");
    $stmt3->bind_param("i", $id);
    $stmt3->execute();
    $stmt3->close();

    header("Location: manage_doctors.php?msg=Doctor deleted successfully&type=success");
    exit();

} catch (Exception $e) {

    header("Location: manage_doctors.php?msg=Failed to delete doctor&type=error");
    exit();

}