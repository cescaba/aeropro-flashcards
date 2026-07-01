<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-flashcards-help-widget" data-vc-flashcards-help-widget>
  <section class="vc-flashcards-feedback-panel" data-vc-flashcards-feedback-panel aria-labelledby="vc-flashcards-feedback-title" hidden>
    <header class="vc-flashcards-feedback-header">
      <div class="vc-flashcards-feedback-header-copy">
        <h2 id="vc-flashcards-feedback-title" class="vc-flashcards-feedback-title"><?php esc_html_e('Help us improve Aeropro', 'vc-flashcards'); ?></h2>
        <p class="vc-flashcards-feedback-description"><?php esc_html_e("We're in launch, tell us about a bug or share an idea.", 'vc-flashcards'); ?></p>
      </div>
      <div class="vc-flashcards-feedback-header-action">
        <svg class="vc-flashcards-feedback-close" data-vc-flashcards-help-close role="button" tabindex="0" aria-label="<?php esc_attr_e('Close feedback form', 'vc-flashcards'); ?>" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
          <path d="M5 5L15 15M15 5L5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </div>
    </header>

    <form class="vc-flashcards-feedback-form" data-vc-flashcards-feedback-form>
      <input type="hidden" name="rendered_at" value="<?php echo esc_attr((string) time()); ?>">
      <label class="vc-flashcards-feedback-honeypot" aria-hidden="true">
        <span><?php esc_html_e('Website', 'vc-flashcards'); ?></span>
        <input type="text" name="vc_flashcards_feedback_website" tabindex="-1" autocomplete="off">
      </label>
      <fieldset class="vc-flashcards-feedback-fieldset">
        <legend class="vc-flashcards-feedback-legend"><?php esc_html_e("What's this about?", 'vc-flashcards'); ?></legend>
        <div class="vc-flashcards-feedback-options">
          <label>
            <input type="radio" name="feedback_type" value="bug" checked>
            <span><?php esc_html_e('Bug', 'vc-flashcards'); ?></span>
          </label>
          <label>
            <input type="radio" name="feedback_type" value="suggestion">
            <span><?php esc_html_e('Suggestion', 'vc-flashcards'); ?></span>
          </label>
          <label>
            <input type="radio" name="feedback_type" value="other">
            <span><?php esc_html_e('Other', 'vc-flashcards'); ?></span>
          </label>
        </div>
      </fieldset>

      <label class="vc-flashcards-feedback-textarea-label" for="vc-flashcards-feedback-message">
        <?php esc_html_e('Tell us more', 'vc-flashcards'); ?>
      </label>
      <textarea id="vc-flashcards-feedback-message" class="vc-flashcards-feedback-textarea" name="message" rows="4" placeholder="<?php esc_attr_e("Describe what happened or what you'd like to see", 'vc-flashcards'); ?>"></textarea>

      <label class="vc-flashcards-feedback-upload-label" for="vc-flashcards-feedback-screenshot">
        <?php esc_html_e('Attach a screenshot (optional)', 'vc-flashcards'); ?>
      </label>
      <label class="vc-flashcards-feedback-dropzone" for="vc-flashcards-feedback-screenshot" data-vc-flashcards-feedback-dropzone>
        <input id="vc-flashcards-feedback-screenshot" name="screenshot" type="file" accept="image/*">
        <img class="vc-flashcards-feedback-preview" data-vc-flashcards-feedback-preview src="" alt="" hidden>
        <svg data-vc-flashcards-feedback-upload-icon width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <path d="M18.9944 2.99902H4.99845C3.8942 2.99902 2.99902 3.8942 2.99902 4.99845V18.9944C2.99902 20.0987 3.8942 20.9938 4.99845 20.9938H18.9944C20.0987 20.9938 20.9938 20.0987 20.9938 18.9944V4.99845C20.9938 3.8942 20.0987 2.99902 18.9944 2.99902Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M8.99747 10.9969C10.1017 10.9969 10.9969 10.1017 10.9969 8.99747C10.9969 7.89322 10.1017 6.99805 8.99747 6.99805C7.89322 6.99805 6.99805 7.89322 6.99805 8.99747C6.99805 10.1017 7.89322 10.9969 8.99747 10.9969Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M20.994 14.9957L17.9089 11.9106C17.5339 11.5358 17.0254 11.3252 16.4953 11.3252C15.9651 11.3252 15.4566 11.5358 15.0817 11.9106L5.99829 20.994" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span data-vc-flashcards-feedback-file-label><?php esc_html_e('Click or drag an image here', 'vc-flashcards'); ?></span>
      </label>

      <button type="submit" class="vc-flashcards-feedback-submit">
        <span><?php esc_html_e('Send feedback', 'vc-flashcards'); ?></span>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <path d="M17.5 2.5L8.75 11.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          <path d="M17.5 2.5L12.5 17.5L8.75 11.25L2.5 7.5L17.5 2.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <p class="vc-flashcards-feedback-status" data-vc-flashcards-feedback-status hidden></p>
    </form>
  </section>

  <button type="button" class="vc-flashcards-help-fab" data-vc-flashcards-help-toggle aria-label="<?php esc_attr_e('Open feedback form', 'vc-flashcards'); ?>" aria-expanded="false">
    <svg class="vc-flashcards-help-fab-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30" fill="none" aria-hidden="true" focusable="false">
      <path d="M12 3C7.03 3 3 6.81 3 11.5c0 2.29.98 4.37 2.58 5.9L4.5 21l4.02-1.62c1.08.41 2.25.62 3.48.62 4.97 0 9-3.81 9-8.5S16.97 3 12 3z" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
  </button>
</div>
