<?php
if (!defined('ABSPATH')) {
  exit;
}

$requested_view = isset($_GET['vc_flashcards_view']) ? sanitize_key(wp_unslash($_GET['vc_flashcards_view'])) : '';
$requested_category = isset($_GET['vc_flashcards_category']) ? sanitize_text_field(wp_unslash($_GET['vc_flashcards_category'])) : '';
$initial_detail_key = '';

if ($requested_view === 'detail' && $requested_category !== '' && !empty($categories)) {
  foreach ($categories as $initial_category) {
    $initial_category_name = isset($initial_category['name']) ? (string) $initial_category['name'] : '';
    $initial_category_id = isset($initial_category['id']) ? (int) $initial_category['id'] : 0;
    $initial_category_key = $initial_category_id > 0 ? (string) $initial_category_id : sanitize_title($initial_category_name);

    if (hash_equals($initial_category_key, $requested_category)) {
      $initial_detail_key = $initial_category_key;
      break;
    }
  }
}

$initial_view = $initial_detail_key !== '' ? 'detail' : 'home';
?>
<div
  class="vc-flashcards-library is-booting"
  data-vc-flashcards-library
  data-vc-flashcards-library-initial-view="<?php echo esc_attr($initial_view); ?>"
  data-vc-flashcards-library-initial-category="<?php echo esc_attr($initial_detail_key); ?>"
  data-vc-flashcards-library-current-view="<?php echo esc_attr($initial_view); ?>"
