<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<section class="vc-exam-view vc-exam-session" data-vc-exam-view="session" hidden aria-labelledby="vc-exam-session-title">
  <div class="vc-mock-test-exam-shell">
    <?php include VC_FLASHCARDS_DIR . 'templates/exam/session-header.php'; ?>

    <div class="vc-exam-session-bar" aria-hidden="true">
      <span data-vc-exam-bar-fill style="width: 0%;"></span>
    </div>

    <article class="vc-exam-card">
      <div class="vc-exam-question-context">
        <p class="vc-exam-question-topic" data-vc-exam-topic-label></p>
        <p class="vc-exam-question-subtopic" data-vc-exam-subtopic-label></p>
      </div>

      <h4 data-vc-exam-question></h4>

      <div class="vc-exam-answers" data-vc-exam-answers></div>

      <div class="vc-exam-session-actions">
        <button
          type="button"
          class="vc-exam-action vc-exam-session-prev"
          data-vc-exam-prev
        >
          <span class="vc-exam-next-icon vc-exam-next-icon--prev" aria-hidden="true">
            <svg class="vc-arrow-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
              <path d="M3 10H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M11 5L16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span><?php esc_html_e('Previous', 'vc-flashcards'); ?></span>
        </button>

        <button
          type="button"
          class="vc-exam-action vc-exam-session-next"
          data-vc-exam-next
        >
          <span data-vc-exam-next-label><?php esc_html_e('Next question', 'vc-flashcards'); ?></span>
          <span class="vc-exam-next-icon" aria-hidden="true">
            <svg class="vc-arrow-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
              <path d="M3 10H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              <path d="M11 5L16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
      </div>
    </article>
  </div>
</section>
