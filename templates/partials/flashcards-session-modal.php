<?php
/**
 * Flashcards session modal: quantity picker before starting a flashcard session.
 */
if (!defined('ABSPATH')) {
  exit;
}
?>

<div
  class="vc-flashcards-session-modal"
  data-vc-flashcards-session-modal
  data-vc-flashcards-session-random-title="<?php esc_attr_e('Random practice', 'vc-flashcards'); ?>"
  data-vc-flashcards-session-random-copy="<?php esc_attr_e('Choose how many questions to review from %s.', 'vc-flashcards'); ?>"
  data-vc-flashcards-session-subtopic-copy="<?php esc_attr_e('Choose how many questions to review from this subtopic.', 'vc-flashcards'); ?>"
  data-vc-flashcards-session-all-label="<?php esc_attr_e('All questions', 'vc-flashcards'); ?>"
  hidden
>
  <div class="vc-flashcards-session-modal-backdrop" data-vc-flashcards-session-close></div>
  <div class="vc-flashcards-session-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vc-flashcards-session-modal-title">
    <div class="vc-flashcards-session-modal-header">
      <div class="vc-flashcards-session-modal-heading">
        <h3 id="vc-flashcards-session-modal-title" data-vc-flashcards-session-modal-title></h3>
        <p class="vc-flashcards-session-modal-copy" data-vc-flashcards-session-modal-copy></p>
      </div>
      <button type="button" class="vc-flashcards-session-close-button vc-flashcards-session-modal-close" data-vc-flashcards-session-close aria-label="<?php esc_attr_e('Close', 'vc-flashcards'); ?>">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/cerrar.svg'); ?>" alt="" width="20" height="20">
      </button>
    </div>

    <div class="vc-flashcards-session-modal-count">
      <strong data-vc-flashcards-session-count-display>20</strong>
      <span><?php esc_html_e('Selected questions', 'vc-flashcards'); ?></span>
    </div>

    <div class="vc-flashcards-session-modal-slider-wrap">
      <div class="vc-flashcards-session-modal-range-meta">
        <span><?php esc_html_e('Number of questions', 'vc-flashcards'); ?></span>
        <strong data-vc-flashcards-session-range-label>1 - 50</strong>
      </div>
      <input type="range" min="1" max="50" value="20" step="1" data-vc-flashcards-session-range>
    </div>

    <div class="vc-flashcards-session-modal-options" data-vc-flashcards-session-options></div>

    <div class="vc-flashcards-session-modal-actions">
      <button type="button" class="vc-flashcards-session-modal-cancel" data-vc-flashcards-session-close><?php esc_html_e('Cancel', 'vc-flashcards'); ?></button>
      <button type="button" class="vc-flashcards-session-modal-confirm" data-vc-flashcards-session-confirm><?php esc_html_e('Start', 'vc-flashcards'); ?></button>
    </div>
    <p class="vc-flashcards-session-modal-status" data-vc-flashcards-session-status hidden></p>
  </div>
</div>
