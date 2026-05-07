<?php

if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-exam-history-header">
  <h2 id="vc-exam-history-title"><?php esc_html_e('Exam History', 'vc-flashcards'); ?></h2>
  <p><?php echo esc_html($exam_history_subtitle); ?></p>
</div>

<?php if (empty($exam_history)): ?>
  <article class="vc-exam-empty">
  <p><?php esc_html_e('No completed exams yet.', 'vc-flashcards'); ?></p>
  </article>
<?php else: ?>
  <div class="vc-exam-history-list">
    <?php foreach ($exam_history as $exam_item): ?>
      <article class="vc-exam-history-item<?php echo !empty($exam_item['passed']) ? ' is-passed' : ' is-failed'; ?>">
        <div class="vc-exam-history-item-copy">
          <span class="vc-exam-history-item-icon" aria-hidden="true">
            <img src="<?php echo esc_url($exam_item['icon_url']); ?>" alt="" width="20" height="20">
          </span>
          <div class="vc-exam-history-item-copy-text">
            <strong><?php echo esc_html($exam_item['date']); ?></strong>
            <span><?php echo esc_html__('Duration:', 'vc-flashcards'); ?> <?php echo esc_html($exam_item['duration']); ?></span>
          </div>
        </div>
        <div class="vc-exam-history-item-score">
          <div class="vc-exam-history-item-score-copy">
            <strong><?php echo esc_html($exam_item['score']); ?></strong>
            <small><?php echo esc_html($exam_item['status_label']); ?></small>
          </div>
          <div class="vc-exam-history-item-score-icon" aria-hidden="true">
            <img
              src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/' . (!empty($exam_item['passed']) ? 'arriba.svg' : 'abajo.svg')); ?>"
              alt=""
            >
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
