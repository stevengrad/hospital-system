<?php
// inc/config.php
// Basic site configuration
define('SITE_NAME', 'Graduation Hospital');

// Database credentials - change as needed
define('DB_HOST', getenv('DB_HOST') ?: 'database-1.c7q2cy4m6u0p.eu-central-1.rds.amazonaws.com');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'hospital_system');
define('DB_USER', getenv('DB_USER') ?: 'mysqldb2026');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'mysqldb2026');
?>
