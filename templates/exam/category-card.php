<?php
if (!defined('ABSPATH')) {
  exit;
}

$category_description = isset($category['description']) ? (string) $category['description'] : '';
$category_is_available = !empty($category['isAvailable']);
$category_key = isset($category['key']) ? (string) $category['key'] : sanitize_title((string) ($category['name'] ?? ''));
?>
<article
  class="vc-exam-category-card"
  aria-labelledby="vc-exam-category-<?php echo esc_attr($category_key); ?>-title"
>
  <div class="vc-exam-category-top">
    <h3 id="vc-exam-category-<?php echo esc_attr($category_key); ?>-title"><?php echo esc_html($category['name']); ?></h3>
  </div>

  <div class="vc-exam-category-meta">
    <p><?php echo esc_html($category_description); ?></p>
  </div>

  <div class="vc-exam-category-action">
    <button
      type="button"
      class="vc-exam-start"
      <?php if ($category_is_available): ?>
        data-vc-exam-start="<?php echo esc_attr((string) $category['id']); ?>"
      <?php else: ?>
        aria-disabled="true"
      <?php endif; ?>
    >
      <span><?php esc_html_e('Start Exam', 'vc-flashcards'); ?></span>
      <span class="vc-exam-start-icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 12 12" fill="none">
          <path d="M1.5 6H10.5M7.5 3L10.5 6L7.5 9" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
    </button>
  </div>
</article>
