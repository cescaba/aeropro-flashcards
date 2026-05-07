<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-exam-session-header">
  <div class="vc-exam-session-title-row">
    <h2 id="vc-exam-session-title" class="vc-exam-session-title"><?php esc_html_e('A&P Mock Test', 'vc-flashcards'); ?></h2>

    <div class="vc-exam-session-header-actions">
      <div class="vc-exam-timer" data-vc-exam-timer aria-live="polite" aria-label="<?php esc_attr_e('Time remaining', 'vc-flashcards'); ?>">
        <span class="vc-exam-timer-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"></circle>
            <path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
          </svg>
        </span>
        <span data-vc-exam-timer-value>0:15:00</span>
      </div>

      <button type="button" class="vc-exam-finish-btn">
        <span><?php esc_html_e('Finish exam', 'vc-flashcards'); ?></span>
      </button>
    </div>
  </div>

  <div class="vc-exam-session-header-copy">
    <p class="vc-exam-progress-label"><?php esc_html_e('Progress', 'vc-flashcards'); ?></p>
    <strong data-vc-exam-progress><?php esc_html_e('Question 1 of 100', 'vc-flashcards'); ?></strong>
  </div>
</div>
