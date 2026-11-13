// =============================================================================
// QuizPulse — auth.js
// Frontend logic for authentication, database calls to PHP backend, and score analysis.
// Communicates with plain PHP scripts in the php/ folder using fetch() with async/await.
// =============================================================================


// =============================================================================
// 1. SIMPLE NOTIFICATION & DATE HELPERS
// =============================================================================

// Displays a notification toast banner at the top of the page
function showToast(messageText, isError) {
  let toastElement = document.getElementById("notification-toast");

  if (toastElement === null) {
    toastElement = document.createElement("div");
    toastElement.id = "notification-toast";
    toastElement.className = "toast-message hidden";
    document.body.appendChild(toastElement);
  }

  toastElement.textContent = messageText;

  if (isError === true) {
    toastElement.className = "toast-message toast-error";
  } else {
    toastElement.className = "toast-message toast-success";
  }

  toastElement.classList.remove("hidden");

  // Automatically hide the toast message after 4 seconds
  setTimeout(function() {
    toastElement.classList.add("hidden");
  }, 4000);
}

// Formats a date string into a friendly, human-readable format
function formatDateSimple(isoString) {
  if (isoString === null || isoString === undefined || isoString === "") {
    return "—";
  }

  const dateObject = new Date(isoString);
  if (isNaN(dateObject.getTime())) {
    return isoString;
  }

  const options = {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  };

  return dateObject.toLocaleDateString(undefined, options);
}


// =============================================================================
// 2. TEACHER AUTHENTICATION (CALLS php/signup.php, login.php, logout.php)
// Enforces: One teacher account per email address
// =============================================================================

// Registers a new organizer account by calling php/signup.php
async function teacherSignUp(email, password, displayName) {
  try {
    const cleanEmail = email.toLowerCase().trim();
    let resolvedName = cleanEmail;
    if (displayName && displayName.trim() !== "") {
      resolvedName = displayName.trim();
    }

    const formData = new FormData();
    formData.append("email", cleanEmail);
    formData.append("password", password);
    formData.append("name", resolvedName);

    // Call the PHP signup endpoint
    const response = await fetch("php/signup.php", {
      method: "POST",
      body: formData
    });

    const result = await response.json();

    if (result.success === false) {
      showToast(result.message, true);
      return {
        success: false,
        errorMessage: result.message
      };
    }

    // Save session in localStorage so dashboard can display name immediately
    const teacherSession = {
      email: cleanEmail,
      name: resolvedName,
      loggedInAt: new Date().toISOString()
    };
    localStorage.setItem("quizpulse_teacher", JSON.stringify(teacherSession));

    showToast("Account created successfully! Welcome, " + resolvedName, false);
    return {
      success: true,
      teacher: teacherSession
    };
  } catch (error) {
    showToast("Sign up error: " + error.message, true);
    return {
      success: false,
      errorMessage: error.message
    };
  }
}

// Signs in an existing organizer by calling php/login.php
async function teacherSignIn(email, password) {
  try {
    const cleanEmail = email.toLowerCase().trim();

    const formData = new FormData();
    formData.append("email", cleanEmail);
    formData.append("password", password);

    // Call the PHP login endpoint
    const response = await fetch("php/login.php", {
      method: "POST",
      body: formData
    });

    const result = await response.json();

    if (result.success === false) {
      return {
        success: false,
        errorMessage: result.message
      };
    }

    // Save active teacher session
    let teacherName = cleanEmail;
    if (result.data && result.data.name) {
      teacherName = result.data.name;
    }

    const teacherSession = {
      email: cleanEmail,
      name: teacherName,
      loggedInAt: new Date().toISOString()
    };
    localStorage.setItem("quizpulse_teacher", JSON.stringify(teacherSession));

    showToast("Signed in as " + teacherName, false);
    return {
      success: true,
      teacher: teacherSession
    };
  } catch (error) {
    showToast("Sign in error: " + error.message, true);
    return {
      success: false,
      errorMessage: error.message
    };
  }
}

// Signs out the teacher by calling php/logout.php and redirecting
async function teacherSignOut() {
  try {
    await fetch("php/logout.php");
  } catch (error) {
    // Continue even if network error
  }

  localStorage.removeItem("quizpulse_teacher");
  showToast("You have been signed out.", false);
  window.location.href = "index.html";
}