>
  <?php // Main Flashcards home view: category cards and session history. ?>
  <section class="vc-flashcards-library-view vc-flashcards-library-home-view" data-vc-flashcards-library-view="home"<?php echo $initial_view === 'home' ? '' : ' hidden'; ?>>
    <?php if (empty($categories)): ?>
      <article class="vc-flashcards-library-empty">
        <p><?php esc_html_e('No flashcards are available yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <section class="vc-flashcards-library-grid" aria-label="<?php esc_attr_e('Flashcard categories', 'vc-flashcards'); ?>">
        <?php foreach ($categories as $category): ?>
          <?php
          $category_name = isset($category['name']) ? (string) $category['name'] : '';
          $category_progress = isset($category['progress']) ? (int) $category['progress'] : 0;
          $category_total_cards = isset($category['totalCards']) ? (int) $category['totalCards'] : 0;
          $category_subtopic_count = count($category['children'] ?? []);
          $category_id = isset($category['id']) ? (int) $category['id'] : 0;
          $category_key = $category_id > 0 ? (string) $category_id : sanitize_title($category_name);
          ?>
          <article class="vc-flashcards-library-card" data-vc-flashcards-library-category="<?php echo esc_attr($category_key); ?>">
            <h3><?php echo esc_html($category_name); ?></h3>
            <div class="vc-flashcards-library-progress-group">
              <div class="vc-flashcards-library-progress-meta">
                <span><?php esc_html_e('Progress', 'vc-flashcards'); ?></span>
                <strong data-vc-flashcards-library-category-progress><?php echo esc_html((string) $category_progress); ?>%</strong>
              </div>
              <div class="vc-flashcards-library-progress" aria-hidden="true">
                <span data-vc-flashcards-library-category-progress-bar style="width: <?php echo esc_attr((string) $category_progress); ?>%;"></span>
              </div>
            </div>
            <p class="vc-flashcards-library-card-meta">
              <span>
                <?php
                echo esc_html(sprintf(
                  /* translators: %d: subtopic count */
                  _n('%d subtopic', '%d subtopics', $category_subtopic_count, 'vc-flashcards'),
                  $category_subtopic_count
                ));
                ?>
              </span>
              <span aria-hidden="true">&middot;</span>
              <span>
                <?php
                echo esc_html(sprintf(
                  /* translators: %d: card count */
                  _n('%d card', '%d cards', $category_total_cards, 'vc-flashcards'),
                  $category_total_cards
                ));
                ?>
              </span>
            </p>
            <button
              type="button"
              class="vc-flashcards-library-card-action"
              data-vc-flashcards-library-start="<?php echo esc_attr($category_key); ?>"
              data-vc-flashcards-library-topic="<?php echo esc_attr($category_name); ?>"
            >
              <span class="vc-flashcards-library-card-action-label"><?php esc_html_e('Play flashcards', 'vc-flashcards'); ?></span>
              <span class="vc-flashcards-library-card-action-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false" xmlns="http://www.w3.org/2000/svg">
                  <path d="M3.3335 8H12.6668M9.3335 4.66667L12.6668 8L9.3335 11.3333" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </button>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

  <section class="vc-flashcards-session-history" aria-labelledby="vc-flashcards-session-history-title">
    <header class="vc-flashcards-session-history-header">
      <h2 id="vc-flashcards-session-history-title"><?php esc_html_e('Session history', 'vc-flashcards'); ?></h2>
      <p><?php esc_html_e('Your last 5 sessions', 'vc-flashcards'); ?></p>
    </header>

    <?php
    $session_history_items = $session_history;
    ?>

    <?php if (empty($session_history_items)): ?>
      <article class="vc-flashcards-session-history-empty">
        <p><?php esc_html_e('No completed flashcard sessions yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <div class="vc-flashcards-session-history-list">
        <?php foreach ($session_history_items as $session_item): ?>
          <?php $history_topic_class = sanitize_html_class(strtolower((string) ($session_item['topic'] ?? ''))); ?>
          <article class="vc-flashcards-session-history-item vc-flashcards-session-history-item--<?php echo esc_attr($history_topic_class); ?><?php echo empty($session_item['is_strong']) ? ' is-needs-review' : ''; ?>">
            <div class="vc-flashcards-session-history-item-main">
              <span class="vc-flashcards-session-history-item-icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" focusable="false" xmlns="http://www.w3.org/2000/svg">
                  <path d="M4 20H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M6.5 10.5H10.5V20H6.5V10.5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                  <path d="M13.5 5H17.5V20H13.5V5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
              </span>
              <div class="vc-flashcards-session-history-item-copy">
                <h3>
                  <span><?php echo esc_html((string) ($session_item['date'] ?? '')); ?></span>
                  <span aria-hidden="true">&middot;</span>
                  <span><?php echo esc_html((string) ($session_item['topic'] ?? '')); ?></span>
                </h3>
                <p>
                  <?php
                  echo esc_html(sprintf(
                    /* translators: %s: session duration */
                    __('Duration: %s', 'vc-flashcards'),
                    (string) ($session_item['duration'] ?? '0 min')
                  ));
                  ?>
                </p>
              </div>
            </div>
            <div class="vc-flashcards-session-history-item-count">
              <strong>
                <?php
                echo esc_html(sprintf(
                  /* translators: %d: reviewed card count */
                  _n('%d card', '%d cards', (int) ($session_item['cards_reviewed'] ?? 0), 'vc-flashcards'),
                  (int) ($session_item['cards_reviewed'] ?? 0)
                ));
                ?>
              </strong>
              <span><?php esc_html_e('reviewed', 'vc-flashcards'); ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  </section>

  <?php // Flashcards category detail view: intermediate step before starting questions. ?>
  <section class="vc-flashcards-library-view vc-flashcards-library-detail-view" data-vc-flashcards-library-view="detail"<?php echo $initial_view === 'detail' ? '' : ' hidden'; ?>>
    <?php foreach ($categories as $category): ?>
      <?php
      $category_name = isset($category['name']) ? (string) $category['name'] : '';
      $category_id = isset($category['id']) ? (int) $category['id'] : 0;
      $category_key = $category_id > 0 ? (string) $category_id : sanitize_title($category_name);
      $category_total_cards = isset($category['totalCards']) ? (int) $category['totalCards'] : 0;
      $category_children = is_array($category['children'] ?? null) ? $category['children'] : [];
      ?>
      <article class="vc-flashcards-library-detail-panel" data-vc-flashcards-library-detail-panel="<?php echo esc_attr($category_key); ?>"<?php echo $initial_detail_key === $category_key ? '' : ' hidden'; ?>>
        <header class="vc-flashcards-library-detail-header">
          <button type="button" class="vc-flashcards-library-back" data-vc-flashcards-library-back>
            <span class="vc-flashcards-library-back-icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" focusable="false" xmlns="http://www.w3.org/2000/svg">
                <path d="M13 8H3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M7 4L3 8L7 12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span><?php esc_html_e('Back', 'vc-flashcards'); ?></span>
          </button>
          <div class="vc-flashcards-library-detail-title">
            <h2><?php echo esc_html($category_name); ?></h2>
            <p>
              <?php
              echo esc_html(sprintf(
                /* translators: %d: question count */
                _n('%d total question', '%d total questions', $category_total_cards, 'vc-flashcards'),
                $category_total_cards
              ));
              ?>
            </p>
          </div>
        </header>

        <div class="vc-flashcards-library-mode-grid">
          <button
            type="button"
            class="vc-flashcards-library-mode-card"
            data-vc-flashcards-library-launch="random"
            data-vc-flashcards-library-topic-id="<?php echo esc_attr((string) $category_id); ?>"
            data-vc-flashcards-library-topic="<?php echo esc_attr($category_name); ?>"
            data-vc-flashcards-library-total="<?php echo esc_attr((string) $category_total_cards); ?>"
          >
            <span class="vc-flashcards-library-mode-icon" aria-hidden="true">
              <picture>
                <source media="(max-width: 480px)" srcset="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/IconMobil.svg'); ?>">
                <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/aletorio.svg'); ?>" alt="" width="24" height="24">
              </picture>
            </span>
            <span class="vc-flashcards-library-mode-copy">
              <strong><?php esc_html_e('Random practice', 'vc-flashcards'); ?></strong>
              <span><?php echo esc_html(sprintf(__('15 flashcards per round, shuffled from all of %s', 'vc-flashcards'), $category_name)); ?></span>
            </span>
            <span class="vc-flashcards-library-mode-chevron" aria-hidden="true">
              <svg class="icono-flecha" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <path d="M1 19L10 10L1 1" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </button>

        </div>

        <section class="vc-flashcards-library-subtopics" aria-label="<?php esc_attr_e('Flashcards by Subtopic', 'vc-flashcards'); ?>">
          <h3><?php esc_html_e('Flashcards by Subtopic', 'vc-flashcards'); ?></h3>
          <?php if (empty($category_children)): ?>
            <article class="vc-flashcards-library-subtopic-empty">
              <p><?php esc_html_e('No subtopics are available yet.', 'vc-flashcards'); ?></p>
            </article>
          <?php else: ?>
            <div class="vc-flashcards-library-subtopics-list">
              <?php foreach ($category_children as $subtopic): ?>
                <?php
                $subtopic_name = isset($subtopic['name']) ? (string) $subtopic['name'] : '';
                $subtopic_status = isset($subtopic['status']) ? (string) $subtopic['status'] : __('Needs review', 'vc-flashcards');
                $subtopic_status_class = isset($subtopic['statusClass']) ? sanitize_html_class((string) $subtopic['statusClass']) : 'needs-review';
                $subtopic_total = isset($subtopic['totalCards']) ? (int) $subtopic['totalCards'] : 0;
                $subtopic_completed = isset($subtopic['masteredCards']) ? (int) $subtopic['masteredCards'] : 0;
                ?>
                <button
                  type="button"
                  class="vc-flashcards-library-subtopic-item"
                  data-vc-flashcards-library-launch="subcategory"
                  data-vc-flashcards-library-subtopic="<?php echo esc_attr((string) ($subtopic['id'] ?? '')); ?>"
                  data-vc-flashcards-library-topic="<?php echo esc_attr($subtopic_name); ?>"
                  data-vc-flashcards-library-total="<?php echo esc_attr((string) $subtopic_total); ?>"
                >
                  <span class="vc-flashcards-library-subtopic-copy">
                    <span class="vc-flashcards-library-subtopic-title">
                      <strong><?php echo esc_html($subtopic_name); ?></strong>
                      <span class="vc-flashcards-library-subtopic-status vc-flashcards-library-subtopic-status--<?php echo esc_attr($subtopic_status_class); ?>" data-vc-flashcards-library-subtopic-status><?php echo esc_html($subtopic_status); ?></span>
                    </span>
                    <span class="vc-flashcards-library-subtopic-meta">
                      <span>
                        <?php
                        echo esc_html(sprintf(
                          /* translators: %d: question count */
                          _n('%d question', '%d questions', $subtopic_total, 'vc-flashcards'),
                          $subtopic_total
                        ));
                        ?>
                      </span>
                      <span aria-hidden="true">&middot;</span>
                      <span data-vc-flashcards-library-subtopic-completed>
                        <?php
                        echo esc_html(sprintf(
                          /* translators: %d: completed count */
                          __('%d completed', 'vc-flashcards'),
                          $subtopic_completed
                        ));
                        ?>
                      </span>
                    </span>
                  </span>
                  <span class="vc-flashcards-library-subtopic-chevron" aria-hidden="true">
                    <svg class="icono-flecha" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                      <path d="M1 19L10 10L1 1" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </article>
    <?php endforeach; ?>
  </section>

  <?php include VC_FLASHCARDS_DIR . 'templates/partials/flashcards-practice-workspace.php'; ?>
</div>
