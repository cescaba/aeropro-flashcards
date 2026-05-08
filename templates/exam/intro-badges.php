<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<ul class="vc-exam-intro-badges" aria-label="<?php esc_attr_e('Exam overview', 'vc-flashcards'); ?>">
  <li class="vc-exam-badge">
    <div class="vc-exam-badge-content">
      <span class="vc-exam-badge-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Best.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-exam-badge-copy">
        <small><?php esc_html_e('Best score', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-best-score><?php echo esc_html((string) ($exam_home_stats['bestScore'] ?? 0)); ?>%</strong>
      </div>
    </div>
  </li>

  <li class="vc-exam-badge">
    <div class="vc-exam-badge-content">
      <span class="vc-exam-badge-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Average.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-exam-badge-copy">
        <small><?php esc_html_e('Average', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-average-score><?php echo esc_html((string) ($exam_home_stats['averageScore'] ?? 0)); ?>%</strong>
      </div>
    </div>
  </li>

  <li class="vc-exam-badge vc-exam-badge--passed-attempts">
    <div class="vc-exam-badge-content">
      <span class="vc-exam-badge-icon" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Passed.svg'); ?>" alt="" width="28" height="28">
      </span>
      <div class="vc-exam-badge-copy">
        <small><?php esc_html_e('Passed attempts', 'vc-flashcards'); ?></small>
        <strong data-vc-exam-passed-attempts><?php echo esc_html((string) ($exam_home_stats['passedAttempts'] ?? '0/5')); ?></strong>
      </div>
    </div>
  </li>
</ul>
