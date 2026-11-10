<?php
/**
 * Flashcards practice workspace: session shell rendered after the quantity modal.
 */
if (!defined('ABSPATH')) {
  exit;
}
?>

<section class="vc-flashcards-library-view vc-flashcards-practice" data-vc-flashcards-library-view="session" data-vc-flashcards-practice hidden>
  <div class="vc-flashcards-practice-top">
    <header class="vc-flashcards-practice-header">
      <div class="vc-flashcards-practice-heading">
        <h2 data-vc-flashcards-practice-title><?php esc_html_e('Flashcards', 'vc-flashcards'); ?></h2>
        <p data-vc-flashcards-practice-subtitle></p>
      </div>
      <button type="button" class="vc-flashcards-practice-finish" data-vc-flashcards-practice-finish><?php esc_html_e('Finish', 'vc-flashcards'); ?></button>
    </header>

    <div class="vc-flashcards-practice-bar" aria-hidden="true">
      <span data-vc-flashcards-practice-bar-fill style="width: 0%;"></span>
    </div>
  </div>

  <div class="vc-flashcards-practice-slider">
  <article class="vc-flashcards-practice-card" data-vc-flashcards-practice-card tabindex="0" role="button" aria-pressed="false" aria-live="polite">
    <div class="vc-flashcards-practice-card-inner">
      <div class="vc-flashcards-practice-card-face vc-flashcards-practice-card-front">
        <div class="vc-flashcards-practice-question-wrap">
          <h3 data-vc-flashcards-practice-question></h3>
        </div>
        <div class="vc-flashcards-practice-flip-hint"><?php esc_html_e('Click the card to flip', 'vc-flashcards'); ?></div>
      </div>
      <div class="vc-flashcards-practice-card-face vc-flashcards-practice-card-back">
        <div class="vc-flashcards-practice-answers" data-vc-flashcards-practice-answers></div>
        <div class="vc-flashcards-practice-flip-hint vc-flashcards-practice-flip-hint--back"><?php esc_html_e('Click the card to flip back', 'vc-flashcards'); ?></div>
      </div>
    </div>
  </article>
  </div>

  <footer class="vc-flashcards-practice-controls">
    <div class="vc-flashcards-practice-side-group">
    <button
      type="button"
      class="vc-flashcards-practice-side-icon vc-flashcards-practice-side-icon--left"
      data-vc-flashcards-practice-repeat
      aria-label="<?php esc_attr_e('Repeat flashcards', 'vc-flashcards'); ?>"
      title="<?php esc_attr_e('Repeat flashcards', 'vc-flashcards'); ?>"
    >
      <img
        class="vc-flashcards-practice-side-icon-image vc-flashcards-practice-side-icon-image--left"
        src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/try-white.svg'); ?>"
        alt=""
        width="19"
        height="19"
      >
      <span class="vc-flashcards-practice-side-icon-label"><?php esc_html_e('Restart', 'vc-flashcards'); ?></span>
    </button>
    </div>
    <div class="vc-flashcards-practice-nav">
      <button type="button" class="vc-flashcards-practice-back" data-vc-flashcards-practice-back aria-label="<?php esc_attr_e('Previous card', 'vc-flashcards'); ?>">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 10H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          <path d="M9 5L4 10L9 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span data-vc-flashcards-practice-back-label><?php esc_html_e('Previous', 'vc-flashcards'); ?></span>
      </button>
      <div class="vc-flashcards-practice-position">
        <strong data-vc-flashcards-practice-progress-count><?php esc_html_e('1 / 10', 'vc-flashcards'); ?></strong>
      </div>
      <button type="button" class="vc-flashcards-practice-next" data-vc-flashcards-practice-next hidden disabled aria-label="<?php esc_attr_e('Next question', 'vc-flashcards'); ?>">
        <span data-vc-flashcards-practice-next-label><?php esc_html_e('Next', 'vc-flashcards'); ?></span>
        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 10H16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          <path d="M11 5L16 10L11 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
    </div>
    <button
      type="button"
      class="vc-flashcards-practice-side-icon vc-flashcards-practice-side-icon--right"
      data-vc-flashcards-practice-shuffle
      aria-label="<?php esc_attr_e('Shuffle flashcards', 'vc-flashcards'); ?>"
      title="<?php esc_attr_e('Shuffle flashcards', 'vc-flashcards'); ?>"
    >
      <span class="vc-flashcards-practice-side-icon-label"><?php esc_html_e('Shuffle', 'vc-flashcards'); ?></span>
      <img
        class="vc-flashcards-practice-side-icon-image vc-flashcards-practice-side-icon-image--right"
        src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/aletorio.svg'); ?>"
        alt=""
        width="19"
        height="19"
      >
    </button>
  </footer>
</section>
