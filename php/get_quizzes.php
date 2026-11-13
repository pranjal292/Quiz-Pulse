<?php
// =============================================================================
// QuizPulse — php/get_quizzes.php
// Returns all quizzes created by a teacher, sorted newest first.
// Includes summary of candidate attempts, scores, and status for each quiz.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Determine which teacher's quizzes to fetch
$filterEmail = "";
if (isset($_GET['email'])) {
    $filterEmail = trim($_GET['email']);
} else if (isset($_SESSION['user_email'])) {
    $filterEmail = $_SESSION['user_email'];
}

$quizzesList = array();

// Build query
if ($filterEmail !== "" && $filterEmail !== "all") {
    $sql = "SELECT * FROM quizzes WHERE teacher_email = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $filterEmail);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT * FROM quizzes ORDER BY created_at DESC";
    $result = mysqli_query($conn, $sql);
    $stmt = null;
}

if ($result === false) {
    $response = array(
        "success" => false,
        "message" => "Database error loading quizzes: " . mysqli_error($conn),
        "data" => array()
    );
    echo json_encode($response);
    exit;
}

while ($quizRow = mysqli_fetch_assoc($result)) {
    $orgCode = $quizRow['org_id'];

    // 1. Fetch questions for this quiz
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

    // 2. Fetch submissions for this quiz
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

    $quizzesList[] = $quizObject;
}

if ($stmt !== null) {
    mysqli_stmt_close($stmt);
}

$response = array(
    "success" => true,
    "message" => "Quizzes loaded successfully.",
    "data" => $quizzesList
);

echo json_encode($response);
?>

