// =============================================================================
// QuizPulse — app.js
// Active examination engine:
// - Renders questions and options
// - Manages examination countdown timer (Timer 2)
// - Arms anti-cheat monitoring system
// - Handles autosave drafts by calling php/save_draft.php
// - Submits final answers by calling php/save_result.php
// - Renders student results according to postQuizVisibility settings
// =============================================================================


// =============================================================================
// 1. QUIZ RUNTIME STATE VARIABLES
// =============================================================================

var currentActiveQuiz = null;
var currentActiveStudentId = null;
var currentActiveOrgCode = null;

var studentAnswersMap = {};
var studentQuestionTimeMap = {};
var activeQuestionIndex = 0;

var quizCountdownIntervalId = null;
var quizStartEpochMs = null;
var totalAttemptDurationSeconds = 0;

var studentCheatViolations = 0;
var isCurrentQuizSubmitted = false;


// =============================================================================
// 2. TIME FORMATTING HELPER
// =============================================================================

// Formats total seconds into MM:SS display string
function formatTimerDisplay(totalSeconds) {
  let secondsToFormat = totalSeconds;
  if (secondsToFormat < 0) {
    secondsToFormat = 0;
  }

  const roundedSeconds = Math.floor(secondsToFormat);
  const minutes = Math.floor(roundedSeconds / 60);
  const seconds = roundedSeconds % 60;

  let minutesString = String(minutes);
  if (minutes < 10) {
    minutesString = "0" + String(minutes);
  }

  let secondsString = String(seconds);
  if (seconds < 10) {
    secondsString = "0" + String(seconds);
  }

  return minutesString + ":" + secondsString;
}


// =============================================================================
// 3. QUESTION RENDERING AND OPTION SELECTION
// =============================================================================

// Renders the list of quiz questions into the DOM container
function renderQuizQuestions(questionsList, containerElement, counterElement) {
  if (containerElement === null) {
    return;
  }

  containerElement.innerHTML = "";

  for (let i = 0; i < questionsList.length; i = i + 1) {
    const question = questionsList[i];
    const questionNumber = i + 1;

    // Create the outer question card
    const cardElement = document.createElement("div");
    cardElement.className = "question-card";
    cardElement.setAttribute("data-question-id", question.id);

    // Question card header
    const headerElement = document.createElement("div");
    headerElement.className = "question-header";

    const badgeElement = document.createElement("span");
    badgeElement.className = "question-badge";
    let formattedNumber = String(questionNumber);
    if (questionNumber < 10) {
      formattedNumber = "0" + String(questionNumber);
    }
    badgeElement.textContent = "Q" + formattedNumber;
    headerElement.appendChild(badgeElement);

    cardElement.appendChild(headerElement);

    // Question prompt text
    const textElement = document.createElement("p");
    textElement.className = "question-text";
    textElement.textContent = question.questionText;
    cardElement.appendChild(textElement);

    // Options container
    const optionsContainer = document.createElement("div");
    optionsContainer.className = "options-list";

    for (let optIndex = 0; optIndex < question.options.length; optIndex = optIndex + 1) {
      const optionLabel = document.createElement("label");
      optionLabel.className = "option-label";

      const radioInput = document.createElement("input");
      radioInput.type = "radio";
      radioInput.name = "question_" + question.id;
      radioInput.value = String(optIndex);

      // Check if candidate already selected this option previously
      if (studentAnswersMap[question.id] === optIndex) {
        radioInput.checked = true;
        optionLabel.classList.add("option-selected");
      }

      // Handle selecting an option
      radioInput.addEventListener("change", function() {
        const allOptionLabels = optionsContainer.querySelectorAll(".option-label");
        for (let l = 0; l < allOptionLabels.length; l = l + 1) {
          allOptionLabels[l].classList.remove("option-selected");
        }

        optionLabel.classList.add("option-selected");
        studentAnswersMap[question.id] = Number(radioInput.value);
        activeQuestionIndex = i;

        updateAnsweredCounter(questionsList.length, counterElement);

        // Auto-save draft answer to PHP backend & sessionStorage
        saveStudentDraft(currentActiveOrgCode, currentActiveStudentId, studentAnswersMap, studentQuestionTimeMap);
      });

      const optionLetter = String.fromCharCode(65 + optIndex);
      const optionTextSpan = document.createElement("span");
      optionTextSpan.textContent = optionLetter + ". " + question.options[optIndex];

      optionLabel.appendChild(radioInput);
      optionLabel.appendChild(optionTextSpan);
      optionsContainer.appendChild(optionLabel);
    }

    cardElement.appendChild(optionsContainer);
    containerElement.appendChild(cardElement);
  }

  updateAnsweredCounter(questionsList.length, counterElement);
}

