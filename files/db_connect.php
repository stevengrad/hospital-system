<?php
$servername = getenv('DB_HOST') ?: 'database-1.c7q2cy4m6u0p.eu-central-1.rds.amazonaws.com';
$username   = getenv('DB_USER') ?: 'mysqldb2026';
$password   = getenv('DB_PASSWORD') ?: 'mysqldb2026';
$dbname     = getenv('DB_NAME') ?: 'hospital_system';
$port       = (int)(getenv('DB_PORT') ?: 3306);

$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>