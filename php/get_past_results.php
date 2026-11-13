<?php
// =============================================================================
// QuizPulse — php/get_past_results.php
// Fetches past submissions or individual candidate drill-down data for a quiz.
// Accessible to organizers to inspect candidate attempts.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

$orgCode = "";
$studentId = "";

if (isset($_GET['code'])) {
    $orgCode = trim($_GET['code']);
} else if (isset($_POST['code'])) {
    $orgCode = trim($_POST['code']);
}

if (isset($_GET['student'])) {
    $studentId = trim($_GET['student']);
} else if (isset($_POST['student'])) {
    $studentId = trim($_POST['student']);
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

// 1. If studentId is provided, build individual drill-down
if ($studentId !== "") {
    // Fetch quiz questions
    $qSql = "SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE quiz_org_id = ? ORDER BY id ASC";
    $qStmt = mysqli_prepare($conn, $qSql);
    mysqli_stmt_bind_param($qStmt, "s", $orgCode);
    mysqli_stmt_execute($qStmt);
    $qResult = mysqli_stmt_get_result($qStmt);

    $questions = array();
    while ($qRow = mysqli_fetch_assoc($qResult)) {
        $options = array();
        if ($qRow['option_a'] !== "") { $options[] = $qRow['option_a']; }
        if ($qRow['option_b'] !== "") { $options[] = $qRow['option_b']; }
        if ($qRow['option_c'] !== "") { $options[] = $qRow['option_c']; }
        if ($qRow['option_d'] !== "") { $options[] = $qRow['option_d']; }

        $questions[] = array(
            "id" => $qRow['question_id'],
            "questionText" => $qRow['question_text'],
            "options" => $options,
            "correctOption" => (int)$qRow['correct_option']
        );
    }
    mysqli_stmt_close($qStmt);

    // Fetch student's submission record
    $subSql = "SELECT * FROM results WHERE quiz_org_id = ? AND student_id = ? LIMIT 1";
    $subStmt = mysqli_prepare($conn, $subSql);
    mysqli_stmt_bind_param($subStmt, "ss", $orgCode, $studentId);
    mysqli_stmt_execute($subStmt);
    $subResult = mysqli_stmt_get_result($subStmt);

    if (mysqli_num_rows($subResult) === 0) {
        mysqli_stmt_close($subStmt);
        $response = array(
            "success" => false,
            "message" => "Submission for student " . $studentId . " not found.",
            "data" => null
        );
        echo json_encode($response);
        exit;
    }

    $subRow = mysqli_fetch_assoc($subResult);
    mysqli_stmt_close($subStmt);

    $responses = json_decode($subRow['responses'], true);
    if (!is_array($responses)) {
        $responses = array();
    }

    $timeSpent = json_decode($subRow['time_spent'], true);
    if (!is_array($timeSpent)) {
        $timeSpent = array();
    }

    $drilldownQuestions = array();
    for ($i = 0; $i < count($questions); $i = $i + 1) {
        $q = $questions[$i];
        $qId = $q['id'];
        $correctIndex = $q['correctOption'];

        $studentChoiceIndex = null;
        if (isset($responses[$qId])) {
            $studentChoiceIndex = (int)$responses[$qId];
        }

        $isCorrect = false;
        if ($studentChoiceIndex !== null && $studentChoiceIndex === $correctIndex) {
            $isCorrect = true;
        }

        $studentChoiceText = "Not Answered";
        if ($studentChoiceIndex !== null && isset($q['options'][$studentChoiceIndex])) {
            $studentChoiceText = chr(65 + $studentChoiceIndex) . ". " . $q['options'][$studentChoiceIndex];
        }

        $correctChoiceText = "Not Available";
        if (isset($q['options'][$correctIndex])) {
            $correctChoiceText = chr(65 + $correctIndex) . ". " . $q['options'][$correctIndex];
        }

        $timeSpentSeconds = 0;
        if (isset($timeSpent[$qId])) {
            $timeSpentSeconds = (int)$timeSpent[$qId];
        }

        $drilldownQuestions[] = array(
            "number" => $i + 1,
            "id" => $qId,
            "questionText" => $q['questionText'],
            "studentChoiceIndex" => $studentChoiceIndex,
            "studentChoiceText" => $studentChoiceText,
            "correctChoiceIndex" => $correctIndex,
            "correctChoiceText" => $correctChoiceText,
            "isCorrect" => $isCorrect,
            "timeSpentSeconds" => $timeSpentSeconds
        );
    }

    $submissionData = array(
        "id" => (int)$subRow['id'],
        "studentId" => $subRow['student_id'],
        "status" => $subRow['status'],
        "autoSubmitted" => (bool)$subRow['auto_submitted'],
        "terminationReason" => $subRow['termination_reason'],
        "tabViolations" => (int)$subRow['tab_violations'],
        "score" => $subRow['score'] !== null ? (int)$subRow['score'] : null,
        "submittedAt" => $subRow['submitted_at'],
        "sessionPurged" => (bool)$subRow['session_purged']
    );

    $response = array(
        "success" => true,
        "message" => "Drilldown loaded.",
        "data" => array(
            "studentId" => $studentId,
            "submission" => $submissionData,
            "questions" => $drilldownQuestions
        )
    );
    echo json_encode($response);
    exit;
}

// 2. If studentId is omitted, return all submissions for this quiz room
$subSql = "SELECT * FROM results WHERE quiz_org_id = ? ORDER BY id ASC";
$subStmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($subStmt, "s", $orgCode);
mysqli_stmt_execute($subStmt);
$subResult = mysqli_stmt_get_result($subStmt);

$submissionsList = array();
while ($row = mysqli_fetch_assoc($subResult)) {
    $submissionsList[] = array(
        "id" => (int)$row['id'],
        "studentId" => $row['student_id'],
        "status" => $row['status'],
        "autoSubmitted" => (bool)$row['auto_submitted'],
        "terminationReason" => $row['termination_reason'],
        "tabViolations" => (int)$row['tab_violations'],
        "score" => $row['score'] !== null ? (int)$row['score'] : null,
        "submittedAt" => $row['submitted_at'],
        "sessionPurged" => (bool)$row['session_purged']
    );
}
mysqli_stmt_close($subStmt);

$response = array(
    "success" => true,
    "message" => "Past results loaded.",
    "data" => $submissionsList
);

echo json_encode($response);
?>