// Updates the "X of Y Answered" indicator badge in the header
function updateAnsweredCounter(totalQuestions, counterElement) {
  if (counterElement === null) {
    return;
  }

  let answeredCount = 0;
  for (const questionKey in studentAnswersMap) {
    if (studentAnswersMap[questionKey] !== undefined) {
      answeredCount = answeredCount + 1;
    }
  }

  counterElement.textContent = answeredCount + " of " + totalQuestions + " Answered";
}


// =============================================================================
// 4. EXAMINATION TIMER (TIMER 2: CANDIDATE ATTEMPT DURATION)
// =============================================================================

// Starts the countdown timer for the student's attempt
function startQuizAttemptTimer(durationSeconds, timerDisplayElement, onTimerExpiredCallback, onOrganizerEndedCallback) {
  if (quizCountdownIntervalId !== null) {
    clearInterval(quizCountdownIntervalId);
  }

  totalAttemptDurationSeconds = durationSeconds;
  quizStartEpochMs = Date.now();

  quizCountdownIntervalId = setInterval(async function() {
    if (isCurrentQuizSubmitted === true) {
      clearInterval(quizCountdownIntervalId);
      return;
    }

    const elapsedSeconds = (Date.now() - quizStartEpochMs) / 1000;
    const remainingSeconds = totalAttemptDurationSeconds - elapsedSeconds;

    if (timerDisplayElement !== null) {
      timerDisplayElement.textContent = formatTimerDisplay(remainingSeconds);

      if (remainingSeconds <= 60) {
        timerDisplayElement.classList.add("timer-urgent");
      } else {
        timerDisplayElement.classList.remove("timer-urgent");
      }
    }

    // Accumulate time spent on the active question
    if (currentActiveQuiz && currentActiveQuiz.questions && currentActiveQuiz.questions[activeQuestionIndex]) {
      const activeQId = currentActiveQuiz.questions[activeQuestionIndex].id;
      let prevTime = 0;
      if (studentQuestionTimeMap[activeQId] !== undefined) {
        prevTime = studentQuestionTimeMap[activeQId];
      }
      studentQuestionTimeMap[activeQId] = prevTime + 1;
    }

    // Check if the organizer ended the quiz in the dashboard (every 3 seconds)
    if (Math.floor(elapsedSeconds) % 3 === 0) {
      const liveQuizData = await fetchQuizByCode(currentActiveOrgCode);
      if (liveQuizData !== null && liveQuizData.status === "ended" && isCurrentQuizSubmitted === false) {
        clearInterval(quizCountdownIntervalId);
        if (onOrganizerEndedCallback !== undefined && typeof onOrganizerEndedCallback === "function") {
          onOrganizerEndedCallback();
        }
        return;
      }
    }

    // Attempt duration expired
    if (remainingSeconds <= 0) {
      clearInterval(quizCountdownIntervalId);
      if (onTimerExpiredCallback !== undefined && typeof onTimerExpiredCallback === "function") {
        onTimerExpiredCallback();
      }
    }
  }, 1000);
}


// =============================================================================
// 5. ANTI-CHEAT MONITORING SYSTEM
// =============================================================================

// Arms anti-cheat monitoring listeners (terminates attempt on single violation)
function armQuizAntiCheat(onViolationTerminateCallback) {
  function handleViolationEvent(violationType) {
    if (isCurrentQuizSubmitted === true) {
      return;
    }

    studentCheatViolations = studentCheatViolations + 1;

    // Save violation count in database via PHP
    recordStudentViolation(currentActiveOrgCode, currentActiveStudentId);

    showToast("Integrity violation detected! Assessment terminated immediately.", true);

    if (onViolationTerminateCallback !== undefined && typeof onViolationTerminateCallback === "function") {
      onViolationTerminateCallback(violationType);
    }
  }

  // 1. Tab switches (page visibility change)
  document.addEventListener("visibilitychange", function() {
    if (document.hidden === true) {
      handleViolationEvent("tab_switch");
    }
  });

  // 2. Window focus loss (blur)
  window.addEventListener("blur", function() {
    handleViolationEvent("window_blur");
  });

  // 3. Exit fullscreen mode
  document.addEventListener("fullscreenchange", function() {
    if (!document.fullscreenElement) {
      handleViolationEvent("fullscreen_exit");
    }
  });

  // 4. Right-click context menu prevention
  document.addEventListener("contextmenu", function(event) {
    event.preventDefault();
    handleViolationEvent("context_menu");
  });

  // 5. Copy prevention
  document.addEventListener("copy", function(event) {
    event.preventDefault();
    handleViolationEvent("copy_attempt");
  });

  // 6. Paste prevention
  document.addEventListener("paste", function(event) {
    event.preventDefault();
    handleViolationEvent("paste_attempt");
  });
}


