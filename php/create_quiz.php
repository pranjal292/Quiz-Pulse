<?php
// =============================================================================
// QuizPulse — php/create_quiz.php
// Creates a new assessment in the database and saves its questions.
// Accessible to organizers.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read raw request body in case data is sent as JSON
$rawInput = file_get_contents("php://input");
$inputData = json_decode($rawInput, true);

// If not sent as JSON, fall back to $_POST
if (!is_array($inputData)) {
    $inputData = $_POST;
}

$quizTitle = "";
$entryWindow = 300;
$attemptDuration = 600;
$postVisibility = "marks_only";
$questions = array();
$answerKey = array();
$desiredOrgId = "";

if (isset($inputData['orgId'])) {
    $desiredOrgId = trim($inputData['orgId']);
}
if (isset($inputData['quizTitle'])) {
    $quizTitle = trim($inputData['quizTitle']);
}
if (isset($inputData['entryWindowSeconds'])) {
    $entryWindow = (int)$inputData['entryWindowSeconds'];
}
if (isset($inputData['attemptDurationSeconds'])) {
    $attemptDuration = (int)$inputData['attemptDurationSeconds'];
}
if (isset($inputData['postQuizVisibility'])) {
    $postVisibility = trim($inputData['postQuizVisibility']);
}
if (isset($inputData['questions']) && is_array($inputData['questions'])) {
    $questions = $inputData['questions'];
}
if (isset($inputData['answerKey']) && is_array($inputData['answerKey'])) {
    $answerKey = $inputData['answerKey'];
}

// Check nested timers/settings structures
if (isset($inputData['timers'])) {
    if (isset($inputData['timers']['entryWindowSeconds'])) {
        $entryWindow = (int)$inputData['timers']['entryWindowSeconds'];
    }
    if (isset($inputData['timers']['attemptDurationSeconds'])) {
        $attemptDuration = (int)$inputData['timers']['attemptDurationSeconds'];
    }
}
if (isset($inputData['settings']) && isset($inputData['settings']['postQuizVisibility'])) {
    $postVisibility = trim($inputData['settings']['postQuizVisibility']);
}

// Teacher email: from session or from request payload
$teacherEmail = "teacher@test.com";
if (isset($_SESSION['user_email']) && $_SESSION['user_email'] !== "") {
    $teacherEmail = $_SESSION['user_email'];
} else if (isset($inputData['organizerEmail']) && $inputData['organizerEmail'] !== "") {
    $teacherEmail = trim($inputData['organizerEmail']);
} else if (isset($inputData['teacherEmail']) && $inputData['teacherEmail'] !== "") {
    $teacherEmail = trim($inputData['teacherEmail']);
}

// Validate inputs
if ($quizTitle === "") {
    $response = array(
        "success" => false,
        "message" => "Quiz title is required.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

if (count($questions) === 0) {
    $response = array(
        "success" => false,
        "message" => "At least one question is required to create a quiz.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// Room code: if a 6-digit code was sent from frontend, check if available; otherwise generate one
$roomCode = "";

if (strlen($desiredOrgId) === 6) {
    // Check if this desired code is already used
    $checkSql = "SELECT id FROM quizzes WHERE org_id = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    mysqli_stmt_bind_param($checkStmt, "s", $desiredOrgId);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($checkResult) === 0) {
        $roomCode = $desiredOrgId;
    }
    mysqli_stmt_close($checkStmt);
}

// If no available code yet, generate a random 6-digit room code
if ($roomCode === "") {
    $codeExists = true;
    while ($codeExists === true) {
        $candidateCode = (string)rand(100000, 999999);
        $checkSql = "SELECT id FROM quizzes WHERE org_id = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $candidateCode);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if (mysqli_num_rows($checkResult) === 0) {
            $codeExists = false;
            $roomCode = $candidateCode;
        }
        mysqli_stmt_close($checkStmt);
    }
}

$status = "waiting";
$createdAt = date("Y-m-d H:i:s");

// ===== 1. INSERT QUIZ RECORD =====
// Prepared statement stops SQL injection
$quizSql = "INSERT INTO quizzes (org_id, teacher_email, quiz_title, entry_window_seconds, attempt_duration_seconds, post_quiz_visibility, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$quizStmt = mysqli_prepare($conn, $quizSql);

if ($quizStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing quiz insert: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($quizStmt, "sssiisss", $roomCode, $teacherEmail, $quizTitle, $entryWindow, $attemptDuration, $postVisibility, $status, $createdAt);
$executeOk = mysqli_stmt_execute($quizStmt);

if ($executeOk === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to save quiz: " . mysqli_stmt_error($quizStmt),
        "data" => null
    );
    mysqli_stmt_close($quizStmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($quizStmt);

// ===== 2. INSERT QUESTIONS =====
$qSql = "INSERT INTO questions (quiz_org_id, question_id, question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$qStmt = mysqli_prepare($conn, $qSql);

if ($qStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing questions insert: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

for ($i = 0; $i < count($questions); $i = $i + 1) {
    $q = $questions[$i];
    $qId = isset($q['id']) ? $q['id'] : ("q" . ($i + 1));
    $qText = isset($q['questionText']) ? $q['questionText'] : "";

    $opts = isset($q['options']) && is_array($q['options']) ? $q['options'] : array();
    $optA = isset($opts[0]) ? $opts[0] : "";
    $optB = isset($opts[1]) ? $opts[1] : "";
    $optC = isset($opts[2]) ? $opts[2] : "";
    $optD = isset($opts[3]) ? $opts[3] : "";

    $correctIndex = 0;
    if (isset($q['correctOptionIndex'])) {
        $correctIndex = (int)$q['correctOptionIndex'];
    } else if (isset($answerKey[$qId])) {
        $correctIndex = (int)$answerKey[$qId];
    }

    mysqli_stmt_bind_param($qStmt, "sssssssi", $roomCode, $qId, $qText, $optA, $optB, $optC, $optD, $correctIndex);
    mysqli_stmt_execute($qStmt);
}

mysqli_stmt_close($qStmt);

$createdQuizData = array(
    "orgId" => $roomCode,
    "quizTitle" => $quizTitle,
    "teacherEmail" => $teacherEmail,
    "entryWindowSeconds" => $entryWindow,
    "attemptDurationSeconds" => $attemptDuration,
    "postQuizVisibility" => $postVisibility,
    "status" => $status,
    "createdAt" => $createdAt,
    "questionsCount" => count($questions)
);

$response = array(
    "success" => true,
    "message" => "Quiz created successfully! Room code: " . $roomCode,
    "data" => $createdQuizData
);

echo json_encode($response);
?>
