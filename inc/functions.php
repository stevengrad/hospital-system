<?php
// inc/functions.php
require_once __DIR__ . '/db.php';

function getDepartments() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    return $stmt->fetchAll();
}

function getDoctors() {
    global $pdo;
    $stmt = $pdo->query("SELECT d.id, d.name, d.specialty, dep.name as department
                         FROM doctors d
                         LEFT JOIN departments dep ON d.department_id = dep.id
                         ORDER BY d.name");
    return $stmt->fetchAll();
}
?>
