<?php

if (!defined('ABSPATH')) {
  exit;
}

class VC_Flashcards_Feedback_Admin {
  private const PAGE_SLUG = 'vc-flashcards-feedback';

  public function register(): void {
    add_action('admin_menu', [$this, 'register_menu']);
    add_action('admin_post_vc_flashcards_mark_feedback_seen', [$this, 'handle_mark_seen']);
    add_action('admin_post_vc_flashcards_download_feedback', [$this, 'handle_download']);
  }

  public function register_menu(): void {
    add_menu_page(
      __('Feedback', 'vc-flashcards'),
      __('Feedback', 'vc-flashcards'),
      'manage_options',
      self::PAGE_SLUG,
      [$this, 'render_page'],
      'dashicons-feedback',
      29
    );
  }

  public function render_page(): void {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'vc-flashcards'));
    }

    $feedback_id = isset($_GET['feedback_id']) ? absint($_GET['feedback_id']) : 0;
    $notice = isset($_GET['vc_feedback_notice']) ? sanitize_key(wp_unslash((string) $_GET['vc_feedback_notice'])) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Feedback', 'vc-flashcards') . '</h1>';

    if ($notice === 'seen') {
      echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Feedback marked as seen.', 'vc-flashcards') . '</p></div>';
    }

    if ($feedback_id > 0) {
      $this->render_detail($feedback_id);
    } else {
      $this->render_list();
    }

    echo '</div>';
  }

  public function handle_mark_seen(): void {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to update feedback.', 'vc-flashcards'));
    }

    $feedback_id = isset($_GET['feedback_id']) ? absint($_GET['feedback_id']) : 0;
    check_admin_referer('vc_flashcards_mark_feedback_seen_' . $feedback_id);

    if ($feedback_id > 0) {
      global $wpdb;
      $wpdb->update(
        $wpdb->prefix . VC_Flashcards_Plugin::FEEDBACK_TABLE,
        [
          'status' => 'seen',
          'updated_at' => current_time('mysql'),
        ],
        ['id' => $feedback_id],
        ['%s', '%s'],
        ['%d']
      );
    }

    wp_safe_redirect(add_query_arg('vc_feedback_notice', 'seen', $this->get_admin_url()));
    exit;
  }

  public function handle_download(): void {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to download feedback.', 'vc-flashcards'));
    }

    $feedback_id = isset($_GET['feedback_id']) ? absint($_GET['feedback_id']) : 0;
    check_admin_referer('vc_flashcards_download_feedback_' . $feedback_id);

    $item = $feedback_id > 0 ? $this->get_item($feedback_id) : null;

    if (!$item) {
      wp_die(esc_html__('Feedback not found.', 'vc-flashcards'));
    }

    $filename = 'feedback-' . $feedback_id . '.csv';
    $headers = [
      'id',
      'type',
      'status',
      'user',
      'created_at',
      'page_url',
      'referrer_url',
      'screenshot_url',
      'user_agent',
      'message',
    ];
    $row = [
      $feedback_id,
      ucfirst((string) $item['feedback_type']),
      $this->format_status((string) $item['status']),
      $this->format_user_label((int) $item['user_id']),
      (string) $item['created_at'],
      (string) $item['page_url'],
      (string) $item['referrer_url'],
      (string) $item['screenshot_url'],
      (string) $item['user_agent'],
      (string) $item['message'],
    ];

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    if ($output !== false) {
      fwrite($output, "\xEF\xBB\xBF");
      fputcsv($output, $headers, ';');
      fputcsv($output, $row, ';');
      fclose($output);
    }

    exit;
  }

  private function render_list(): void {
    $items = $this->get_items();
    $admin_url = $this->get_admin_url();

    include VC_FLASHCARDS_DIR . 'templates/admin/feedback-list.php';
  }

  private function render_detail(int $feedback_id): void {
    $item = $this->get_item($feedback_id);
    $admin_url = $this->get_admin_url();

    include VC_FLASHCARDS_DIR . 'templates/admin/feedback-detail.php';
  }

  private function get_items(int $limit = 100): array {
    global $wpdb;

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT id, user_id, feedback_type, message, status, created_at
        FROM {$wpdb->prefix}" . VC_Flashcards_Plugin::FEEDBACK_TABLE . "
        ORDER BY created_at DESC, id DESC
        LIMIT %d",
        $limit
      ),
      ARRAY_A
    ) ?: [];
  }

  private function get_item(int $feedback_id): ?array {
    global $wpdb;

    $item = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT *
        FROM {$wpdb->prefix}" . VC_Flashcards_Plugin::FEEDBACK_TABLE . "
        WHERE id = %d",
        $feedback_id
      ),
      ARRAY_A
    );

    return is_array($item) ? $item : null;
  }

  private function get_admin_url(): string {
    return add_query_arg(['page' => self::PAGE_SLUG], admin_url('admin.php'));
  }

  public function get_mark_seen_url(int $feedback_id): string {
    return wp_nonce_url(
      admin_url('admin-post.php?action=vc_flashcards_mark_feedback_seen&feedback_id=' . $feedback_id),
      'vc_flashcards_mark_feedback_seen_' . $feedback_id
    );
  }

  public function get_download_url(int $feedback_id): string {
    return wp_nonce_url(
      admin_url('admin-post.php?action=vc_flashcards_download_feedback&feedback_id=' . $feedback_id),
      'vc_flashcards_download_feedback_' . $feedback_id
    );
  }

  public function get_detail_url(int $feedback_id): string {
    return add_query_arg('feedback_id', $feedback_id, $this->get_admin_url());
  }

  public function format_user_label(int $user_id): string {
    if ($user_id <= 0) {
      return __('Guest', 'vc-flashcards');
    }

    $user = get_userdata($user_id);

    if (!$user) {
      return sprintf(__('User #%d', 'vc-flashcards'), $user_id);
    }

    return sprintf('%s (#%d)', $user->display_name, $user_id);
  }

  public function format_status(string $status): string {
    return $status === 'seen' ? __('Seen', 'vc-flashcards') : __('New', 'vc-flashcards');
  }

  public function format_url(string $url): string {
    if ($url === '') {
      return esc_html__('None', 'vc-flashcards');
    }

    return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
  }
}