// Returns the currently logged in teacher from local storage
function getLoggedInTeacher() {
  const savedString = localStorage.getItem("quizpulse_teacher");
  if (savedString !== null && savedString !== "") {
    try {
      const teacherObject = JSON.parse(savedString);
      return teacherObject;
    } catch (parseError) {
      return null;
    }
  }
  return null;
}

// Route guard: checks session and redirects if not teacher
function requireTeacherAuth() {
  const localTeacher = getLoggedInTeacher();
  if (localTeacher === null) {
    window.location.href = "index.html";
    return null;
  }
  return localTeacher;
}


// =============================================================================
// 3. CANDIDATE / STUDENT AUTHENTICATION
// =============================================================================

// Validates student login credentials and checks room access via php/student_login.php
async function studentLogin(orgCode, studentId, password) {
  try {
    if (studentId === "" || password === "") {
      return {
        success: false,
        errorMessage: "Student ID and password are required."
      };
    }

    if (studentId !== password) {
      return {
        success: false,
        errorMessage: "Per system rules, your password must match your Student ID."
      };
    }

    const formData = new FormData();
    formData.append("orgCode", orgCode);
    formData.append("studentId", studentId);
    formData.append("password", password);

    // Call PHP student login script
    const response = await fetch("php/student_login.php", {
      method: "POST",
      body: formData
    });

    const result = await response.json();

    if (result.success === false) {
      return {
        success: false,
        errorMessage: result.message
      };
    }

    // Save session in sessionStorage
    const sessionData = {
      orgCode: orgCode,
      studentId: studentId,
      quizTitle: result.data.quiz.quizTitle,
      loggedInAt: new Date().toISOString()
    };
    sessionStorage.setItem("quizpulse_student", JSON.stringify(sessionData));

    return {
      success: true,
      isAlreadyCompleted: result.data.isAlreadyCompleted,
      isResuming: result.data.isResuming,
      quiz: result.data.quiz,
      submission: result.data.submission
    };
  } catch (error) {
    return {
      success: false,
      errorMessage: "Login error: " + error.message
    };
  }
}

// Saves student session to sessionStorage
function saveStudentSession(orgCode, studentId, quiz) {
  const sessionData = {
    orgCode: orgCode,
    studentId: studentId,
    quizTitle: quiz.quizTitle,
    loggedInAt: new Date().toISOString()
  };
  sessionStorage.setItem("quizpulse_student", JSON.stringify(sessionData));
}

// Returns the current student session from sessionStorage
function getLoggedInStudent() {
  const savedString = sessionStorage.getItem("quizpulse_student");
  if (savedString !== null && savedString !== "") {
    try {
      return JSON.parse(savedString);
    } catch (parseError) {
      return null;
    }
  }
  return null;
}

// Clears the student session and redirects
async function studentLogout() {
  try {
    await fetch("php/logout.php");
  } catch (err) {}

  sessionStorage.removeItem("quizpulse_student");
  window.location.href = "index.html";
}


// =============================================================================
// 4. DATABASE OPERATIONS (TALKS TO PHP BACKEND)
// =============================================================================

// Fetches all quizzes from the PHP backend
async function fetchAllQuizzes() {
  try {
    const response = await fetch("php/get_quizzes.php?email=all");
    const result = await response.json();

    if (result.success === true && Array.isArray(result.data)) {
      return result.data;
    }

    return [];
  } catch (error) {
    console.log("Error loading quizzes: " + error.message);
    return [];
  }
}

// Fetches a single quiz by its 6-digit room code
async function fetchQuizByCode(orgCode) {
  try {
    const response = await fetch("php/get_quiz_by_code.php?code=" + encodeURIComponent(orgCode));
    const result = await response.json();

    if (result.success === true && result.data) {
      return result.data;
    }

    return null;
  } catch (error) {
    console.log("Error fetching quiz " + orgCode + ": " + error.message);
    return null;
  }
}

