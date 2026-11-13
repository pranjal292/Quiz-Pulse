<?php
// =============================================================================
// QuizPulse — php/save_draft.php
// Autosaves student answers as an in-progress draft to prevent data loss.
// Called whenever a candidate selects an answer option.
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
$responsesJson = "{}";
$timeSpentJson = "{}";

if (isset($inputData['orgCode'])) {
    $orgCode = trim($inputData['orgCode']);
}
if (isset($inputData['studentId'])) {
    $studentId = trim($inputData['studentId']);
}

if (isset($inputData['responses'])) {
    if (is_array($inputData['responses'])) {
        $responsesJson = json_encode($inputData['responses']);
    } else {
        $responsesJson = (string)$inputData['responses'];
    }
}

if (isset($inputData['timeSpentPerQuestion'])) {
    if (is_array($inputData['timeSpentPerQuestion'])) {
        $timeSpentJson = json_encode($inputData['timeSpentPerQuestion']);
    } else {
        $timeSpentJson = (string)$inputData['timeSpentPerQuestion'];
    }
}

if ($orgCode === "" || $studentId === "") {
    $response = array(
        "success" => false,
        "message" => "Missing room code or student ID.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Update draft answers in results table
// Prepared statement protects against SQL injection
$sql = "UPDATE results SET responses = ?, time_spent = ? WHERE quiz_org_id = ? AND student_id = ?";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing draft update: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, "ssss", $responsesJson, $timeSpentJson, $orgCode, $studentId);
$executeOk = mysqli_stmt_execute($stmt);

if ($executeOk === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to save draft answers: " . mysqli_stmt_error($stmt),
        "data" => null
    );
    mysqli_stmt_close($stmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($stmt);

$response = array(
    "success" => true,
    "message" => "Draft autosaved successfully.",
    "data" => null
);

echo json_encode($response);
?>

