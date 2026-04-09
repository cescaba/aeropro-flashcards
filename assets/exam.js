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
    var summaryView = root.querySelector('[data-vc-exam-view="summary"]');
    var feedbackEl  = root.querySelector('[data-vc-exam-feedback]');
    var sessionHeader = root.querySelector('.vc-exam-session-header');
    var finishButton = root.querySelector('.vc-exam-finish-btn');

    // Session
    var progressEl    = root.querySelector('[data-vc-exam-progress]');
    var timerEl       = root.querySelector('[data-vc-exam-timer]');
    var timerValueEl  = root.querySelector('[data-vc-exam-timer-value]');
    var barFill       = root.querySelector('[data-vc-exam-bar-fill]');
    var questionEl    = root.querySelector('[data-vc-exam-question]');
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
    var unansweredCount = root.querySelector('[data-vc-exam-unanswered-count]');
    var breakdownGeneralMeta = root.querySelector('[data-vc-exam-breakdown-general-meta]');
    var breakdownGeneralScore = root.querySelector('[data-vc-exam-breakdown-general-score]');
    var breakdownAirframeMeta = root.querySelector('[data-vc-exam-breakdown-airframe-meta]');
    var breakdownAirframeScore = root.querySelector('[data-vc-exam-breakdown-airframe-score]');
    var breakdownPowerplantMeta = root.querySelector('[data-vc-exam-breakdown-powerplant-meta]');
    var breakdownPowerplantScore = root.querySelector('[data-vc-exam-breakdown-powerplant-score]');
    var summaryBox    = root.querySelector('.vc-exam-summary-dialog');
    var historyContent = root.querySelector('.vc-exam-history-content');
    var dashboardPanel = root.closest('.vc-dashboard-panel--mock-test');
    var dashboardContent = root.closest('.vc-dashboard-content');
    var dashboardHeadingMeta = null;

    if (dashboardPanel && dashboardPanel.previousElementSibling && dashboardPanel.previousElementSibling.classList.contains('vc-dashboard-heading')) {
      dashboardHeadingMeta = dashboardPanel.previousElementSibling.querySelector('.vc-dashboard-heading-session-meta');
    }

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

    // Resetea por completo el estado del examen en memoria.
    // Se usa cuando cerramos una sesion, limpiamos progreso o partimos de cero.
    function resetExamState() {
      sessionId = 0;
      cards = [];
      cardIndex = 0;
      attempts = [];
      examStartTime = 0;
      answeredCurrentCard = false;
      answerStartedAt = 0;
      isSubmitting = false;
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

    function getRemainingSeconds() {
      if (!examStartTime) { return config.timeLimitSeconds; }
      var elapsed = Math.floor((Date.now() - examStartTime) / 1000);
      return Math.max(0, config.timeLimitSeconds - elapsed);
    }

    function getElapsedSeconds() {
      if (!examStartTime) { return 0; }
      return Math.floor((Date.now() - examStartTime) / 1000);
    }

    // Convierte el estado local de las preguntas en un payload plano listo para guardar o resumir.
    // Aqui se empaqueta, por cada tarjeta del examen, la respuesta elegida, la correcta y el tiempo de respuesta.
    function buildAttemptPayload() {
      return cards.map(function (card, index) {
        var attempt = attempts[index] || {};

        return {
          flashcardId: card.id,
          topicTermId: card.topicTermId,
          selectedAnswer: attempt.selectedAnswer || '',
          correctAnswer: card.correctAnswer,
          responseTimeMs: Number(attempt.responseTimeMs || 0),
        };
      });
    }

    // Agrupa el resultado del examen por categoria principal para alimentar el desglose del summary.
    // Calcula cuantas preguntas hubo por tema y cuantas quedaron correctas dentro de ese tema.
    function buildTopicBreakdown(attemptPayload) {
      var topics = {
        General: { total: 0, correct: 0 },
        Airframe: { total: 0, correct: 0 },
        Powerplant: { total: 0, correct: 0 },
      };

      attemptPayload.forEach(function (attempt, index) {
        var card = cards[index];
        var topicName = card && card.topicLabel ? card.topicLabel : '';

        if (!topics[topicName]) { return; }

        topics[topicName].total += 1;

        if (attempt.selectedAnswer && attempt.selectedAnswer === attempt.correctAnswer) {
          topics[topicName].correct += 1;
        }
      });

      return topics;
    }

    // Construye el resumen global del examen a partir del payload final.
    // Devuelve aciertos, errores, no respondidas, porcentaje final, tiempo consumido y el desglose por categoria.
    function buildSummaryResults(attemptPayload, elapsed, expired) {
      var total = attemptPayload.length;
      // Separa cada intento en tres estados excluyentes:
      // correcta, incorrecta o no respondida.
      // Asi evitamos que una pregunta vacia se sume tambien como incorrecta.
      var counts = attemptPayload.reduce(function (result, attempt) {
        if (!attempt.selectedAnswer) {
          result.unanswered += 1;
          return result;
        }

        if (attempt.selectedAnswer === attempt.correctAnswer) {
          result.correct += 1;
          return result;
        }

        result.incorrect += 1;
        return result;
      }, {
        correct: 0,
        incorrect: 0,
        unanswered: 0,
      });

      return {
        correct: counts.correct,
        incorrect: counts.incorrect,
        unanswered: counts.unanswered,
        breakdown: buildTopicBreakdown(attemptPayload),
        // El porcentaje final del examen se calcula con respuestas correctas
        // sobre el total de preguntas del mock test.
        score: total > 0 ? Math.round((counts.correct / total) * 100) : 0,
        elapsed: elapsed,
        expired: expired,
      };
    }

    // Mueve cronometro, progreso y boton Finish entre el heading del dashboard y la sesion del examen.
    // Esto permite que el header superior muestre los controles correctos solo mientras el examen esta en curso.
    function syncSessionHeading(name) {
      if (!dashboardHeadingMeta || !sessionHeader || !progressEl || !timerEl || !finishButton) {
        return;
      }

      if (name === 'session') {
        dashboardHeadingMeta.hidden = false;
        dashboardHeadingMeta.appendChild(timerEl);
        dashboardHeadingMeta.appendChild(finishButton);
        sessionHeader.appendChild(progressEl);
        return;
      }

      dashboardHeadingMeta.hidden = true;
      sessionHeader.appendChild(finishButton);
      sessionHeader.appendChild(progressEl);
      sessionHeader.appendChild(timerEl);
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

    // Hace scroll al inicio del contenedor real del dashboard.
    // Se usa al cambiar de vista para que home, session y summary siempre arranquen desde arriba.
    function scrollDashboardContentToTop() {
      if (!dashboardContent) { return; }
      dashboardContent.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ── State persistence ──────────────────────────────────────────────── */

    function persistState() {
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          view:                'session',
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

    // Guarda el resumen final en sessionStorage para poder restaurarlo si la vista se recarga.
    // Aqui no se guardan las preguntas activas; solo el estado final del summary.
    function persistSummaryState(results) {
      try {
        sessionStorage.setItem(storageKey, JSON.stringify({
          view:            'summary',
          lastTopicTermId: lastTopicTermId,
          summaryResults:  results,
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

        // Si la ultima vista guardada fue el resumen, reconstruimos directamente esa pantalla.
        if (state.view === 'summary' && state.summaryResults) {
          resetExamState();
          lastTopicTermId = state.lastTopicTermId || 0;
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
        answeredCurrentCard = !!state.answeredCurrentCard;
        lastTopicTermId     = state.lastTopicTermId || 0;

        // Si hay una vista guardada distinta de session, no intentamos restaurar una sesion activa.
        if (state.view && state.view !== 'session') {
          return false;
        }

        renderCard();
        startTimer();
        showView('session');
        return true;
      } catch (e) {}
      return false;
    }

    /* ── View management ────────────────────────────────────────────────── */

    function showView(name) {
      homeView.hidden    = (name !== 'home');
      sessionView.hidden = (name !== 'session');
      if (summaryView) {
        summaryView.hidden = (name !== 'summary');
      }
      // Reubica controles del header y reposiciona el scroll al inicio del panel actual.
      syncSessionHeading(name);
      scrollDashboardContentToTop();
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
      var card = cards[cardIndex];
      // Recupera la respuesta ya guardada de la pregunta actual, si existe.
      var currentAttempt = attempts[cardIndex] || null;

      if (!card) {
        finishExam(false);
        return;
      }

      answeredCurrentCard = !!(currentAttempt && currentAttempt.selectedAnswer);
      answerStartedAt     = Date.now();

      // Reset answer area
      answersWrap.innerHTML = '';
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

      // Si la pregunta ya tenia respuesta, la volvemos a pintar al re-renderizar la card.
      if (currentAttempt && currentAttempt.selectedAnswer) {
        updateSelectedAnswerState(currentAttempt.selectedAnswer);
      }

      persistState();
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
      answersWrap.querySelectorAll('.vc-flashcards-answer').forEach(function (buttonEl) {
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
      var selected     = button.dataset.answerKey;
      var responseMs   = Date.now() - answerStartedAt;

      answeredCurrentCard = true;

      updateSelectedAnswerState(selected);

      attempts[cardIndex] = {
        flashcardId:    card.id,
        topicTermId:    card.topicTermId,
        selectedAnswer: selected,
        correctAnswer:  card.correctAnswer,
        responseTimeMs: responseMs,
      };

      persistState();
    }

    /* ── Exam completion ────────────────────────────────────────────────── */

    function finishExam(expired) {
      if (isSubmitting) { return; }
      isSubmitting = true;
      stopTimer();

      var elapsed = getElapsedSeconds();
      var attemptPayload = buildAttemptPayload();
      var summaryResults = buildSummaryResults(attemptPayload, elapsed, expired);

      persistSummaryState(summaryResults);
      renderSummary(summaryResults);

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
          return refreshExamHistory();
        })
        .catch(function () {
          return null;
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
        if (iconEl && iconEl.tagName === 'IMG') {
          iconEl.src = passed
            ? (iconEl.dataset.passSrc || iconEl.src)
            : (iconEl.dataset.failSrc || iconEl.src);
        }
      }

      // Summary box state class
      if (summaryBox) {
        summaryBox.classList.toggle('is-passed', passed);
        summaryBox.classList.toggle('is-failed', !passed);
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
      // Refleja cuantas preguntas quedaron sin responder en el resumen final.
      if (unansweredCount) { unansweredCount.textContent = String(results.unanswered || 0); }

      // Toma el desglose por categoria y asegura una estructura segura aunque falten datos.
      var breakdown = results.breakdown || {};
      var general = breakdown.General || { total: 0, correct: 0 };
      var airframe = breakdown.Airframe || { total: 0, correct: 0 };
      var powerplant = breakdown.Powerplant || { total: 0, correct: 0 };

      // Llena el bloque de General con su texto auxiliar y porcentaje.
      if (breakdownGeneralMeta) {
        breakdownGeneralMeta.textContent = general.correct + ' of ' + general.total + ' correct';
      }
      if (breakdownGeneralScore) {
        var generalPercent = general.total ? Math.round((general.correct / general.total) * 100) : 0;
        breakdownGeneralScore.textContent = generalPercent + '%';
        breakdownGeneralScore.classList.toggle('is-passing', generalPercent >= config.passingScore);
      }

      // Llena el bloque de Airframe con su texto auxiliar y porcentaje.
      if (breakdownAirframeMeta) {
        breakdownAirframeMeta.textContent = airframe.correct + ' of ' + airframe.total + ' correct';
      }
      if (breakdownAirframeScore) {
        var airframePercent = airframe.total ? Math.round((airframe.correct / airframe.total) * 100) : 0;
        breakdownAirframeScore.textContent = airframePercent + '%';
        breakdownAirframeScore.classList.toggle('is-passing', airframePercent >= config.passingScore);
      }

      // Llena el bloque de Powerplant con su texto auxiliar y porcentaje.
      if (breakdownPowerplantMeta) {
        breakdownPowerplantMeta.textContent = powerplant.correct + ' of ' + powerplant.total + ' correct';
      }
      if (breakdownPowerplantScore) {
        var powerplantPercent = powerplant.total ? Math.round((powerplant.correct / powerplant.total) * 100) : 0;
        breakdownPowerplantScore.textContent = powerplantPercent + '%';
        breakdownPowerplantScore.classList.toggle('is-passing', powerplantPercent >= config.passingScore);
      }

      // Finalmente cambia la interfaz a la vista final del examen.
      showView('summary');
    }

    /* ── Start exam ─────────────────────────────────────────────────────── */

    // Inicia una nueva sesion de examen para la categoria elegida.
    // Este mismo flujo se reutiliza tanto desde la home como desde "Try again".
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

    // Permite volver a la pregunta anterior sin salir del rango valido del examen.
    if (prevButton) {
      prevButton.addEventListener('click', function () {
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
        clearState();
        showView('home');
        setFeedback('', '');
      });
    });

    // Summary: try again (same category)
    root.querySelectorAll('[data-vc-exam-retry]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        clearState();
        if (lastTopicTermId) {
          // Reinicia directamente la misma categoria sin pasar primero por la home.
          // Asi evitamos el salto visual mientras el servidor prepara la nueva sesion.
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