// Fetches all quizzes owned by a specific teacher, sorted newest first
async function fetchTeacherQuizzes(teacherEmail) {
  try {
    let emailParam = teacherEmail;
    if (!emailParam) {
      emailParam = "all";
    }

    const response = await fetch("php/get_quizzes.php?email=" + encodeURIComponent(emailParam));
    const result = await response.json();

    if (result.success === true && Array.isArray(result.data)) {
      return result.data;
    }

    return [];
  } catch (error) {
    console.log("Error fetching teacher quizzes: " + error.message);
    return [];
  }
}

// Saves a newly created quiz to the database by calling php/create_quiz.php
async function saveQuizToDatabase(quizObject) {
  try {
    const response = await fetch("php/create_quiz.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(quizObject)
    });

    const result = await response.json();
    return result;
  } catch (error) {
    console.log("Error saving quiz: " + error.message);
    return {
      success: false,
      message: error.message
    };
  }
}

// Updates a quiz's live status ("waiting", "active", "ended")
async function updateQuizStatus(orgCode, newStatus) {
  try {
    const formData = new FormData();
    formData.append("orgCode", orgCode);
    formData.append("newStatus", newStatus);

    const response = await fetch("php/update_status.php", {
      method: "POST",
      body: formData
    });

    const result = await response.json();
    return result.success;
  } catch (error) {
    console.log("Error updating status: " + error.message);
    return false;
  }
}

// Updates a quiz's postQuizVisibility setting
async function updateQuizVisibility(orgCode, newVisibility) {
  try {
    const formData = new FormData();
    formData.append("orgCode", orgCode);
    formData.append("postQuizVisibility", newVisibility);

    const response = await fetch("php/update_visibility.php", {
      method: "POST",
      body: formData
    });

    const result = await response.json();
    return result.success;
  } catch (error) {
    console.log("Error updating visibility: " + error.message);
    return false;
  }
}


// =============================================================================
// 5. ANALYSIS OF RESULTS (SCORES, AVERAGES, SUMMARIES)
// =============================================================================

// Calculates performance statistics for an entire quiz session using plain loops
function calculateQuizOverviewStats(quiz) {
  let submissions = [];
  if (quiz.submissions && Array.isArray(quiz.submissions)) {
    submissions = quiz.submissions;
  }

  let questions = [];
  if (quiz.questions && Array.isArray(quiz.questions)) {
    questions = quiz.questions;
  }

  let totalAttempts = submissions.length;
  let submittedCount = 0;
  let inProgressCount = 0;
  let terminatedCount = 0;

  let totalScoreSum = 0;
  let highestScore = 0;
  let lowestScore = 999999;
  let scoredSubmissionsCount = 0;

  // Track per-question correct counts
  const questionCorrectCounts = {};
  for (let q = 0; q < questions.length; q = q + 1) {
    questionCorrectCounts[questions[q].id] = 0;
  }

  for (let s = 0; s < submissions.length; s = s + 1) {
    const sub = submissions[s];

    if (sub.status === "submitted") {
      submittedCount = submittedCount + 1;
    } else if (sub.status === "terminated_cheat") {
      terminatedCount = terminatedCount + 1;
    } else {
      inProgressCount = inProgressCount + 1;
    }

    if (sub.score !== null && sub.score !== undefined) {
      const currentScore = sub.score;
      totalScoreSum = totalScoreSum + currentScore;
      scoredSubmissionsCount = scoredSubmissionsCount + 1;

      if (currentScore > highestScore) {
        highestScore = currentScore;
      }
      if (currentScore < lowestScore) {
        lowestScore = currentScore;
      }
    }

    // Check student responses for per-question accuracy
    let responses = {};
    if (sub.responses) {
      responses = sub.responses;
    }

    let answerKey = {};
    if (quiz.answerKey) {
      answerKey = quiz.answerKey;
    }

    for (let q = 0; q < questions.length; q = q + 1) {
      const qId = questions[q].id;
      const studentChoice = responses[qId];
      const correctChoice = answerKey[qId];

      if (studentChoice !== undefined && correctChoice !== undefined) {
        if (Number(studentChoice) === Number(correctChoice)) {
          let prevCount = 0;
          if (questionCorrectCounts[qId] !== undefined) {
            prevCount = questionCorrectCounts[qId];
          }
          questionCorrectCounts[qId] = prevCount + 1;
        }
      }
    }
  }

  let averageScore = "0.0";
  if (scoredSubmissionsCount > 0) {
    averageScore = (totalScoreSum / scoredSubmissionsCount).toFixed(1);
  } else {
    lowestScore = 0;
  }

  if (lowestScore === 999999) {
    lowestScore = 0;
  }

  // Calculate per-question percentages
  const questionStatsList = [];
  for (let q = 0; q < questions.length; q = q + 1) {
    const qObj = questions[q];
    let correctCount = 0;
    if (questionCorrectCounts[qObj.id] !== undefined) {
      correctCount = questionCorrectCounts[qObj.id];
    }

    let percentage = 0;
    if (scoredSubmissionsCount > 0) {
      percentage = Math.round((correctCount / scoredSubmissionsCount) * 100);
    }

    questionStatsList.push({
      id: qObj.id,
      questionText: qObj.questionText,
      correctCount: correctCount,
      percentageCorrect: percentage
    });
  }

  return {
    totalAttempts: totalAttempts,
    submittedCount: submittedCount,
    inProgressCount: inProgressCount,
    terminatedCount: terminatedCount,
    scoredCount: scoredSubmissionsCount,
    averageScore: averageScore,
    highestScore: highestScore,
    lowestScore: lowestScore,
    questionStats: questionStatsList
  };
}

