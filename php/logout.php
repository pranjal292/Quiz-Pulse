<?php
// =============================================================================
// QuizPulse — php/logout.php
// Clears session variables and destroys the active PHP session.
// Works for both teachers and students.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Empty the session array
$_SESSION = array();

// If session cookie exists, expire it in the browser
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy session on the server
session_destroy();

$response = array(
    "success" => true,
    "message" => "Logged out successfully.",
    "data" => null
);

echo json_encode($response);
?>

