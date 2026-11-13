<?php
// =============================================================================
// QuizPulse — php/get_analysis.php
// Calculates performance statistics, class averages, and question difficulty
// percentages for an entire quiz session.
// Replaces the client-side score analysis with server-side processing.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

$orgCode = "";
if (isset($_GET['code'])) {
    $orgCode = trim($_GET['code']);
} else if (isset($_POST['code'])) {
    $orgCode = trim($_POST['code']);
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

// 1. Fetch questions and answer key
$qSql = "SELECT question_id, question_text, correct_option FROM questions WHERE quiz_org_id = ? ORDER BY id ASC";
$qStmt = mysqli_prepare($conn, $qSql);

if ($qStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error loading questions: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($qStmt, "s", $orgCode);
mysqli_stmt_execute($qStmt);
$qResult = mysqli_stmt_get_result($qStmt);

$questions = array();
$questionCorrectCounts = array();

while ($qRow = mysqli_fetch_assoc($qResult)) {
    $qId = $qRow['question_id'];
    $questions[] = array(
        "id" => $qId,
        "questionText" => $qRow['question_text'],
        "correctOption" => (int)$qRow['correct_option']
    );
    $questionCorrectCounts[$qId] = 0;
}
mysqli_stmt_close($qStmt);

// 2. Fetch submissions
$subSql = "SELECT * FROM results WHERE quiz_org_id = ?";
$subStmt = mysqli_prepare($conn, $subSql);
mysqli_stmt_bind_param($subStmt, "s", $orgCode);
mysqli_stmt_execute($subStmt);
$subResult = mysqli_stmt_get_result($subStmt);

$totalAttempts = 0;
$submittedCount = 0;
$inProgressCount = 0;
$terminatedCount = 0;
$scoredCount = 0;
$scoreSum = 0;
$highestScore = 0;
$lowestScore = 999999;

while ($sub = mysqli_fetch_assoc($subResult)) {
    $totalAttempts = $totalAttempts + 1;

    if ($sub['status'] === "submitted") {
        $submittedCount = $submittedCount + 1;
    } else if ($sub['status'] === "terminated_cheat") {
        $terminatedCount = $terminatedCount + 1;
    } else {
        $inProgressCount = $inProgressCount + 1;
    }

    if ($sub['score'] !== null) {
        $currentScore = (int)$sub['score'];
        $scoreSum = $scoreSum + $currentScore;
        $scoredCount = $scoredCount + 1;

        if ($currentScore > $highestScore) {
            $highestScore = $currentScore;
        }
        if ($currentScore < $lowestScore) {
            $lowestScore = $currentScore;
        }
    }

    // Check responses for per-question accuracy
    $responses = json_decode($sub['responses'], true);
    if (is_array($responses)) {
        for ($i = 0; $i < count($questions); $i = $i + 1) {
            $q = $questions[$i];
            $qId = $q['id'];
            if (isset($responses[$qId])) {
                $studentChoice = (int)$responses[$qId];
                if ($studentChoice === $q['correctOption']) {
                    $questionCorrectCounts[$qId] = $questionCorrectCounts[$qId] + 1;
                }
            }
        }
    }
}
mysqli_stmt_close($subStmt);

$averageScore = 0.0;
if ($scoredCount > 0) {
    $averageScore = round($scoreSum / $scoredCount, 1);
} else {
    $lowestScore = 0;
}
if ($lowestScore === 999999) {
    $lowestScore = 0;
}

// Calculate per-question percentages
$questionStatsList = array();
for ($i = 0; $i < count($questions); $i = $i + 1) {
    $q = $questions[$i];
    $qId = $q['id'];
    $correctCount = $questionCorrectCounts[$qId];

    $percentage = 0;
    if ($scoredCount > 0) {
        $percentage = round(($correctCount / $scoredCount) * 100);
    }

    $questionStatsList[] = array(
        "id" => $qId,
        "questionText" => $q['questionText'],
        "correctCount" => $correctCount,
        "percentageCorrect" => $percentage
    );
}

$analysisData = array(
    "totalAttempts" => $totalAttempts,
    "submittedCount" => $submittedCount,
    "inProgressCount" => $inProgressCount,
    "terminatedCount" => $terminatedCount,
    "scoredCount" => $scoredCount,
    "averageScore" => $averageScore,
    "highestScore" => $highestScore,
    "lowestScore" => $lowestScore,
    "questionStats" => $questionStatsList
);

$response = array(
    "success" => true,
    "message" => "Analysis calculated successfully.",
    "data" => $analysisData
);

echo json_encode($response);
?>

