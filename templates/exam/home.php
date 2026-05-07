<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<section class="vc-exam-view vc-exam-home" data-vc-exam-view="home" aria-labelledby="vc-exam-home-title">
  <header class="vc-exam-page-header">
    <h1 id="vc-exam-home-title"><?php esc_html_e('A&P Mock Test', 'vc-flashcards'); ?></h1>
    <p><?php esc_html_e('Practice under real exam conditions and track your progress.', 'vc-flashcards'); ?></p>
  </header>

  <div class="vc-exam-intro">
    <?php include VC_FLASHCARDS_DIR . 'templates/exam/intro-badges.php'; ?>
  </div>

  <?php if (empty($exam_categories)): ?>
    <article class="vc-exam-empty">
      <p><?php esc_html_e('No categories or flashcards are available yet.', 'vc-flashcards'); ?></p>
    </article>
  <?php else: ?>
    <div class="vc-exam-category-grid">
      <?php foreach ($exam_categories as $category): ?>
        <?php include VC_FLASHCARDS_DIR . 'templates/exam/category-card.php'; ?>
      <?php endforeach; ?>
    </div>

    <section class="vc-exam-history">
      <section class="vc-exam-history-content" aria-labelledby="vc-exam-history-title">
        <?php include VC_FLASHCARDS_DIR . 'templates/partials/exam-history-content.php'; ?>
      </section>

      <section class="vc-exam-history-test-details" aria-labelledby="vc-exam-test-details-title">
        <h3 id="vc-exam-test-details-title"><?php esc_html_e('Test Details', 'vc-flashcards'); ?></h3>
        <ul class="vc-exam-history-test-details-list">
          <li class="vc-exam-history-test-details-item">
            <span class="vc-exam-history-test-details-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="8" cy="8" r="1.25" fill="currentColor"/>
              </svg>
            </span>
            <span><?php esc_html_e('100 random questions', 'vc-flashcards'); ?></span>
          </li>
          <li class="vc-exam-history-test-details-item">
            <span class="vc-exam-history-test-details-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                <path d="M8 4.75V8l2.25 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span><?php echo esc_html($exam_time_limit_label); ?></span>
          </li>
          <li class="vc-exam-history-test-details-item">
            <span class="vc-exam-history-test-details-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M4 2.75h8v2a4 4 0 0 1-2.5 3.69V10.5H11a1.25 1.25 0 0 1 0 2.5H5a1.25 1.25 0 0 1 0-2.5h1.5V8.44A4 4 0 0 1 4 4.75v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M4 4.25H2.75a1.25 1.25 0 0 0 0 2.5H4m8-2.5h1.25a1.25 1.25 0 0 1 0 2.5H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </span>
            <span><?php esc_html_e('70% minimum to pass', 'vc-flashcards'); ?></span>
          </li>
          <li class="vc-exam-history-test-details-item">
            <span class="vc-exam-history-test-details-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                <path d="M5.5 8.25 7.1 9.85 10.75 6.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span><?php esc_html_e('Answer review allowed', 'vc-flashcards'); ?></span>
          </li>
        </ul>
      </section>
    </section>
  <?php endif; ?>
</section>
