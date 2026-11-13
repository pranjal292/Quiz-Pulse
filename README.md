# QuizPulse — Real-Time Assessment Platform

QuizPulse is a lightweight, real-time examination and assessment platform built with vanilla **HTML5**, **CSS3**, and **JavaScript**, powered by a beginner-friendly **PHP (Procedural MySQLi)** backend and **MySQL** database running on **XAMPP**.

---

## Features

- **Organizer Studio**:
  - Create assessments using standardized JSON schema.
  - Built-in schema validator with syntax and key error feedback.
  - Real-time room control (**Start Quiz**, **End Quiz**) with synchronized 6-digit room codes.
  - Post-quiz visibility configuration:
    - `marks_only`: Candidate views official score only.
    - `marks_and_answers`: Candidate views score plus question-and-answer comparison.
    - `full_analysis`: Candidate views score, breakdown, and overall performance metrics.
  - Historical reports, class averages, question difficulty percentages, and candidate drill-downs.

- **Student Portal**:
  - Simple 6-digit room code check-in with single-attempt enforcement.
  - Synchronized waiting lobby with live entry window countdown (**Timer 1**).
  - Fullscreen examination view with live attempt duration timer (**Timer 2**).
  - Anti-cheat monitoring (detects tab switches, window blur, exiting fullscreen, right-click context menu, copy/paste attempts).
  - Autosaved question drafts to prevent data loss on accidental disconnects.
  - Immediate evaluation and breakdown based on organizer-configured visibility.

---

## Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript (Procedural style, `fetch()`, `async/await`)
- **Backend**: Plain PHP (Procedural style using the `mysqli` extension)
- **Database**: MySQL (Managed with phpMyAdmin on XAMPP)
- **Authentication & Sessions**: Built-in PHP Sessions (`$_SESSION`) with `password_hash()` and `password_verify()`

---

## File Structure

```text
Quiz-pulse/
├── index.html                   # Landing page (teacher login/signup & student code check)
├── teacher.html                 # Organizer dashboard, studio, live room & drill-downs
├── student.html                 # Candidate portal & synchronized waiting lobby
├── quiz.html                    # Candidate exam screen (fullscreen, timer, anti-cheat)
├── auth.js                      # Frontend JS: auth calls, fetch wrappers, statistical math
├── app.js                       # Frontend JS: active quiz runner, timers, options, autosave
├── database.sql                 # MySQL schema + tables + sample data for phpMyAdmin
├── README.md                    # Project documentation
└── php/                         # Backend PHP folder (Procedural MySQLi)
    ├── db.php                   # Database connection ($conn) & session_start()
    ├── signup.php               # Teacher registration (password_hash)
    ├── login.php                # Teacher login (password_verify)
    ├── student_login.php        # Candidate check-in & single attempt enforcement
    ├── logout.php               # Clears $_SESSION and expires session cookies
    ├── check_login.php          # Session verification & route guard
    ├── create_quiz.php          # Saves new assessment and questions into MySQL
    ├── get_quizzes.php          # Lists quizzes for teacher dashboard
    ├── get_quiz_by_code.php     # Fetches quiz metadata, roster, and submissions
    ├── get_questions.php        # Delivers exam questions without leaking answers
    ├── update_status.php        # Room control: 'waiting' -> 'active' -> 'ended'
    ├── update_visibility.php    # Real-time results visibility setting
    ├── save_draft.php           # Real-time autosave of in-progress candidate answers
    ├── record_violation.php     # Anti-cheat strike counter (tab switch, blur, etc.)
    ├── save_result.php          # Grades quiz server-side and locks submission
    ├── purge_session.php        # Purges candidate session and blocks re-entry
    ├── get_past_results.php     # Candidate drill-down analysis
    └── get_analysis.php         # Class averages and question difficulty percentages
```

---

## Quick Setup Guide (XAMPP)

### 1. Start Services in XAMPP
1. Open the **XAMPP Control Panel**.
2. Start both **Apache** and **MySQL** (both should turn green).

### 2. Import Database
1. Open your browser and navigate to: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click the **Import** tab.
3. Select `database.sql` from this repository.
4. Click **Import** at the bottom. The `quizpulse` database and all required tables (`users`, `quizzes`, `questions`, `results`) will be created with sample testing data.

### 3. Deploy to `htdocs`
1. Copy or clone this project folder into your XAMPP web directory:
   ```text
   C:\xampp\htdocs\quizpulse
   ```
2. Verify database settings in `php/db.php`:
   ```php
   $dbHost = "localhost";
   $dbUser = "root";
   $dbPass = "";
   $dbName = "quizpulse";
   ```

### 4. Open in Browser
Open your browser and navigate to:
👉 **`http://localhost/quizpulse/index.html`**

---

## Test Accounts

- **Organizer / Teacher Login**:
  - Email: `teacher@test.com`
  - Password: `password123`
- **Sample Assessment Room**:
  - Room Code: `101101`
- **Candidate / Student Check-In**:
  - Room Code: `101101`
  - Student ID: `STU102`
  - Password: `STU102` *(Rule: Password matches Student ID)*

---

## Assessment JSON Schema (Organizer Studio)

Assessments can be created in the Organizer Studio using the standardized JSON schema:

```json
{
  "quizTitle": "Computer Science Assessment",
  "timers": {
    "entryWindowSeconds": 300,
    "attemptDurationSeconds": 600
  },
  "settings": {
    "postQuizVisibility": "full_analysis"
  },
  "questions": [
    {
      "id": "q1",
      "questionText": "Which data structure follows the LIFO (Last-In First-Out) principle?",
      "options": ["Queue", "Stack", "Binary Tree", "Linked List"],
      "correctOptionIndex": 1
    },
    {
      "id": "q2",
      "questionText": "What is the worst-case time complexity of standard QuickSort?",
      "options": ["O(n)", "O(n log n)", "O(n^2)", "O(1)"],
      "correctOptionIndex": 2
    }
  ]
}
```

---

## License

MIT License &copy; 2026 QuizPulse
