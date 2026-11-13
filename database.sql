-- =============================================================================
-- QuizPulse Database Schema
-- Compatible with MySQL running on XAMPP (phpMyAdmin)
-- =============================================================================

-- Step 1: Create the database if it does not already exist
CREATE DATABASE IF NOT EXISTS quizpulse;

-- Step 2: Switch to the quizpulse database
USE quizpulse;

-- Step 3: Drop tables in reverse order of foreign keys for clean reset
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS users;

-- -----------------------------------------------------------------------------
-- TABLE 1: users
-- This table stores organizer (teacher) accounts.
-- Columns:
--   id         : Unique auto-incrementing number for each user.
--   name       : Full name or display name of the teacher.
--   email      : Unique login email of the teacher.
--   password   : Secure password hash created with PHP's password_hash().
--   role       : User role in the system (e.g. 'teacher').
--   created_at : Date and time when the user signed up.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'teacher',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- TABLE 2: quizzes
-- This table stores all quizzes created by teachers.
-- Columns:
--   id                       : Unique auto-incrementing number for each quiz.
--   org_id                   : The 6-digit room code used by students to join.
--   teacher_email            : Email of the teacher who created this quiz.
--   quiz_title               : Title of the quiz / exam.
--   entry_window_seconds     : Timer 1: Allowed entry window in seconds (default 300 = 5 mins).
--   attempt_duration_seconds : Timer 2: Exam duration in seconds (default 600 = 10 mins).
--   post_quiz_visibility     : 'marks_only', 'marks_and_answers', or 'full_analysis'.
--   status                   : Room state: 'waiting', 'active', or 'ended'.
--   quiz_start_timestamp     : Milliseconds timestamp when teacher clicked 'Start Quiz'.
--   created_at               : Date and time when the quiz was created.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id VARCHAR(10) NOT NULL UNIQUE,
    teacher_email VARCHAR(100) NOT NULL,
    quiz_title VARCHAR(255) NOT NULL,
    entry_window_seconds INT NOT NULL DEFAULT 300,
    attempt_duration_seconds INT NOT NULL DEFAULT 600,
    post_quiz_visibility VARCHAR(50) NOT NULL DEFAULT 'marks_only',
    status VARCHAR(20) NOT NULL DEFAULT 'waiting',
    quiz_start_timestamp BIGINT DEFAULT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- TABLE 3: questions
-- This table stores individual questions belonging to a quiz.
-- Columns:
--   id             : Unique auto-incrementing number for each question.
--   quiz_org_id    : The 6-digit room code of the quiz this question belongs to.
--   question_id    : Question identifier string (such as 'q1', 'q2').
--   question_text  : The actual prompt / question asked.
--   option_a       : Text for option A (index 0).
--   option_b       : Text for option B (index 1).
--   option_c       : Text for option C (index 2).
--   option_d       : Text for option D (index 3).
--   correct_option : Index of the correct answer (0 = A, 1 = B, 2 = C, 3 = D).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_org_id VARCHAR(10) NOT NULL,
    question_id VARCHAR(50) NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_option INT NOT NULL,
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_org_id) REFERENCES quizzes(org_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- TABLE 4: results
-- This table stores student attempts, drafts, violations, and final scores.
-- Columns:
--   id                 : Unique auto-incrementing number for each attempt.
--   quiz_org_id        : The 6-digit room code of the quiz.
--   student_id         : Unique identifier / username of the student.
--   status             : Attempt state: 'in_progress', 'submitted', or 'terminated_cheat'.
--   auto_submitted     : 1 if submitted automatically by timer or teacher, 0 if manual.
--   termination_reason : Reason for completion ('manual', 'timer_expired', 'tab_switch', etc.).
--   responses          : JSON text of student's answers (e.g. {"q1": 1, "q2": 2}).
--   time_spent         : JSON text of seconds spent on each question.
--   tab_violations     : Number of anti-cheat integrity violations (tab switches, etc.).
--   score              : Total points scored by the student (number of correct answers).
--   submitted_at       : Date and time when the student finalized or was terminated.
--   session_purged     : 1 if the student has logged out and closed attempt, blocking re-entry.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_org_id VARCHAR(10) NOT NULL,
    student_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
    auto_submitted TINYINT(1) NOT NULL DEFAULT 0,
    termination_reason VARCHAR(100) DEFAULT NULL,
    responses TEXT DEFAULT NULL,
    time_spent TEXT DEFAULT NULL,
    tab_violations INT NOT NULL DEFAULT 0,
    score INT DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    session_purged TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_results_quiz FOREIGN KEY (quiz_org_id) REFERENCES quizzes(org_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================================
-- SAMPLE DATA FOR TESTING
-- =============================================================================

-- Sample Teacher 1:
-- Email: teacher@test.com
-- Password: password123 (hashed using standard bcrypt)
INSERT INTO users (name, email, password, role, created_at)
VALUES (
    'Prof. Alan Turing',
    'teacher@test.com',
    '$2y$10$wT0lV3fJkQ6B7YgT2u7QOOKyJjM3Qe4V7l0f2r8H7iJ5t9k1P6sC6',
    'teacher',
    NOW()
)
ON DUPLICATE KEY UPDATE id=id;

-- Sample Quiz:
-- Room Code: 101101
INSERT INTO quizzes (org_id, teacher_email, quiz_title, entry_window_seconds, attempt_duration_seconds, post_quiz_visibility, status, quiz_start_timestamp, created_at)
VALUES (
    '101101',
    'teacher@test.com',
    'Computer Science Assessment',
    300,
    600,
    'full_analysis',
    'waiting',
    NULL,
    NOW()
)
ON DUPLICATE KEY UPDATE id=id;

-- Sample Questions for Quiz 101101:
INSERT INTO questions (quiz_org_id, question_id, question_text, option_a, option_b, option_c, option_d, correct_option)
VALUES
(
    '101101',
    'q1',
    'Which data structure follows the LIFO (Last-In First-Out) principle?',
    'Queue',
    'Stack',
    'Binary Tree',
    'Linked List',
    1
),
(
    '101101',
    'q2',
    'What is the worst-case time complexity of standard QuickSort?',
    'O(n)',
    'O(n log n)',
    'O(n^2)',
    'O(1)',
    2
),
(
    '101101',
    'q3',
    'Which HTML5 tag is used to include client-side JavaScript code?',
    '<js>',
    '<script>',
    '<javascript>',
    '<code>',
    1
);

-- Sample Result for Student 'STU101' in Quiz 101101:
INSERT INTO results (quiz_org_id, student_id, status, auto_submitted, termination_reason, responses, time_spent, tab_violations, score, submitted_at, session_purged)
VALUES (
    '101101',
    'STU101',
    'submitted',
    0,
    'manual',
    '{"q1": 1, "q2": 2, "q3": 0}',
    '{"q1": 15, "q2": 45, "q3": 20}',
    0,
    2,
    NOW(),
    0
);

