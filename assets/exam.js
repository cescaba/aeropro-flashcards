(function () {
  'use strict';

  /* ── Constants ─────────────────────────────────────────────────────────── */

  var DEFAULT_CONFIG = {
    totalQuestions:   100,
    timeLimitSeconds: 900,
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
    var summaryView = root.querySelector('[data-vc-exam-summary]');
    var feedbackEl  = root.querySelector('[data-vc-exam-feedback]');
    var finishButton = root.querySelector('.vc-exam-finish-btn');
    var dashboardContent = root.closest ? root.closest('.vc-dashboard-content--mock-test') : null;

    // Session
    var progressEl    = root.querySelector('[data-vc-exam-progress]');
    var timerEl       = root.querySelector('[data-vc-exam-timer]');
    var timerValueEl  = root.querySelector('[data-vc-exam-timer-value]');
    var barFill       = root.querySelector('[data-vc-exam-bar-fill]');
    var questionEl    = root.querySelector('[data-vc-exam-question]');
    var referenceImageButton = root.querySelector('[data-vc-exam-reference-image]');
    var referenceImageInline = root.querySelector('[data-vc-exam-reference-image-inline]');
    var referenceImageInlineWrap = referenceImageInline ? referenceImageInline.closest('.vc-exam-reference-image-preview') : null;
    var answersWrap   = root.querySelector('[data-vc-exam-answers]');
    var prevButton    = root.querySelector('[data-vc-exam-prev]');
    var nextButton    = root.querySelector('[data-vc-exam-next]');
    var nextLabel     = root.querySelector('[data-vc-exam-next-label]');
    var topicLabelEl  = root.querySelector('[data-vc-exam-topic-label]');
    var subtopicLabelEl = root.querySelector('[data-vc-exam-subtopic-label]');

    // Summary view
    var resultBadge   = root.querySelector('[data-vc-exam-result-badge]');
    var resultKicker  = root.querySelector('[data-vc-exam-result-kicker]');
    var correctCount  = root.querySelector('[data-vc-exam-correct-count]');
    var incorrectCount = root.querySelector('[data-vc-exam-incorrect-count]');
    var historyContent = root.querySelector('.vc-exam-history-content');
    var bestScoreEl = root.querySelector('[data-vc-exam-best-score]');
    var averageScoreEl = root.querySelector('[data-vc-exam-average-score]');
    var passedAttemptsEl = root.querySelector('[data-vc-exam-passed-attempts]');
    var startButtons = root.querySelectorAll('[data-vc-exam-start]');

    /* ── Mutable state ──────────────────────────────────────────────────── */

    var sessionId          = 0;
    var cards              = [];
    var cardIndex          = 0;
    var attempts           = [];
    var examStartTime      = 0;   // Unix ms when the exam started
    var timerInterval      = null;
    var answerStartedAt    = 0;
    var autoAdvanceTimer   = null;
    var isSubmitting       = false;
    var isStartingExam     = false;
    var lastTopicTermId    = 0;
    var activeSummaryResults = null;
    var activeViewName     = homeView && !homeView.hidden ? 'home' : 'session';

    // Publica el estado visual del mock test sin obligar al dashboard a inspeccionar
    // nodos internos con :has(). El dashboard solo consume estas clases publicas.
    function syncViewStateClasses() {
      var isHome = activeViewName === 'home';
      var isSession = activeViewName === 'session';
      var isSummaryOpen = !!(summaryView && !summaryView.hidden);

      root.classList.toggle('is-home', isHome);
      root.classList.toggle('is-session', isSession);
      root.classList.toggle('is-summary-open', isSummaryOpen);

      if (dashboardContent) {
        dashboardContent.classList.toggle('is-exam-home', isHome);
        dashboardContent.classList.toggle('is-exam-session', isSession);
        dashboardContent.classList.toggle('is-exam-summary-open', isSummaryOpen);
      }
    }

    // Resetea por completo el estado del examen en memoria.
    // Se usa cuando cerramos una sesion, limpiamos progreso o partimos de cero.
    function resetExamState() {
      sessionId = 0;
      cards = [];
      cardIndex = 0;
      attempts = [];
      examStartTime = 0;
      answerStartedAt = 0;
      clearAutoAdvanceTimer();
      isSubmitting = false;
      activeSummaryResults = null;
    }

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

    function clearAutoAdvanceTimer() {
      if (!autoAdvanceTimer) { return; }
      clearTimeout(autoAdvanceTimer);
      autoAdvanceTimer = null;
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

    // Convierte el estado local en un payload minimo. El servidor decide si cada
    // respuesta es correcta usando el snapshot confiable de la sesion.
    function buildAttemptPayload() {
      return cards.map(function (card, index) {
        var attempt = attempts[index] || {};

        return {
          flashcardId: card.id,
          topicTermId: card.topicTermId,
          selectedAnswer: attempt.selectedAnswer || '',
          responseTimeMs: Number(attempt.responseTimeMs || 0),
        };
      });
    }

    // Normaliza el resultado que ya fue validado y guardado por el servidor.
    function buildServerSummaryResults(serverData, elapsed, expired) {
      var data = serverData || {};
      return {
        correct: Number(data.correctAnswers || 0),
        incorrect: Number(data.incorrectAnswers || 0),
        score: Math.round(Number(data.scorePercent || 0)),
        elapsed: elapsed,
        expired: expired,
      };
    }

    // Vuelve a pedir al servidor el historial de examenes ya completados y reemplaza el HTML del bloque.
    // Se usa despues de terminar el examen para refrescar la lista sin recargar toda la pagina.
    function refreshExamHistory() {
      if (!historyContent || !ajaxUrl || !nonce) { return Promise.resolve(); }

      var body = new URLSearchParams();
      body.append('action', 'vc_flashcards_get_exam_history');
      body.append('nonce', nonce);

      return fetch(ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: body.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (!payload.success || !payload.data || typeof payload.data.html !== 'string') {
            throw new Error('history');
          }

          historyContent.innerHTML = payload.data.html;
        })
        .catch(function () {
          return null;
        });
    }

    // Refresca los badges del home del Mock Test con valores recalculados por el servidor.
    function updateExamHomeBadges(examHomeStats) {
      if (!examHomeStats) { return; }

      if (bestScoreEl && typeof examHomeStats.bestScore !== 'undefined') {
        var bestScore = Math.max(0, Math.round(Number(examHomeStats.bestScore || 0)));
        bestScoreEl.textContent = bestScore + '%';
      }

      if (averageScoreEl && typeof examHomeStats.averageScore !== 'undefined') {
        var averageScore = Math.max(0, Math.round(Number(examHomeStats.averageScore || 0)));
        averageScoreEl.textContent = averageScore + '%';
      }

      if (passedAttemptsEl && typeof examHomeStats.passedAttempts !== 'undefined') {
        passedAttemptsEl.textContent = String(examHomeStats.passedAttempts || '0/5');
      }
    }

    // Mock Test scroll: usa el scroller real del dashboard para entrar siempre al inicio del panel.
    function scrollExamToTop(behavior) {
      var scrollBehavior = behavior || 'smooth';
      var dashboardContent = root.closest('.vc-dashboard-content');

      if (dashboardContent && typeof dashboardContent.scrollTo === 'function') {
        dashboardContent.scrollTo({ top: 0, behavior: scrollBehavior });
        return;
      }

      if (typeof window.scrollTo === 'function') {
        window.scrollTo({
          top: Math.max(0, root.getBoundingClientRect().top + window.pageYOffset),
          behavior: scrollBehavior,
        });
      }
    }

    /* ── State persistence ──────────────────────────────────────────────── */

    function persistState() {
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          view:                activeViewName,
          summaryOpen:         summaryView ? !summaryView.hidden : false,
          summaryResults:      activeSummaryResults,
          sessionId:           sessionId,
          cards:               cards,
          cardIndex:           cardIndex,
          attempts:            attempts,
          examStartTime:       examStartTime,
          lastTopicTermId:     lastTopicTermId,
        }));
      } catch (e) {}
    }

    // Guarda el resumen final junto con una instantanea de la sesion.
    // Esto permite restaurar el modal sobre la ultima pregunta si la pagina se recarga.
    function persistSummaryState(results) {
      activeSummaryResults = results;
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          view:                activeViewName,
          summaryOpen:         true,
          sessionId:           sessionId,
          cards:               cards,
          cardIndex:           cardIndex,
          attempts:            attempts,
          examStartTime:       examStartTime,
          lastTopicTermId:     lastTopicTermId,
          summaryResults:      results,
        }));
      } catch (e) {}
    }

    function clearState() {
      try { sessionStorage.removeItem(storageKey); } catch (e) {}
    }

    // Vigila la navegacion lateral del dashboard para detectar cuando el usuario
    // abandona el mock test y entra a otra vista como Flashcards, Profile o Logout.
    // En ese momento limpiamos el estado persistido del examen para que el summary
    // no quede "pegado" al volver mas tarde.
    function bindDashboardExitCleanup() {
      var navLinks = document.querySelectorAll('.vc-dashboard-nav-link, .vc-dashboard-logout');

      navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
          // Leemos el destino real del enlace para comparar la vista actual del dashboard
          // contra la vista a la que el usuario va a navegar.
          var href = link.getAttribute('href');
          if (!href) { return; }

          var targetUrl;
          try {
            targetUrl = new URL(href, window.location.href);
          } catch (e) {
            return;
          }

          // "view" identifica el panel activo del dashboard. Si cambia de mock-test
          // a cualquier otro panel, debemos descartar el estado del examen anterior.
          var currentView = new URL(window.location.href).searchParams.get('view') || 'flashcards';
          var targetView = targetUrl.searchParams.get('view') || 'flashcards';
          var samePath = targetUrl.pathname === window.location.pathname;
          var leavingMockTestView = !samePath || targetView !== currentView;

          if (leavingMockTestView) {
            // Borra sessionStorage del examen y tambien la memoria viva del script.
            // Asi evitamos restaurar por accidente un summary viejo al volver al mock test.
            clearState();
            resetExamState();
          }
        });
      });
    }

    function restoreState() {
      try {
        var raw = sessionStorage.getItem(storageKey);
        if (!raw) { return false; }

        var state = JSON.parse(raw);
        if (!state) {
          return false;
        }

        // Si habia un resultado abierto, restauramos la sesion como fondo
        // y volvemos a abrir el modal encima, igual que Flashcards.
        if ((state.view === 'summary' || state.summaryOpen) && state.summaryResults) {
          sessionId           = state.sessionId || 0;
          cards               = state.cards || [];
          cardIndex           = Math.max(0, Math.min(state.cardIndex || 0, Math.max(cards.length - 1, 0)));
          attempts            = state.attempts || [];
          examStartTime       = state.examStartTime || 0;
          lastTopicTermId     = state.lastTopicTermId || 0;
          activeSummaryResults = state.summaryResults;

          if (cards.length) {
            activeViewName = 'session';
            renderCard();
            showView('session');
          } else {
            showView('home');
          }

          renderSummary(state.summaryResults);
          return true;
        }

        if (!state.sessionId || !state.cards || !state.cards.length) {
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
        lastTopicTermId     = state.lastTopicTermId || 0;

        // Restore active exam: versiones anteriores podian guardar view=home con una sesion valida.
        activeViewName = 'session';
        renderCard();
        startTimer();
        showView('session');
        return true;
      } catch (e) {}
      return false;
    }

    /* ── View management ────────────────────────────────────────────────── */

    function showView(name, options) {
      var opts = options || {};
      activeViewName = name;
      homeView.hidden    = (name !== 'home');
      sessionView.hidden = (name !== 'session');
      setSummaryOpen(false, false);
      syncViewStateClasses();
      scrollExamToTop(opts.behavior);

      if (opts.persist !== false) {
        persistState();
      }
    }

    // Controla el summary como modal independiente de las vistas home/session.
    // El parametro shouldPersist evita escrituras intermedias al cambiar vistas base.
    function setSummaryOpen(isOpen, shouldPersist) {
      if (!summaryView) { return; }
      summaryView.hidden = !isOpen;
      syncViewStateClasses();
      if (shouldPersist !== false) {
        persistState();
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
        // Si existe un nodo interno dedicado al valor del timer, escribimos ahi;
        // si no, usamos el contenedor completo como fallback.
        if (timerValueEl) {
          timerValueEl.textContent = formatTime(remaining);
        } else {
          timerEl.textContent = formatTime(remaining);
        }
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
      clearAutoAdvanceTimer();

      var card = cards[cardIndex];
      // Recupera la respuesta ya guardada de la pregunta actual, si existe.
      var currentAttempt = attempts[cardIndex] || null;

      if (!card) {
        finishExam(false);
        return;
      }

      answerStartedAt     = Date.now();

      // Reset answer area
      answersWrap.innerHTML = '';
      resetReferenceImage();
      if (nextButton) {
        nextButton.hidden   = false;
        nextButton.disabled = false;
      }
      if (prevButton) {
        prevButton.hidden = false;
        prevButton.disabled = (cardIndex === 0);
      }
      setFeedback('', '');

      var total = cards.length;
      var answeredCount = attempts.reduce(function (count, attempt) {
        return attempt && attempt.selectedAnswer ? count + 1 : count;
      }, 0);

      // Progress text
      if (progressEl) {
        progressEl.textContent =
          answeredCount + ' ' +
          (labels.answered || 'answered') + ' ' +
          (labels.outOf || 'out of') + ' ' +
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
      if (subtopicLabelEl) {
        subtopicLabelEl.textContent = (labels.question || 'Question') + ' ' + (cardIndex + 1);
      }

      // Question text
      if (questionEl) { questionEl.textContent = card.question; }
      syncReferenceImage(card);

      // Answer buttons
      Object.keys(card.answers).forEach(function (key) {
        var btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'vc-exam-answer';
        btn.dataset.answerKey = key;
        btn.innerHTML =
          '<span>' + key.toUpperCase() + '</span>' +
          '<strong>' + escapeHtml(card.answers[key]) + '</strong>';
        btn.addEventListener('click', function () { handleAnswer(btn); });
        answersWrap.appendChild(btn);
      });

      // Si la pregunta ya tenia respuesta, la volvemos a pintar al re-renderizar la card.
      if (currentAttempt && currentAttempt.selectedAnswer) {
        updateSelectedAnswerState(currentAttempt.selectedAnswer);
      }

      persistState();
    }

    function getReferenceImageUrl(card) {
      if (card && card.questionImageUrl) {
        return String(card.questionImageUrl);
      }

      return referenceImageButton ? String(referenceImageButton.dataset.vcExamReferenceImageFallback || '') : '';
    }

    function resetReferenceImage() {
      if (referenceImageButton) {
        referenceImageButton.hidden = true;
        referenceImageButton.classList.remove('is-expanded');
        referenceImageButton.setAttribute('aria-expanded', 'false');
      }
      if (referenceImageInlineWrap) {
        referenceImageInlineWrap.hidden = true;
      }
      if (referenceImageInline) {
        referenceImageInline.removeAttribute('src');
      }
    }

    function syncReferenceImage(card) {
      if (!referenceImageButton) {
        return;
      }

      referenceImageButton.hidden = !getReferenceImageUrl(card);
    }

    function toggleReferenceImageInline() {
      var card = cards[cardIndex];
      var imageUrl = getReferenceImageUrl(card);

      if (!imageUrl || !referenceImageButton || !referenceImageInline || !referenceImageInlineWrap) {
        return;
      }

      var isExpanded = referenceImageButton.classList.toggle('is-expanded');
      referenceImageButton.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

      if (isExpanded) {
        referenceImageInline.src = imageUrl;
        referenceImageInlineWrap.hidden = false;
      } else {
        referenceImageInlineWrap.hidden = true;
        referenceImageInline.removeAttribute('src');
      }
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // Mantiene una sola opcion marcada a la vez dentro de la pregunta actual.
    // Si el usuario cambia de A a C, esta funcion marca C y limpia el resto.
    function updateSelectedAnswerState(selectedAnswer) {
      answersWrap.querySelectorAll('.vc-exam-answer').forEach(function (buttonEl) {
        if (buttonEl.dataset.answerKey === selectedAnswer) {
          buttonEl.dataset.state = 'selected';
          return;
        }

        delete buttonEl.dataset.state;
      });
    }

    /* ── Answer handling ────────────────────────────────────────────────── */

    function handleAnswer(button) {
      var card         = cards[cardIndex];
      var selectedCardIndex = cardIndex;
      var selected     = button.dataset.answerKey;
      var responseMs   = Date.now() - answerStartedAt;

      updateSelectedAnswerState(selected);

      attempts[cardIndex] = {
        flashcardId:    card.id,
        topicTermId:    card.topicTermId,
        selectedAnswer: selected,
        responseTimeMs: responseMs,
      };

      persistState();

      clearAutoAdvanceTimer();

      // Auto advance solo cuando existe una siguiente pregunta.
      // El boton Next queda disponible para saltar preguntas sin responder.
      if (selectedCardIndex >= cards.length - 1) { return; }

      autoAdvanceTimer = setTimeout(function () {
        if (cardIndex !== selectedCardIndex || isSubmitting) { return; }
        cardIndex += 1;
        renderCard();
      }, 240);
    }

    /* ── Exam completion ────────────────────────────────────────────────── */

    function finishExam(expired) {
      if (isSubmitting) { return; }
      clearAutoAdvanceTimer();
      isSubmitting = true;
      stopTimer();

      var elapsed = getElapsedSeconds();
      var attemptPayload = buildAttemptPayload();

      var body = new URLSearchParams();
      body.append('action',     'vc_flashcards_complete_session');
      body.append('nonce',      nonce);
      body.append('session_id', String(sessionId));
      body.append('attempts',   JSON.stringify(attemptPayload));

      fetch(ajaxUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body:    body.toString(),
      })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
          if (!payload.success) { throw new Error('server'); }
          updateExamHomeBadges(payload.data && payload.data.examHomeStats);
          var summaryResults = buildServerSummaryResults(payload.data || {}, elapsed, expired);
          persistSummaryState(summaryResults);
          renderSummary(summaryResults);
          return refreshExamHistory();
        })
        .catch(function () {
          setFeedback(labels.saveFailed || 'We could not save your exam. Please try again.', 'error');
          if (!expired) {
            startTimer();
          }
          return null;
        })
        .finally(function () {
          isSubmitting = false;
        });
    }

    /* ── Summary rendering ──────────────────────────────────────────────── */

    function renderSummary(results) {
      activeSummaryResults = results;

      var passed = results.score >= config.passingScore;

      // Badge
      if (resultBadge) {
        resultBadge.className =
          'vc-exam-result-badge ' +
          (passed ? 'vc-exam-result-badge--passed' : 'vc-exam-result-badge--failed');
        var iconEl = resultBadge.querySelector('[data-vc-exam-result-icon]');
        if (iconEl && iconEl.tagName === 'IMG') {
          iconEl.src = passed
            ? (iconEl.dataset.passSrc || iconEl.src)
            : (iconEl.dataset.failSrc || iconEl.src);
        }
      }

      // Kicker / headline
      if (resultKicker) {
        resultKicker.textContent = passed
          ? (labels.approved || 'Approved')
          : (labels.notApproved || 'Not approved');
      }

      // Counts
      if (correctCount)   { correctCount.textContent   = String(results.correct); }
      if (incorrectCount) { incorrectCount.textContent = String(results.incorrect); }

      // Abre el resultado como modal para mantener visible la ultima pregunta de fondo.
      setSummaryOpen(true);
    }

    /* ── Start exam ─────────────────────────────────────────────────────── */

    // Inicia una nueva sesion de examen para la categoria elegida.
    // Este mismo flujo se reutiliza tanto desde la home como desde "Try again".
    function startExam(topicTermId) {
      if (isStartingExam) { return; }
      isStartingExam = true;
      lastTopicTermId = topicTermId;
      setFeedback(labels.loading || 'Preparing your exam...', 'info');
      startButtons.forEach(function (button) {
        button.disabled = true;
      });

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
          isSubmitting        = false;
          examStartTime       = Date.now();
          activeSummaryResults = null;

          // Start exam persistence: renderCard persiste, por eso la vista debe ser session antes de pintar.
          activeViewName       = 'session';
          renderCard();
          startTimer();
          showView('session');
        })
        .catch(function (err) {
          setFeedback(err.message || (labels.noCards || 'No questions found.'), 'error');
        })
        .finally(function () {
          isStartingExam = false;
          startButtons.forEach(function (button) {
            button.disabled = false;
          });
        });
    }

    /* ── Event listeners ────────────────────────────────────────────────── */

    // Start exam buttons (one per category card)
    startButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        startExam(Number(btn.dataset.vcExamStart));
      });
    });

    if (referenceImageButton) {
      referenceImageButton.addEventListener('click', toggleReferenceImageInline);
    }

    // Next / Finish
    if (nextButton) {
      nextButton.addEventListener('click', function () {
        clearAutoAdvanceTimer();
        cardIndex += 1;
        renderCard();
      });
    }

    // Permite volver a la pregunta anterior sin salir del rango valido del examen.
    if (prevButton) {
      prevButton.addEventListener('click', function () {
        clearAutoAdvanceTimer();
        if (prevButton.disabled) { return; }
        if (cardIndex === 0) { return; }
        cardIndex -= 1;
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
        resetExamState();
        showView('home');
        setFeedback('', '');
      });
    });

    if (finishButton) {
      finishButton.addEventListener('click', function () {
        finishExam(false);
      });
    }

    // Summary: back to menu (also acts as close button)
    root.querySelectorAll('[data-vc-exam-summary-back]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        stopTimer();
        clearAutoAdvanceTimer();
        resetExamState();
        setSummaryOpen(false, false);
        showView('home', { persist: false });
        clearState();
        setFeedback('', '');
      });
    });

    // Summary: try again (same category)
    root.querySelectorAll('[data-vc-exam-retry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var topicTermId = lastTopicTermId;
        clearState();
        if (topicTermId) {
          // Cierra el resultado anterior antes de pedir una nueva sesion. Si el
          // servidor falla, el usuario ve el error sin un modal obsoleto encima.
          setSummaryOpen(false, false);
          startExam(topicTermId);
        } else {
          resetExamState();
          setSummaryOpen(false, false);
          showView('home', { persist: false });
          clearState();
        }
      });
    });

    // Keyboard shortcut: press A/B/C to select an answer
    document.addEventListener('keydown', function (event) {
      if (sessionView.hidden) { return; }
      // Mientras el resultado esta abierto, el teclado no debe operar la sesion del fondo.
      if (summaryView && !summaryView.hidden) { return; }
      var key = event.key.toLowerCase();
      var answerBtn = answersWrap.querySelector('.vc-exam-answer[data-answer-key="' + key + '"]');
      if (answerBtn && !answerBtn.disabled) {
        event.preventDefault();
        answerBtn.click();
      }
    });

    /* ── Init ───────────────────────────────────────────────────────────── */

    if (!restoreState()) {
      showView('home', { behavior: 'auto' });
    }

    // Se registra al final del boot para que el cleanup viva durante toda la pagina
    // y cubra cualquier salida desde el mock test hacia otra vista del dashboard.
    bindDashboardExitCleanup();

    root.classList.remove('is-booting');
  }

  /* ── Boot all exam apps on the page ──────────────────────────────────── */

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vc-exam-app').forEach(initExamApp);
  });

}());
