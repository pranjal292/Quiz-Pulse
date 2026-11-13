<?php
// =============================================================================
// QuizPulse — php/check_login.php
// Route guard helper: checks whether the user has a valid active PHP session
// and returns their role ('teacher' or 'student').
// Used by JavaScript on page load to protect teacher and student pages.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Check if user is logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = "";
    if (isset($_SESSION['user_role'])) {
        $role = $_SESSION['user_role'];
    }

    if ($role === "teacher") {
        $email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : "";
        $name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : "";

        $response = array(
            "success" => true,
            "message" => "Teacher session active.",
            "data" => array(
                "loggedIn" => true,
                "role" => "teacher",
                "email" => $email,
                "name" => $name
            )
        );
        echo json_encode($response);
        exit;
    }

    if ($role === "student") {
        $studentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : "";
        $orgCode = isset($_SESSION['org_code']) ? $_SESSION['org_code'] : "";

        $response = array(
            "success" => true,
            "message" => "Student session active.",
            "data" => array(
                "loggedIn" => true,
                "role" => "student",
                "studentId" => $studentId,
                "orgCode" => $orgCode
            )
        );
        echo json_encode($response);
        exit;
    }
}

// If no active session was found
$response = array(
    "success" => false,
    "message" => "No active session found.",
    "data" => array(
        "loggedIn" => false,
        "role" => null
    )
);

echo json_encode($response);
?>

