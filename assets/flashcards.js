(function () {
  
  /*
   * Flashcards practice controller.
   * Manages category selection, configurable sessions, answer review, summaries
   * and lightweight session restoration for the flashcards shortcode.
   */
  function initFlashcardsApp(root) {
    // Datos iniciales entregados por PHP y valores por defecto del modulo.
    const categories = parseCategories(root.dataset.categories);
    const labels = (window.vcFlashcardsData && window.vcFlashcardsData.labels) || {};
    const cardOptions = (window.vcFlashcardsData && window.vcFlashcardsData.cardOptions) || [10, 20, 30, 40, 50];

    // Referencias DOM principales: vistas, controles, modal y metricas del home.
    const feedback = root.querySelector('[data-vc-flashcards-feedback]');
    const homeView = root.querySelector('[data-vc-flashcards-view="home"]');
    const detailView = root.querySelector('[data-vc-flashcards-view="detail"]');
    const sessionPanel = root.querySelector('[data-vc-flashcards-session]');
    const summaryPanel = root.querySelector('[data-vc-flashcards-summary]');
    const modal = root.querySelector('[data-vc-flashcards-modal]');
    const categoryTitle = root.querySelector('[data-vc-flashcards-category-title]');
    const categoryMeta = root.querySelector('[data-vc-flashcards-category-meta]');
    const categoryTotal = root.querySelector('[data-vc-flashcards-category-total]');
    const subtopicsWrap = root.querySelector('[data-vc-flashcards-subtopics]');
    const nextButton = root.querySelector('[data-vc-flashcards-next]');
    const revealButton = root.querySelector('[data-vc-flashcards-reveal]');
    const explanationToggle = root.querySelector('[data-vc-flashcards-explanation-toggle]');
    const explanationToggleLabel = root.querySelector('.vc-flashcards-explanation-toggle-label');
    const restartButton = root.querySelector('[data-vc-flashcards-restart]');
    const summaryBackButton = root.querySelector('[data-vc-flashcards-summary-back]');
    const answersWrap = root.querySelector('[data-vc-flashcards-answers]');
    const questionEl = root.querySelector('[data-vc-flashcards-question]');
    const referenceImageButton = root.querySelector('[data-vc-flashcards-reference-image]');
    const referenceImageInline = root.querySelector('[data-vc-flashcards-reference-image-inline]');
    const referenceImageInlineWrap = referenceImageInline ? referenceImageInline.closest('.vc-flashcards-reference-image-preview') : null;
    const explanationEl = root.querySelector('[data-vc-flashcards-explanation]');
    const progressCount = root.querySelector('[data-vc-flashcards-progress-count]');
    const sessionBarFill = root.querySelector('[data-vc-flashcards-session-bar-fill]');
    const kicker = root.querySelector('[data-vc-flashcards-kicker]');
    const nextButtonLabel = root.querySelector('.vc-flashcards-next-label');
    const summaryPrecision = root.querySelector('[data-vc-flashcards-summary-precision]');
    const summaryScore = root.querySelector('[data-vc-flashcards-summary-score]');

    const summaryCorrectCount = root.querySelector('[data-vc-flashcards-correct-count]');
    const summaryIncorrectCount = root.querySelector('[data-vc-flashcards-incorrect-count]');
    const modalTitle = root.querySelector('[data-vc-flashcards-modal-title]');
    const modalCopy = root.querySelector('[data-vc-flashcards-modal-copy]');
    const countDisplay = root.querySelector('[data-vc-flashcards-count-display]');
    const rangeLabel = root.querySelector('[data-vc-flashcards-range-label]');
    const rangeInput = root.querySelector('[data-vc-flashcards-range]');
    const optionsWrap = root.querySelector('[data-vc-flashcards-options]');
    const confirmButton = root.querySelector('[data-vc-flashcards-confirm]');
    // Dashboard stat nodes only. Category/subtopic card metrics are rendered from category payloads.
    const statHeaderCardsMastered = root.querySelector('[data-vc-flashcards-stat="cards-mastered"]');
    const statTotalReviewed = root.querySelector('[data-vc-flashcards-stat="total-reviewed"]');
    const statTopicsCompleted = root.querySelector('[data-vc-flashcards-stat="topics-completed"]');

    // Estado vivo de la app: categoria activa, sesion, respuestas y modal.
    let currentCategory = null;
    let pendingConfig = null;
    let lastConfig = null;
    let sessionId = 0;
    let cards = [];
    let cardIndex = 0;
    let attempts = [];
    let summaryMetrics = null;
    let isStartingSession = false;
    let answerStartedAt = 0;
    let answeredCurrentCard = false;
    let currentExplanationHtml = '';
    let activeConfigCard = null;
    const storageKey = 'vcFlashcardsState:' + window.location.pathname;

    // Persistencia: limpia el estado cuando el usuario sale de flashcards.
    function clearPersistedState() {
      try {
        window.sessionStorage.removeItem(storageKey);
      } catch (error) {}
    }

    // Persistencia: borra el estado si el dashboard navega a otra seccion.
    function bindDashboardExitReset() {
      document.querySelectorAll('.vc-dashboard-nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
          try {
            const nextUrl = new URL(link.href, window.location.origin);
            const nextView = nextUrl.searchParams.get('view') || 'flashcards';

            if (nextView !== 'flashcards') {
              clearPersistedState();
            }
          } catch (error) {}
        });
      });
    }

    // Datos: normaliza el JSON de categorias para evitar errores con payloads vacios.
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

    // Persistencia: guarda lo minimo para restaurar una practica al recargar.
    function persistState() {
      try {
        window.sessionStorage.setItem(storageKey, JSON.stringify({
          activeView: root.dataset.activeView || 'home',
          summaryOpen: summaryPanel ? !summaryPanel.hidden : false,
          summaryMetrics: summaryMetrics,
          currentCategoryId: currentCategory ? Number(currentCategory.id) : 0,
          pendingConfig: pendingConfig,
          lastConfig: lastConfig,
          sessionId: sessionId,
          cards: cards,
          cardIndex: cardIndex,
          attempts: attempts,
          answeredCurrentCard: answeredCurrentCard,
          currentExplanationHtml: currentExplanationHtml,
          explanationVisible: explanationEl ? !explanationEl.hidden : false
        }));
      } catch (error) {}
    }

    // Summary: controla la visibilidad del panel final sin perder el estado.
    function setSummaryOpen(isOpen) {
      if (!summaryPanel) {
        return;
      }

      summaryPanel.hidden = !isOpen;
      persistState();
    }

    // Summary: pinta los resultados finales devueltos por el backend.
    function renderSummaryMetrics(metrics) {
      if (!metrics) {
        return;
      }

      if (summaryCorrectCount) {
        summaryCorrectCount.textContent = String(metrics.correctAnswers);
      }
      if (summaryIncorrectCount) {
        summaryIncorrectCount.textContent = String(metrics.incorrectAnswers);
      }
      if (summaryPrecision) {
        summaryPrecision.textContent = String(Math.round(metrics.precisionPercent)) + '%';
      }
      if (summaryScore) {
        summaryScore.textContent = String(Math.round(metrics.scorePercent)) + '%';
      }
    }

    // Detail: actualiza el header con la categoria seleccionada.
    function renderCategoryHeader(category) {
      if (!category) {
        return;
      }

      categoryTitle.textContent = category.name;

      if (categoryMeta) {
        categoryMeta.textContent = '';
        categoryMeta.hidden = true;
      }

      if (categoryTotal) {
        categoryTotal.textContent = String(Number(category.totalCards || 0)) + ' total flashcards';
      }
    }

    // Persistencia: reconstruye detail, session o summary desde sessionStorage.
    function restoreState() {
      try {
        const rawState = window.sessionStorage.getItem(storageKey);

        if (!rawState) {
          return false;
        }

        const state = JSON.parse(rawState);

        if (!state || !state.activeView) {
          return false;
        }

        currentCategory = state.currentCategoryId ? getCategoryById(state.currentCategoryId) : null;
        pendingConfig = state.pendingConfig || null;
        lastConfig = state.lastConfig || null;
        summaryMetrics = state.summaryMetrics || null;
        sessionId = Number(state.sessionId || 0);
        cards = Array.isArray(state.cards) ? state.cards : [];
        cardIndex = Math.max(0, Math.min(Number(state.cardIndex || 0), Math.max(cards.length - 1, 0)));
        attempts = Array.isArray(state.attempts) ? state.attempts : [];
        currentExplanationHtml = state.currentExplanationHtml || '';

        if (currentCategory) {
          renderCategoryHeader(currentCategory);
          renderSubtopics(currentCategory.children || []);
        }

        if (state.activeView === 'session' && currentCategory && cards.length) {
          renderCard();
          restoreAnsweredCardUi(Boolean(state.explanationVisible));
          renderSummaryMetrics(summaryMetrics);
          setSummaryOpen(Boolean(state.summaryOpen));

          return true;
        }

        if (state.activeView === 'detail' && currentCategory) {
          showView('detail');
          setFeedback('', '');
          return true;
        }

        if (currentCategory) {
          showView('detail');
          setFeedback('', '');
          return true;
        }
      } catch (error) {}

      return false;
    }

    // Session: construye chips de topic y subtopic para la tarjeta actual.
    function renderSessionKicker(topicLabel, subtopicLabel) {
      if (!kicker) {
        return;
      }

      kicker.textContent = '';

      if (topicLabel) {
        const topicChip = document.createElement('span');
        topicChip.className = 'vc-flashcards-session-kicker-part vc-flashcards-session-kicker-part--topic';
        topicChip.textContent = topicLabel;
        kicker.appendChild(topicChip);
      }

      if (subtopicLabel) {
        const subtopicChip = document.createElement('span');
        subtopicChip.className = 'vc-flashcards-session-kicker-part vc-flashcards-session-kicker-part--subtopic';
        subtopicChip.textContent = subtopicLabel;
        kicker.appendChild(subtopicChip);
      }
    }

    // Feedback: muestra errores o mensajes informativos de la app.
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

    // Navegacion: alterna home, detail, session y summary.
    function showView(viewName) {
      root.dataset.activeView = viewName;
      homeView.hidden = viewName !== 'home';
      detailView.hidden = viewName !== 'detail';
      sessionPanel.hidden = viewName !== 'session';
      summaryPanel.hidden = viewName !== 'summary';
      persistState();
    }

    // Navegacion: vuelve al home y limpia la categoria activa.
    function openHome() {
      currentCategory = null;
      showView('home');
      setFeedback('', '');
      persistState();
    }

    // Datos: busca una categoria por id normalizando tipos numericos.
    function getCategoryById(categoryId) {
      return categories.find(function (item) {
        return Number(item.id) === Number(categoryId);
      }) || null;
    }

    // Detail: abre una categoria y renderiza sus subtopics.
    function openCategory(categoryId) {
      currentCategory = getCategoryById(categoryId);

      if (!currentCategory) {
        return;
      }

      renderCategoryHeader(currentCategory);
      renderSubtopics(currentCategory.children || []);
      showView('detail');
      setFeedback('', '');
      persistState();
    }

    // Detail: renderiza subtopics como botones que abren el modal.
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
        const copy = document.createElement('div');
        const heading = document.createElement('div');
        const title = document.createElement('strong');
        const description = document.createElement('span');
        const chevron = document.createElement('span');
        const chevronSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        const chevronPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');

        item.type = 'button';
        item.className = 'vc-flashcards-subtopic-item';

        copy.className = 'vc-flashcards-subtopic-copy';
        heading.className = 'vc-flashcards-subtopic-heading';
        chevron.className = 'vc-flashcards-subtopic-chevron';
        chevron.setAttribute('aria-hidden', 'true');
        chevronSvg.setAttribute('class', 'icono-flecha');
        chevronSvg.setAttribute('viewBox', '0 0 11 20');
        chevronSvg.setAttribute('fill', 'none');
        chevronSvg.setAttribute('focusable', 'false');
        chevronPath.setAttribute('d', 'M1 19L10 10L1 1');
        chevronPath.setAttribute('stroke', 'currentColor');
        chevronPath.setAttribute('stroke-width', '2.5');
        chevronPath.setAttribute('stroke-linecap', 'round');
        chevronPath.setAttribute('stroke-linejoin', 'round');
        title.textContent = subtopic.name || '';
        description.textContent = subtopic.description || '';

        chevronSvg.appendChild(chevronPath);
        chevron.appendChild(chevronSvg);
        heading.appendChild(title);
        copy.appendChild(heading);
        copy.appendChild(description);
        item.appendChild(copy);
        item.appendChild(chevron);

        item.addEventListener('click', function () {
          openConfigModal({
            mode: 'subcategory',
            termId: Number(subtopic.id),
            title: 'Card study: ' + subtopic.name,
            description: 'Focus on one specific subtopic.',
            maxCards: Number(subtopic.totalCards || 0),
            kicker: currentCategory ? currentCategory.name + ' / ' + subtopic.name : subtopic.name
          });
        });

        subtopicsWrap.appendChild(item);
      });
    }

    // Modal: limita la cantidad seleccionada al rango permitido.
    function normalizeCount(value, maxCards) {
      const safeMax = Math.max(1, Math.min(50, Number(maxCards || 1)));
      const safeValue = Math.max(1, Math.min(safeMax, Number(value || safeMax)));
      return safeValue;
    }

    // Modal: calcula las opciones rapidas de cantidad segun el maximo disponible.
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

    // Modal: sincroniza slider, contador y botones rapidos.
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

      updateRangeFill();
    }

    // Modal: actualiza el relleno visual del range con una variable CSS.
    function updateRangeFill() {
      const min = Number(rangeInput.min || 0);
      const max = Number(rangeInput.max || 100);
      const value = Number(rangeInput.value || min);
      const percent = max > min ? ((value - min) / (max - min)) * 100 : 0;

      rangeInput.style.setProperty('--vc-range-progress', percent + '%');
    }

    // Modal: dibuja los botones de cantidades predefinidas.
    function renderModalOptions(maxCards) {
      const values = getOptionValues(maxCards);
      optionsWrap.innerHTML = '';

      values.forEach(function (value) {
        const button = document.createElement('button');
        const label = document.createElement('span');
        button.type = 'button';
        button.className = 'vc-flashcards-count-option';
        button.dataset.count = String(value);
        label.className = 'vc-flashcards-count-option-label';
        label.textContent = String(value);
        button.appendChild(label);
        button.addEventListener('click', function () {
          updateModalSelection(value);
        });
        optionsWrap.appendChild(button);
      });
    }

    // Detail: marca la tarjeta que disparo el modal de configuracion.
    function setActiveConfigCard(card) {
      if (activeConfigCard && activeConfigCard !== card) {
        activeConfigCard.classList.remove('is-active');
      }

      activeConfigCard = card || null;

      if (activeConfigCard) {
        activeConfigCard.classList.add('is-active');
      }
    }

    // Modal: prepara titulo, descripcion, rango y cantidad antes de mostrarlo.
    function openConfigModal(config) {
      const maxCards = Number(config.maxCards || 0);
      if (maxCards < 1) {
        setActiveConfigCard(null);
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
      persistState();
    }

    // Modal: cierra el dialogo y limpia el estado visual de la tarjeta activa.
    function closeConfigModal() {
      modal.hidden = true;
      document.body.classList.remove('vc-flashcards-modal-open');
      setActiveConfigCard(null);
      persistState();
    }

    // Session: expande o contrae la imagen dentro de la tarjeta actual.
    function toggleReferenceImageInline() {
      const card = cards[cardIndex];
      const fallbackUrl = referenceImageButton ? String(referenceImageButton.dataset.vcFlashcardsReferenceImageFallback || '') : '';
      const imageUrl = card && card.questionImageUrl ? String(card.questionImageUrl) : fallbackUrl;

      if (!imageUrl || !referenceImageButton || !referenceImageInline || !referenceImageInlineWrap) {
        return;
      }

      const isExpanded = referenceImageButton.classList.toggle('is-expanded');
      referenceImageButton.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

      if (isExpanded) {
        referenceImageInline.src = imageUrl;
        referenceImageInlineWrap.hidden = false;
      } else {
        referenceImageInlineWrap.hidden = true;
        referenceImageInline.removeAttribute('src');
      }
    }

    // Modal: bloquea el CTA mientras se crea la sesion por AJAX.
    function setSessionStartLoading(isLoading) {
      isStartingSession = isLoading;

      if (confirmButton) {
        confirmButton.disabled = isLoading;
        confirmButton.textContent = isLoading
          ? (labels.starting || 'Starting...')
          : (labels.start || 'Start');
      }
    }

    // Session: actualiza el texto del toggle de explicacion.
    function setExplanationToggleLabel(label) {
      if (!explanationToggleLabel) {
        return;
      }

      explanationToggleLabel.textContent = label;
    }

    // Session: sincroniza el estado visual y ARIA del toggle de explicacion.
    function setExplanationToggleOpen(isOpen) {
      if (!explanationToggle) {
        return;
      }

      explanationToggle.classList.toggle('is-open', isOpen);
      explanationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    // Session: limpia pregunta, respuestas y explicacion antes de pintar una nueva tarjeta.
    function resetSessionUi() {
      answersWrap.innerHTML = '';
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
      explanationEl.hidden = true;
      explanationEl.innerHTML = '';
      currentExplanationHtml = '';
      if (revealButton) {
        revealButton.hidden = false;
      }
      if (explanationToggle) {
        explanationToggle.hidden = true;
        setExplanationToggleOpen(false);
        setExplanationToggleLabel(labels.viewExplanation || 'View detailed explanation');
      }
      nextButton.hidden = true;
      nextButton.disabled = true;
      setFeedback('', '');
    }

    // Session: crea cada respuesta como boton para controlar estados y teclado.
    function buildAnswerButton(key, text) {
      const button = document.createElement('button');
      const badge = document.createElement('span');
      const label = document.createElement('strong');

      button.type = 'button';
      button.className = 'vc-flashcards-answer';
      button.dataset.answerKey = key;
      badge.textContent = key.toUpperCase();
      label.textContent = text;
      button.appendChild(badge);
      button.appendChild(label);
      button.addEventListener('click', function () {
        handleAnswer(button);
      });
      return button;
    }

    // Seguridad: escapa textos dinamicos antes de insertarlos en HTML controlado.
    function escapeHtml(value) {
      return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    // Session: prepara explicacion y referencias sin abrirlas automaticamente.
    function renderExplanation(card, isCorrect) {
      const refs = Array.isArray(card.references) && card.references.length
        ? '<ul>' + card.references.map(function (ref) { return '<li>' + escapeHtml(ref) + '</li>'; }).join('') + '</ul>'
        : '';

      currentExplanationHtml =
        '<div class="vc-flashcards-explanation-body">' +
          card.explanation +
          refs +
        '</div>';

      explanationEl.hidden = true;
      explanationEl.innerHTML = currentExplanationHtml;

      if (explanationToggle) {
        explanationToggle.hidden = false;
        setExplanationToggleOpen(false);
      }
    }

    // Session: busca el ultimo intento guardado para una tarjeta.
    function findAttemptForCard(card) {
      if (!card) {
        return null;
      }

      for (let index = attempts.length - 1; index >= 0; index -= 1) {
        if (Number(attempts[index].flashcardId) === Number(card.id)) {
          return attempts[index];
        }
      }

      return null;
    }

    // Session: restaura una pregunta ya respondida despues de recargar o volver.
    function restoreAnsweredCardUi(explanationVisible) {
      const card = cards[cardIndex];
      const attempt = findAttemptForCard(card);

      if (!card || !attempt) {
        return;
      }

      answeredCurrentCard = true;

      if (revealButton) {
        revealButton.hidden = true;
      }

      nextButton.hidden = false;
      nextButton.disabled = false;

      answersWrap.querySelectorAll('.vc-flashcards-answer').forEach(function (answerButton) {
        answerButton.disabled = true;

        if (answerButton.dataset.answerKey === attempt.correctAnswer) {
          answerButton.dataset.state = 'correct';
        } else if (attempt.selectedAnswer && answerButton.dataset.answerKey === attempt.selectedAnswer) {
          answerButton.dataset.state = 'incorrect';
        }
      });

      renderExplanation(card, attempt.selectedAnswer === attempt.correctAnswer);
      explanationEl.hidden = !explanationVisible;
      setExplanationToggleOpen(!explanationEl.hidden);
      setExplanationToggleLabel(
        explanationEl.hidden
          ? (labels.viewExplanation || 'View detailed explanation')
          : (labels.hideExplanation || 'Hide detailed explanation')
      );
    }

    // Session: pinta la tarjeta actual y termina cuando no quedan preguntas.
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

      progressCount.textContent = 'Question ' + String(cardIndex + 1) + ' de ' + String(cards.length);
      if (sessionBarFill) {
        sessionBarFill.style.width = String(Math.round(((cardIndex + 1) / cards.length) * 100)) + '%';
      }
      if (nextButtonLabel) {
        nextButtonLabel.textContent = cardIndex === cards.length - 1 ? 'Finish' : 'Next question';
      }
      questionEl.textContent = card.question;
      if (referenceImageButton) {
        // Solo mostrar el botón si la tarjeta tiene una imagen real (no usar fallback para la decisión de visibilidad)
        referenceImageButton.hidden = !card.questionImageUrl;
      }
      renderSessionKicker(card.topicLabel, card.subtopicLabel);

      Object.keys(card.answers).forEach(function (key) {
        answersWrap.appendChild(buildAnswerButton(key, card.answers[key]));
      });

      persistState();
    }

    // Session: registra la opcion elegida y marca correcto o incorrecto.
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
      persistState();
    }

    // Session: revela la respuesta sin seleccion y guarda el intento.
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
        setExplanationToggleOpen(false);
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
      setExplanationToggleOpen(true);
      persistState();
    }

    // Home: refresca metricas despues de completar una sesion.
    function updateStats(stats) {
      if (!stats) {
        return;
      }

      if (statHeaderCardsMastered) {
        statHeaderCardsMastered.textContent = String(stats.latestSessionScorePercent || 0) + '%';
      }
      if (statTotalReviewed) {
        statTotalReviewed.textContent = String(stats.totalReviewed || '0/0');
      }
      if (statTopicsCompleted) {
        statTopicsCompleted.textContent = String(stats.topicsCompleted || '0/0');
      }
    }

    // AJAX: envia intentos al backend y abre el summary final.
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
          summaryMetrics = {
            correctAnswers: Number(data.correctAnswers || 0),
            incorrectAnswers: Number(data.incorrectAnswers || 0),
            precisionPercent: Number(data.precisionPercent || 0),
            scorePercent: Number(data.scorePercent || 0)
          };
          updateStats(data.stats);
          setSummaryOpen(true);
          renderSummaryMetrics(summaryMetrics);
          persistState();
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

    // AJAX: crea una sesion con la configuracion elegida en el modal.
    function startSession(config) {
      if (!config || isStartingSession) {
        return;
      }

      setSessionStartLoading(true);
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

          closeConfigModal();
          resetSessionUi();
          sessionId = payload.data.sessionId;
          cards = payload.data.cards || [];
          cardIndex = 0;
          attempts = [];
          summaryMetrics = null;
          lastConfig = Object.assign({}, config);
          setFeedback('', '');
          renderCard();
        })
        .catch(function (error) {
          closeConfigModal();
          setFeedback(error.message, 'error');
          if (currentCategory) {
            showView('detail');
          } else {
            showView('home');
          }
        })
        .finally(function () {
          setSessionStartLoading(false);
        });
    }

    // Eventos: navegacion hacia categorias desde el home.
    root.querySelectorAll('[data-vc-flashcards-open-category]').forEach(function (button) {
      button.addEventListener('click', function () {
        openCategory(Number(button.dataset.vcFlashcardsOpenCategory));
      });
    });

    // Eventos: apertura de modal para categoria, random y global random.
    root.querySelectorAll('[data-vc-flashcards-launch]').forEach(function (button) {
      button.addEventListener('click', function () {
        const mode = button.dataset.vcFlashcardsLaunch;
        const isRandom = mode === 'random';
        const isGlobalRandom = mode === 'global-random';
        const launchCard = button.closest('[data-vc-flashcards-launch-card]');

        if (!isGlobalRandom && !currentCategory) {
          return;
        }

        setActiveConfigCard(launchCard);

        openConfigModal({
          mode: isGlobalRandom ? 'global-random' : (isRandom ? 'random' : 'category'),
          termId: isGlobalRandom ? 0 : Number(currentCategory.id),
          title: isGlobalRandom ? 'Global Random Practice' : (isRandom ? 'Study in random mode' : 'Study the full category'),
          description: isGlobalRandom
            ? 'Mix cards from all categories for a comprehensive review.'
            : (isRandom
              ? 'Cards will be shuffled randomly within this category'
              : 'Select how many cards you want to study in sequential order'),
          maxCards: isGlobalRandom ? Number(categories.reduce(function (sum, item) { return sum + Number(item.totalCards || 0); }, 0)) : Number(currentCategory.totalCards || 0),
          kicker: isGlobalRandom ? 'Global Random' : currentCategory.name
        });
      });
    });

    // Eventos: permite activar una tarjeta completa sin duplicar el click del boton interno.
    root.querySelectorAll('[data-vc-flashcards-launch-card]').forEach(function (card) {
      const launchButton = card.querySelector('[data-vc-flashcards-launch]');

      if (!launchButton) {
        return;
      }

      card.addEventListener('click', function (event) {
        if (event.target.closest('[data-vc-flashcards-launch]')) {
          return;
        }

        launchButton.click();
      });

      card.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }

        if (event.target.closest('[data-vc-flashcards-launch]')) {
          return;
        }

        event.preventDefault();
        launchButton.click();
      });
    });

    // Eventos: controles compartidos de regreso, cierre de modal y avance.
    root.querySelectorAll('[data-vc-flashcards-back]').forEach(function (button) {
      button.addEventListener('click', openHome);
    });

    root.querySelectorAll('[data-vc-flashcards-close]').forEach(function (button) {
      button.addEventListener('click', closeConfigModal);
    });

    if (referenceImageButton) {
      referenceImageButton.addEventListener('click', toggleReferenceImageInline);
    }

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
        setExplanationToggleOpen(!explanationEl.hidden);
        setExplanationToggleLabel(
          explanationEl.hidden
            ? (labels.viewExplanation || 'View detailed explanation')
            : (labels.hideExplanation || 'Hide detailed explanation')
        );
        persistState();
      });
    }

    summaryBackButton.addEventListener('click', function () {
      if (currentCategory) {
        showView('detail');
        return;
      }

      openHome();
    });

    // Eventos: atajos de teclado para responder con la letra de la opcion.
    document.addEventListener('keydown', function (event) {
      if (root.dataset.activeView !== 'session') {
        return;
      }

      const key = event.key.toLowerCase();
      const answerButton = answersWrap.querySelector('.vc-flashcards-answer[data-answer-key="' + key + '"]');
      if (answerButton && !answerButton.disabled) {
        event.preventDefault();
        answerButton.click();
      }
    });

    bindDashboardExitReset();

    // Arranque: restaura una sesion previa o inicia en home.
    if (!restoreState()) {
      openHome();
    }
  }

  // Inicializa todas las instancias del shortcode presentes en la pagina.
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.vc-flashcards-app').forEach(initFlashcardsApp);
  });
}());
