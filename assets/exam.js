(function () {
  'use strict';

  /* ── Constants ─────────────────────────────────────────────────────────── */

  var DEFAULT_CONFIG = {
    totalQuestions:   100,
    timeLimitSeconds: 9000,  // 2 h 30 min
    passingScore:     70,
  };

  /* ── Bootstrap ─────────────────────────────────────────────────────────── */

  function initExamApp(root) {
    var data      = window.vcExamData    || {};
    var config    = Object.assign({}, DEFAULT_CONFIG, data.examConfig || {});
    var labels    = data.labels          || {};
    var ajaxUrl   = data.ajaxUrl         || '';
    var nonce     = data.nonce           || '';

    var storageKey = 'vcExamState:' + window.location.pathname;

    /* ── DOM references ─────────────────────────────────────────────────── */

    var homeView    = root.querySelector('[data-vc-exam-view="home"]');
    var sessionView = root.querySelector('[data-vc-exam-view="session"]');
    var summaryModal = root.querySelector('[data-vc-exam-modal]');
    var feedbackEl  = root.querySelector('[data-vc-exam-feedback]');

    // Session
    var progressEl    = root.querySelector('[data-vc-exam-progress]');
    var timerEl       = root.querySelector('[data-vc-exam-timer]');
    var barFill       = root.querySelector('[data-vc-exam-bar-fill]');
    var questionEl    = root.querySelector('[data-vc-exam-question]');
    var answersWrap   = root.querySelector('[data-vc-exam-answers]');
    var nextButton    = root.querySelector('[data-vc-exam-next]');
    var nextLabel     = root.querySelector('[data-vc-exam-next-label]');
    var topicLabelEl  = root.querySelector('[data-vc-exam-topic-label]');
    var subtopicLabelEl = root.querySelector('[data-vc-exam-subtopic-label]');

    // Summary modal
    var resultBadge   = root.querySelector('[data-vc-exam-result-badge]');
    var resultKicker  = root.querySelector('[data-vc-exam-result-kicker]');
    var resultMessage = root.querySelector('[data-vc-exam-result-message]');
    var correctCount  = root.querySelector('[data-vc-exam-correct-count]');
    var incorrectCount = root.querySelector('[data-vc-exam-incorrect-count]');
    var scorePercentEl = root.querySelector('[data-vc-exam-score-percent]');
    var scoreBar      = root.querySelector('[data-vc-exam-score-bar]');
    var timeUsedEl    = root.querySelector('[data-vc-exam-time-used]');
    var summaryBox    = root.querySelector('.vc-exam-modal-dialog');

    /* ── Mutable state ──────────────────────────────────────────────────── */

    var sessionId          = 0;
    var cards              = [];
    var cardIndex          = 0;
    var attempts           = [];
    var examStartTime      = 0;   // Unix ms when the exam started
    var timerInterval      = null;
    var answeredCurrentCard = false;
    var answerStartedAt    = 0;
    var isSubmitting       = false;
    var lastTopicTermId    = 0;

    /* ── Utilities ──────────────────────────────────────────────────────── */

    function formatTime(totalSeconds) {
      var s   = Math.max(0, Math.floor(totalSeconds));
      var h   = Math.floor(s / 3600);
      var m   = Math.floor((s % 3600) / 60);
      var sec = s % 60;
      return h + ':' + pad(m) + ':' + pad(sec);
    }

    function pad(n) {
      return n < 10 ? '0' + n : String(n);
    }

    function getRemainingSeconds() {
      if (!examStartTime) { return config.timeLimitSeconds; }
      var elapsed = Math.floor((Date.now() - examStartTime) / 1000);
      return Math.max(0, config.timeLimitSeconds - elapsed);
    }

    function getElapsedSeconds() {
      if (!examStartTime) { return 0; }
      return Math.floor((Date.now() - examStartTime) / 1000);
    }

    /* ── State persistence ──────────────────────────────────────────────── */

    function persistState() {
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          sessionId:           sessionId,
          cards:               cards,
          cardIndex:           cardIndex,
          attempts:            attempts,
          examStartTime:       examStartTime,
          answeredCurrentCard: answeredCurrentCard,
          lastTopicTermId:     lastTopicTermId,
        }));
      } catch (e) {}
    }

    function clearState() {
      try { sessionStorage.removeItem(storageKey); } catch (e) {}
    }

    function restoreState() {
      try {
        var raw = sessionStorage.getItem(storageKey);
        if (!raw) { return false; }

        var state = JSON.parse(raw);
        if (!state || !state.sessionId || !state.cards || !state.cards.length) {
          return false;
        }

        // Abort restore if time has already expired.
        var remaining = config.timeLimitSeconds - Math.floor((Date.now() - state.examStartTime) / 1000);
        if (remaining <= 0) {
          clearState();
          return false;
        }

        sessionId           = state.sessionId;
        cards               = state.cards;
        cardIndex           = Math.max(0, Math.min(state.cardIndex || 0, cards.length - 1));
        attempts            = state.attempts || [];
        examStartTime       = state.examStartTime;
        answeredCurrentCard = !!state.answeredCurrentCard;
        lastTopicTermId     = state.lastTopicTermId || 0;

        renderCard();
        startTimer();
        showView('session');
        return true;
      } catch (e) {}
      return false;
    }

    /* ── View management ────────────────────────────────────────────────── */

    function showView(name) {
      homeView.hidden    = (name !== 'home' && name !== 'session');
      sessionView.hidden = (name !== 'session');
    }

    function openSummaryModal() {
      if (summaryModal) {
        summaryModal.hidden = false;
        document.body.classList.add('vc-exam-modal-open');
      }
    }

    function closeSummaryModal() {
      if (summaryModal) {
        summaryModal.hidden = true;
        document.body.classList.remove('vc-exam-modal-open');
      }
    }

    function setFeedback(message, type) {
      if (!feedbackEl) { return; }
      if (!message) {
        feedbackEl.hidden = true;
        feedbackEl.textContent = '';
        feedbackEl.dataset.state = '';
        return;
      }
      feedbackEl.hidden = false;
      feedbackEl.textContent = message;
      feedbackEl.dataset.state = type || 'info';
    }

    /* ── Timer ──────────────────────────────────────────────────────────── */

    function startTimer() {
      stopTimer();
      timerInterval = setInterval(tickTimer, 1000);
      tickTimer();
    }

    function stopTimer() {
      if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
      }
    }

    function tickTimer() {
      var remaining = getRemainingSeconds();

      if (timerEl) {
        timerEl.textContent = formatTime(remaining);
        timerEl.classList.toggle('vc-exam-timer--warning', remaining > 0 && remaining <= 300);
        timerEl.classList.toggle('vc-exam-timer--expired', remaining === 0);
      }

      if (remaining === 0 && !isSubmitting) {
        stopTimer();
        finishExam(true /* expired */);
      }
    }

    /* ── Question rendering ─────────────────────────────────────────────── */

    function renderCard() {
      var card = cards[cardIndex];

      if (!card) {
        finishExam(false);
        return;
      }

      answeredCurrentCard = false;
      answerStartedAt     = Date.now();

      // Reset answer area
      answersWrap.innerHTML = '';
      if (nextButton) {
        nextButton.hidden   = true;
        nextButton.disabled = true;
      }
      setFeedback('', '');

      var total = cards.length;

      // Progress text
      if (progressEl) {
        progressEl.textContent =
          (labels.question || 'Question') + ' ' +
          (cardIndex + 1) + ' ' +
          (labels.of || 'of') + ' ' +
          total;
      }

      // Progress bar
      if (barFill) {
        barFill.style.width = Math.round(((cardIndex + 1) / total) * 100) + '%';
      }

      // Next / Finish label
      if (nextLabel) {
        nextLabel.textContent =
          cardIndex === total - 1
            ? (labels.finish  || 'Finish exam')
            : (labels.next    || 'Next question');
      }

      // Topic breadcrumb
      if (topicLabelEl)    { topicLabelEl.textContent    = card.topicLabel    || ''; }
      if (subtopicLabelEl) { subtopicLabelEl.textContent = card.subtopicLabel || ''; }

      // Question text
      if (questionEl) { questionEl.textContent = card.question; }

      // Answer buttons
      Object.keys(card.answers).forEach(function (key) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'vc-flashcards-answer';
        btn.dataset.answerKey = key;
        btn.innerHTML =
          '<span>' + key.toUpperCase() + '</span>' +
          '<strong>' + escapeHtml(card.answers[key]) + '</strong>';
        btn.addEventListener('click', function () { handleAnswer(btn); });
        answersWrap.appendChild(btn);
      });

      persistState();
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    /* ── Answer handling ────────────────────────────────────────────────── */

    function handleAnswer(button) {
      if (answeredCurrentCard) { return; }

      var card         = cards[cardIndex];
      var selected     = button.dataset.answerKey;
      var responseMs   = Date.now() - answerStartedAt;

      answeredCurrentCard = true;

      answersWrap.querySelectorAll('.vc-flashcards-answer').forEach(function (b) {
        b.disabled = true;
        if (b.dataset.answerKey === card.correctAnswer) {
          b.dataset.state = 'correct';
        } else if (b.dataset.answerKey === selected) {
          b.dataset.state = 'incorrect';
        }
      });

      attempts.push({
        flashcardId:    card.id,
        topicTermId:    card.topicTermId,
        selectedAnswer: selected,
        correctAnswer:  card.correctAnswer,
        responseTimeMs: responseMs,
      });

      if (nextButton) {
        nextButton.hidden   = false;
        nextButton.disabled = false;
      }

      persistState();
    }

    /* ── Exam completion ────────────────────────────────────────────────── */

    function finishExam(expired) {
      if (isSubmitting) { return; }
      isSubmitting = true;
      stopTimer();

      var elapsed = getElapsedSeconds();

      // Fill blank attempts for unanswered cards (time-expired scenario).
      if (expired) {
        for (var i = attempts.length; i < cards.length; i++) {
          var c = cards[i];
          attempts.push({
            flashcardId:    c.id,
            topicTermId:    c.topicTermId,
            selectedAnswer: '',
            correctAnswer:  c.correctAnswer,
            responseTimeMs: 0,
          });
        }
      }

      var body = new URLSearchParams();
      body.append('action',     'vc_flashcards_complete_session');
      body.append('nonce',      nonce);
      body.append('session_id', String(sessionId));
      body.append('attempts',   JSON.stringify(attempts));

      fetch(ajaxUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body:    body.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (!payload.success) { throw new Error('server'); }
          var d = payload.data;
          clearState();
          renderSummary({
            correct:  Number(d.correctAnswers  || 0),
            incorrect: Number(d.incorrectAnswers || 0),
            score:    Number(d.scorePercent    || 0),
            elapsed:  elapsed,
            expired:  expired,
          });
        })
        .catch(function () {
          // Fallback: compute locally.
          var correct = 0;
          attempts.forEach(function (a) {
            if (a.selectedAnswer && a.selectedAnswer === a.correctAnswer) { correct++; }
          });
          var total = attempts.length;
          var score = total > 0 ? Math.round((correct / total) * 100) : 0;
          clearState();
          renderSummary({
            correct:  correct,
            incorrect: total - correct,
            score:    score,
            elapsed:  elapsed,
            expired:  expired,
          });
        })
        .finally(function () {
          isSubmitting = false;
        });
    }

    /* ── Summary rendering ──────────────────────────────────────────────── */

    function renderSummary(results) {
      var passed = results.score >= config.passingScore;

      // Badge
      if (resultBadge) {
        resultBadge.className =
          'vc-exam-result-badge ' +
          (passed ? 'vc-exam-result-badge--passed' : 'vc-exam-result-badge--failed');
        var iconEl = resultBadge.querySelector('[data-vc-exam-result-icon]');
        if (iconEl) { iconEl.textContent = passed ? '\u2713' : '\u2717'; }
      }

      // Summary box class for score bar color
      if (summaryBox) {
        summaryBox.classList.toggle('is-passed', passed);
        summaryBox.classList.toggle('is-failed', !passed);
      }

      // Kicker / headline
      if (resultKicker) {
        if (results.expired) {
          resultKicker.textContent = labels.timeExpired || 'Time expired';
        } else if (passed) {
          resultKicker.textContent = labels.passed || 'Passed!';
        } else {
          resultKicker.textContent = labels.examComplete || 'Exam complete!';
        }
      }

      // Message
      if (resultMessage) {
        resultMessage.textContent = passed
          ? (labels.congratulations || 'Congratulations! You passed the exam.')
          : (labels.keepStudying    || 'Keep studying. You need 70% to pass.');
      }

      // Counts
      if (correctCount)   { correctCount.textContent   = String(results.correct); }
      if (incorrectCount) { incorrectCount.textContent = String(results.incorrect); }

      // Score percentage
      if (scorePercentEl) { scorePercentEl.textContent = Math.round(results.score) + '%'; }

      // Score bar
      if (scoreBar) {
        scoreBar.style.width = Math.max(0, Math.min(100, results.score)) + '%';
      }

      // Time used
      if (timeUsedEl) { timeUsedEl.textContent = formatTime(results.elapsed); }

      openSummaryModal();
    }

    /* ── Start exam ─────────────────────────────────────────────────────── */

    function startExam(topicTermId) {
      lastTopicTermId = topicTermId;
      setFeedback(labels.loading || 'Preparing your exam…', 'info');

      var body = new URLSearchParams();
      body.append('action',        'vc_flashcards_start_exam');
      body.append('nonce',         nonce);
      body.append('topic_term_id', String(topicTermId));

      fetch(ajaxUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body:    body.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (!payload.success) {
            var msg = payload.data && payload.data.message
              ? payload.data.message
              : (labels.noCards || 'No questions found.');
            throw new Error(msg);
          }

          setFeedback('', '');
          sessionId           = payload.data.sessionId;
          cards               = payload.data.cards || [];
          cardIndex           = 0;
          attempts            = [];
          answeredCurrentCard = false;
          isSubmitting        = false;
          examStartTime       = Date.now();

          renderCard();
          startTimer();
          showView('session');
        })
        .catch(function (err) {
          setFeedback(err.message || (labels.noCards || 'No questions found.'), 'error');
        });
    }

    /* ── Event listeners ────────────────────────────────────────────────── */

    // Start exam buttons (one per category card)
    root.querySelectorAll('[data-vc-exam-start]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        startExam(Number(btn.dataset.vcExamStart));
      });
    });

    // Next / Finish
    if (nextButton) {
      nextButton.addEventListener('click', function () {
        cardIndex += 1;
        renderCard();
      });
    }

    // Abandon exam (with confirmation)
    root.querySelectorAll('[data-vc-exam-abandon]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var msg = labels.confirmAbandon || 'Are you sure you want to abandon the exam? Your progress will be lost.';
        if (!window.confirm(msg)) { return; }
        stopTimer();
        clearState();
        // Reset state
        sessionId = 0; cards = []; cardIndex = 0; attempts = []; examStartTime = 0;
        isSubmitting = false;
        showView('home');
        setFeedback('', '');
      });
    });

    // Summary modal: close on backdrop click
    var modalBackdrop = root.querySelector('[data-vc-exam-modal-backdrop]');
    if (modalBackdrop) {
      modalBackdrop.addEventListener('click', function () {
        closeSummaryModal();
        showView('home');
        setFeedback('', '');
      });
    }

    // Summary: back to menu (also acts as modal close button)
    root.querySelectorAll('[data-vc-exam-summary-back]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeSummaryModal();
        showView('home');
        setFeedback('', '');
      });
    });

    // Summary: try again (same category)
    root.querySelectorAll('[data-vc-exam-retry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        closeSummaryModal();
        if (lastTopicTermId) {
          showView('home');
          setFeedback('', '');
          startExam(lastTopicTermId);
        } else {
          showView('home');
        }
      });
    });

    // Keyboard shortcut: press A/B/C to select an answer
    document.addEventListener('keydown', function (event) {
      if (sessionView.hidden) { return; }
      var key = event.key.toLowerCase();
      var answerBtn = answersWrap.querySelector('.vc-flashcards-answer[data-answer-key="' + key + '"]');
      if (answerBtn && !answerBtn.disabled) {
        event.preventDefault();
        answerBtn.click();
      }
    });

    /* ── Init ───────────────────────────────────────────────────────────── */

    if (!restoreState()) {
      showView('home');
    }
  }

  /* ── Boot all exam apps on the page ──────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vc-exam-app').forEach(initExamApp);
  });

}());
