<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<section class="vc-study-sessions-stats" aria-label="<?php esc_attr_e('Exam overview', 'vc-flashcards'); ?>">
  <article class="vc-study-sessions-stat-card">
    <div class="vc-study-sessions-stat-card-content">
      <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Best.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-study-sessions-stat-card-copy">
        <small><?php esc_html_e('Best score', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-best-score><?php echo esc_html((string) ($exam_home_stats['bestScore'] ?? 0)); ?>%</strong>
      </div>
    </div>
  </article>

  <article class="vc-study-sessions-stat-card">
    <div class="vc-study-sessions-stat-card-content">
      <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Average.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-study-sessions-stat-card-copy">
        <small><?php esc_html_e('Average', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-average-score><?php echo esc_html((string) ($exam_home_stats['averageScore'] ?? 0)); ?>%</strong>
      </div>
    </div>
  </article>

  <article class="vc-study-sessions-stat-card">
    <div class="vc-study-sessions-stat-card-content">
      <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Passed.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-study-sessions-stat-card-copy">
        <small><?php esc_html_e('Passed attempts', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-passed-attempts><?php echo esc_html((string) ($exam_home_stats['passedAttempts'] ?? '0/5')); ?></strong>
      </div>
    </div>
  </article>
</section>
