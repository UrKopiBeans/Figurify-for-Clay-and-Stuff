<?php

// gumagawa ng $conn (mysqli connection) - ito ang ginagamit ng halos lahat ng pages, kaya required_once ito bago ang kahit anong DB query
// Ang database login ay nasa helpers/security_helper.php (FIGURIFY_DB_*), kusang localhost o public.
require_once __DIR__ . "/../helpers/security_helper.php";

$conn = new mysqli(
    FIGURIFY_DB_HOST,
    FIGURIFY_DB_USER,
    FIGURIFY_DB_PASS,
    FIGURIFY_DB_NAME
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please check the database configuration.");
}

$conn->set_charset("utf8mb4");

/* Para ang NOW / CURRENT_TIMESTAMP ng MySQL (hal. paid_at, created_at)
   ay pareho ng oras ng PHP (Asia/Manila), kahit ibang timezone ang server. */
$conn->query("SET time_zone = '+08:00'");

?>
