(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const widget = document.querySelector('[data-vc-flashcards-help-widget]');

    if (!widget) {
      return;
    }

    const toggle = widget.querySelector('[data-vc-flashcards-help-toggle]');
    const panel = widget.querySelector('[data-vc-flashcards-feedback-panel]');
    const close = widget.querySelector('[data-vc-flashcards-help-close]');
    const form = widget.querySelector('[data-vc-flashcards-feedback-form]');
    const status = widget.querySelector('[data-vc-flashcards-feedback-status]');
    const submit = form ? form.querySelector('.vc-flashcards-feedback-submit') : null;
    const dropzone = form ? form.querySelector('[data-vc-flashcards-feedback-dropzone]') : null;
    const screenshotInput = form ? form.querySelector('input[name="screenshot"]') : null;
    const screenshotPreview = form ? form.querySelector('[data-vc-flashcards-feedback-preview]') : null;
    const screenshotIcon = form ? form.querySelector('[data-vc-flashcards-feedback-upload-icon]') : null;
    const screenshotLabel = form ? form.querySelector('[data-vc-flashcards-feedback-file-label]') : null;
    const screenshotDefaultLabel = screenshotLabel ? screenshotLabel.textContent : '';
    let screenshotPreviewUrl = '';
    const labels = (window.vcFlashcardsHelpFab && window.vcFlashcardsHelpFab.labels) || {};

    if (!toggle || !panel) {
      return;
    }

    function setOpen(isOpen) {
      panel.hidden = !isOpen;
      widget.classList.toggle('is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
      setOpen(panel.hidden);
    });

    if (close) {
      close.addEventListener('click', function () {
        setOpen(false);
        toggle.focus();
      });

      close.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          setOpen(false);
          toggle.focus();
        }
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        setOpen(false);
        toggle.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!panel.hidden && !widget.contains(event.target)) {
        setOpen(false);
      }
    });

    if (form) {
      if (screenshotInput) {
        screenshotInput.addEventListener('change', function () {
          updateScreenshotPreview(screenshotInput.files && screenshotInput.files[0] ? screenshotInput.files[0] : null);
        });
      }

      if (dropzone && screenshotInput) {
        dropzone.addEventListener('dragover', function (event) {
          event.preventDefault();
          dropzone.classList.add('is-dragover');
        });

        dropzone.addEventListener('dragleave', function () {
          dropzone.classList.remove('is-dragover');
        });

        dropzone.addEventListener('drop', function (event) {
          const files = event.dataTransfer && event.dataTransfer.files;

          event.preventDefault();
          dropzone.classList.remove('is-dragover');

          if (!files || !files.length || !files[0].type || files[0].type.indexOf('image/') !== 0) {
            return;
          }

          screenshotInput.files = files;
          updateScreenshotPreview(files[0]);
        });
      }

      form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!window.vcFlashcardsHelpFab || !window.vcFlashcardsHelpFab.ajaxUrl || !window.vcFlashcardsHelpFab.nonce) {
          setStatus(labels.error || 'Could not send feedback. Please try again.', 'error');
          return;
        }

        const data = new FormData(form);
        data.append('action', 'vc_flashcards_submit_feedback');
        data.append('nonce', window.vcFlashcardsHelpFab.nonce);
        data.append('page_url', window.location.href);
        data.append('referrer_url', document.referrer || '');

        setBusy(true);
        setStatus(labels.sending || 'Sending...', '');

        fetch(window.vcFlashcardsHelpFab.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: data
        })
          .then(function (response) {
            return response.json();
          })
          .then(function (payload) {
            const message = payload && payload.data && payload.data.message
              ? payload.data.message
              : '';

            if (!payload || !payload.success) {
              throw new Error(message || labels.error || 'Could not send feedback. Please try again.');
            }

            form.reset();
            updateScreenshotPreview(null);
            updateRenderedAt();
            setStatus(message || labels.success || 'Thanks. Your feedback was sent.', 'success');
          })
          .catch(function (error) {
            setStatus(error.message || labels.error || 'Could not send feedback. Please try again.', 'error');
          })
          .finally(function () {
            setBusy(false);
          });
      });
    }

    function setBusy(isBusy) {
      if (submit) {
        submit.disabled = isBusy;
      }
    }

    function setStatus(message, state) {
      if (!status) {
        return;
      }

      status.hidden = !message;
      status.textContent = message || '';
      status.dataset.state = state || '';
    }

    function updateRenderedAt() {
      const renderedAt = form ? form.querySelector('input[name="rendered_at"]') : null;

      if (renderedAt) {
        renderedAt.value = String(Math.floor(Date.now() / 1000));
      }
    }

    function updateScreenshotPreview(file) {
      if (screenshotPreviewUrl) {
        URL.revokeObjectURL(screenshotPreviewUrl);
        screenshotPreviewUrl = '';
      }

      if (!dropzone || !screenshotPreview || !screenshotLabel || !screenshotIcon) {
        return;
      }

      if (!file) {
        screenshotPreview.src = '';
        screenshotPreview.hidden = true;
        screenshotIcon.hidden = false;
        screenshotLabel.hidden = false;
        screenshotLabel.textContent = screenshotDefaultLabel;
        dropzone.classList.remove('is-has-file');
        return;
      }

      screenshotPreviewUrl = URL.createObjectURL(file);
      screenshotPreview.src = screenshotPreviewUrl;
      screenshotPreview.alt = file.name;
      screenshotPreview.hidden = false;
      screenshotIcon.hidden = true;
      screenshotLabel.hidden = true;
      screenshotLabel.textContent = file.name;
      dropzone.classList.add('is-has-file');
    }
  });
}());
