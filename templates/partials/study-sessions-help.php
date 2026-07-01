<?php
if (!defined('ABSPATH')) {
  exit;
}

$help_sections = isset($help_sections) && is_array($help_sections) ? $help_sections : [];

if (empty($help_sections)) {
  return;
}
?>
<section class="vc-study-sessions-help" aria-labelledby="vc-study-sessions-help-title">
  <header class="vc-study-sessions-help-header">
    <div>
      <span class="vc-study-sessions-help-kicker"><?php esc_html_e('Help', 'vc-study-sessions'); ?></span>
      <h3 id="vc-study-sessions-help-title"><?php esc_html_e('Study guide', 'vc-study-sessions'); ?></h3>
    </div>
  </header>

  <div class="vc-study-sessions-help-grid">
    <?php foreach ($help_sections as $section): ?>
      <?php
      $section_title = isset($section['title']) ? (string) $section['title'] : '';
      $section_description = isset($section['description']) ? (string) $section['description'] : '';
      $section_items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];

      if ($section_title === '' && $section_description === '' && empty($section_items)) {
        continue;
      }
      ?>
      <article class="vc-study-sessions-help-group">
        <?php if ($section_title !== ''): ?>
          <h4><?php echo esc_html($section_title); ?></h4>
        <?php endif; ?>

        <?php if ($section_description !== ''): ?>
          <p><?php echo esc_html($section_description); ?></p>
        <?php endif; ?>

        <?php if (!empty($section_items)): ?>
          <ul class="vc-study-sessions-help-list">
            <?php foreach ($section_items as $item): ?>
              <?php
              $item_title = isset($item['title']) ? (string) $item['title'] : '';
              $item_description = isset($item['description']) ? (string) $item['description'] : '';

              if ($item_title === '' && $item_description === '') {
                continue;
              }
              ?>
              <li class="vc-study-sessions-help-item">
                <?php if ($item_title !== ''): ?>
                  <strong><?php echo esc_html($item_title); ?></strong>
                <?php endif; ?>

                <?php if ($item_description !== ''): ?>
                  <span><?php echo esc_html($item_description); ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
