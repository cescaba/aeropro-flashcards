<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<section class="vc-exam-view vc-exam-session" data-vc-exam-view="session" hidden aria-labelledby="vc-exam-session-title">
  <div class="vc-mock-test-exam-shell">
    <?php include VC_FLASHCARDS_DIR . 'templates/exam/session-header.php'; ?>

    <article class="vc-exam-card">
      <div class="vc-exam-question-context">
        <p class="vc-exam-question-topic" data-vc-exam-topic-label></p>
        <p class="vc-exam-question-subtopic" data-vc-exam-subtopic-label></p>
      </div>

      <h4 data-vc-exam-question></h4>

      <button type="button" class="vc-exam-reference-image reference_image" data-vc-exam-reference-image data-vc-exam-reference-image-fallback="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/referencia.png'); ?>" hidden>
        <span class="vc-exam-reference-image-control">
          <span class="vc-exam-reference-image-icon-wrap" aria-hidden="true">
            <svg class="image-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18.9944 2.99902H4.99845C3.8942 2.99902 2.99902 3.8942 2.99902 4.99845V18.9944C2.99902 20.0987 3.8942 20.9938 4.99845 20.9938H18.9944C20.0987 20.9938 20.9938 20.0987 20.9938 18.9944V4.99845C20.9938 3.8942 20.0987 2.99902 18.9944 2.99902Z" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M8.99747 10.9969C10.1017 10.9969 10.9969 10.1017 10.9969 8.99747C10.9969 7.89322 10.1017 6.99805 8.99747 6.99805C7.89322 6.99805 6.99805 7.89322 6.99805 8.99747C6.99805 10.1017 7.89322 10.9969 8.99747 10.9969Z" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M20.994 14.9957L17.9089 11.9106C17.5339 11.5358 17.0254 11.3252 16.4953 11.3252C15.9651 11.3252 15.4566 11.5358 15.0817 11.9106L5.99829 20.994" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="vc-exam-reference-image-copy">
            <span class="vc-exam-reference-image-title"><?php esc_html_e('View reference image', 'vc-flashcards'); ?></span>
            <span class="vc-exam-reference-image-subtitle"><?php esc_html_e('Click to expand', 'vc-flashcards'); ?></span>
          </span>
          <span class="vc-exam-reference-image-chevron" aria-hidden="true">
            <svg class="chevron-right-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </span>
        <span class="vc-exam-reference-image-preview" hidden>
          <img data-vc-exam-reference-image-inline alt="<?php esc_attr_e('Reference image', 'vc-flashcards'); ?>">
        </span>
      </button>

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

      <div class="vc-exam-session-actions-divider" aria-hidden="true"></div>

      <button type="button" class="vc-exam-finish-btn vc-exam-finish-btn--mobile">
        <span><?php esc_html_e('Finish exam', 'vc-flashcards'); ?></span>
      </button>
    </article>
  </div>
</section>
