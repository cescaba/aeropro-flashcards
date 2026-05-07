<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<section class="vc-exam-summary" data-vc-exam-summary hidden aria-label="<?php esc_attr_e('A&P Mock Test results', 'vc-flashcards'); ?>">
  <div class="vc-exam-summary-backdrop" data-vc-exam-summary-back></div>
  <article class="vc-exam-summary-dialog" aria-label="<?php esc_attr_e('A&P Mock Test results', 'vc-flashcards'); ?>">
    <div class="vc-exam-summary-section vc-exam-summary-section--content">
      <p class="vc-exam-summary-title"><?php esc_html_e('A&P Mock Test results', 'vc-flashcards'); ?></p>

      <div class="vc-exam-result-header">
        <div class="vc-exam-result-badge" data-vc-exam-result-badge aria-hidden="true">
          <img
            data-pass-src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/checkverde.svg'); ?>"
            data-fail-src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/incorrectoHist.svg'); ?>"
            data-vc-exam-result-icon
            src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/checkverde.svg'); ?>"
            width="40"
            height="40"
            alt=""
          >
        </div>
        <p class="vc-exam-summary-kicker" data-vc-exam-result-kicker><?php esc_html_e('Not approved', 'vc-flashcards'); ?></p>
        <p class="vc-exam-result-requirement"><?php esc_html_e('70% is required to pass', 'vc-flashcards'); ?></p>
      </div>

      <div class="vc-exam-summary-metrics">
        <div class="vc-exam-summary-results">
          <div class="vc-exam-summary-result-card vc-exam-summary-result-card--correct">
            <strong class="vc-exam-summary-count vc-exam-summary-count--correct" data-vc-exam-correct-count>0</strong>
            <h3><?php esc_html_e('Correct', 'vc-flashcards'); ?></h3>
          </div>
          <div class="vc-exam-summary-result-card vc-exam-summary-result-card--incorrect">
            <strong class="vc-exam-summary-count vc-exam-summary-count--incorrect" data-vc-exam-incorrect-count>0</strong>
            <h3><?php esc_html_e('Incorrect', 'vc-flashcards'); ?></h3>
          </div>
        </div>
      </div>

      <p class="vc-exam-summary-message"><?php esc_html_e('Keep studying. Practice makes perfect.', 'vc-flashcards'); ?></p>

      <div class="vc-exam-summary-actions">
        <button
          type="button"
          class="vc-exam-summary-action vc-exam-summary-action--back"
          data-vc-exam-summary-back
        ><?php esc_html_e('Back to menu', 'vc-flashcards'); ?></button>

        <button
          type="button"
          class="vc-exam-summary-action vc-exam-summary-action--restart"
          data-vc-exam-retry
        >
          <span class="vc-exam-summary-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/try.svg'); ?>" alt="" width="16" height="16">
          </span>
          <span><?php esc_html_e('Retake Test', 'vc-flashcards'); ?></span>
        </button>
      </div>
    </div>
  </article>
</section>
