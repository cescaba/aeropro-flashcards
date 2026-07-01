<?php

if (!defined('ABSPATH')) {
  exit;
}

?>
<p><?php esc_html_e('Review messages sent from the floating feedback widget.', 'vc-flashcards'); ?></p>

<table class="widefat fixed striped">
  <thead>
    <tr>
      <th scope="col"><?php esc_html_e('ID', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('Type', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('Preview', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('User', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('Status', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('Created', 'vc-flashcards'); ?></th>
      <th scope="col"><?php esc_html_e('Actions', 'vc-flashcards'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($items)): ?>
      <tr>
        <td colspan="7"><?php esc_html_e('No feedback has been submitted yet.', 'vc-flashcards'); ?></td>
      </tr>
    <?php endif; ?>

    <?php foreach ($items as $item): ?>
      <?php
      $item_id = (int) $item['id'];
      $preview = wp_html_excerpt((string) $item['message'], 90, '...');
      ?>
      <tr>
        <td><?php echo esc_html((string) $item_id); ?></td>
        <td><?php echo esc_html(ucfirst((string) $item['feedback_type'])); ?></td>
        <td><?php echo esc_html($preview); ?></td>
        <td><?php echo esc_html($this->format_user_label((int) $item['user_id'])); ?></td>
        <td><?php echo esc_html($this->format_status((string) $item['status'])); ?></td>
        <td><?php echo esc_html((string) $item['created_at']); ?></td>
        <td>
          <a class="button button-small" href="<?php echo esc_url($this->get_detail_url($item_id)); ?>"><?php esc_html_e('View detail', 'vc-flashcards'); ?></a>
          <a class="button button-small" href="<?php echo esc_url($this->get_mark_seen_url($item_id)); ?>"><?php esc_html_e('Seen', 'vc-flashcards'); ?></a>
          <a class="button button-small" href="<?php echo esc_url($this->get_download_url($item_id)); ?>"><?php esc_html_e('Download CSV', 'vc-flashcards'); ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
