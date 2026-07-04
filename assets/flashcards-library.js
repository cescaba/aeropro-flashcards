(function () {
  'use strict';

  function initFlashcardsLibrary(root) {
    if (!root) {
      return;
    }

    var views = root.querySelectorAll('[data-vc-flashcards-library-view]');
    var detailPanels = root.querySelectorAll('[data-vc-flashcards-library-detail-panel]');
    var dashboardContent = root.closest('.vc-dashboard-content');

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

      showDetailPanel(viewName === 'detail' ? panelKey : '');
      showView(viewName);

      if (shouldUpdateUrl) {
        updateUrl(viewName, panelKey);
      }
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

    window.addEventListener('popstate', function () {
      var params = new URLSearchParams(window.location.search);
      var viewName = params.get('vc_flashcards_view') === 'detail' ? 'detail' : 'home';
      var panelKey = params.get('vc_flashcards_category') || '';

      showLibraryState(viewName, panelKey, false);
    });

    showLibraryState(
      root.getAttribute('data-vc-flashcards-library-initial-view') === 'detail' ? 'detail' : 'home',
      root.getAttribute('data-vc-flashcards-library-initial-category') || '',
      false
    );
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vc-flashcards-library]').forEach(initFlashcardsLibrary);
  });
}());
