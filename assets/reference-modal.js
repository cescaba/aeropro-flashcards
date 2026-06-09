(function () {
  'use strict';

  function getElement(root, selector) {
    return selector ? root.querySelector(selector) : null;
  }

  function createReferenceImageModal(options) {
    var root = options.root;
    var modal = getElement(root, options.modalSelector);
    var image = getElement(root, options.imageSelector);
    var zoomButton = getElement(root, options.zoomSelector);
    var frame = getElement(root, options.frameSelector);
    var trigger = options.trigger || null;
    var expandedClass = options.expandedClass || 'is-expanded';
    var bodyClass = options.bodyClass || 'vc-flashcards-modal-open';
    var zoomWidthProperty = options.zoomWidthProperty || '--vc-reference-modal-zoom-width';
    var zoomPercentProperty = options.zoomPercentProperty || '--vc-reference-modal-zoom-percent';
    var frameWidthProperty = '--vc-reference-modal-frame-width';
    var frameHeightProperty = '--vc-reference-modal-frame-height';
    var touch = null;
    var touchTransform = { x: 0, y: 0, scale: 1 };

    function shouldUseTouchModal() {
      return window.matchMedia && window.matchMedia('(max-width: 480px)').matches;
    }

    function shouldUseModal() {
      return window.matchMedia && window.matchMedia('(min-width: 481px)').matches;
    }

    function getZoomPercent() {
      if (!modal || !window.getComputedStyle) {
        return 145;
      }

      var configuredPercent = Number(window.getComputedStyle(modal).getPropertyValue(zoomPercentProperty));

      if (!Number.isFinite(configuredPercent)) {
        return 145;
      }

      return Math.min(Math.max(configuredPercent, 70), 220);
    }

    function syncSize() {
      if (!modal || !image || !image.naturalWidth) {
        return;
      }

      if (frame && image.offsetWidth && image.offsetHeight) {
        modal.style.setProperty(frameWidthProperty, String(Math.round(image.offsetWidth)) + 'px');
        modal.style.setProperty(frameHeightProperty, String(Math.round(image.offsetHeight)) + 'px');
      }

      modal.style.setProperty(
        zoomWidthProperty,
        String(Math.round(image.naturalWidth * (getZoomPercent() / 100))) + 'px'
      );
    }

    function getTouchDistance(touches) {
      var deltaX = touches[0].clientX - touches[1].clientX;
      var deltaY = touches[0].clientY - touches[1].clientY;
      return Math.hypot(deltaX, deltaY);
    }

    function clampTouchTransform(transform) {
      if (!frame || !image) {
        return { x: 0, y: 0, scale: 1 };
      }

      var scale = Math.min(Math.max(transform.scale, 1), 4);
      var maxX = Math.max(0, ((image.offsetWidth * scale) - frame.clientWidth) / 2);
      var maxY = Math.max(0, ((image.offsetHeight * scale) - frame.clientHeight) / 2);

      return {
        x: Math.min(Math.max(transform.x, -maxX), maxX),
        y: Math.min(Math.max(transform.y, -maxY), maxY),
        scale: scale
      };
    }

    function renderTouchTransform() {
      if (!image) {
        return;
      }

      touchTransform = clampTouchTransform(touchTransform);
      image.style.transform = 'translate3d(' + touchTransform.x + 'px, ' + touchTransform.y + 'px, 0) scale(' + touchTransform.scale + ')';
    }

    function resetTouchTransform() {
      touch = null;
      touchTransform = { x: 0, y: 0, scale: 1 };

      if (image) {
        image.style.removeProperty('transform');
      }
    }

    function setZoom(isZoomed) {
      if (!modal) {
        return;
      }

      syncSize();
      modal.classList.toggle('is-zoomed', Boolean(isZoomed));

      if (zoomButton) {
        zoomButton.setAttribute('aria-pressed', isZoomed ? 'true' : 'false');
      }
    }

    function open(imageUrl) {
      if (!modal || !image || !imageUrl) {
        return;
      }

      setZoom(false);
      resetTouchTransform();
      modal.classList.toggle('is-touch-modal', shouldUseTouchModal());
      image.src = imageUrl;
      modal.hidden = false;
      syncSize();
      document.body.classList.add(bodyClass);

      if (trigger) {
        trigger.setAttribute('aria-expanded', 'true');
      }
    }

    function close() {
      if (!modal || !image) {
        return;
      }

      modal.hidden = true;
      setZoom(false);
      resetTouchTransform();
      image.removeAttribute('src');
      modal.style.removeProperty(zoomWidthProperty);
      modal.style.removeProperty(frameWidthProperty);
      modal.style.removeProperty(frameHeightProperty);
      modal.classList.remove('is-touch-modal');
      document.body.classList.remove(bodyClass);

      if (trigger) {
        trigger.setAttribute('aria-expanded', trigger.classList.contains(expandedClass) ? 'true' : 'false');
      }
    }

    if (options.closeSelector) {
      root.querySelectorAll(options.closeSelector).forEach(function (button) {
        button.addEventListener('click', close);
      });
    }

    if (zoomButton) {
      zoomButton.addEventListener('click', function () {
        setZoom(!modal.classList.contains('is-zoomed'));
      });
    }

    if (frame) {
      frame.addEventListener('touchstart', function (event) {
        if (!modal || !modal.classList.contains('is-touch-modal')) {
          return;
        }

        if (event.touches.length === 1) {
          touch = {
            mode: 'pan',
            startX: event.touches[0].clientX,
            startY: event.touches[0].clientY,
            x: touchTransform.x,
            y: touchTransform.y,
            scale: touchTransform.scale
          };
        } else if (event.touches.length === 2) {
          var distance = getTouchDistance(event.touches);

          touch = {
            mode: 'pinch',
            distance: distance > 0 ? distance : 1,
            x: touchTransform.x,
            y: touchTransform.y,
            scale: touchTransform.scale
          };
        }
      }, { passive: false });

      frame.addEventListener('touchmove', function (event) {
        if (!touch || !modal || !modal.classList.contains('is-touch-modal')) {
          return;
        }

        event.preventDefault();

        if (touch.mode === 'pan' && event.touches.length === 1) {
          touchTransform = {
            x: touch.x + event.touches[0].clientX - touch.startX,
            y: touch.y + event.touches[0].clientY - touch.startY,
            scale: touch.scale
          };
          renderTouchTransform();
        } else if (touch.mode === 'pinch' && event.touches.length === 2) {
          touchTransform = {
            x: touch.x,
            y: touch.y,
            scale: touch.scale * (getTouchDistance(event.touches) / touch.distance)
          };
          renderTouchTransform();
        }
      }, { passive: false });

      frame.addEventListener('touchend', function (event) {
        if (!modal || !modal.classList.contains('is-touch-modal')) {
          return;
        }

        if (event.touches.length === 0) {
          touch = null;
        } else if (event.touches.length === 1) {
          touch = {
            mode: 'pan',
            startX: event.touches[0].clientX,
            startY: event.touches[0].clientY,
            x: touchTransform.x,
            y: touchTransform.y,
            scale: touchTransform.scale
          };
        }
      }, { passive: false });
    }

    if (image) {
      image.addEventListener('load', syncSize);

      image.addEventListener('click', function () {
        if (!modal || modal.hidden || modal.classList.contains('is-touch-modal')) {
          return;
        }

        setZoom(!modal.classList.contains('is-zoomed'));
      });
    }

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && modal && !modal.hidden) {
        close();
      }
    });

    return {
      open: open,
      close: close,
      shouldUseModal: shouldUseModal
    };
  }

  window.VCReferenceImageModal = {
    create: createReferenceImageModal
  };
}());
