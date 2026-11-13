<?php
// =============================================================================
// QuizPulse — php/student_login.php
// Validates candidate credentials and grants entry to the assessment room.
// Enforces rules:
//   1. Password must equal Student ID.
//   2. Single attempt per candidate (blocks re-entry after submission/purge).
//   3. Room status validation (cannot enter ended rooms).
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read input POST variables
$orgCode = "";
$studentId = "";
$password = "";

if (isset($_POST['orgCode'])) {
    $orgCode = trim($_POST['orgCode']);
}
if (isset($_POST['studentId'])) {
    $studentId = trim($_POST['studentId']);
}
if (isset($_POST['password'])) {
    $password = trim($_POST['password']);
}

// ===== VALIDATE INPUT FIELDS =====
if ($orgCode === "" || $studentId === "" || $password === "") {
    $response = array(
        "success" => false,
        "message" => "Room code, student ID, and password are all required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Rule: Candidate password MUST match their student ID
if ($studentId !== $password) {
    $response = array(
        "success" => false,
        "message" => "Per system rules, your password must match your Student ID.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// ===== LOOK UP THE QUIZ IN THE DATABASE =====
// Prepared statement protects against SQL injection
$quizSql = "SELECT * FROM quizzes WHERE org_id = ? LIMIT 1";
$quizStmt = mysqli_prepare($conn, $quizSql);

if ($quizStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error checking room: " . mysqli_error($conn),
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

// Format quiz object for frontend
$quizData = array(
    "id" => (int)$quizRow['id'],
    "orgId" => $quizRow['org_id'],
    "quizTitle" => $quizRow['quiz_title'],
    "teacherEmail" => $quizRow['teacher_email'],
    "entryWindowSeconds" => (int)$quizRow['entry_window_seconds'],
    "attemptDurationSeconds" => (int)$quizRow['attempt_duration_seconds'],
    "postQuizVisibility" => $quizRow['post_quiz_visibility'],
    "status" => $quizRow['status'],
    "quizStartTimestamp" => $quizRow['quiz_start_timestamp'] !== null ? (float)$quizRow['quiz_start_timestamp'] : null,
    "createdAt" => $quizRow['created_at']
);

// Count total questions for this quiz
$qCountSql = "SELECT COUNT(*) AS total_q FROM questions WHERE quiz_org_id = ?";
$qCountStmt = mysqli_prepare($conn, $qCountSql);
mysqli_stmt_bind_param($qCountStmt, "s", $orgCode);
mysqli_stmt_execute($qCountStmt);
$qCountResult = mysqli_stmt_get_result($qCountStmt);
$qCountRow = mysqli_fetch_assoc($qCountResult);
$quizData['totalQuestions'] = (int)$qCountRow['total_q'];
mysqli_stmt_close($qCountStmt);

// ===== CHECK PREVIOUS ATTEMPTS FOR THIS CANDIDATE =====
$subSql = "SELECT * FROM results WHERE quiz_org_id = ? AND student_id = ? LIMIT 1";
$subStmt = mysqli_prepare($conn, $subSql);

if ($subStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error checking submissions: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($subStmt, "ss", $orgCode, $studentId);
mysqli_stmt_execute($subStmt);
$subResult = mysqli_stmt_get_result($subStmt);

if (mysqli_num_rows($subResult) > 0) {
    $subRow = mysqli_fetch_assoc($subStmtResult = $subResult);
    mysqli_stmt_close($subStmt);

    $subData = array(
        "id" => (int)$subRow['id'],
        "orgCode" => $subRow['quiz_org_id'],
        "studentId" => $subRow['student_id'],
        "status" => $subRow['status'],
        "autoSubmitted" => (bool)$subRow['auto_submitted'],
        "terminationReason" => $subRow['termination_reason'],
        "responses" => $subRow['responses'] !== null ? json_decode($subRow['responses'], true) : new stdClass(),
        "timeSpentPerQuestion" => $subRow['time_spent'] !== null ? json_decode($subRow['time_spent'], true) : new stdClass(),
        "tabViolations" => (int)$subRow['tab_violations'],
        "score" => $subRow['score'] !== null ? (int)$subRow['score'] : null,
        "submittedAt" => $subRow['submitted_at'],
        "sessionPurged" => (bool)$subRow['session_purged']
    );

    // Rule: If purged or completed, prevent new test attempt
    if ((int)$subRow['session_purged'] === 1 || $subRow['status'] === "purged") {
        $response = array(
            "success" => false,
            "message" => "Assessment Completed & Session Expired. Re-entry blocked.",
            "data" => null
        );
        echo json_encode($response);
        exit;
    }

    if ($subRow['status'] === "submitted" || $subRow['status'] === "terminated_cheat") {
        $_SESSION['student_id'] = $studentId;
        $_SESSION['org_code'] = $orgCode;
        $_SESSION['user_role'] = "student";
        $_SESSION['logged_in'] = true;

        $response = array(
            "success" => true,
            "message" => "Assessment already completed.",
            "data" => array(
                "isAlreadyCompleted" => true,
                "isResuming" => false,
                "quiz" => $quizData,
                "submission" => $subData
            )
        );
        echo json_encode($response);
        exit;
    }

    // Resuming an in-progress attempt
    $_SESSION['student_id'] = $studentId;
    $_SESSION['org_code'] = $orgCode;
    $_SESSION['user_role'] = "student";
    $_SESSION['logged_in'] = true;

    $response = array(
        "success" => true,
        "message" => "Resuming active assessment attempt.",
        "data" => array(
            "isAlreadyCompleted" => false,
            "isResuming" => true,
            "quiz" => $quizData,
            "submission" => $subData
        )
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($subStmt);

// ===== FOR NEW CANDIDATE ATTEMPTS: CHECK ROOM STATUS =====
if ($quizRow['status'] === "ended") {
    $response = array(
        "success" => false,
        "message" => "This quiz session has already ended.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// ===== CREATE A NEW SUBMISSION RECORD =====
$newStatus = "in_progress";
$autoSub = 0;
$emptyJson = "{}";
$tabViolations = 0;
$sessionPurged = 0;

$insertSubSql = "INSERT INTO results (quiz_org_id, student_id, status, auto_submitted, responses, time_spent, tab_violations, session_purged) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$insertSubStmt = mysqli_prepare($conn, $insertSubSql);

if ($insertSubStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error creating attempt: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($insertSubStmt, "sssisssi", $orgCode, $studentId, $newStatus, $autoSub, $emptyJson, $emptyJson, $tabViolations, $sessionPurged);
$insertOk = mysqli_stmt_execute($insertSubStmt);

if ($insertOk === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to initialize student attempt: " . mysqli_stmt_error($insertSubStmt),
        "data" => null
    );
    mysqli_stmt_close($insertSubStmt);
    echo json_encode($response);
    exit;
}

$newInsertId = mysqli_insert_id($conn);
mysqli_stmt_close($insertSubStmt);

// Store student session
$_SESSION['student_id'] = $studentId;
$_SESSION['org_code'] = $orgCode;
$_SESSION['user_role'] = "student";
$_SESSION['logged_in'] = true;

$newSubData = array(
    "id" => $newInsertId,
    "orgCode" => $orgCode,
    "studentId" => $studentId,
    "status" => "in_progress",
    "autoSubmitted" => false,
    "responses" => new stdClass(),
    "timeSpentPerQuestion" => new stdClass(),
    "tabViolations" => 0,
    "score" => null,
    "submittedAt" => null,
    "sessionPurged" => false
);

$response = array(
    "success" => true,
    "message" => "Joined quiz room successfully.",
    "data" => array(
        "isAlreadyCompleted" => false,
        "isResuming" => false,
        "quiz" => $quizData,
        "submission" => $newSubData
    )
);

echo json_encode($response);
?>

