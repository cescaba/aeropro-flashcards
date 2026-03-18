(function () {
  function initFlashcardsApp(root) {
    const categories = parseCategories(root.dataset.categories);
    const labels = (window.vcFlashcardsData && window.vcFlashcardsData.labels) || {};
    const cardOptions = (window.vcFlashcardsData && window.vcFlashcardsData.cardOptions) || [10, 20, 30, 40, 50];

    const feedback = root.querySelector('[data-vc-flashcards-feedback]');
    const homeView = root.querySelector('[data-vc-flashcards-view="home"]');
    const detailView = root.querySelector('[data-vc-flashcards-view="detail"]');
    const sessionPanel = root.querySelector('[data-vc-flashcards-session]');
    const summaryPanel = root.querySelector('[data-vc-flashcards-summary]');
    const modal = root.querySelector('[data-vc-flashcards-modal]');
    const categoryTitle = root.querySelector('[data-vc-flashcards-category-title]');
    const categoryMeta = root.querySelector('[data-vc-flashcards-category-meta]');
    const subtopicsWrap = root.querySelector('[data-vc-flashcards-subtopics]');
    const nextButton = root.querySelector('[data-vc-flashcards-next]');
    const revealButton = root.querySelector('[data-vc-flashcards-reveal]');
    const explanationToggle = root.querySelector('[data-vc-flashcards-explanation-toggle]');
    const restartButton = root.querySelector('[data-vc-flashcards-restart]');
    const summaryBackButton = root.querySelector('[data-vc-flashcards-summary-back]');
    const answersWrap = root.querySelector('[data-vc-flashcards-answers]');
    const questionEl = root.querySelector('[data-vc-flashcards-question]');
    const explanationEl = root.querySelector('[data-vc-flashcards-explanation]');
    const progressTitle = root.querySelector('[data-vc-flashcards-progress-title]');
    const progressCount = root.querySelector('[data-vc-flashcards-progress-count]');
    const kicker = root.querySelector('[data-vc-flashcards-kicker]');
    const summaryScore = root.querySelector('[data-vc-flashcards-summary-score]');
    const summaryCopy = root.querySelector('[data-vc-flashcards-summary-copy]');
    const modalTitle = root.querySelector('[data-vc-flashcards-modal-title]');
    const modalCopy = root.querySelector('[data-vc-flashcards-modal-copy]');
    const countDisplay = root.querySelector('[data-vc-flashcards-count-display]');
    const rangeLabel = root.querySelector('[data-vc-flashcards-range-label]');
    const rangeInput = root.querySelector('[data-vc-flashcards-range]');
    const optionsWrap = root.querySelector('[data-vc-flashcards-options]');
    const confirmButton = root.querySelector('[data-vc-flashcards-confirm]');
    const statCorrectStreak = root.querySelector('[data-vc-flashcards-stat="correct-streak"]');
    const statStudyStreak = root.querySelector('[data-vc-flashcards-stat="study-streak"]');
    const statCoverage = root.querySelector('[data-vc-flashcards-stat="coverage"]');

    let currentCategory = null;
    let pendingConfig = null;
    let lastConfig = null;
    let sessionId = 0;
    let cards = [];
    let cardIndex = 0;
    let attempts = [];
    let answerStartedAt = 0;
    let answeredCurrentCard = false;
    let currentExplanationHtml = '';

    function parseCategories(serialized) {
      if (!serialized) {
        return [];
      }

      try {
        const parsed = JSON.parse(serialized);
        return Array.isArray(parsed) ? parsed : [];
      } catch (error) {
        return [];
      }
    }

    function setFeedback(message, type) {
      if (!feedback) {
        return;
      }

      if (!message) {
        feedback.hidden = true;
        feedback.textContent = '';
        feedback.dataset.state = '';
        return;
      }

      feedback.hidden = false;
      feedback.textContent = message;
      feedback.dataset.state = type || 'info';
    }

    function showView(viewName) {
      root.dataset.activeView = viewName;
      homeView.hidden = viewName !== 'home';
      detailView.hidden = viewName !== 'detail';
      sessionPanel.hidden = viewName !== 'session';
      summaryPanel.hidden = viewName !== 'summary';
    }

    function openHome() {
      currentCategory = null;
      showView('home');
      setFeedback('', '');
    }

    function getCategoryById(categoryId) {
      return categories.find(function (item) {
        return Number(item.id) === Number(categoryId);
      }) || null;
    }

    function openCategory(categoryId) {
      currentCategory = getCategoryById(categoryId);

      if (!currentCategory) {
        return;
      }

      categoryTitle.textContent = currentCategory.name;
      categoryMeta.textContent = currentCategory.description;
      renderSubtopics(currentCategory.children || []);
      showView('detail');
      setFeedback('', '');
    }

    function renderSubtopics(subtopics) {
      subtopicsWrap.innerHTML = '';

      if (!Array.isArray(subtopics) || subtopics.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'vc-flashcards-subtopics-empty';
        empty.textContent = labels.noSubtopics || 'No subtopics have been added yet for this category.';
        subtopicsWrap.appendChild(empty);
        return;
      }

      subtopics.forEach(function (subtopic) {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'vc-flashcards-subtopic-item';
        item.innerHTML =
          '<div class="vc-flashcards-subtopic-copy">' +
            '<strong>' + subtopic.name + '</strong>' +
            '<span>' + subtopic.description + '</span>' +
          '</div>' +
          (subtopic.status ? '<span class="vc-flashcards-subtopic-status">' + subtopic.status + '</span>' : '');

        item.addEventListener('click', function () {
          openConfigModal({
            mode: 'subcategory',
            termId: Number(subtopic.id),
            title: subtopic.name,
            description: 'Focus on one specific subtopic.',
            maxCards: Number(subtopic.totalCards || 0),
            kicker: currentCategory ? currentCategory.name + ' / ' + subtopic.name : subtopic.name
          });
        });

        subtopicsWrap.appendChild(item);
      });
    }

    function normalizeCount(value, maxCards) {
      const safeMax = Math.max(1, Math.min(50, Number(maxCards || 1)));
      const safeValue = Math.max(1, Math.min(safeMax, Number(value || safeMax)));
      return safeValue;
    }

    function getOptionValues(maxCards) {
      const safeMax = Math.max(1, Math.min(50, Number(maxCards || 1)));
      const filtered = cardOptions
        .map(function (value) { return Number(value); })
        .filter(function (value) { return value <= safeMax; });

      if (filtered.length === 0 || filtered[filtered.length - 1] !== safeMax) {
        filtered.push(safeMax);
      }

      if (filtered[0] !== 1 && safeMax < filtered[0]) {
        filtered.unshift(safeMax);
      }

      return filtered.filter(function (value, index, values) {
        return values.indexOf(value) === index;
      });
    }

    function updateModalSelection(value) {
      if (!pendingConfig) {
        return;
      }

      pendingConfig.selectedCount = normalizeCount(value, pendingConfig.maxCards);
      rangeInput.value = String(pendingConfig.selectedCount);
      countDisplay.textContent = String(pendingConfig.selectedCount);

      optionsWrap.querySelectorAll('button').forEach(function (button) {
        button.classList.toggle('is-active', Number(button.dataset.count) === pendingConfig.selectedCount);
      });
    }

    function renderModalOptions(maxCards) {
      const values = getOptionValues(maxCards);
      optionsWrap.innerHTML = '';

      values.forEach(function (value) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'vc-flashcards-count-option';
        button.dataset.count = String(value);
        button.textContent = String(value);
        button.addEventListener('click', function () {
          updateModalSelection(value);
        });
        optionsWrap.appendChild(button);
      });
    }

    function openConfigModal(config) {
      const maxCards = Number(config.maxCards || 0);
      if (maxCards < 1) {
        setFeedback(labels.noCards || 'No flashcards were found for this selection.', 'error');
        return;
      }

      pendingConfig = {
        mode: config.mode,
        termId: Number(config.termId || 0),
        title: config.title,
        description: config.description,
        maxCards: maxCards,
        kicker: config.kicker || config.title,
        selectedCount: normalizeCount(Math.min(20, maxCards), maxCards)
      };

      modalTitle.textContent = config.title;
      modalCopy.textContent = config.description;
      rangeInput.min = '1';
      rangeInput.max = String(Math.max(1, Math.min(50, maxCards)));
      rangeLabel.textContent = '1 - ' + String(Math.max(1, Math.min(50, maxCards)));

      renderModalOptions(maxCards);
      updateModalSelection(pendingConfig.selectedCount);

      modal.hidden = false;
      document.body.classList.add('vc-flashcards-modal-open');
    }

    function closeConfigModal() {
      modal.hidden = true;
      document.body.classList.remove('vc-flashcards-modal-open');
    }

    function resetSessionUi() {
      answersWrap.innerHTML = '';
      explanationEl.hidden = true;
      explanationEl.innerHTML = '';
      currentExplanationHtml = '';
      if (revealButton) {
        revealButton.hidden = false;
      }
      if (explanationToggle) {
        explanationToggle.hidden = true;
        explanationToggle.textContent = labels.viewExplanation || 'View detailed explanation';
      }
      nextButton.hidden = true;
      nextButton.disabled = true;
      setFeedback('', '');
    }

    function buildAnswerButton(key, text) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'vc-flashcards-answer';
      button.dataset.answerKey = key;
      button.innerHTML = '<span>' + key.toUpperCase() + '</span><strong>' + text + '</strong>';
      button.addEventListener('click', function () {
        handleAnswer(button);
      });
      return button;
    }

    function renderExplanation(card, isCorrect) {
      const refs = Array.isArray(card.references) && card.references.length
        ? '<ul>' + card.references.map(function (ref) { return '<li>' + ref + '</li>'; }).join('') + '</ul>'
        : '';

      currentExplanationHtml =
        '<div class="vc-flashcards-explanation-state" data-state="' + (isCorrect ? 'correct' : 'incorrect') + '">' +
          (isCorrect ? labels.correct : labels.incorrect) +
        '</div>' +
        '<div class="vc-flashcards-explanation-body">' +
          card.explanation +
          refs +
        '</div>';

      explanationEl.hidden = true;
      explanationEl.innerHTML = currentExplanationHtml;

      if (explanationToggle) {
        explanationToggle.hidden = false;
      }
    }

    function renderCard() {
      const card = cards[cardIndex];

      if (!card) {
        finishSession();
        return;
      }

      answeredCurrentCard = false;
      answerStartedAt = Date.now();
      resetSessionUi();
      showView('session');

      progressTitle.textContent = 'Card ' + String(cardIndex + 1);
      progressCount.textContent = String(cardIndex + 1) + ' / ' + String(cards.length);
      questionEl.textContent = card.question;
      kicker.textContent = card.subtopicLabel ? card.topicLabel + ' / ' + card.subtopicLabel : card.topicLabel;

      Object.keys(card.answers).forEach(function (key) {
        answersWrap.appendChild(buildAnswerButton(key, card.answers[key]));
      });
    }

    function handleAnswer(button) {
      if (answeredCurrentCard) {
        return;
      }

      const card = cards[cardIndex];
      const selectedAnswer = button.dataset.answerKey;
      const isCorrect = selectedAnswer === card.correctAnswer;
      const responseTimeMs = Date.now() - answerStartedAt;

      answeredCurrentCard = true;
      if (revealButton) {
        revealButton.hidden = true;
      }
      nextButton.hidden = false;
      nextButton.disabled = false;

      answersWrap.querySelectorAll('.vc-flashcards-answer').forEach(function (answerButton) {
        answerButton.disabled = true;
        if (answerButton.dataset.answerKey === card.correctAnswer) {
          answerButton.dataset.state = 'correct';
        } else if (answerButton.dataset.answerKey === selectedAnswer) {
          answerButton.dataset.state = 'incorrect';
        }
      });

      attempts.push({
        flashcardId: card.id,
        topicTermId: card.topicTermId,
        selectedAnswer: selectedAnswer,
        correctAnswer: card.correctAnswer,
        responseTimeMs: responseTimeMs
      });

      renderExplanation(card, isCorrect);
    }

    function revealAnswer() {
      if (answeredCurrentCard) {
        return;
      }

      const card = cards[cardIndex];
      const responseTimeMs = Date.now() - answerStartedAt;

      answeredCurrentCard = true;
      if (revealButton) {
        revealButton.hidden = true;
      }
      if (explanationToggle) {
        explanationToggle.hidden = true;
      }
      nextButton.hidden = false;
      nextButton.disabled = false;

      answersWrap.querySelectorAll('.vc-flashcards-answer').forEach(function (answerButton) {
        answerButton.disabled = true;
        if (answerButton.dataset.answerKey === card.correctAnswer) {
          answerButton.dataset.state = 'correct';
        }
      });

      attempts.push({
        flashcardId: card.id,
        topicTermId: card.topicTermId,
        selectedAnswer: '',
        correctAnswer: card.correctAnswer,
        responseTimeMs: responseTimeMs
      });

      renderExplanation(card, false);
      explanationEl.hidden = false;
    }

    function updateStats(stats) {
      if (!stats) {
        return;
      }

      if (statCorrectStreak) {
        statCorrectStreak.textContent = String(stats.correctStreak);
      }
      if (statStudyStreak) {
        statStudyStreak.textContent = String(stats.studyStreak) + 'd';
      }
      if (statCoverage) {
        statCoverage.textContent = String(stats.reviewedCoverage) + '%';
      }
    }

    function finishSession() {
      const body = new window.URLSearchParams();
      body.append('action', 'vc_flashcards_complete_session');
      body.append('nonce', window.vcFlashcardsData.nonce);
      body.append('session_id', String(sessionId));
      body.append('attempts', JSON.stringify(attempts));

      fetch(window.vcFlashcardsData.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload.success) {
            throw new Error(payload.data && payload.data.message ? payload.data.message : labels.noCards);
          }

          const data = payload.data;
          updateStats(data.stats);
          showView('summary');
          summaryScore.textContent = String(Math.round(data.scorePercent)) + '%';
          summaryCopy.textContent = data.correctAnswers + ' / ' + data.totalCards + ' correct answers';
        })
        .catch(function (error) {
          setFeedback(error.message, 'error');
          if (currentCategory) {
            showView('detail');
          } else {
            showView('home');
          }
        });
    }

    function startSession(config) {
      if (!config) {
        return;
      }

      closeConfigModal();
      resetSessionUi();
      setFeedback(labels.loading, 'info');

      const body = new window.URLSearchParams();
      body.append('action', 'vc_flashcards_start_session');
      body.append('nonce', window.vcFlashcardsData.nonce);
      body.append('mode', config.mode);
      body.append('term_id', String(config.termId || 0));
      body.append('card_limit', String(config.selectedCount || 10));

      fetch(window.vcFlashcardsData.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      })
        .then(function (response) { return response.json(); })
        .then(function (payload) {
          if (!payload.success) {
            throw new Error(payload.data && payload.data.message ? payload.data.message : labels.noCards);
          }

          sessionId = payload.data.sessionId;
          cards = payload.data.cards || [];
          cardIndex = 0;
          attempts = [];
          lastConfig = Object.assign({}, config);
          setFeedback('', '');
          renderCard();
        })
        .catch(function (error) {
          setFeedback(error.message, 'error');
          if (currentCategory) {
            showView('detail');
          } else {
            showView('home');
          }
        });
    }

    root.querySelectorAll('[data-vc-flashcards-open-category]').forEach(function (button) {
      button.addEventListener('click', function () {
        openCategory(Number(button.dataset.vcFlashcardsOpenCategory));
      });
    });

    root.querySelectorAll('[data-vc-flashcards-launch]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!currentCategory) {
          return;
        }

        const mode = button.dataset.vcFlashcardsLaunch;
        const isRandom = mode === 'random';

        openConfigModal({
          mode: isRandom ? 'random' : 'category',
          termId: Number(currentCategory.id),
          title: isRandom ? 'Random practice' : 'Study full category',
          description: isRandom
            ? 'Choose how many cards you want to mix from this category.'
            : 'Choose how many cards you want to study in sequential order.',
          maxCards: Number(currentCategory.totalCards || 0),
          kicker: currentCategory.name
        });
      });
    });

    root.querySelectorAll('[data-vc-flashcards-back]').forEach(function (button) {
      button.addEventListener('click', openHome);
    });

    root.querySelectorAll('[data-vc-flashcards-close]').forEach(function (button) {
      button.addEventListener('click', closeConfigModal);
    });

    rangeInput.addEventListener('input', function () {
      updateModalSelection(Number(rangeInput.value));
    });

    confirmButton.addEventListener('click', function () {
      if (!pendingConfig) {
        return;
      }

      startSession(pendingConfig);
    });

    nextButton.addEventListener('click', function () {
      cardIndex += 1;
      renderCard();
    });

    if (revealButton) {
      revealButton.addEventListener('click', revealAnswer);
    }

    restartButton.addEventListener('click', function () {
      if (lastConfig) {
        startSession(lastConfig);
      }
    });

    if (explanationToggle) {
      explanationToggle.addEventListener('click', function () {
        if (!currentExplanationHtml) {
          return;
        }

        explanationEl.hidden = !explanationEl.hidden;
        explanationToggle.textContent = explanationEl.hidden
          ? (labels.viewExplanation || 'View detailed explanation')
          : (labels.hideExplanation || 'Hide detailed explanation');
      });
    }

    summaryBackButton.addEventListener('click', function () {
      if (currentCategory) {
        showView('detail');
        return;
      }

      openHome();
    });

    openHome();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vc-flashcards-app').forEach(initFlashcardsApp);
  });
}());
