<?php
// =============================================================================
// QuizPulse — php/purge_session.php
// Permanently marks a candidate's session as purged and clears their PHP session.
// Enforces single-attempt rule so the student cannot re-enter the assessment.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

$orgCode = "";
$studentId = "";

if (isset($inputData['orgCode'])) {
    $orgCode = trim($inputData['orgCode']);
}
if (isset($inputData['studentId'])) {
    $studentId = trim($inputData['studentId']);
}

if ($orgCode !== "" && $studentId !== "") {
    // Mark attempt as session_purged in database
    // Prepared statement stops SQL injection
    $sql = "UPDATE results SET session_purged = 1 WHERE quiz_org_id = ? AND student_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt !== false) {
        mysqli_stmt_bind_param($stmt, "ss", $orgCode, $studentId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Clear PHP session
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}
session_destroy();

$response = array(
    "success" => true,
    "message" => "Session purged successfully. Re-entry blocked.",
    "data" => null
);

echo json_encode($response);
?>

