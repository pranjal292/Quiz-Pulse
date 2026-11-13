<?php
// =============================================================================
// QuizPulse — php/update_status.php
// Updates a quiz's live status ("waiting", "active", "ended").
// When the status switches to "active", records the start timestamp.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read POST data
$orgCode = "";
$newStatus = "";

if (isset($_POST['orgCode'])) {
    $orgCode = trim($_POST['orgCode']);
}
if (isset($_POST['newStatus'])) {
    $newStatus = trim($_POST['newStatus']);
}

// Support raw JSON body
if ($orgCode === "" || $newStatus === "") {
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true);
    if (is_array($inputData)) {
        if (isset($inputData['orgCode'])) {
            $orgCode = trim($inputData['orgCode']);
        }
        if (isset($inputData['newStatus'])) {
            $newStatus = trim($inputData['newStatus']);
        }
    }
}

if ($orgCode === "" || $newStatus === "") {
    $response = array(
        "success" => false,
        "message" => "Room code and new status are required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Fetch current quiz to check start timestamp
$checkSql = "SELECT quiz_start_timestamp FROM quizzes WHERE org_id = ? LIMIT 1";
$checkStmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($checkStmt, "s", $orgCode);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) === 0) {
    mysqli_stmt_close($checkStmt);
    $response = array(
        "success" => false,
        "message" => "Quiz room not found.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

$row = mysqli_fetch_assoc($checkResult);
mysqli_stmt_close($checkStmt);

$startTimestamp = $row['quiz_start_timestamp'];

// If starting the quiz and start timestamp is not yet set, set to current epoch ms
if ($newStatus === "active" && $startTimestamp === null) {
    $startTimestamp = round(microtime(true) * 1000);
}

// Update the quiz status in the database
// Prepared statement prevents SQL injection
$updateSql = "UPDATE quizzes SET status = ?, quiz_start_timestamp = ? WHERE org_id = ?";
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing status update: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "sis", $newStatus, $startTimestamp, $orgCode);
$ok = mysqli_stmt_execute($updateStmt);

if ($ok === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to update quiz status: " . mysqli_stmt_error($updateStmt),
        "data" => null
    );
    mysqli_stmt_close($updateStmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($updateStmt);

$response = array(
    "success" => true,
    "message" => "Quiz status updated to " . $newStatus,
    "data" => array(
        "orgCode" => $orgCode,
        "status" => $newStatus,
        "quizStartTimestamp" => $startTimestamp
    )
);

echo json_encode($response);
?>

