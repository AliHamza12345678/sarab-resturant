<?php

// Never show raw PHP/MySQL errors to visitors in production.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$host     = "localhost";
$username = "root";
$password = "";
$database = "restaurant_db";

// PHP 8.1+ makes mysqli throw exceptions by default on connection errors —
// the @ suppression operator does NOT catch exceptions (only traditional
// warnings), so without this try/catch a failed connection would crash
// with a raw, unhandled error instead of the safe message below.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    // Log the real reason for developers, show a safe generic message to visitors.
    error_log("Database Connection Failed: " . mysqli_connect_error());
    http_response_code(500);
    die("We're having a temporary issue. Please try again in a moment.");
}

mysqli_set_charset($conn, "utf8mb4");

?>
