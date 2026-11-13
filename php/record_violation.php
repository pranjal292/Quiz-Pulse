<?php
// =============================================================================
// QuizPulse — php/record_violation.php
// Records an anti-cheat integrity violation (e.g. tab switch or blur).
// Increments the candidate's violation counter in the database.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read POST data or JSON payload
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

if ($orgCode === "" || $studentId === "") {
    $response = array(
        "success" => false,
        "message" => "Room code and student ID are required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Increment tab violations count
// Prepared statement prevents SQL injection
$updateSql = "UPDATE results SET tab_violations = tab_violations + 1 WHERE quiz_org_id = ? AND student_id = ?";
$stmt = mysqli_prepare($conn, $updateSql);

if ($stmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing violation update: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $orgCode, $studentId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Fetch updated violation count
$countSql = "SELECT tab_violations FROM results WHERE quiz_org_id = ? AND student_id = ? LIMIT 1";
$countStmt = mysqli_prepare($conn, $countSql);
mysqli_stmt_bind_param($countStmt, "ss", $orgCode, $studentId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);

$currentViolations = 1;
if ($row = mysqli_fetch_assoc($countResult)) {
    $currentViolations = (int)$row['tab_violations'];
}
mysqli_stmt_close($countStmt);

$response = array(
    "success" => true,
    "message" => "Violation recorded.",
    "data" => array(
        "tabViolations" => $currentViolations
    )
);

echo json_encode($response);
?>

