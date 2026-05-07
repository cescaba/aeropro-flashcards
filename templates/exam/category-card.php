<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<article
  class="vc-exam-category-card"
  aria-labelledby="vc-exam-category-<?php echo esc_attr((string) $category['id']); ?>-title"
>
  <div class="vc-exam-category-top">
    <h3 id="vc-exam-category-<?php echo esc_attr((string) $category['id']); ?>-title"><?php echo esc_html($category['name']); ?></h3>
  </div>

  <div class="vc-exam-category-meta">
    <p>
      <?php echo esc_html(
        sprintf(
          /* translators: %d: subtopic count */
          _n('%d subtopic', '%d subtopics', $category['subtopicCount'], 'vc-flashcards'),
          $category['subtopicCount']
        ) . ' · ' . sprintf(
          /* translators: %d: total cards */
          _n('%d card available', '%d cards available', $category['totalCards'], 'vc-flashcards'),
          $category['totalCards']
        )
      ); ?>
    </p>
  </div>

  <div class="vc-exam-category-action">
    <button
      type="button"
      class="vc-exam-start"
      data-vc-exam-start="<?php echo esc_attr((string) $category['id']); ?>"
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
