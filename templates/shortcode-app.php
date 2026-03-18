<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-flashcards-app" data-categories="<?php echo esc_attr(wp_json_encode($categories)); ?>">
  <section class="vc-flashcards-stats">
    <article class="vc-flashcards-stat-card">
      <small><?php esc_html_e('Correct answers streak', 'vc-flashcards'); ?></small>
      <strong data-vc-flashcards-stat="correct-streak"><?php echo esc_html($stats['correctStreak']); ?></strong>
    </article>
    <article class="vc-flashcards-stat-card">
      <small><?php esc_html_e('Connected days streak', 'vc-flashcards'); ?></small>
      <strong data-vc-flashcards-stat="study-streak"><?php echo esc_html($stats['studyStreak']); ?>d</strong>
    </article>
    <article class="vc-flashcards-stat-card">
      <small><?php esc_html_e('Cards reviewed', 'vc-flashcards'); ?></small>
      <strong data-vc-flashcards-stat="coverage"><?php echo esc_html($stats['reviewedCoverage']); ?>%</strong>
    </article>
  </section>

  <p class="vc-flashcards-feedback" data-vc-flashcards-feedback hidden></p>

  <section class="vc-flashcards-view" data-vc-flashcards-view="home">
    <header class="vc-flashcards-section-header">
      <div>
        <h2><?php esc_html_e('Choose your flashcards category', 'vc-flashcards'); ?></h2>
        <p>
          <?php
          printf(
            /* translators: %d: total flashcards */
            esc_html__('%d flashcards are available. Pick a category to configure your study session.', 'vc-flashcards'),
            (int) $published_flashcards
          );
          ?>
        </p>
      </div>
    </header>

    <?php if (empty($categories)): ?>
      <article class="vc-flashcards-empty">
        <p><?php esc_html_e('No categories or flashcards are available yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <div class="vc-flashcards-category-grid">
        <?php foreach ($categories as $category): ?>
          <article class="vc-flashcards-category-card">
            <div class="vc-flashcards-category-top">
              <h3><?php echo esc_html($category['name']); ?></h3>
            </div>
            <div class="vc-flashcards-category-progress">
              <span><?php esc_html_e('Progress', 'vc-flashcards'); ?></span>
              <strong><?php echo esc_html((string) $category['progress']); ?>%</strong>
            </div>
            <div class="vc-flashcards-topic-bar" aria-hidden="true">
              <span style="width: <?php echo esc_attr((string) $category['progress']); ?>%;"></span>
            </div>
            <div class="vc-flashcards-category-meta">
              <span><?php echo esc_html($category['description']); ?></span>
              <button type="button" class="vc-flashcards-start vc-flashcards-start--full" data-vc-flashcards-open-category="<?php echo esc_attr((string) $category['id']); ?>">
                <span><?php esc_html_e('Study', 'vc-flashcards'); ?></span>
                <svg width="6" height="11" viewBox="0 0 6 11" fill="none" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                  <path d="M0.666504 0.666565L5.33241 5.33247L0.666504 9.99837" stroke="white" stroke-width="1.33312" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="vc-flashcards-view" data-vc-flashcards-view="detail" hidden>
    <header class="vc-flashcards-detail-header">
      <button type="button" class="vc-flashcards-back" data-vc-flashcards-back><?php esc_html_e('Back', 'vc-flashcards'); ?></button>
      <div>
        <h2 data-vc-flashcards-category-title></h2>
        <p data-vc-flashcards-category-meta></p>
      </div>
    </header>

    <div class="vc-flashcards-config-grid">
      <article class="vc-flashcards-config-card">
        <div class="vc-flashcards-config-copy">
          <h3><?php esc_html_e('Study full category', 'vc-flashcards'); ?></h3>
          <p><?php esc_html_e('Study the complete category in sequential order.', 'vc-flashcards'); ?></p>
        </div>
        <button type="button" class="vc-flashcards-start" data-vc-flashcards-launch="category"><?php esc_html_e('Study', 'vc-flashcards'); ?></button>
      </article>

      <article class="vc-flashcards-config-card">
        <div class="vc-flashcards-config-copy">
          <h3><?php esc_html_e('Random practice', 'vc-flashcards'); ?></h3>
          <p><?php esc_html_e('Mix cards from this category for a random practice session.', 'vc-flashcards'); ?></p>
        </div>
        <button type="button" class="vc-flashcards-start vc-flashcards-start--secondary" data-vc-flashcards-launch="random"><?php esc_html_e('Study', 'vc-flashcards'); ?></button>
      </article>
    </div>

    <article class="vc-flashcards-subtopics-card">
      <div class="vc-flashcards-subtopics-header">
        <div>
          <h3><?php esc_html_e('Study by subtopic', 'vc-flashcards'); ?></h3>
          <p><?php esc_html_e('Pick a specific subtopic to focus your practice.', 'vc-flashcards'); ?></p>
        </div>
      </div>
      <div class="vc-flashcards-subtopics-list" data-vc-flashcards-subtopics></div>
    </article>
  </section>

  <section class="vc-flashcards-session" data-vc-flashcards-session hidden>
    <div class="vc-flashcards-session-header">
      <div>
        <p class="vc-flashcards-session-kicker" data-vc-flashcards-kicker></p>
        <h3 data-vc-flashcards-progress-title><?php esc_html_e('Card 1', 'vc-flashcards'); ?></h3>
      </div>
      <strong data-vc-flashcards-progress-count>1 / 10</strong>
    </div>

    <article class="vc-flashcards-card">
      <h4 data-vc-flashcards-question></h4>
      <div class="vc-flashcards-answers" data-vc-flashcards-answers></div>
      <div class="vc-flashcards-explanation" data-vc-flashcards-explanation hidden></div>
      <div class="vc-flashcards-actions">
        <button type="button" class="vc-flashcards-reveal" data-vc-flashcards-reveal><?php esc_html_e("Don't know the answer? Reveal answer", 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-back" data-vc-flashcards-explanation-toggle hidden><?php esc_html_e('View detailed explanation', 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-next" data-vc-flashcards-next hidden disabled><?php esc_html_e('Next question', 'vc-flashcards'); ?></button>
      </div>
    </article>
  </section>

  <section class="vc-flashcards-summary" data-vc-flashcards-summary hidden>
    <div class="vc-flashcards-summary-box">
      <p class="vc-flashcards-summary-kicker"><?php esc_html_e('Session complete', 'vc-flashcards'); ?></p>
      <h3 data-vc-flashcards-summary-score>0%</h3>
      <p data-vc-flashcards-summary-copy></p>
      <div class="vc-flashcards-summary-actions">
        <button type="button" class="vc-flashcards-start" data-vc-flashcards-restart><?php esc_html_e('Study again', 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-back" data-vc-flashcards-summary-back><?php esc_html_e('Back to category', 'vc-flashcards'); ?></button>
      </div>
    </div>
  </section>

  <div class="vc-flashcards-modal" data-vc-flashcards-modal hidden>
    <div class="vc-flashcards-modal-backdrop" data-vc-flashcards-close></div>
    <div class="vc-flashcards-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vc-flashcards-modal-title">
      <button type="button" class="vc-flashcards-modal-close" data-vc-flashcards-close aria-label="<?php esc_attr_e('Close', 'vc-flashcards'); ?>">×</button>
      <h3 id="vc-flashcards-modal-title" data-vc-flashcards-modal-title></h3>
      <p class="vc-flashcards-modal-copy" data-vc-flashcards-modal-copy></p>

      <div class="vc-flashcards-modal-count">
        <strong data-vc-flashcards-count-display>20</strong>
        <span><?php esc_html_e('Selected cards', 'vc-flashcards'); ?></span>
      </div>

      <div class="vc-flashcards-modal-slider-wrap">
        <div class="vc-flashcards-modal-range-meta">
          <span><?php esc_html_e('Number of cards', 'vc-flashcards'); ?></span>
          <strong data-vc-flashcards-range-label>10 - 50</strong>
        </div>
        <input type="range" min="1" max="50" value="20" step="1" data-vc-flashcards-range>
      </div>

      <div class="vc-flashcards-modal-options" data-vc-flashcards-options></div>

      <div class="vc-flashcards-modal-actions">
        <button type="button" class="vc-flashcards-back" data-vc-flashcards-close><?php esc_html_e('Cancel', 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-start" data-vc-flashcards-confirm><?php esc_html_e('Start', 'vc-flashcards'); ?></button>
      </div>
    </div>
  </div>
</div>
