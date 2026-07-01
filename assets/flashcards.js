(function () {
  
  /*
   * Study sessions practice controller.
   * Manages category selection, configurable sessions, answer review, summaries
   * and lightweight session restoration for the Study sessions shortcode.
   */
  function initStudySessionsApp(root) {
    // Datos iniciales entregados por PHP y valores por defecto del modulo.
    const categories = parseCategories(root.dataset.categories);
    const labels = (window.vcStudySessionsData && window.vcStudySessionsData.labels) || {};
    const cardOptions = (window.vcStudySessionsData && window.vcStudySessionsData.cardOptions) || [10, 20, 30, 40, 50];

    // Referencias DOM principales: vistas, controles, modal y metricas del home.
    const feedback = root.querySelector('[data-vc-study-sessions-feedback]');
    const homeView = root.querySelector('[data-vc-study-sessions-view="home"]');
    const detailView = root.querySelector('[data-vc-study-sessions-view="detail"]');
    const sessionPanel = root.querySelector('[data-vc-study-sessions-session]');
    const summaryPanel = root.querySelector('[data-vc-study-sessions-summary]');
    const modal = root.querySelector('[data-vc-study-sessions-modal]');
    const categoryTitle = root.querySelector('[data-vc-study-sessions-category-title]');
    const categoryMeta = root.querySelector('[data-vc-study-sessions-category-meta]');
    const categoryTotal = root.querySelector('[data-vc-study-sessions-category-total]');
    const subtopicsWrap = root.querySelector('[data-vc-study-sessions-subtopics]');
    const nextButton = root.querySelector('[data-vc-study-sessions-next]');
    const revealButton = root.querySelector('[data-vc-study-sessions-reveal]');
    const explanationToggle = root.querySelector('[data-vc-study-sessions-explanation-toggle]');
    const explanationToggleLabel = root.querySelector('.vc-study-sessions-explanation-toggle-label');
    const restartButton = root.querySelector('[data-vc-study-sessions-restart]');
    const summaryBackButton = root.querySelector('[data-vc-study-sessions-summary-back]');
    const answersWrap = root.querySelector('[data-vc-study-sessions-answers]');
    const questionEl = root.querySelector('[data-vc-study-sessions-question]');
    const referenceImageButton = root.querySelector('[data-vc-study-sessions-reference-image]');
    const referenceImageInline = root.querySelector('[data-vc-study-sessions-reference-image-inline]');
    const referenceImageInlineWrap = referenceImageInline ? referenceImageInline.closest('.vc-study-sessions-reference-image-preview') : null;
    const referenceImageModal = window.VCReferenceImageModal ? window.VCReferenceImageModal.create({
      root: root,
      trigger: referenceImageButton,
      expandedClass: 'is-expanded',
      modalSelector: '[data-vc-study-sessions-reference-modal]',
      imageSelector: '[data-vc-study-sessions-reference-modal-image]',
      zoomSelector: '[data-vc-study-sessions-reference-modal-zoom]',
      frameSelector: '.vc-study-sessions-reference-modal-frame',
      closeSelector: '[data-vc-study-sessions-reference-modal-close]'
    }) : null;
    const explanationEl = root.querySelector('[data-vc-study-sessions-explanation]');
    const progressCount = root.querySelector('[data-vc-study-sessions-progress-count]');
    const sessionBarFill = root.querySelector('[data-vc-study-sessions-session-bar-fill]');
    const kicker = root.querySelector('[data-vc-study-sessions-kicker]');
    const nextButtonLabel = root.querySelector('.vc-study-sessions-next-label');
    const summaryPrecision = root.querySelector('[data-vc-study-sessions-summary-precision]');
    const summaryScore = root.querySelector('[data-vc-study-sessions-summary-score]');

    const summaryCorrectCount = root.querySelector('[data-vc-study-sessions-correct-count]');
    const summaryIncorrectCount = root.querySelector('[data-vc-study-sessions-incorrect-count]');
    const modalTitle = root.querySelector('[data-vc-study-sessions-modal-title]');
    const modalCopy = root.querySelector('[data-vc-study-sessions-modal-copy]');
    const countDisplay = root.querySelector('[data-vc-study-sessions-count-display]');
    const rangeLabel = root.querySelector('[data-vc-study-sessions-range-label]');
    const rangeInput = root.querySelector('[data-vc-study-sessions-range]');
    const optionsWrap = root.querySelector('[data-vc-study-sessions-options]');
    const confirmButton = root.querySelector('[data-vc-study-sessions-confirm]');
    const acsModal = root.querySelector('[data-vc-study-sessions-acs-modal]');
    const acsModalTitle = root.querySelector('[data-vc-study-sessions-acs-modal-title]');
    const acsModalCopy = root.querySelector('[data-vc-study-sessions-acs-modal-copy]');
    const acsCountDisplay = root.querySelector('[data-vc-study-sessions-acs-count-display]');
    const acsRangeLabel = root.querySelector('[data-vc-study-sessions-acs-range-label]');
    const acsRangeInput = root.querySelector('[data-vc-study-sessions-acs-range]');
    const acsOptionsWrap = root.querySelector('[data-vc-study-sessions-acs-options]');
    const acsConfirmButton = root.querySelector('[data-vc-study-sessions-acs-confirm]');
    const acsCodeList = root.querySelector('[data-vc-study-sessions-acs-code-list]');
    const acsCodeSummary = root.querySelector('[data-vc-study-sessions-acs-code-summary]');
    const acsCodeSearch = root.querySelector('[data-vc-study-sessions-acs-search]');
    const acsWeakAreasButton = root.querySelector('[data-vc-study-sessions-acs-weak-areas]');
    const acsClearButton = root.querySelector('[data-vc-study-sessions-acs-clear]');
    // Dashboard stat nodes only. Category/subtopic card metrics are rendered from category payloads.
    const statHeaderCardsMastered = root.querySelector('[data-vc-study-sessions-stat="cards-mastered"]');
    const statTotalReviewed = root.querySelector('[data-vc-study-sessions-stat="total-reviewed"]');
    const statTopicsCompleted = root.querySelector('[data-vc-study-sessions-stat="topics-completed"]');

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
    let activeConfigModal = 'default';
    let selectedAcsCodes = new Set();
    let weakAreasSortActive = false;
    const storageKey = 'vcStudySessionsState:' + window.location.pathname;

    // Persistencia: limpia el estado cuando el usuario sale de Study sessions.
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
            const nextView = nextUrl.searchParams.get('view') || 'study-sessions';

            if (nextView !== 'study-sessions') {
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
        categoryTotal.textContent = String(Number(category.totalCards || 0)) + ' total questions';
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

    // Session: construye chips de topic, ACS y subtopic para la tarjeta actual.
    function renderSessionKicker(topicLabel, subtopicLabel, acsCode) {
      if (!kicker) {
        return;
      }

      kicker.textContent = '';

      const topRow = document.createElement('div');
      topRow.className = 'vc-study-sessions-session-context-row';

      if (topicLabel) {
        const topicChip = document.createElement('p');
        // Fix: conserva la clase original del dise�o mientras el texto se pinta dinamicamente.
        topicChip.className = 'vc-study-sessions-session-topic';
        topicChip.textContent = topicLabel;
        topRow.appendChild(topicChip);
      }

      if (acsCode) {
        const acsChip = document.createElement('p');
        acsChip.className = 'vc-study-sessions-session-acs';
        acsChip.textContent = acsCode;
        topRow.appendChild(acsChip);
      }

      if (topRow.childNodes.length) {
        kicker.appendChild(topRow);
      }

      if (subtopicLabel) {
        const subtopicChip = document.createElement('p');
        // Fix: conserva la clase original del dise�o mientras el subtopic viene desde la tarjeta actual.
        subtopicChip.className = 'vc-study-sessions-session-subtopic';
        subtopicChip.textContent = subtopicLabel;
        subtopicChip.title = subtopicLabel;
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
        empty.className = 'vc-study-sessions-subtopics-empty';
        empty.textContent = labels.noSubtopics || 'No subtopics have been added yet for this category.';
        subtopicsWrap.appendChild(empty);
        return;
      }

      subtopics.forEach(function (subtopic) {
        const item = document.createElement('button');
        const copy = document.createElement('div');
        const heading = document.createElement('div');
        const title = document.createElement('strong');
        const status = document.createElement('span');
        const description = document.createElement('span');
        const chevron = document.createElement('span');
        const chevronSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        const chevronPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');

        item.type = 'button';
        item.className = 'vc-study-sessions-subtopic-item';

        copy.className = 'vc-study-sessions-subtopic-copy';
        heading.className = 'vc-study-sessions-subtopic-heading';
        chevron.className = 'vc-study-sessions-subtopic-chevron';
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
        status.className = 'vc-study-sessions-subtopic-status vc-study-sessions-subtopic-status--' + String(subtopic.statusClass || 'needs-review');
        status.textContent = subtopic.status || '';
        description.textContent = subtopic.description || '';

        chevronSvg.appendChild(chevronPath);
        chevron.appendChild(chevronSvg);
        heading.appendChild(title);
        if (status.textContent) {
          heading.appendChild(status);
        }
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
    function getOptionValues(maxCards, mode) {
      const safeMax = Math.max(1, Math.min(50, Number(maxCards || 1)));
      const filtered = cardOptions
        .map(function (value) { return Number(value); })
        .filter(function (value) {
          return value <= safeMax && (mode !== 'subcategory' || value < 40);
        });

      const values = filtered.length > 0 ? filtered : [safeMax];

      return values.filter(function (value, index, values) {
        return values.indexOf(value) === index;
      });
    }

    // Modal: devuelve el set de nodos del modal activo.
    function getModalControls() {
      if (activeConfigModal === 'acs' && acsModal) {
        return {
          modal: acsModal,
          title: acsModalTitle,
          copy: acsModalCopy,
          countDisplay: acsCountDisplay,
          rangeLabel: acsRangeLabel,
          rangeInput: acsRangeInput,
          optionsWrap: acsOptionsWrap,
          confirmButton: acsConfirmButton
        };
      }

      return {
        modal: modal,
        title: modalTitle,
        copy: modalCopy,
        countDisplay: countDisplay,
        rangeLabel: rangeLabel,
        rangeInput: rangeInput,
        optionsWrap: optionsWrap,
        confirmButton: confirmButton
      };
    }

    // Modal: sincroniza slider, contador y botones rapidos.
    function updateModalSelection(value) {
      if (!pendingConfig) {
        return;
      }

      const controls = getModalControls();

      if (!controls.rangeInput || !controls.countDisplay || !controls.optionsWrap) {
        return;
      }

      pendingConfig.selectedCount = normalizeCount(value, pendingConfig.maxCards);
      controls.rangeInput.value = String(pendingConfig.selectedCount);
      controls.countDisplay.textContent = String(pendingConfig.selectedCount);

      controls.optionsWrap.querySelectorAll('button').forEach(function (button) {
        button.classList.toggle('is-active', Number(button.dataset.count) === pendingConfig.selectedCount);
      });

      updateRangeFill();
    }

    // Modal: actualiza el relleno visual del range con una variable CSS.
    function updateRangeFill() {
      const controls = getModalControls();

      if (!controls.rangeInput) {
        return;
      }

      const min = Number(controls.rangeInput.min || 0);
      const max = Number(controls.rangeInput.max || 100);
      const value = Number(controls.rangeInput.value || min);
      const percent = max > min ? ((value - min) / (max - min)) * 100 : 0;

      controls.rangeInput.style.setProperty('--vc-range-progress', percent + '%');
    }

    // Modal: dibuja los botones de cantidades predefinidas.
    function renderModalOptions(maxCards, mode) {
      const values = getOptionValues(maxCards, mode);
      const controls = getModalControls();

      if (!controls.optionsWrap) {
        return;
      }

      controls.optionsWrap.innerHTML = '';

      values.forEach(function (value) {
        const button = document.createElement('button');
        const label = document.createElement('span');
        button.type = 'button';
        button.className = 'vc-study-sessions-count-option';
        button.dataset.count = String(value);
        label.className = 'vc-study-sessions-count-option-label';
        label.textContent = String(value);
        button.appendChild(label);
        button.addEventListener('click', function () {
          updateModalSelection(value);
        });
        controls.optionsWrap.appendChild(button);
      });

      if (mode !== 'subcategory') {
        return;
      }

      const allButton = document.createElement('button');
      const allLabel = document.createElement('span');
      const allCount = normalizeCount(maxCards, maxCards);
      allButton.type = 'button';
      allButton.className = 'vc-study-sessions-count-option vc-study-sessions-count-option--all';
      allButton.dataset.count = String(allCount);
      allLabel.className = 'vc-study-sessions-count-option-label';
      allLabel.textContent = labels.allQuestions || 'All questions';
      allButton.appendChild(allLabel);
      allButton.addEventListener('click', function () {
        updateModalSelection(allCount);
      });
      controls.optionsWrap.appendChild(allButton);
    }

    function updateAcsCodeSummary(category) {
      if (!acsCodeSummary) {
        return;
      }

      const acsCodes = Array.isArray(category && category.acsCodes) ? category.acsCodes : [];
      const selectedItems = acsCodes.filter(function (item) {
        return selectedAcsCodes.has(String(item.code || ''));
      });
      const selectedCards = selectedItems.reduce(function (sum, item) {
        return sum + Number(item.totalCards || 0);
      }, 0);

      acsCodeSummary.textContent = String(selectedCards) + ' ' + (selectedCards === 1 ? 'question' : 'questions') + ' \u00b7 ' + String(selectedItems.length) + ' ' + (selectedItems.length === 1 ? 'code' : 'codes');
    }

    function normalizeAcsSearchValue(value) {
      return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, ' ')
        .trim()
        .toLowerCase();
    }

    // ACS Code workspace: pinta los codigos disponibles de la categoria activa.
    function renderAcsCodeList(category, searchTerm) {
      if (!acsCodeList || !acsCodeSummary) {
        return;
      }

      const acsCodes = Array.isArray(category && category.acsCodes) ? category.acsCodes.slice() : [];
      acsCodes.sort(function (first, second) {
        if (weakAreasSortActive) {
          const firstAttempted = Number(first.attemptedCards || 0);
          const secondAttempted = Number(second.attemptedCards || 0);

          if ((firstAttempted > 0) !== (secondAttempted > 0)) {
            return firstAttempted > 0 ? -1 : 1;
          }

          if (firstAttempted > 0 && secondAttempted > 0) {
            const firstApproval = (firstAttempted - Number(first.incorrectCards || 0)) / firstAttempted;
            const secondApproval = (secondAttempted - Number(second.incorrectCards || 0)) / secondAttempted;

            if (firstApproval !== secondApproval) {
              return firstApproval - secondApproval;
            }

            const incorrectDifference = Number(second.incorrectCards || 0) - Number(first.incorrectCards || 0);
            if (incorrectDifference !== 0) {
              return incorrectDifference;
            }
          }
        }

        return String(first.code || '').localeCompare(String(second.code || ''), undefined, { numeric: true });
      });
      const normalizedSearch = normalizeAcsSearchValue(searchTerm);
      const visibleCodes = acsCodes.filter(function (item) {
        const topics = Array.isArray(item.topics) ? item.topics : [];
        const searchableText = normalizeAcsSearchValue([item.code].concat(topics).join(' '));

        return !normalizedSearch || searchableText.indexOf(normalizedSearch) !== -1;
      });
      acsCodeList.innerHTML = '';

      if (visibleCodes.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'vc-study-sessions-acs-code-empty';
        empty.textContent = acsCodes.length
          ? 'No ACS codes match your search.'
          : 'No ACS codes are available for this category.';
        acsCodeList.appendChild(empty);
      } else {
        visibleCodes.forEach(function (item) {
          const label = document.createElement('label');
          const checkbox = document.createElement('input');
          const copy = document.createElement('span');
          const code = document.createElement('strong');
          const count = document.createElement('span');
          const cardCount = Number(item.totalCards || 0);

          label.className = 'vc-study-sessions-acs-code-item';
          checkbox.type = 'checkbox';
          checkbox.value = String(item.code || '');
          checkbox.dataset.vcStudySessionsAcsCode = String(item.code || '');
          checkbox.checked = selectedAcsCodes.has(checkbox.value);
          label.classList.toggle('is-selected', checkbox.checked);
          copy.className = 'vc-study-sessions-acs-code-item-copy';
          code.textContent = String(item.code || 'ACS Code');
          count.textContent = String(cardCount) + ' ' + (cardCount === 1 ? 'question' : 'questions');

          checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
              selectedAcsCodes.add(checkbox.value);
            } else {
              selectedAcsCodes.delete(checkbox.value);
            }

            label.classList.toggle('is-selected', checkbox.checked);
            updateAcsCodeSummary(category);
          });

          copy.appendChild(code);
          copy.appendChild(count);
          label.appendChild(checkbox);
          label.appendChild(copy);
          acsCodeList.appendChild(label);
        });
      }

      updateAcsCodeSummary(category);
    }

    function selectWeakAcsCodes() {
      weakAreasSortActive = !weakAreasSortActive;
      acsWeakAreasButton.classList.toggle('is-active', weakAreasSortActive);
      acsWeakAreasButton.setAttribute('aria-pressed', weakAreasSortActive ? 'true' : 'false');
      renderAcsCodeList(currentCategory, acsCodeSearch ? acsCodeSearch.value : '');
    }

    function clearSelectedAcsCodes() {
      selectedAcsCodes.clear();
      weakAreasSortActive = false;
      acsWeakAreasButton.classList.remove('is-active');
      acsWeakAreasButton.setAttribute('aria-pressed', 'false');
      renderAcsCodeList(currentCategory, acsCodeSearch ? acsCodeSearch.value : '');
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
        setFeedback(labels.noCards || 'No questions were found for this selection.', 'error');
        return;
      }

      activeConfigModal = config.modalVariant === 'acs' ? 'acs' : 'default';
      const controls = getModalControls();

      if (!controls.modal || !controls.title || !controls.copy || !controls.rangeInput || !controls.rangeLabel) {
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

      controls.title.textContent = config.title;
      controls.copy.textContent = config.description;
      controls.rangeInput.min = '1';
      controls.rangeInput.max = String(Math.max(1, Math.min(50, maxCards)));
      controls.rangeLabel.textContent = '1 - ' + String(Math.max(1, Math.min(50, maxCards)));

      renderModalOptions(maxCards, pendingConfig.mode);
      updateModalSelection(pendingConfig.selectedCount);

      if (activeConfigModal === 'acs') {
        selectedAcsCodes.clear();
        weakAreasSortActive = false;
        if (acsWeakAreasButton) {
          acsWeakAreasButton.classList.remove('is-active');
          acsWeakAreasButton.setAttribute('aria-pressed', 'false');
        }
        if (acsCodeSearch) {
          acsCodeSearch.value = '';
        }
        renderAcsCodeList(currentCategory, '');
      }

      if (modal) {
        modal.hidden = true;
      }
      if (acsModal) {
        acsModal.hidden = true;
      }
      controls.modal.hidden = false;
      document.body.classList.add('vc-study-sessions-modal-open');
      persistState();
    }

    // Modal: cierra el dialogo y limpia el estado visual de la tarjeta activa.
    function closeConfigModal() {
      if (modal) {
        modal.hidden = true;
      }
      if (acsModal) {
        acsModal.hidden = true;
      }
      document.body.classList.remove('vc-study-sessions-modal-open');
      activeConfigModal = 'default';
      setActiveConfigCard(null);
      persistState();
    }

    // Session reference image: obtiene la URL real de la imagen o fallback disponible.
    function getReferenceImageUrl(card) {
      const fallbackUrl = referenceImageButton ? String(referenceImageButton.dataset.vcStudySessionsReferenceImageFallback || '') : '';
      return card && card.questionImageUrl ? String(card.questionImageUrl) : fallbackUrl;
    }

    // Session: expande o contrae la imagen dentro de la tarjeta actual.
    function toggleReferenceImageInline() {
      const card = cards[cardIndex];
      const imageUrl = getReferenceImageUrl(card);

      if (!imageUrl || !referenceImageButton) {
        return;
      }

      if (referenceImageModal && referenceImageModal.shouldUseModal()) {
        referenceImageModal.open(imageUrl);
        return;
      }

      if (!referenceImageInline || !referenceImageInlineWrap) {
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

      [confirmButton, acsConfirmButton].forEach(function (button) {
        if (!button) {
          return;
        }

        button.disabled = isLoading;
        button.textContent = isLoading
          ? (labels.starting || 'Starting...')
          : (labels.start || 'Start');
      });
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
      if (referenceImageModal) {
        referenceImageModal.close();
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
      nextButton.classList.remove('is-finish');
      setFeedback('', '');
    }

    // Session: crea cada respuesta como boton para controlar estados y teclado.
    function buildAnswerButton(key, text) {
      const button = document.createElement('button');
      const badge = document.createElement('span');
      const label = document.createElement('strong');

      button.type = 'button';
      button.className = 'vc-study-sessions-answer';
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

    // Session: prepara explicacion sin exponer codigos internos de referencia.
    function renderExplanation(card, isCorrect) {
      currentExplanationHtml =
        '<div class="vc-study-sessions-explanation-body">' +
          card.explanation +
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

      answersWrap.querySelectorAll('.vc-study-sessions-answer').forEach(function (answerButton) {
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
      const isLastCard = cardIndex === cards.length - 1;
      nextButton.classList.toggle('is-finish', isLastCard);
      if (nextButtonLabel) {
        nextButtonLabel.textContent = isLastCard ? 'Finish' : 'Next question';
      }
      questionEl.textContent = card.question;
      if (referenceImageButton) {
        // Solo mostrar el bot�n si la tarjeta tiene una imagen real (no usar fallback para la decisi�n de visibilidad)
        referenceImageButton.hidden = !card.questionImageUrl;
      }
      renderSessionKicker(card.topicLabel, card.subtopicLabel, card.acsCode);

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

      answersWrap.querySelectorAll('.vc-study-sessions-answer').forEach(function (answerButton) {
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

      answersWrap.querySelectorAll('.vc-study-sessions-answer').forEach(function (answerButton) {
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
      body.append('nonce', window.vcStudySessionsData.nonce);
      body.append('session_id', String(sessionId));
      body.append('attempts', JSON.stringify(attempts));

      fetch(window.vcStudySessionsData.ajaxUrl, {
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
      body.append('nonce', window.vcStudySessionsData.nonce);
      body.append('mode', config.mode);
      body.append('term_id', String(config.termId || 0));
      body.append('card_limit', String(config.selectedCount || 10));
      if (Array.isArray(config.acsCodes) && config.acsCodes.length > 0) {
        body.append('acs_codes', JSON.stringify(config.acsCodes));
      }

      fetch(window.vcStudySessionsData.ajaxUrl, {
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
    root.querySelectorAll('[data-vc-study-sessions-open-category]').forEach(function (button) {
      button.addEventListener('click', function () {
        openCategory(Number(button.dataset.vcStudySessionsOpenCategory));
      });
    });

    // Eventos: apertura de modal para categoria, random y global random.
    root.querySelectorAll('[data-vc-study-sessions-launch]').forEach(function (button) {
      button.addEventListener('click', function () {
        const mode = button.dataset.vcStudySessionsLaunch;
        const isRandom = mode === 'random';
        const isGlobalRandom = mode === 'global-random';
        const launchCard = button.closest('[data-vc-study-sessions-launch-card]');

        if (!isGlobalRandom && !currentCategory) {
          return;
        }

        setActiveConfigCard(launchCard);

        openConfigModal({
          modalVariant: isGlobalRandom || isRandom ? 'default' : 'acs',
          mode: isGlobalRandom ? 'global-random' : (isRandom ? 'random' : 'category'),
          termId: isGlobalRandom ? 0 : Number(currentCategory.id),
          title: isGlobalRandom ? 'Global Random Practice' : (isRandom ? 'Study in random mode' : 'Study by ACS Code'),
          description: isGlobalRandom
            ? 'Mix cards from all categories for a comprehensive review.'
            : (isRandom
              ? 'Questions will be shuffled randomly within this category'
              : 'Select how many ACS-code questions you want to study from this category.'),
          maxCards: isGlobalRandom ? Number(categories.reduce(function (sum, item) { return sum + Number(item.totalCards || 0); }, 0)) : Number(currentCategory.totalCards || 0),
          kicker: isGlobalRandom ? 'Global Random' : currentCategory.name
        });
      });
    });

    // Eventos: permite activar una tarjeta completa sin duplicar el click del boton interno.
    root.querySelectorAll('[data-vc-study-sessions-launch-card]').forEach(function (card) {
      const launchButton = card.querySelector('[data-vc-study-sessions-launch]');

      if (!launchButton) {
        return;
      }

      card.addEventListener('click', function (event) {
        if (event.target.closest('[data-vc-study-sessions-launch]')) {
          return;
        }

        launchButton.click();
      });

      card.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
          return;
        }

        if (event.target.closest('[data-vc-study-sessions-launch]')) {
          return;
        }

        event.preventDefault();
        launchButton.click();
      });
    });

    // Eventos: controles compartidos de regreso, cierre de modal y avance.
    root.querySelectorAll('[data-vc-study-sessions-back]').forEach(function (button) {
      button.addEventListener('click', openHome);
    });

    root.querySelectorAll('[data-vc-study-sessions-close]').forEach(function (button) {
      button.addEventListener('click', closeConfigModal);
    });

    root.querySelectorAll('[data-vc-study-sessions-acs-close]').forEach(function (button) {
      button.addEventListener('click', closeConfigModal);
    });

    if (referenceImageButton) {
      referenceImageButton.addEventListener('click', toggleReferenceImageInline);
    }

    if (referenceImageInline) {
      referenceImageInline.addEventListener('click', function (event) {
        const card = cards[cardIndex];
        const imageUrl = getReferenceImageUrl(card);

        if (!imageUrl || (referenceImageModal && referenceImageModal.shouldUseModal())) {
          return;
        }

        event.stopPropagation();
        if (referenceImageModal) {
          referenceImageModal.open(imageUrl);
        }
      });
    }

    rangeInput.addEventListener('input', function () {
      updateModalSelection(Number(rangeInput.value));
    });

    if (acsRangeInput) {
      acsRangeInput.addEventListener('input', function () {
        updateModalSelection(Number(acsRangeInput.value));
      });
    }

    if (acsCodeSearch) {
      acsCodeSearch.addEventListener('input', function () {
        renderAcsCodeList(currentCategory, acsCodeSearch.value);
      });
    }

    if (acsWeakAreasButton) {
      acsWeakAreasButton.addEventListener('click', selectWeakAcsCodes);
    }

    if (acsClearButton) {
      acsClearButton.addEventListener('click', clearSelectedAcsCodes);
    }

    confirmButton.addEventListener('click', function () {
      if (!pendingConfig) {
        return;
      }

      startSession(pendingConfig);
    });

    if (acsConfirmButton) {
      acsConfirmButton.addEventListener('click', function () {
        if (!pendingConfig) {
          return;
        }

        const selectedCodes = Array.from(selectedAcsCodes);
        if (selectedCodes.length === 0) {
          setFeedback('Select at least one ACS Code.', 'error');
          return;
        }

        const availableCodes = Array.isArray(currentCategory && currentCategory.acsCodes)
          ? currentCategory.acsCodes
          : [];
        const selectedQuestionCount = availableCodes.reduce(function (total, item) {
          return selectedAcsCodes.has(String(item.code || ''))
            ? total + Number(item.totalCards || 0)
            : total;
        }, 0);
        const acsConfig = Object.assign({}, pendingConfig, {
          acsCodes: selectedCodes,
          selectedCount: Math.max(1, Math.min(50, selectedQuestionCount))
        });

        startSession(acsConfig);
      });
    }

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
      const answerButton = answersWrap.querySelector('.vc-study-sessions-answer[data-answer-key="' + key + '"]');
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
    document.querySelectorAll('.vc-study-sessions-app').forEach(initStudySessionsApp);
  });
}());
