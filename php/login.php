<?php
// =============================================================================
// QuizPulse — php/login.php
// Signs in an existing organizer / teacher account.
// Verifies password using password_verify() against database hash.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read POST data sent from the JavaScript fetch request
$email = "";
$password = "";

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
}
if (isset($_POST['password'])) {
    $password = $_POST['password'];
}

// ===== CHECK THE USER INPUT =====
if ($email === "" || $password === "") {
    $response = array(
        "success" => false,
        "message" => "Please enter both your email address and password.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

$cleanEmail = strtolower($email);

// ===== FIND USER IN DATABASE =====
// Prepared statement stops attackers from injecting malicious SQL through the email input
$querySql = "SELECT id, name, email, password, role FROM users WHERE email = ? AND role = 'teacher' LIMIT 1";
$stmt = mysqli_prepare($conn, $querySql);

if ($stmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database error preparing query: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $cleanEmail);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($stmt);
    $response = array(
        "success" => false,
        "message" => "No organizer account found for " . $cleanEmail . ". Please create an account first.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// ===== VERIFY PASSWORD HASH =====
$passwordMatches = password_verify($password, $user['password']);

if ($passwordMatches === false) {
    $response = array(
        "success" => false,
        "message" => "Incorrect password for " . $cleanEmail . ".",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

// ===== SAVE TEACHER DETAILS IN PHP SESSION =====
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = "teacher";
$_SESSION['logged_in'] = true;

$teacherData = array(
    "email" => $user['email'],
    "name" => $user['name'],
    "role" => "teacher"
);

$response = array(
    "success" => true,
    "message" => "Signed in successfully. Welcome back, " . $user['name'],
    "data" => $teacherData
);

echo json_encode($response);
?>

