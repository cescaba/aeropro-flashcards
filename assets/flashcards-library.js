(function () {
  'use strict';

  function initFlashcardsLibrary(root) {
    if (!root) {
      return;
    }

    var views = root.querySelectorAll('[data-vc-flashcards-library-view]');
    var detailPanels = root.querySelectorAll('[data-vc-flashcards-library-detail-panel]');
    var dashboardContent = root.closest('.vc-dashboard-content');
    var modal = root.querySelector('[data-vc-flashcards-session-modal]');
    var modalTitle = root.querySelector('[data-vc-flashcards-session-modal-title]');
    var modalCopy = root.querySelector('[data-vc-flashcards-session-modal-copy]');
    var countDisplay = root.querySelector('[data-vc-flashcards-session-count-display]');
    var rangeLabel = root.querySelector('[data-vc-flashcards-session-range-label]');
    var rangeInput = root.querySelector('[data-vc-flashcards-session-range]');
    var optionsWrap = root.querySelector('[data-vc-flashcards-session-options]');
    var confirmButton = root.querySelector('[data-vc-flashcards-session-confirm]');
    var modalStatus = root.querySelector('[data-vc-flashcards-session-status]');
    var confirmDefaultLabel = confirmButton ? confirmButton.textContent : 'Start';
    var pendingConfig = null;
    var activeLaunchButton = null;
    var optionValues = [10, 20, 30, 40, 50];
    var libraryData = window.vcFlashcardsLibraryData || {};
    var labels = libraryData.labels || {};
    var practiceStateVersion = 1;
    var practiceStateCompatibleVersions = [1, 2];
    var practiceStateMaxAgeMs = 12 * 60 * 60 * 1000;
    var currentPanelKey = root.getAttribute('data-vc-flashcards-library-initial-category') || '';
    var sessionId = 0;
    var cards = [];
    var cardIndex = 0;
    var attempts = [];
    var answerStartedAt = 0;
    var answeredCurrentCard = false;
    var pendingPracticeMotion = '';
    var currentPracticeConfig = null;
    var practiceState = window.VCViewState && typeof window.VCViewState.create === 'function'
      ? window.VCViewState.create('flashcards-practice', { scope: window.location.pathname })
      : null;
    var practiceCard = root.querySelector('[data-vc-flashcards-practice-card]');
    var practiceCardInner = root.querySelector('.vc-flashcards-practice-card-inner');
    var practiceTitle = root.querySelector('[data-vc-flashcards-practice-title]');
    var practiceSubtitle = root.querySelector('[data-vc-flashcards-practice-subtitle]');
    var practiceFinishButtons = root.querySelectorAll('[data-vc-flashcards-practice-finish]');
    var practiceQuestion = root.querySelector('[data-vc-flashcards-practice-question]');
    var practiceAnswers = root.querySelector('[data-vc-flashcards-practice-answers]');
    var practiceProgressCount = root.querySelector('[data-vc-flashcards-practice-progress-count]');
    var practiceBarFill = root.querySelector('[data-vc-flashcards-practice-bar-fill]');
    var practiceBack = root.querySelector('[data-vc-flashcards-practice-back]');
    var practiceNext = root.querySelector('[data-vc-flashcards-practice-next]');
    var practiceNextLabel = root.querySelector('[data-vc-flashcards-practice-next-label]');
    var practiceRepeat = root.querySelector('[data-vc-flashcards-practice-repeat]');
    var practiceShuffle = root.querySelector('[data-vc-flashcards-practice-shuffle]');

    function getModalLabel(name, fallback) {
      return modal && modal.dataset[name] ? modal.dataset[name] : fallback;
    }

    function updateUrl(viewName, panelKey) {
      if (!window.history || !window.URL) {
        return;
      }

      var url = new URL(window.location.href);

      if (viewName === 'detail' && panelKey) {
        url.searchParams.set('vc_flashcards_view', 'detail');
        url.searchParams.set('vc_flashcards_category', panelKey);
      } else {
        url.searchParams.delete('vc_flashcards_view');
        url.searchParams.delete('vc_flashcards_category');
      }

      window.history.pushState({
        vcFlashcardsView: viewName,
        vcFlashcardsCategory: panelKey || ''
      }, '', url.toString());
    }

    function showView(viewName) {
      root.setAttribute('data-vc-flashcards-library-current-view', viewName);

      views.forEach(function (view) {
        view.hidden = view.getAttribute('data-vc-flashcards-library-view') !== viewName;
      });

      if (dashboardContent) {
        dashboardContent.classList.toggle('is-vc-flashcards-detail-active', viewName === 'detail');
      }
    }

    function showDetailPanel(panelKey) {
      detailPanels.forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-vc-flashcards-library-detail-panel') !== panelKey;
      });
    }

    function hasDetailPanel(panelKey) {
      return Array.prototype.some.call(detailPanels, function (panel) {
        return panel.getAttribute('data-vc-flashcards-library-detail-panel') === panelKey;
      });
    }

    function showLibraryState(viewName, panelKey, shouldUpdateUrl) {
      if (viewName === 'detail' && !hasDetailPanel(panelKey)) {
        viewName = 'home';
        panelKey = '';
      }

      currentPanelKey = viewName === 'detail' ? panelKey : currentPanelKey;
      showDetailPanel(viewName === 'detail' ? panelKey : '');
      showView(viewName);

      if (shouldUpdateUrl) {
        updateUrl(viewName, panelKey);
      }
    }

    function formatLabel(template, first, second) {
      return String(template || '')
        .replace('%1$d', String(first))
        .replace('%2$d', String(second));
    }

    function normalizeCount(value, maxCards) {
      var safeMax = Math.max(1, Math.min(50, Number(maxCards) || 1));
      var count = Number(value) || 1;

      return Math.max(1, Math.min(safeMax, Math.round(count)));
    }

    function getOptionValues(maxCards, mode) {
      var safeMax = Math.max(1, Math.min(50, Number(maxCards) || 1));
      var filtered = optionValues.filter(function (value) {
        return value <= safeMax && (mode !== 'subcategory' || value < 40);
      });
      var values = filtered.length > 0 ? filtered : [safeMax];

      return values.filter(function (value, index) {
        return values.indexOf(value) === index;
      });
    }

    function updateRangeFill() {
      if (!rangeInput) {
        return;
      }

      var min = Number(rangeInput.min || 0);
      var max = Number(rangeInput.max || 100);
      var value = Number(rangeInput.value || min);
      var percent = max > min ? ((value - min) / (max - min)) * 100 : 0;

      rangeInput.style.setProperty('--vc-flashcards-session-range-progress', percent + '%');
    }

    function updateModalSelection(value) {
      if (!pendingConfig || !rangeInput || !countDisplay || !optionsWrap) {
        return;
      }

      pendingConfig.selectedCount = normalizeCount(value, pendingConfig.maxCards);
      rangeInput.value = String(pendingConfig.selectedCount);
      countDisplay.textContent = String(pendingConfig.selectedCount);

      optionsWrap.querySelectorAll('button').forEach(function (button) {
        button.classList.toggle('is-active', Number(button.dataset.count) === pendingConfig.selectedCount);
      });

      updateRangeFill();
    }

    function renderModalOptions(maxCards, mode) {
      if (!optionsWrap) {
        return;
      }

      var values = getOptionValues(maxCards, mode);
      optionsWrap.innerHTML = '';

      values.forEach(function (value) {
        var button = document.createElement('button');
        var label = document.createElement('span');
        button.type = 'button';
        button.className = 'vc-flashcards-session-count-option';
        button.dataset.count = String(value);
        label.className = 'vc-flashcards-session-count-option-label';
        label.textContent = String(value);
        button.appendChild(label);
        button.addEventListener('click', function () {
          updateModalSelection(value);
        });
        optionsWrap.appendChild(button);
      });

      if (mode !== 'subcategory') {
        optionsWrap.style.setProperty('--vc-flashcards-session-option-count', String(Math.max(1, values.length)));
        return;
      }

      var allCount = normalizeCount(maxCards, maxCards);
      var allButton = document.createElement('button');
      var allLabel = document.createElement('span');
      allButton.type = 'button';
      allButton.className = 'vc-flashcards-session-count-option vc-flashcards-session-count-option--all';
      allButton.dataset.count = String(allCount);
      allLabel.className = 'vc-flashcards-session-count-option-label';
      allLabel.textContent = getModalLabel('vcFlashcardsSessionAllLabel', 'All questions');
      allButton.appendChild(allLabel);
      allButton.addEventListener('click', function () {
        updateModalSelection(allCount);
      });
      optionsWrap.appendChild(allButton);
      optionsWrap.style.setProperty('--vc-flashcards-session-option-count', String(Math.max(1, values.length + 2)));
    }

    function openConfigModal(button) {
      if (!modal || !rangeInput) {
        return;
      }

      var mode = button.getAttribute('data-vc-flashcards-library-launch') || 'random';
      var termId = mode === 'subcategory'
        ? button.getAttribute('data-vc-flashcards-library-subtopic')
        : button.getAttribute('data-vc-flashcards-library-topic-id');
      var topic = button.getAttribute('data-vc-flashcards-library-topic') || '';
      var maxCards = Math.max(1, Math.min(50, Number(button.getAttribute('data-vc-flashcards-library-total')) || 1));
      var selectedCount = normalizeCount(Math.min(20, maxCards), maxCards);

      pendingConfig = {
        mode: mode,
        termId: Number(termId || 0),
        topic: topic,
        maxCards: maxCards,
        selectedCount: selectedCount
      };

      if (modalTitle) {
        modalTitle.textContent = mode === 'subcategory' ? topic : getModalLabel('vcFlashcardsSessionRandomTitle', 'Random practice');
      }

      if (modalCopy) {
        var randomCopy = getModalLabel('vcFlashcardsSessionRandomCopy', 'Choose how many questions to review from %s.');
        modalCopy.textContent = mode === 'subcategory'
          ? getModalLabel('vcFlashcardsSessionSubtopicCopy', 'Choose how many questions to review from this subtopic.')
          : randomCopy.replace('%s', topic);
      }

      rangeInput.min = '1';
      rangeInput.max = String(maxCards);
      if (rangeLabel) {
        rangeLabel.textContent = '1 - ' + String(maxCards);
      }

      renderModalOptions(maxCards, mode);
      updateModalSelection(selectedCount);
      setModalStatus('', '');
      if (activeLaunchButton) {
        activeLaunchButton.classList.remove('is-session-modal-active');
      }
      activeLaunchButton = button;
      activeLaunchButton.classList.add('is-session-modal-active');
      modal.hidden = false;
      root.classList.add('is-flashcards-session-modal-open');
      document.body.classList.add('vc-flashcards-session-modal-open');

      if (document.activeElement === button) {
        button.blur();
      }
    }

    function closeConfigModal() {
      if (modal) {
        modal.hidden = true;
      }

      root.classList.remove('is-flashcards-session-modal-open');
      document.body.classList.remove('vc-flashcards-session-modal-open');
      if (activeLaunchButton) {
        activeLaunchButton.classList.remove('is-session-modal-active');
        activeLaunchButton = null;
      }
      pendingConfig = null;
    }

    function setStartLoading(isLoading) {
      if (!confirmButton) {
        return;
      }

      confirmButton.disabled = !!isLoading;
      confirmButton.textContent = isLoading ? (labels.loading || 'Loading questions...') : confirmDefaultLabel;
    }

    function setModalStatus(message, state) {
      if (!modalStatus) {
        return;
      }

      modalStatus.textContent = message || '';
      modalStatus.hidden = !message;
      modalStatus.dataset.state = state || '';
    }

    function resetPracticeUi() {
      answeredCurrentCard = false;
      if (practiceAnswers) {
        practiceAnswers.innerHTML = '';
      }
      if (practiceNext) {
        practiceNext.hidden = true;
        practiceNext.disabled = true;
      }
      updatePracticeStepControls();
      if (practiceCard) {
        practiceCard.classList.remove('is-flipped', 'is-flipping', 'is-loading', 'has-answer');
        practiceCard.setAttribute('aria-pressed', 'false');
      }
      updatePracticeRepeatControl();
      updatePracticeShuffleControl();
    }

    function getPracticeTitle(config, card) {
      return (config && config.topic) || (card && card.topicLabel) || 'Flashcards';
    }

    function getPracticeSubtitle(config, card) {
      if (config && config.mode === 'subcategory') {
        return (card && card.subtopicLabel) || config.topic || 'Subtopic practice';
      }

      return 'Subtopic name';
    }

    function updatePracticeHeading(config, card) {
      if (practiceTitle) {
        practiceTitle.textContent = getPracticeTitle(config, card);
      }
      if (practiceSubtitle) {
        practiceSubtitle.textContent = getPracticeSubtitle(config, card);
      }
    }

    function setPracticeFinishDisabled(isDisabled) {
      practiceFinishButtons.forEach(function (button) {
        button.disabled = !!isDisabled;
      });
    }

    function updatePracticeRepeatControl() {
      if (!practiceRepeat) {
        return;
      }

      practiceRepeat.disabled = !currentPracticeConfig;
    }

    function updatePracticeShuffleControl() {
      if (!practiceShuffle) {
        return;
      }

      practiceShuffle.disabled = !currentPracticeConfig;
    }

    function restartCurrentPracticeSession() {
      if (!cards.length) {
        return;
      }

      cardIndex = 0;
      attempts = [];
      renderPracticeCard();
      savePracticeState();
    }

    function updatePracticeStepControls() {
      if (practiceBack) {
        practiceBack.disabled = cardIndex < 1;
      }
    }

    function blurButton(button) {
      if (button && document.activeElement === button) {
        button.blur();
      }
    }

    function applyPracticeCardMotion(isAnswered) {
      if (!practiceCard || pendingPracticeMotion !== 'back') {
        pendingPracticeMotion = '';
        return;
      }

      practiceCard.classList.add(isAnswered ? 'is-returning-flipped' : 'is-returning');
      pendingPracticeMotion = '';
    }

    function clearStoredPracticeState() {
      if (practiceState) {
        practiceState.clear();
      }
    }

    function hasValidPracticeCard(card) {
      return !!(
        card
        && card.id
        && typeof card.question === 'string'
        && card.question !== ''
        && card.correctAnswer
        && card.answers
        && typeof card.answers === 'object'
      );
    }

    function isValidStoredPracticeState(storedState) {
      var savedAt = Number(storedState && storedState.savedAt);
      var isFresh = savedAt > 0 && Date.now() - savedAt <= practiceStateMaxAgeMs;
      var storedVersion = Number(storedState && storedState.version);

      return !!(
        storedState
        && practiceStateCompatibleVersions.indexOf(storedVersion) !== -1
        && isFresh
        && Number(storedState.sessionId) > 0
        && Array.isArray(storedState.cards)
        && storedState.cards.length > 0
        && storedState.cards.every(hasValidPracticeCard)
      );
    }

    function savePracticeState() {
      if (!sessionId || !cards.length) {
        return;
      }

      if (!practiceState) {
        return;
      }

      practiceState.save({
        version: practiceStateVersion,
        savedAt: Date.now(),
        sessionId: sessionId,
        cards: cards,
        cardIndex: cardIndex,
        attempts: normalizePracticeAttempts(attempts),
        currentPracticeConfig: currentPracticeConfig,
        currentPanelKey: currentPanelKey
      });
    }

    function normalizePracticeAttempt(attempt) {
      if (!attempt || !attempt.flashcardId) {
        return null;
      }

      return {
        flashcardId: Number(attempt.flashcardId) || 0,
        topicTermId: Number(attempt.topicTermId) || 0,
        selectedAnswer: attempt.selectedAnswer ? String(attempt.selectedAnswer) : '',
        responseTimeMs: Math.max(0, Number(attempt.responseTimeMs) || 0)
      };
    }

    function normalizePracticeAttempts(input) {
      if (!Array.isArray(input)) {
        return [];
      }

      return input
        .map(normalizePracticeAttempt)
        .filter(function (attempt) {
          return !!(attempt && attempt.flashcardId > 0);
        });
    }

    function getCurrentCardAttempt(card) {
      if (!card) {
        return null;
      }

      for (var index = attempts.length - 1; index >= 0; index -= 1) {
        if (Number(attempts[index].flashcardId) === Number(card.id)) {
          return attempts[index];
        }
      }

      return null;
    }

    function showReviewedCurrentCard(card) {
      if (practiceCard) {
        practiceCard.classList.add('is-flipped', 'has-answer');
        practiceCard.setAttribute('aria-pressed', 'true');
      }

      if (practiceNext) {
        practiceNext.hidden = false;
        practiceNext.disabled = false;
      }
    }

    function restoreAnsweredCurrentCard(card) {
      var currentAttempt = getCurrentCardAttempt(card);
      if (!currentAttempt) {
        return;
      }

      answeredCurrentCard = true;
      showReviewedCurrentCard(card);
    }

    function getCorrectAnswerText(card) {
      var answers = card && card.answers ? card.answers : {};
      var correctKey = card && card.correctAnswer ? String(card.correctAnswer) : '';

      return answers[correctKey] || '';
    }

    function buildAnswerDisplay(card) {
      var answer = document.createElement('div');
      var label = document.createElement('strong');
      var correctKey = card && card.correctAnswer ? String(card.correctAnswer) : '';

      answer.className = 'vc-flashcards-practice-answer vc-flashcards-practice-answer--revealed';
      answer.dataset.answerKey = correctKey;
      label.textContent = getCorrectAnswerText(card);
      answer.appendChild(label);

      return answer;
    }

    function buildAnswerLoadingRow(index) {
      var row = document.createElement('div');
      var key = document.createElement('span');
      var line = document.createElement('span');

      row.className = 'vc-flashcards-practice-answer vc-flashcards-practice-answer--loading';
      row.setAttribute('aria-hidden', 'true');
      key.className = 'vc-flashcards-practice-answer-key';
      key.textContent = ['A', 'B', 'C'][index] || '';
      line.className = 'vc-flashcards-practice-answer-loading-line';
      row.appendChild(key);
      row.appendChild(line);

      return row;
    }

    function renderPracticeLoading(config) {
      resetPracticeUi();
      answerStartedAt = 0;
      closeConfigModal();
      showView('session');
      updatePracticeHeading(config, null);
      setPracticeFinishDisabled(true);
      updatePracticeRepeatControl();
      updatePracticeShuffleControl();

      if (practiceProgressCount) {
        practiceProgressCount.textContent = labels.loading || 'Loading questions...';
      }
      if (practiceBarFill) {
        practiceBarFill.style.width = '0%';
      }
      if (practiceQuestion) {
        practiceQuestion.textContent = labels.loading || 'Loading questions...';
      }
      if (practiceAnswers) {
        var loadingFragment = document.createDocumentFragment();
        [0].forEach(function (index) {
          loadingFragment.appendChild(buildAnswerLoadingRow(index));
        });
        practiceAnswers.appendChild(loadingFragment);
      }
      if (practiceCard) {
        practiceCard.classList.add('is-loading');
      }
    }

    function renderPracticeStartError(message) {
      resetPracticeUi();
      showView('session');
      updatePracticeHeading(currentPracticeConfig, null);
      setPracticeFinishDisabled(false);
      updatePracticeRepeatControl();
      updatePracticeShuffleControl();

      if (practiceProgressCount) {
        practiceProgressCount.textContent = labels.error || 'Could not start session';
      }
      if (practiceBarFill) {
        practiceBarFill.style.width = '0%';
      }
      if (practiceQuestion) {
        practiceQuestion.textContent = message || (labels.noCards || 'No questions were found for this selection.');
      }
    }

    function renderPracticeCard() {
      var card = cards[cardIndex];
      var currentAttempt = null;

      if (!card) {
        finishPracticeSession();
        return;
      }

      resetPracticeUi();
      answerStartedAt = Date.now();
      showView('session');
      setPracticeFinishDisabled(false);
      updatePracticeRepeatControl();
      updatePracticeShuffleControl();

      if (practiceProgressCount) {
        practiceProgressCount.textContent = String(cardIndex + 1) + ' / ' + String(cards.length);
      }
      updatePracticeStepControls();
      if (practiceBarFill) {
        practiceBarFill.style.width = String(Math.round(((cardIndex + 1) / cards.length) * 100)) + '%';
      }
      if (practiceQuestion) {
        practiceQuestion.textContent = card.question || '';
      }

      updatePracticeHeading(currentPracticeConfig, card);
      currentAttempt = getCurrentCardAttempt(card);

      if (practiceAnswers) {
        practiceAnswers.appendChild(buildAnswerDisplay(card));
      }

      if (practiceNextLabel) {
        practiceNextLabel.textContent = cardIndex === cards.length - 1 ? (labels.finish || 'Finish') : (labels.nextQuestion || 'Next question');
      }
      if (practiceNext) {
        practiceNext.hidden = false;
        practiceNext.disabled = false;
      }

      if (currentAttempt) {
        answeredCurrentCard = true;
        showReviewedCurrentCard(card);
      }
      applyPracticeCardMotion(!!currentAttempt);
      savePracticeState();
    }

    function flipPracticeCard() {
      if (!practiceCard || practiceCard.classList.contains('is-loading') || practiceCard.classList.contains('is-flipped')) {
        return;
      }

      practiceCard.classList.add('is-flipping');
      practiceCard.classList.add('is-flipped');
      practiceCard.setAttribute('aria-pressed', 'true');
      recordCurrentCardReview();
    }

    function recordCurrentCardReview() {
      if (answeredCurrentCard) {
        return;
      }

      var card = cards[cardIndex];
      if (!card) {
        return;
      }

      var responseTimeMs = Date.now() - answerStartedAt;
      answeredCurrentCard = true;
      if (practiceCard) {
        practiceCard.classList.add('has-answer');
      }

      attempts.push({
        flashcardId: card.id,
        topicTermId: card.topicTermId,
        selectedAnswer: card.correctAnswer,
        responseTimeMs: responseTimeMs
      });
      savePracticeState();

      if (practiceNext) {
        practiceNext.hidden = false;
        practiceNext.disabled = false;
      }
    }

    function startPracticeSession(config) {
      if (!config || !libraryData.ajaxUrl || !libraryData.nonce) {
        setModalStatus('Could not start the session. Please refresh the page and try again.', 'error');
        return;
      }

      setStartLoading(true);
      setModalStatus('', '');
      currentPracticeConfig = Object.assign({}, config);
      renderPracticeLoading(currentPracticeConfig);

      var body = new window.URLSearchParams();
      body.append('action', 'vc_flashcards_start_session');
      body.append('nonce', libraryData.nonce);
      body.append('session_source', 'flashcards_library');
      body.append('mode', config.mode);
      body.append('term_id', String(config.termId || 0));
      body.append('card_limit', String(config.selectedCount || 10));

      fetch(libraryData.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload.success) {
            throw new Error(payload.data && payload.data.message ? payload.data.message : (labels.noCards || 'No questions were found for this selection.'));
          }

          sessionId = Number(payload.data.sessionId || 0);
          cards = payload.data.cards || [];
          cardIndex = 0;
          attempts = [];
          renderPracticeCard();
          savePracticeState();
        })
        .catch(function (error) {
          renderPracticeStartError(error.message || (labels.noCards || 'No questions were found for this selection.'));
        })
        .finally(function () {
          setStartLoading(false);
        });
    }

    function exitPracticeSession() {
      clearStoredPracticeState();
      sessionId = 0;
      cards = [];
      cardIndex = 0;
      attempts = [];
      answerStartedAt = 0;
      answeredCurrentCard = false;
      pendingPracticeMotion = '';
      currentPracticeConfig = null;
      showLibraryState(currentPanelKey && hasDetailPanel(currentPanelKey) ? 'detail' : 'home', currentPanelKey, true);
    }

    function getLocalPracticeMetrics() {
      var correct = attempts.filter(function (attempt) {
        return attempt && attempt.selectedAnswer;
      }).length;
      var incorrect = Math.max(0, cards.length - correct);
      var score = cards.length > 0 ? Math.round((correct / cards.length) * 100) : 0;

      return {
        correctAnswers: correct,
        incorrectAnswers: incorrect,
        scorePercent: score
      };
    }

    function finishPracticeSession() {
      if (!sessionId || !libraryData.ajaxUrl || !libraryData.nonce) {
        exitPracticeSession();
        return;
      }

      var body = new window.URLSearchParams();
      body.append('action', 'vc_flashcards_complete_session');
      body.append('nonce', libraryData.nonce);
      body.append('session_id', String(sessionId));
      body.append('attempts', JSON.stringify(attempts));

      fetch(libraryData.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload.success) {
            throw new Error(payload.data && payload.data.message ? payload.data.message : '');
          }

          exitPracticeSession();
        })
        .catch(function () {
          exitPracticeSession();
        });
    }

    function restoreStoredPracticeState() {
      if (!practiceState) {
        return false;
      }

      var storedState = practiceState.restore();

      if (!isValidStoredPracticeState(storedState)) {
        clearStoredPracticeState();
        return false;
      }

      sessionId = Number(storedState.sessionId || 0);
      cards = storedState.cards;
      cardIndex = Math.max(0, Math.min(cards.length - 1, Number(storedState.cardIndex) || 0));
      attempts = normalizePracticeAttempts(storedState.attempts);
      currentPracticeConfig = storedState.currentPracticeConfig || null;
      currentPanelKey = hasDetailPanel(storedState.currentPanelKey) ? storedState.currentPanelKey : '';

      showDetailPanel('');
      renderPracticeCard();
      return true;
    }

    root.querySelectorAll('[data-vc-flashcards-library-start]').forEach(function (button) {
      button.addEventListener('click', function () {
        showLibraryState('detail', button.getAttribute('data-vc-flashcards-library-start') || '', true);
      });
    });

    root.querySelectorAll('[data-vc-flashcards-library-back]').forEach(function (button) {
      button.addEventListener('click', function () {
        showLibraryState('home', '', true);
      });
    });

    root.querySelectorAll('[data-vc-flashcards-library-launch]').forEach(function (button) {
      button.addEventListener('click', function () {
        openConfigModal(button);
      });
    });

    root.querySelectorAll('[data-vc-flashcards-session-close]').forEach(function (button) {
      button.addEventListener('click', closeConfigModal);
    });

    if (rangeInput) {
      rangeInput.addEventListener('input', function () {
        updateModalSelection(Number(rangeInput.value));
      });
    }

    if (confirmButton) {
      confirmButton.addEventListener('click', function () {
        if (!pendingConfig) {
          return;
        }

        root.dispatchEvent(new CustomEvent('vc-flashcards-library-session-configured', {
          bubbles: true,
          detail: Object.assign({}, pendingConfig)
        }));
        startPracticeSession(pendingConfig);
      });
    }

    if (practiceBack) {
      practiceBack.addEventListener('click', function () {
        if (cardIndex < 1) {
          return;
        }

        pendingPracticeMotion = 'back';
        cardIndex -= 1;
        renderPracticeCard();
        savePracticeState();
        blurButton(practiceBack);
      });
    }

    root.querySelectorAll('[data-vc-flashcards-practice-finish]').forEach(function (button) {
      button.addEventListener('click', function () {
        finishPracticeSession();
      });
    });

    if (practiceCard) {
      practiceCard.addEventListener('click', flipPracticeCard);
      practiceCard.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          flipPracticeCard();
        }
      });
    }

    if (practiceCardInner) {
      practiceCardInner.addEventListener('animationend', function (event) {
        if (
          event.animationName === 'vcFlashcardsPracticeFlipDown'
          || event.animationName === 'vcFlashcardsPracticeReturnUp'
          || event.animationName === 'vcFlashcardsPracticeReturnUpFlipped'
        ) {
          practiceCard.classList.remove('is-flipping', 'is-returning', 'is-returning-flipped');
        }
      });
    }

    if (practiceNext) {
      practiceNext.addEventListener('click', function () {
        cardIndex += 1;
        renderPracticeCard();
        savePracticeState();
        blurButton(practiceNext);
      });
    }

    if (practiceRepeat) {
      practiceRepeat.addEventListener('click', function () {
        if (!cards.length) {
          return;
        }

        restartCurrentPracticeSession();
        blurButton(practiceRepeat);
      });
    }

    if (practiceShuffle) {
      practiceShuffle.addEventListener('click', function () {
        if (!currentPracticeConfig) {
          return;
        }

        startPracticeSession(currentPracticeConfig);
        blurButton(practiceShuffle);
      });
    }

    window.addEventListener('popstate', function () {
      var params = new URLSearchParams(window.location.search);
      var viewName = params.get('vc_flashcards_view') === 'detail' ? 'detail' : 'home';
      var panelKey = params.get('vc_flashcards_category') || '';

      showLibraryState(viewName, panelKey, false);
    });

    try {
      if (!restoreStoredPracticeState()) {
        showLibraryState(
          root.getAttribute('data-vc-flashcards-library-initial-view') === 'detail' ? 'detail' : 'home',
          root.getAttribute('data-vc-flashcards-library-initial-category') || '',
          false
        );
      }
    } finally {
      updatePracticeRepeatControl();
      updatePracticeShuffleControl();
      root.classList.remove('is-booting');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vc-flashcards-library]').forEach(initFlashcardsLibrary);
  });
}());