// Calculates detailed drill-down data for a single candidate attempt
function calculateStudentDrilldownData(quiz, studentId) {
  let submissions = [];
  if (quiz.submissions && Array.isArray(quiz.submissions)) {
    submissions = quiz.submissions;
  }

  let studentSubmission = null;

  for (let s = 0; s < submissions.length; s = s + 1) {
    if (submissions[s].studentId === studentId) {
      studentSubmission = submissions[s];
      break;
    }
  }

  if (studentSubmission === null) {
    return null;
  }

  let questions = [];
  if (quiz.questions && Array.isArray(quiz.questions)) {
    questions = quiz.questions;
  }

  let responses = {};
  if (studentSubmission.responses) {
    responses = studentSubmission.responses;
  }

  let timeSpent = {};
  if (studentSubmission.timeSpentPerQuestion) {
    timeSpent = studentSubmission.timeSpentPerQuestion;
  }

  let answerKey = {};
  if (quiz.answerKey) {
    answerKey = quiz.answerKey;
  }

  const drilldownQuestions = [];

  for (let i = 0; i < questions.length; i = i + 1) {
    const q = questions[i];
    const studentChoiceIndex = responses[q.id];
    const correctChoiceIndex = answerKey[q.id];

    let isCorrect = false;
    if (studentChoiceIndex !== undefined && correctChoiceIndex !== undefined) {
      if (Number(studentChoiceIndex) === Number(correctChoiceIndex)) {
        isCorrect = true;
      }
    }

    let studentChoiceText = "Not Answered";
    if (studentChoiceIndex !== undefined && q.options && q.options[studentChoiceIndex] !== undefined) {
      studentChoiceText = String.fromCharCode(65 + studentChoiceIndex) + ". " + q.options[studentChoiceIndex];
    }

    let correctChoiceText = "Not Available";
    if (correctChoiceIndex !== undefined && q.options && q.options[correctChoiceIndex] !== undefined) {
      correctChoiceText = String.fromCharCode(65 + correctChoiceIndex) + ". " + q.options[correctChoiceIndex];
    }

    let spentSeconds = 0;
    if (timeSpent[q.id] !== undefined) {
      spentSeconds = timeSpent[q.id];
    }

    drilldownQuestions.push({
      number: i + 1,
      id: q.id,
      questionText: q.questionText,
      studentChoiceIndex: studentChoiceIndex,
      studentChoiceText: studentChoiceText,
      correctChoiceIndex: correctChoiceIndex,
      correctChoiceText: correctChoiceText,
      isCorrect: isCorrect,
      timeSpentSeconds: spentSeconds
    });
  }

  return {
    studentId: studentId,
    submission: studentSubmission,
    questions: drilldownQuestions
  };
}
