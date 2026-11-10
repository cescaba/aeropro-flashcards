<?php

if (!defined('ABSPATH')) {
  exit;
}

?>
<?php if (!$item): ?>
  <div class="notice notice-error">
    <p><?php esc_html_e('Feedback not found.', 'vc-flashcards'); ?></p>
  </div>
  <p>
    <a class="button" href="<?php echo esc_url($admin_url); ?>"><?php esc_html_e('Back to feedback', 'vc-flashcards'); ?></a>
  </p>
  <?php return; ?>
<?php endif; ?>

<p>
  <a class="button" href="<?php echo esc_url($admin_url); ?>"><?php esc_html_e('Back to feedback', 'vc-flashcards'); ?></a>
  <a class="button button-secondary" href="<?php echo esc_url($this->get_mark_seen_url($feedback_id)); ?>"><?php esc_html_e('Mark as seen', 'vc-flashcards'); ?></a>
  <a class="button button-primary" href="<?php echo esc_url($this->get_download_url($feedback_id)); ?>"><?php esc_html_e('Download CSV', 'vc-flashcards'); ?></a>
</p>

<div class="postbox">
  <div class="inside">
    <h2><?php echo esc_html(sprintf(__('Feedback #%d', 'vc-flashcards'), $feedback_id)); ?></h2>

    <table class="widefat striped">
      <tbody>
        <tr>
          <th scope="row"><?php esc_html_e('Type', 'vc-flashcards'); ?></th>
          <td><?php echo esc_html(ucfirst((string) $item['feedback_type'])); ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Status', 'vc-flashcards'); ?></th>
          <td><?php echo esc_html($this->format_status((string) $item['status'])); ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('User', 'vc-flashcards'); ?></th>
          <td><?php echo esc_html($this->format_user_label((int) $item['user_id'])); ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Created', 'vc-flashcards'); ?></th>
          <td><?php echo esc_html((string) $item['created_at']); ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Page URL', 'vc-flashcards'); ?></th>
          <td><?php echo $this->format_url((string) $item['page_url']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Referrer URL', 'vc-flashcards'); ?></th>
          <td><?php echo $this->format_url((string) $item['referrer_url']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('Screenshot', 'vc-flashcards'); ?></th>
          <td>
            <?php if ((string) $item['screenshot_url'] !== ''): ?>
              <p>
                <a href="<?php echo esc_url((string) $item['screenshot_url']); ?>" target="_blank" rel="noopener noreferrer">
                  <img
                    src="<?php echo esc_url((string) $item['screenshot_url']); ?>"
                    alt="<?php esc_attr_e('Feedback screenshot', 'vc-flashcards'); ?>"
                    style="display:block;max-width:420px;width:100%;height:auto;border:1px solid #dcdcde;border-radius:6px;background:#fff;"
                  >
                </a>
              </p>
              <?php echo $this->format_url((string) $item['screenshot_url']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else: ?>
              <?php esc_html_e('None', 'vc-flashcards'); ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th scope="row"><?php esc_html_e('User agent', 'vc-flashcards'); ?></th>
          <td><code><?php echo esc_html((string) $item['user_agent']); ?></code></td>
        </tr>
      </tbody>
    </table>

    <h3><?php esc_html_e('Message', 'vc-flashcards'); ?></h3>
    <pre><?php echo esc_html((string) $item['message']); ?></pre>
  </div>
</div>