// =============================================================================
// 6. CHECKING ANSWERS & CALCULATING SCORES
// =============================================================================

// Compares student responses with the quiz answer key and returns total points
function calculateStudentScore(questionsList, responsesMap, answerKeyMap) {
  let correctTotal = 0;

  for (let i = 0; i < questionsList.length; i = i + 1) {
    const questionId = questionsList[i].id;
    const studentChoice = responsesMap[questionId];
    const correctChoice = answerKeyMap[questionId];

    if (studentChoice !== undefined && correctChoice !== undefined) {
      if (Number(studentChoice) === Number(correctChoice)) {
        correctTotal = correctTotal + 1;
      }
    }
  }

  return correctTotal;
}


// =============================================================================
// 7. DATABASE OPERATIONS (TALKS TO PHP BACKEND)
// =============================================================================

// Saves current student answers as an in-progress draft via php/save_draft.php
async function saveStudentDraft(orgCode, studentId, responsesMap, timeSpentMap) {
  try {
    // Cache draft in sessionStorage for instant recovery on page refresh
    sessionStorage.setItem("draft_responses_" + orgCode, JSON.stringify(responsesMap));
    sessionStorage.setItem("draft_time_" + orgCode, JSON.stringify(timeSpentMap));

    const payload = {
      orgCode: orgCode,
      studentId: studentId,
      responses: responsesMap,
      timeSpentPerQuestion: timeSpentMap
    };

    const response = await fetch("php/save_draft.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    return result.success;
  } catch (error) {
    // Non-fatal draft autosave notice
    return false;
  }
}

// Records an anti-cheat violation in the database via php/record_violation.php
async function recordStudentViolation(orgCode, studentId) {
  try {
    const payload = {
      orgCode: orgCode,
      studentId: studentId
    };

    const response = await fetch("php/record_violation.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    if (result.success === true && result.data) {
      return result.data.tabViolations;
    }
    return 1;
  } catch (error) {
    return 1;
  }
}

// Finalizes the quiz, calculates score, and saves submission via php/save_result.php
async function saveFinalQuizSubmission(orgCode, studentId, responsesMap, timeSpentMap, isAutoSubmitted, terminationReason) {
  try {
    isCurrentQuizSubmitted = true;
    if (quizCountdownIntervalId !== null) {
      clearInterval(quizCountdownIntervalId);
    }

    const payload = {
      orgCode: orgCode,
      studentId: studentId,
      responses: responsesMap,
      timeSpentPerQuestion: timeSpentMap,
      autoSubmitted: isAutoSubmitted,
      terminationReason: terminationReason
    };

    const response = await fetch("php/save_result.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (result.success === true && result.data) {
      return {
        quiz: result.data.quiz,
        submission: result.data.submission
      };
    }

    showToast("Error recording final submission: " + result.message, true);
    return null;
  } catch (error) {
    showToast("Error saving final submission: " + error.message, true);
    return null;
  }
}

// Purges student session on exit to prevent re-taking the quiz via php/purge_session.php
async function purgeStudentSession(orgCode, studentId) {
  try {
    const payload = {
      orgCode: orgCode,
      studentId: studentId
    };

    const response = await fetch("php/purge_session.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify(payload)
    });

    await response.json();
    sessionStorage.clear();
    showToast("Session purged. Assessment completed.", false);
    return true;
  } catch (error) {
    sessionStorage.clear();
    return true;
  }
}


// =============================================================================
// 8. RENDERING RESULTS VIEW BASED ON POST-QUIZ VISIBILITY
// =============================================================================

