<?php
// =============================================================================
// QuizPulse — php/db.php
// This file ONLY handles the database connection.
// It replaces the old firebase.js file.
// Every other PHP file includes this file using: require_once 'db.php';
// =============================================================================

// Start PHP session if one has not already been started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection credentials for XAMPP
// Default username is "root" with an EMPTY password ""
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "quizpulse";

// If the connection fails, check the host, username, password, and database name here. Also make sure MySQL is running in the XAMPP control panel.
$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

// Check if the connection succeeded
if ($conn === false) {
    header("Content-Type: application/json");
    $errorResponse = array(
        "success" => false,
        "message" => "Database connection failed: " . mysqli_connect_error() . ". Make sure MySQL is running in the XAMPP control panel.",
        "data" => null
    );
    echo json_encode($errorResponse);
    exit;
}

// Set database character encoding to UTF-8
mysqli_set_charset($conn, "utf8mb4");
?>

