<?php
// =============================================================================
// QuizPulse — php/save_result.php
// Finalizes a candidate's assessment attempt:
// 1. Grades candidate responses against the database answer key.
// 2. Locks attempt status ('submitted' or 'terminated_cheat').
// 3. Saves score, submission timestamp, and reason.
// 4. Returns the updated quiz and submission record to display results.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read request data
$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true);

if (!is_array($inputData)) {
    $inputData = $_POST;
}

$orgCode = "";
$studentId = "";
$responsesMap = array();
$timeSpentMap = array();
$isAuto = false;
$terminationReason = "manual";

if (isset($inputData['orgCode'])) {
    $orgCode = trim($inputData['orgCode']);
}
if (isset($inputData['studentId'])) {
    $studentId = trim($inputData['studentId']);
}

if (isset($inputData['responses'])) {
    if (is_array($inputData['responses'])) {
        $responsesMap = $inputData['responses'];
    } else {
        $parsed = json_decode($inputData['responses'], true);
        if (is_array($parsed)) {
            $responsesMap = $parsed;
        }
    }
}

if (isset($inputData['timeSpentPerQuestion'])) {
    if (is_array($inputData['timeSpentPerQuestion'])) {
        $timeSpentMap = $inputData['timeSpentPerQuestion'];
    } else {
        $parsed = json_decode($inputData['timeSpentPerQuestion'], true);
        if (is_array($parsed)) {
            $timeSpentMap = $parsed;
        }
    }
}

if (isset($inputData['autoSubmitted'])) {
    $isAuto = (bool)$inputData['autoSubmitted'];
}
if (isset($inputData['terminationReason']) && $inputData['terminationReason'] !== "") {
    $terminationReason = trim($inputData['terminationReason']);
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

// ===== 1. FETCH QUIZ QUESTIONS & ANSWER KEY TO GRADE ACCURATELY =====
$qSql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE quiz_org_id = ? ORDER BY id ASC";
$qStmt = mysqli_prepare($conn, $qSql);

if ($qStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error loading questions for grading: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($qStmt, "s", $orgCode);
mysqli_stmt_execute($qStmt);
$qResult = mysqli_stmt_get_result($qStmt);

$questionsList = array();
$answerKeyMap = array();
$calculatedScore = 0;

while ($qRow = mysqli_fetch_assoc($qResult)) {
    $qId = $qRow['question_id'];
    $correctOption = (int)$qRow['correct_option'];

    $options = array();
    if ($qRow['option_a'] !== "") { $options[] = $qRow['option_a']; }
    if ($qRow['option_b'] !== "") { $options[] = $qRow['option_b']; }
    if ($qRow['option_c'] !== "") { $options[] = $qRow['option_c']; }
    if ($qRow['option_d'] !== "") { $options[] = $qRow['option_d']; }

    $questionsList[] = array(
        "id" => $qId,
        "questionText" => $qRow['question_text'],
        "options" => $options
    );

    $answerKeyMap[$qId] = $correctOption;

    // Check candidate response for this question
    if (isset($responsesMap[$qId])) {
        $studentChoice = (int)$responsesMap[$qId];
        if ($studentChoice === $correctOption) {
            $calculatedScore = $calculatedScore + 1;
        }
    }
}
mysqli_stmt_close($qStmt);

// ===== 2. DETERMINE FINAL STATUS =====
$submissionStatus = "submitted";
if ($terminationReason !== "timer_expired" && $terminationReason !== "manual" && $terminationReason !== "organizer_ended") {
    $submissionStatus = "terminated_cheat";
}

$submittedAt = date("Y-m-d H:i:s");
$autoSubmittedInt = $isAuto === true ? 1 : 0;
$responsesJson = json_encode($responsesMap);
$timeSpentJson = json_encode($timeSpentMap);

// ===== 3. UPDATE SUBMISSION RECORD IN DATABASE =====
// Prepared statement stops SQL injection
$updateSql = "UPDATE results SET status = ?, auto_submitted = ?, termination_reason = ?, responses = ?, time_spent = ?, score = ?, submitted_at = ? WHERE quiz_org_id = ? AND student_id = ?";
$updateStmt = mysqli_prepare($conn, $updateSql);

if ($updateStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing results update: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($updateStmt, "sisssisss", $submissionStatus, $autoSubmittedInt, $terminationReason, $responsesJson, $timeSpentJson, $calculatedScore, $submittedAt, $orgCode, $studentId);
$updateOk = mysqli_stmt_execute($updateStmt);

if ($updateOk === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to save final evaluation: " . mysqli_stmt_error($updateStmt),
        "data" => null
    );
    mysqli_stmt_close($updateStmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($updateStmt);

// ===== 4. FETCH FULL QUIZ ROW TO RETURN TOGETHER =====
$quizSql = "SELECT * FROM quizzes WHERE org_id = ? LIMIT 1";
$quizStmt = mysqli_prepare($conn, $quizSql);
mysqli_stmt_bind_param($quizStmt, "s", $orgCode);
mysqli_stmt_execute($quizStmt);
$quizResult = mysqli_stmt_get_result($quizStmt);
$quizRow = mysqli_fetch_assoc($quizResult);
mysqli_stmt_close($quizStmt);

// Fetch candidate's current tab violations count
$subSql = "SELECT tab_violations, session_purged FROM results WHERE quiz_org_id = ? AND student_id = ? LIMIT 1";
$subStmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($subStmt, "ss", $orgCode, $studentId);
mysqli_stmt_execute($subStmt);
$subResult = mysqli_stmt_get_result($subStmt);
$subRow = mysqli_fetch_assoc($subResult);
mysqli_stmt_close($subStmt);

$tabViolationsCount = 0;
$sessionPurgedFlag = false;
if ($subRow) {
    $tabViolationsCount = (int)$subRow['tab_violations'];
    $sessionPurgedFlag = (bool)$subRow['session_purged'];
}

$quizData = array(
    "id" => (int)$quizRow['id'],
    "orgId" => $quizRow['org_id'],
    "quizTitle" => $quizRow['quiz_title'],
    "postQuizVisibility" => $quizRow['post_quiz_visibility'],
    "status" => $quizRow['status'],
    "attemptDurationSeconds" => (int)$quizRow['attempt_duration_seconds'],
    "entryWindowSeconds" => (int)$quizRow['entry_window_seconds'],
    "questions" => $questionsList,
    "answerKey" => $answerKeyMap
);

$submissionData = array(
    "studentId" => $studentId,
    "status" => $submissionStatus,
    "autoSubmitted" => $isAuto,
    "terminationReason" => $terminationReason,
    "responses" => $responsesMap,
    "timeSpentPerQuestion" => $timeSpentMap,
    "tabViolations" => $tabViolationsCount,
    "score" => $calculatedScore,
    "submittedAt" => $submittedAt,
    "sessionPurged" => $sessionPurgedFlag
);

$response = array(
    "success" => true,
    "message" => "Assessment finalized and graded successfully.",
    "data" => array(
        "quiz" => $quizData,
        "submission" => $submissionData
    )
);

echo json_encode($response);
?>

