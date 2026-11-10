<?php
/**
 * Study Sessions: dedicated Study by ACS Code workspace.
 *
 * This template is intentionally separated from shortcode-app.php because the
 * ACS Code experience will have its own larger design and behavior surface.
 *
 * @package VC_Flashcards
 */

if (!defined('ABSPATH')) {
  exit;
}
?>

<div class="vc-study-sessions-acs-code-modal" data-vc-study-sessions-acs-modal hidden>
  <div class="vc-study-sessions-acs-code-backdrop" data-vc-study-sessions-acs-close></div>
  <div class="vc-study-sessions-acs-code-workspace" role="dialog" aria-modal="true" aria-labelledby="vc-study-sessions-acs-code-title">
    <div class="vc-study-sessions-acs-code-header">
      <div class="vc-study-sessions-acs-code-header-copy">
        <h3 id="vc-study-sessions-acs-code-title" data-vc-study-sessions-acs-modal-title><?php esc_html_e('Study by ACS Code', 'vc-study-sessions'); ?></h3>
        <p class="vc-study-sessions-acs-code-copy" data-vc-study-sessions-acs-modal-copy></p>
        <p class="vc-study-sessions-acs-code-intro"><?php esc_html_e('Pick one or more areas. Your session is built from those cards.', 'vc-study-sessions'); ?></p>
      </div>
      <button type="button" class="vc-study-sessions-close-button vc-study-sessions-acs-code-close" data-vc-study-sessions-acs-close aria-label="<?php esc_attr_e('Close', 'vc-study-sessions'); ?>">
        <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
          <path d="M5 5L15 15M15 5L5 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
        </svg>
      </button>
    </div>

    <label class="vc-study-sessions-acs-code-search">
      <span class="vc-study-sessions-acs-code-search-icon" aria-hidden="true">
        <svg viewBox="0 0 18 18" focusable="false">
          <path d="M8 14.5A6.5 6.5 0 1 0 8 1.5a6.5 6.5 0 0 0 0 13ZM12.5 12.5 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
      </span>
      <input type="search" placeholder="<?php esc_attr_e('Search code or topic...', 'vc-study-sessions'); ?>" aria-label="<?php esc_attr_e('Search code or topic', 'vc-study-sessions'); ?>" data-vc-study-sessions-acs-search>
    </label>

    <div class="vc-study-sessions-acs-code-tools">
      <button type="button" class="vc-study-sessions-acs-code-weak-areas" data-vc-study-sessions-acs-weak-areas aria-pressed="false">
        <span aria-hidden="true"></span>
        <?php esc_html_e('Select my weak areas', 'vc-study-sessions'); ?>
      </button>
      <button type="button" class="vc-study-sessions-acs-code-clear" data-vc-study-sessions-acs-clear><?php esc_html_e('Clear', 'vc-study-sessions'); ?></button>
    </div>

    <div class="vc-study-sessions-acs-code-list-frame">
      <div class="vc-study-sessions-acs-code-list" data-vc-study-sessions-acs-code-list aria-label="<?php esc_attr_e('ACS Code areas', 'vc-study-sessions'); ?>"></div>
    </div>

    <div class="vc-study-sessions-acs-code-js-hooks" aria-hidden="true">
      <strong data-vc-study-sessions-acs-count-display>20</strong>
      <strong data-vc-study-sessions-acs-range-label>10 - 50</strong>
      <input type="range" min="1" max="50" value="20" step="1" tabindex="-1" data-vc-study-sessions-acs-range>
      <div data-vc-study-sessions-acs-options></div>
    </div>

    <div class="vc-study-sessions-acs-code-footer">
      <p class="vc-study-sessions-acs-code-summary" data-vc-study-sessions-acs-code-summary></p>
      <div class="vc-study-sessions-acs-code-actions">
        <button type="button" class="vc-study-sessions-acs-code-cancel" data-vc-study-sessions-acs-close><?php esc_html_e('Cancel', 'vc-study-sessions'); ?></button>
        <button type="button" class="vc-study-sessions-acs-code-confirm" data-vc-study-sessions-acs-confirm><?php esc_html_e('Start', 'vc-study-sessions'); ?></button>
      </div>
    </div>
  </div>
</div>
