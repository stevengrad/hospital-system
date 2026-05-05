<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: manage_users.php?msg=Invalid user ID&type=error");
    exit();
}

$stmt = $conn->prepare("
    DELETE FROM registration
    WHERE id = ?
");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    header("Location: manage_users.php?msg=User deleted successfully&type=success");
    exit();

} else {

    header("Location: manage_users.php?msg=Failed to delete user&type=error");
    exit();

}