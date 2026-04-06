<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-exam-app">

  <?php /* Feedback area shared by all views. */ ?>
  <p class="vc-flashcards-feedback" data-vc-exam-feedback hidden></p>

  <?php /* ── HOME: category selection ─────────────────────────────── */ ?>
  <section class="vc-exam-view" data-vc-exam-view="home">

    <div class="vc-exam-intro">
      <h2><?php esc_html_e('Exam Simulator', 'vc-flashcards'); ?></h2>
      <p><?php esc_html_e('Select a category to start your final exam simulation.', 'vc-flashcards'); ?></p>
      <div class="vc-exam-intro-badges">
        <span class="vc-exam-badge">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <?php esc_html_e('2 h 30 min', 'vc-flashcards'); ?>
        </span>
        <span class="vc-exam-badge">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><rect x="1" y="2" width="12" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M4 7h6M4 9.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          <?php esc_html_e('100 questions', 'vc-flashcards'); ?>
        </span>
        <span class="vc-exam-badge">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1.5l1.545 3.13 3.455.502-2.5 2.437.59 3.44L7 9.379 3.91 10.01l.59-3.44L2 4.132l3.455-.502L7 1.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
          <?php esc_html_e('70% to pass', 'vc-flashcards'); ?>
        </span>
      </div>
    </div>

    <?php if (empty($exam_categories)): ?>
      <article class="vc-flashcards-empty">
        <p><?php esc_html_e('No categories or flashcards are available yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <div class="vc-flashcards-category-grid">
        <?php foreach ($exam_categories as $category): ?>
          <article class="vc-flashcards-category-card vc-exam-category-card">

            <div class="vc-flashcards-category-top">
              <h3><?php echo esc_html($category['name']); ?></h3>
            </div>

            <div class="vc-exam-category-meta">
              <span>
                <?php echo esc_html(
                  sprintf(
                    /* translators: 1: subtopic count, 2: total cards */
                    _n('%2$d subtopic', '%1$d subtopics', $category['subtopicCount'], 'vc-flashcards'),
                    $category['subtopicCount'],
                    $category['subtopicCount']
                  ) . ' · ' . sprintf(
                    /* translators: %d: total cards */
                    _n('%d card available', '%d cards available', $category['totalCards'], 'vc-flashcards'),
                    $category['totalCards']
                  )
                ); ?>
              </span>
            </div>

            <div class="vc-exam-category-pill">
              <svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              <?php esc_html_e('Distributed across subtopics', 'vc-flashcards'); ?>
            </div>

            <div class="vc-flashcards-category-meta">
              <button
                type="button"
                class="vc-flashcards-start vc-flashcards-start--full vc-exam-start-btn"
                data-vc-exam-start="<?php echo esc_attr((string) $category['id']); ?>"
              >
                <span><?php esc_html_e('Start Exam', 'vc-flashcards'); ?></span>
                <span class="vc-flashcards-start-icon" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 12 12" fill="none">
                    <path d="M1.5 6H10.5M7.5 3L10.5 6L7.5 9" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              </button>
            </div>

          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </section>

  <?php /* ── SESSION: question answering + timer ─────────────────── */ ?>
  <section class="vc-exam-view" data-vc-exam-view="session" hidden>

    <div class="vc-exam-session-header">
      <button type="button" class="vc-flashcards-back vc-exam-abandon-btn" data-vc-exam-abandon>
        <span class="vc-flashcards-back-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 12 12" fill="none">
            <path d="M10.5 6H1.5M4.5 3L1.5 6L4.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span><?php esc_html_e('Abandon', 'vc-flashcards'); ?></span>
      </button>

      <div class="vc-exam-session-progress">
        <strong data-vc-exam-progress><?php esc_html_e('Question 1 of 100', 'vc-flashcards'); ?></strong>
      </div>

      <div class="vc-exam-timer" data-vc-exam-timer aria-live="polite" aria-label="<?php esc_attr_e('Time remaining', 'vc-flashcards'); ?>">
        0:15:00
      </div>
    </div>

    <div class="vc-flashcards-session-bar" aria-hidden="true">
      <span data-vc-exam-bar-fill style="width: 0%;"></span>
    </div>

    <article class="vc-flashcards-card vc-exam-card">
      <div class="vc-flashcards-session-context">
        <p class="vc-flashcards-session-topic" data-vc-exam-topic-label></p>
        <p class="vc-flashcards-session-subtopic" data-vc-exam-subtopic-label></p>
      </div>

      <h4 data-vc-exam-question></h4>

      <div class="vc-flashcards-answers" data-vc-exam-answers></div>

      <button
        type="button"
        class="vc-flashcards-session-action vc-flashcards-session-next"
        data-vc-exam-next
        hidden
        disabled
      >
        <span data-vc-exam-next-label><?php esc_html_e('Next question', 'vc-flashcards'); ?></span>
        <span class="vc-flashcards-next-icon" aria-hidden="true">&gt;</span>
      </button>
    </article>

  </section>

  <?php /* ── SUMMARY MODAL: results popup ──────────────────────────── */ ?>
  <div class="vc-exam-modal" data-vc-exam-modal hidden role="dialog" aria-modal="true" aria-labelledby="vc-exam-modal-title">
    <div class="vc-exam-modal-backdrop" data-vc-exam-modal-backdrop></div>
    <div class="vc-exam-modal-dialog">

      <button type="button" class="vc-exam-modal-close" data-vc-exam-summary-back aria-label="<?php esc_attr_e('Close results', 'vc-flashcards'); ?>">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/cerrar.svg'); ?>" alt="" width="20" height="20">
      </button>

      <div class="vc-exam-result-badge" data-vc-exam-result-badge aria-hidden="true">
        <span data-vc-exam-result-icon>&#10003;</span>
      </div>

      <p class="vc-flashcards-summary-kicker" id="vc-exam-modal-title" data-vc-exam-result-kicker><?php esc_html_e('Exam complete!', 'vc-flashcards'); ?></p>

      <div class="vc-flashcards-summary-top">
        <div class="vc-flashcards-summary-results">
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--correct">
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--correct" data-vc-exam-correct-count>0</strong>
            <h3><?php esc_html_e('Correct', 'vc-flashcards'); ?></h3>
          </div>
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--incorrect">
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--incorrect" data-vc-exam-incorrect-count>0</strong>
            <h3><?php esc_html_e('Incorrect', 'vc-flashcards'); ?></h3>
          </div>
        </div>

        <div class="vc-flashcards-summary-accuracy-card">
          <span class="vc-flashcards-summary-accuracy-title"><?php esc_html_e('Score', 'vc-flashcards'); ?></span>
          <strong data-vc-exam-score-percent>0%</strong>
          <span class="vc-flashcards-summary-accuracy-bar">
            <span data-vc-exam-score-bar style="width: 0%;"></span>
          </span>
        </div>
      </div>

      <div class="vc-exam-result-meta">
        <span>
          <?php esc_html_e('Required: 70%', 'vc-flashcards'); ?>
          &nbsp;·&nbsp;
          <?php esc_html_e('Time used:', 'vc-flashcards'); ?>
          <strong data-vc-exam-time-used>—</strong>
        </span>
      </div>

      <h3 class="vc-flashcards-summary-message" data-vc-exam-result-message>
        <?php esc_html_e('Keep studying. Practice makes perfect.', 'vc-flashcards'); ?>
      </h3>

      <div class="vc-flashcards-summary-actions">
        <button
          type="button"
          class="vc-flashcards-back vc-flashcards-summary-action vc-flashcards-summary-action--back"
          data-vc-exam-summary-back
        ><?php esc_html_e('Back to menu', 'vc-flashcards'); ?></button>

        <button
          type="button"
          class="vc-flashcards-start vc-flashcards-summary-action vc-flashcards-summary-action--restart"
          data-vc-exam-retry
        >
          <span class="vc-flashcards-summary-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/repeat-session.svg'); ?>" alt="" width="16" height="16">
          </span>
          <span><?php esc_html_e('Try again', 'vc-flashcards'); ?></span>
        </button>
      </div>

    </div>
  </div>

</div>
