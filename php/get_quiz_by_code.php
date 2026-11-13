<?php
// =============================================================================
// QuizPulse — php/get_quiz_by_code.php
// Returns the complete details of a single quiz by its 6-digit room code.
// Used by student waiting room, live room roster, and quiz attempt screen.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

$orgCode = "";
if (isset($_GET['code'])) {
    $orgCode = trim($_GET['code']);
}

if ($orgCode === "") {
    $response = array(
        "success" => false,
        "message" => "Room code is required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Find quiz in database
// Prepared statement prevents SQL injection
$quizSql = "SELECT * FROM quizzes WHERE org_id = ? LIMIT 1";
$quizStmt = mysqli_prepare($conn, $quizSql);

if ($quizStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing query: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($quizStmt, "s", $orgCode);
mysqli_stmt_execute($quizStmt);
$quizResult = mysqli_stmt_get_result($quizStmt);

if (mysqli_num_rows($quizResult) === 0) {
    mysqli_stmt_close($quizStmt);
    $response = array(
        "success" => false,
        "message" => "Quiz room " . $orgCode . " not found.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

$quizRow = mysqli_fetch_assoc($quizResult);
mysqli_stmt_close($quizStmt);

// Fetch questions
$qSql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE quiz_org_id = ? ORDER BY id ASC";
$qStmt = mysqli_prepare($conn, $qSql);
mysqli_stmt_bind_param($qStmt, "s", $orgCode);
mysqli_stmt_execute($qStmt);
$qResult = mysqli_stmt_get_result($qStmt);

$questions = array();
$answerKey = array();

while ($qRow = mysqli_fetch_assoc($qResult)) {
    $options = array();
    if ($qRow['option_a'] !== "") { $options[] = $qRow['option_a']; }
    if ($qRow['option_b'] !== "") { $options[] = $qRow['option_b']; }
    if ($qRow['option_c'] !== "") { $options[] = $qRow['option_c']; }
    if ($qRow['option_d'] !== "") { $options[] = $qRow['option_d']; }

    $questions[] = array(
        "id" => $qRow['question_id'],
        "questionText" => $qRow['question_text'],
        "options" => $options
    );

    $answerKey[$qRow['question_id']] = (int)$qRow['correct_option'];
}
mysqli_stmt_close($qStmt);

// Fetch candidate submissions
$subSql = "SELECT * FROM results WHERE quiz_org_id = ? ORDER BY id ASC";
$subStmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($subStmt, "s", $orgCode);
mysqli_stmt_execute($subStmt);
$subResult = mysqli_stmt_get_result($subStmt);

$submissions = array();
while ($sRow = mysqli_fetch_assoc($subResult)) {
    $parsedResponses = json_decode($sRow['responses'], true);
    if (!is_array($parsedResponses)) {
        $parsedResponses = new stdClass();
    }

    $parsedTime = json_decode($sRow['time_spent'], true);
    if (!is_array($parsedTime)) {
        $parsedTime = new stdClass();
    }

    $submissions[] = array(
        "id" => (int)$sRow['id'],
        "studentId" => $sRow['student_id'],
        "status" => $sRow['status'],
        "autoSubmitted" => (bool)$sRow['auto_submitted'],
        "terminationReason" => $sRow['termination_reason'],
        "responses" => $parsedResponses,
        "timeSpentPerQuestion" => $parsedTime,
        "tabViolations" => (int)$sRow['tab_violations'],
        "score" => $sRow['score'] !== null ? (int)$sRow['score'] : null,
        "submittedAt" => $sRow['submitted_at'],
        "sessionPurged" => (bool)$sRow['session_purged']
    );
}
mysqli_stmt_close($subStmt);

$quizObject = array(
    "id" => (int)$quizRow['id'],
    "orgId" => $quizRow['org_id'],
    "teacherEmail" => $quizRow['teacher_email'],
    "organizerEmail" => $quizRow['teacher_email'],
    "quizTitle" => $quizRow['quiz_title'],
    "entryWindowSeconds" => (int)$quizRow['entry_window_seconds'],
    "attemptDurationSeconds" => (int)$quizRow['attempt_duration_seconds'],
    "postQuizVisibility" => $quizRow['post_quiz_visibility'],
    "status" => $quizRow['status'],
    "quizStartTimestamp" => $quizRow['quiz_start_timestamp'] !== null ? (float)$quizRow['quiz_start_timestamp'] : null,
    "createdAt" => $quizRow['created_at'],
    "questions" => $questions,
    "answerKey" => $answerKey,
    "submissions" => $submissions
);

$response = array(
    "success" => true,
    "message" => "Quiz loaded successfully.",
    "data" => $quizObject
);

echo json_encode($response);
?>