// Renders the results view strictly according to postQuizVisibility
function renderStudentResultsView(quiz, submission, containerElement) {
  if (containerElement === null) {
    return;
  }

  containerElement.innerHTML = "";

  let visibility = "marks_only";
  if (quiz.postQuizVisibility) {
    visibility = quiz.postQuizVisibility;
  }

  const totalQuestions = quiz.questions.length;
  let studentScore = 0;
  if (submission.score !== null && submission.score !== undefined) {
    studentScore = submission.score;
  }

  let percentageScore = 0;
  if (totalQuestions > 0) {
    percentageScore = Math.round((studentScore / totalQuestions) * 100);
  }

  // Card 1: Score Banner (Always shown for marks_only, marks_and_answers, and full_analysis)
  const scoreCard = document.createElement("div");
  scoreCard.className = "result-card";

  const scoreHeader = document.createElement("h3");
  scoreHeader.className = "result-card-title";
  scoreHeader.textContent = "Your Official Score";
  scoreCard.appendChild(scoreHeader);

  const scoreValue = document.createElement("div");
  scoreValue.className = "result-score-large";
  scoreValue.textContent = studentScore + " / " + totalQuestions + " (" + percentageScore + "%)";
  scoreCard.appendChild(scoreValue);

  const statusNote = document.createElement("p");
  statusNote.className = "hint-text";
  statusNote.textContent = "Status: " + submission.status + " · Submitted at: " + formatDateSimple(submission.submittedAt);
  scoreCard.appendChild(statusNote);

  containerElement.appendChild(scoreCard);

  // If marks_only: STOP HERE! Never insert answers into DOM
  if (visibility === "marks_only") {
    const privacyNotice = document.createElement("div");
    privacyNotice.className = "callout-box";
    privacyNotice.textContent = "Notice: The organizer has configured this quiz to display marks only. Answer review is not permitted.";
    containerElement.appendChild(privacyNotice);
    return;
  }

  // Card 2: Question & Answer Breakdown (For marks_and_answers and full_analysis)
  const breakdownCard = document.createElement("div");
  breakdownCard.className = "result-card";

  const breakdownHeader = document.createElement("h3");
  breakdownHeader.className = "result-card-title";
  breakdownHeader.textContent = "Question & Answer Breakdown";
  breakdownCard.appendChild(breakdownHeader);

  let answerKey = {};
  if (quiz.answerKey) {
    answerKey = quiz.answerKey;
  }

  let responses = {};
  if (submission.responses) {
    responses = submission.responses;
  }

  for (let i = 0; i < quiz.questions.length; i = i + 1) {
    const q = quiz.questions[i];
    const studentChoice = responses[q.id];
    const correctChoice = answerKey[q.id];

    let isCorrect = false;
    if (studentChoice !== undefined && correctChoice !== undefined) {
      if (Number(studentChoice) === Number(correctChoice)) {
        isCorrect = true;
      }
    }

    const qRow = document.createElement("div");
    qRow.className = "result-question-row";

    const qNum = document.createElement("div");
    qNum.className = "result-q-badge";
    qNum.textContent = "Q" + (i + 1);
    qRow.appendChild(qNum);

    const qDetails = document.createElement("div");
    qDetails.className = "result-q-details";

    const qText = document.createElement("p");
    qText.className = "result-q-text";
    qText.textContent = q.questionText;
    qDetails.appendChild(qText);

    let studentChoiceText = "Not answered";
    if (studentChoice !== undefined && q.options && q.options[studentChoice] !== undefined) {
      studentChoiceText = String.fromCharCode(65 + studentChoice) + ". " + q.options[studentChoice];
    }

    let correctChoiceText = "Not available";
    if (correctChoice !== undefined && q.options && q.options[correctChoice] !== undefined) {
      correctChoiceText = String.fromCharCode(65 + correctChoice) + ". " + q.options[correctChoice];
    }

    const answerComparison = document.createElement("p");
    answerComparison.className = "result-q-answers";

    if (isCorrect === true) {
      answerComparison.innerHTML = "<span class='text-ok'>✓ Correct: " + studentChoiceText + "</span>";
    } else {
      answerComparison.innerHTML = "<span class='text-danger'>✗ Marked: " + studentChoiceText + "</span> · <span class='text-ok'>Correct: " + correctChoiceText + "</span>";
    }

    qDetails.appendChild(answerComparison);
    qRow.appendChild(qDetails);
    breakdownCard.appendChild(qRow);
  }

  containerElement.appendChild(breakdownCard);

  // If full_analysis: Add performance summary
  if (visibility === "full_analysis") {
    const analysisCard = document.createElement("div");
    analysisCard.className = "result-card";

    const analysisHeader = document.createElement("h3");
    analysisHeader.className = "result-card-title";
    analysisHeader.textContent = "Performance Summary";
    analysisCard.appendChild(analysisHeader);

    const totalRow = document.createElement("div");
    totalRow.className = "stat-row";
    totalRow.innerHTML = "<span>Total Questions</span><strong>" + totalQuestions + "</strong>";
    analysisCard.appendChild(totalRow);

    const correctRow = document.createElement("div");
    correctRow.className = "stat-row";
    correctRow.innerHTML = "<span>Correct Answers</span><strong class='text-ok'>" + studentScore + "</strong>";
    analysisCard.appendChild(correctRow);

    const wrongRow = document.createElement("div");
    wrongRow.className = "stat-row";
    wrongRow.innerHTML = "<span>Incorrect / Unanswered</span><strong class='text-danger'>" + (totalQuestions - studentScore) + "</strong>";
    analysisCard.appendChild(wrongRow);

    const accuracyRow = document.createElement("div");
    accuracyRow.className = "stat-row";
    accuracyRow.innerHTML = "<span>Overall Accuracy</span><strong>" + percentageScore + "%</strong>";
    analysisCard.appendChild(accuracyRow);

    containerElement.appendChild(analysisCard);
  }
}
