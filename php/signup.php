<?php
// =============================================================================
// QuizPulse — php/signup.php
// Registers a new organizer / teacher account.
// Enforces one teacher account per email address.
// =============================================================================

require_once 'db.php';

header("Content-Type: application/json");

// Read POST data sent from the JavaScript fetch request
$email = "";
$password = "";
$displayName = "";

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
}
if (isset($_POST['password'])) {
    $password = $_POST['password'];
}
if (isset($_POST['name'])) {
    $displayName = trim($_POST['name']);
}

// ===== CHECK THE USER INPUT =====
if ($email === "" || $password === "") {
    $response = array(
        "success" => false,
        "message" => "Please provide both an email address and a password.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

$cleanEmail = strtolower($email);

$resolvedName = $cleanEmail;
if ($displayName !== "") {
    $resolvedName = $displayName;
}

// ===== CHECK IF AN ACCOUNT WITH THIS EMAIL ALREADY EXISTS =====
// We use a prepared statement to prevent SQL injection attacks.
// SQL injection happens when malicious text in the email field alters the SQL query.
$checkSql = "SELECT id FROM users WHERE email = ?";
$checkStmt = mysqli_prepare($conn, $checkSql);

if ($checkStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Database prepare statement failed: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($checkStmt, "s", $cleanEmail);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    mysqli_stmt_close($checkStmt);
    $response = array(
        "success" => false,
        "message" => "An account with email " . $cleanEmail . " already exists. Please Sign In.",
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($checkStmt);

// ===== HASH THE PASSWORD SECURELY =====
// We never store plain text passwords in the database.
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = "teacher";
$createdAt = date("Y-m-d H:i:s");

// ===== INSERT NEW USER INTO DATABASE =====
// Prepared statement protects against SQL injection
$insertSql = "INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)";
$insertStmt = mysqli_prepare($conn, $insertSql);

if ($insertStmt === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to prepare insert query: " . mysqli_error($conn),
        "data" => null
    );
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($insertStmt, "sssss", $resolvedName, $cleanEmail, $hashedPassword, $role, $createdAt);
$executeSuccess = mysqli_stmt_execute($insertStmt);

if ($executeSuccess === false) {
    $response = array(
        "success" => false,
        "message" => "Failed to create account: " . mysqli_stmt_error($insertStmt),
        "data" => null
    );
    mysqli_stmt_close($insertStmt);
    echo json_encode($response);
    exit;
}

mysqli_stmt_close($insertStmt);

// ===== STORE TEACHER SESSION IN PHP SESSION =====
$_SESSION['user_email'] = $cleanEmail;
$_SESSION['user_name'] = $resolvedName;
$_SESSION['user_role'] = "teacher";
$_SESSION['logged_in'] = true;

// Send success response back to JavaScript
$teacherData = array(
    "email" => $cleanEmail,
    "name" => $resolvedName,
    "role" => "teacher"
);

$response = array(
    "success" => true,
    "message" => "Account created successfully! Welcome, " . $resolvedName,
    "data" => $teacherData
);

echo json_encode($response);
?>

