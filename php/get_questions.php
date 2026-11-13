<?php
// =============================================================================
// QuizPulse — php/get_questions.php
// Returns the question cards for an active assessment room.
// Does NOT send the correct answers to candidates taking the test.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

$orgCode = "";
if (isset($_GET['code'])) {
    $orgCode = trim($_GET['code']);
} else if (isset($_POST['orgCode'])) {
    $orgCode = trim($_POST['orgCode']);
}

if ($orgCode === "") {
    $response = array(
        "success" => false,
        "message" => "Room code is required.",
        "data" => array()
    );
    echo json_encode($response);
    exit;
}

// Fetch questions for this quiz
// Prepared statement stops attackers from altering the SQL query
$sql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d FROM questions WHERE quiz_org_id = ? ORDER BY id ASC";
$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing query: " . mysqli_error($conn),
        "data" => array()
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $orgCode);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$questionsList = array();

while ($row = mysqli_fetch_assoc($result)) {
    $options = array();
    if ($row['option_a'] !== "") { $options[] = $row['option_a']; }
    if ($row['option_b'] !== "") { $options[] = $row['option_b']; }
    if ($row['option_c'] !== "") { $options[] = $row['option_c']; }
    if ($row['option_d'] !== "") { $options[] = $row['option_d']; }

    $questionsList[] = array(
        "id" => $row['question_id'],
        "questionText" => $row['question_text'],
        "options" => $options
    );
}

mysqli_stmt_close($stmt);

$response = array(
    "success" => true,
    "message" => "Questions loaded successfully.",
    "data" => $questionsList
);

echo json_encode($response);
?>

