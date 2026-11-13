<?php
// =============================================================================
// QuizPulse — php/update_visibility.php
// Updates a quiz's postQuizVisibility setting in real-time.
// Allows the organizer to control what results candidates see after submitting.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read POST data
$orgCode = "";
$newVisibility = "";

if (isset($_POST['orgCode'])) {
    $orgCode = trim($_POST['orgCode']);
}
if (isset($_POST['postQuizVisibility'])) {
    $newVisibility = trim($_POST['postQuizVisibility']);
}

// Support raw JSON body
if ($orgCode === "" || $newVisibility === "") {
    $rawInput = file_get_contents("php://input");
    $inputData = json_decode($rawInput, true);
    if (is_array($inputData)) {
        if (isset($inputData['orgCode'])) {
            $orgCode = trim($inputData['orgCode']);
        }
        if (isset($inputData['postQuizVisibility'])) {
            $newVisibility = trim($inputData['postQuizVisibility']);
        }
    }
}

if ($orgCode === "" || $newVisibility === "") {
    $response = array(
        "success" => false,
        "message" => "Room code and visibility setting are required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Update database
// Prepared statement stops SQL injection
$updateSql = "UPDATE quizzes SET post_quiz_visibility = ? WHERE org_id = ?";
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing visibility update: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "ss", $newVisibility, $orgCode);
$ok = mysqli_stmt_execute($updateStmt);

if ($ok === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to update visibility: " . mysqli_stmt_error($updateStmt),
        "data" => null
    );
    mysqli_stmt_close($updateStmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($updateStmt);

$response = array(
    "success" => true,
    "message" => "Post-quiz visibility updated to " . $newVisibility,
    "data" => array(
        "orgCode" => $orgCode,
        "postQuizVisibility" => $newVisibility
    )
);

echo json_encode($response);
?>

