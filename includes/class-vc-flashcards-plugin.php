<?php

if (!defined('ABSPATH')) {
  exit;
}

class VC_Flashcards_Plugin {
  const POST_TYPE = 'vc_flashcard';
  const TAXONOMY = 'vc_flashcard_topic';
  const SESSION_TABLE = 'vc_flashcard_sessions';
  const ATTEMPT_TABLE = 'vc_flashcard_attempts';
  const NONCE_ACTION = 'vc_flashcards_nonce';
  const PASSING_SCORE = 70;
  const DEFAULT_TOPIC_ORDER = ['General', 'Airframe', 'Powerplant'];

  private static $instance = null;

  public static function instance(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  public static function activate(): void {
    self::register_content_types();
    self::create_tables();
    self::seed_default_topics();
    flush_rewrite_rules();
  }

  public static function deactivate(): void {
    flush_rewrite_rules();
  }

  private function __construct() {
    add_action('init', [__CLASS__, 'register_content_types']);
    add_action('admin_menu', [$this, 'register_admin_submenus']);
    add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
    add_action('save_post_' . self::POST_TYPE, [$this, 'save_flashcard_meta']);
    add_action('admin_post_vc_flashcards_import_csv', [$this, 'handle_import_csv']);
    add_action('admin_post_vc_flashcards_download_sample', [$this, 'handle_download_sample']);
    add_filter('wp_terms_checklist_args', [$this, 'filter_terms_checklist_args']);
    add_shortcode('vc_flashcards_app', [$this, 'render_flashcards_shortcode']);
    add_shortcode('vc_flashcards', [$this, 'render_flashcards_shortcode']);
    add_action('wp_ajax_vc_flashcards_start_session', [$this, 'ajax_start_session']);
    add_action('wp_ajax_vc_flashcards_complete_session', [$this, 'ajax_complete_session']);
  }

  public static function register_content_types(): void {
    register_post_type(self::POST_TYPE, [
      'labels' => [
        'name' => __('Flashcards', 'vc-flashcards'),
        'singular_name' => __('Flashcard', 'vc-flashcards'),
        'add_new_item' => __('Add New Flashcard', 'vc-flashcards'),
        'edit_item' => __('Edit Flashcard', 'vc-flashcards'),
      ],
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'show_in_rest' => true,
      'menu_icon' => 'dashicons-index-card',
      'supports' => ['title'],
      'menu_position' => 28,
      'rewrite' => false,
    ]);

    register_taxonomy(self::TAXONOMY, [self::POST_TYPE], [
      'labels' => [
        'name' => __('Topics & Subtopics', 'vc-flashcards'),
        'singular_name' => __('Topic', 'vc-flashcards'),
      ],
      'public' => false,
      'show_ui' => true,
      'show_admin_column' => true,
      'show_in_rest' => true,
      'hierarchical' => true,
      'rewrite' => false,
    ]);
  }

  public function register_admin_submenus(): void {
    add_submenu_page(
      'edit.php?post_type=' . self::POST_TYPE,
      __('Bulk Import', 'vc-flashcards'),
      __('Bulk Import', 'vc-flashcards'),
      'edit_posts',
      'vc-flashcards-import',
      [$this, 'render_bulk_import_page']
    );
  }

  public function render_bulk_import_page(): void {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'vc-flashcards'));
    }

    $sample_url = wp_nonce_url(
      admin_url('admin-post.php?action=vc_flashcards_download_sample'),
      'vc_flashcards_download_sample'
    );

    $notice_type = isset($_GET['vc_notice']) ? sanitize_key(wp_unslash($_GET['vc_notice'])) : '';
    $created = isset($_GET['created']) ? absint($_GET['created']) : 0;
    $updated = isset($_GET['updated']) ? absint($_GET['updated']) : 0;
    $errors = isset($_GET['errors']) ? absint($_GET['errors']) : 0;
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Bulk Import Flashcards', 'vc-flashcards'); ?></h1>
      <p><?php esc_html_e('Upload a CSV file to create or update flashcards in bulk. Topics and subtopics will be created automatically if they do not exist yet.', 'vc-flashcards'); ?></p>

      <?php if ($notice_type === 'success'): ?>
        <div class="notice notice-success is-dismissible">
          <p>
            <?php
            echo esc_html(sprintf(
              /* translators: 1: created count, 2: updated count, 3: error count */
              __('Import completed. Created: %1$d, Updated: %2$d, Errors: %3$d.', 'vc-flashcards'),
              $created,
              $updated,
              $errors
            ));
            ?>
          </p>
        </div>
      <?php elseif ($notice_type === 'error'): ?>
        <div class="notice notice-error">
          <p><?php echo esc_html(isset($_GET['message']) ? sanitize_text_field(wp_unslash($_GET['message'])) : __('The import could not be processed.', 'vc-flashcards')); ?></p>
        </div>
      <?php endif; ?>

      <div style="max-width: 980px; display: grid; gap: 24px;">
        <div style="padding: 24px; border: 1px solid #dcdcde; border-radius: 12px; background: #fff;">
          <h2 style="margin-top: 0;"><?php esc_html_e('CSV Template', 'vc-flashcards'); ?></h2>
          <p><?php esc_html_e('Download the example file and keep the same column names. The `id` column is optional: if you provide an existing flashcard ID, that flashcard will be updated; if you leave it empty, a new flashcard will be created.', 'vc-flashcards'); ?></p>
          <p><a class="button button-secondary" href="<?php echo esc_url($sample_url); ?>"><?php esc_html_e('Download CSV Sample', 'vc-flashcards'); ?></a></p>

          <table class="widefat striped" style="margin-top: 16px;">
            <thead>
              <tr>
                <th><?php esc_html_e('Column', 'vc-flashcards'); ?></th>
                <th><?php esc_html_e('Required', 'vc-flashcards'); ?></th>
                <th><?php esc_html_e('Notes', 'vc-flashcards'); ?></th>
              </tr>
            </thead>
            <tbody>
              <tr><td><code>id</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('Existing flashcard post ID for updates.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>question</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Main question text.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>answer_a</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Option A.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>answer_b</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Option B.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>answer_c</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Option C.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>correct_answer</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Use only `a`, `b`, or `c`.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>explanation</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('Shown after answering the card.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>references</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('Use `|` to separate multiple references in one cell.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>topic</code></td><td><?php esc_html_e('Yes', 'vc-flashcards'); ?></td><td><?php esc_html_e('Parent topic, for example General, Airframe, or Powerplant.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>subtopic</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('Optional child term under the topic.', 'vc-flashcards'); ?></td></tr>
            </tbody>
          </table>
        </div>

        <div style="padding: 24px; border: 1px solid #dcdcde; border-radius: 12px; background: #fff;">
          <h2 style="margin-top: 0;"><?php esc_html_e('Upload CSV', 'vc-flashcards'); ?></h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('vc_flashcards_import_csv', 'vc_flashcards_import_nonce'); ?>
            <input type="hidden" name="action" value="vc_flashcards_import_csv">
            <input type="file" name="vc_flashcards_csv" accept=".csv,text/csv" required>
            <p class="description"><?php esc_html_e('The file must be UTF-8 CSV with the same headers as the sample.', 'vc-flashcards'); ?></p>
            <p><button type="submit" class="button button-primary"><?php esc_html_e('Import Flashcards', 'vc-flashcards'); ?></button></p>
          </form>
        </div>
      </div>
    </div>
    <?php
  }

  public function register_meta_boxes(): void {
    add_meta_box(
      'vc-flashcard-details',
      __('Flashcard Details', 'vc-flashcards'),
      [$this, 'render_flashcard_meta_box'],
      self::POST_TYPE,
      'normal',
      'high'
    );
  }

  public function render_flashcard_meta_box(WP_Post $post): void {
    wp_nonce_field('vc_flashcard_meta', 'vc_flashcard_meta_nonce');

    $question = (string) get_post_meta($post->ID, '_vc_flashcard_question', true);
    $answer_a = (string) get_post_meta($post->ID, '_vc_flashcard_answer_a', true);
    $answer_b = (string) get_post_meta($post->ID, '_vc_flashcard_answer_b', true);
    $answer_c = (string) get_post_meta($post->ID, '_vc_flashcard_answer_c', true);
    $correct_answer = (string) get_post_meta($post->ID, '_vc_flashcard_correct_answer', true);
    $explanation = (string) get_post_meta($post->ID, '_vc_flashcard_explanation', true);
    $references = (string) get_post_meta($post->ID, '_vc_flashcard_references', true);
    ?>
    <style>
      .vc-flashcards-admin-grid {
        display: grid;
        gap: 16px;
      }
      .vc-flashcards-admin-grid label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
      }
      .vc-flashcards-admin-grid input[type="text"],
      .vc-flashcards-admin-grid textarea,
      .vc-flashcards-admin-grid select {
        width: 100%;
      }
      .vc-flashcards-admin-inline {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    </style>
    <div class="vc-flashcards-admin-grid">
      <p><strong><?php esc_html_e('Flashcard ID', 'vc-flashcards'); ?>:</strong> <?php echo esc_html((string) $post->ID); ?></p>

      <div>
        <label for="vc_flashcard_question"><?php esc_html_e('Question', 'vc-flashcards'); ?></label>
        <textarea id="vc_flashcard_question" name="vc_flashcard_question" rows="4"><?php echo esc_textarea($question); ?></textarea>
      </div>

      <div class="vc-flashcards-admin-inline">
        <div>
          <label for="vc_flashcard_answer_a"><?php esc_html_e('Answer A', 'vc-flashcards'); ?></label>
          <input id="vc_flashcard_answer_a" type="text" name="vc_flashcard_answer_a" value="<?php echo esc_attr($answer_a); ?>">
        </div>
        <div>
          <label for="vc_flashcard_answer_b"><?php esc_html_e('Answer B', 'vc-flashcards'); ?></label>
          <input id="vc_flashcard_answer_b" type="text" name="vc_flashcard_answer_b" value="<?php echo esc_attr($answer_b); ?>">
        </div>
        <div>
          <label for="vc_flashcard_answer_c"><?php esc_html_e('Answer C', 'vc-flashcards'); ?></label>
          <input id="vc_flashcard_answer_c" type="text" name="vc_flashcard_answer_c" value="<?php echo esc_attr($answer_c); ?>">
        </div>
      </div>

      <div>
        <label for="vc_flashcard_correct_answer"><?php esc_html_e('Correct Answer', 'vc-flashcards'); ?></label>
        <select id="vc_flashcard_correct_answer" name="vc_flashcard_correct_answer">
          <option value=""><?php esc_html_e('Select the correct option', 'vc-flashcards'); ?></option>
          <option value="a" <?php selected($correct_answer, 'a'); ?>><?php esc_html_e('Answer A', 'vc-flashcards'); ?></option>
          <option value="b" <?php selected($correct_answer, 'b'); ?>><?php esc_html_e('Answer B', 'vc-flashcards'); ?></option>
          <option value="c" <?php selected($correct_answer, 'c'); ?>><?php esc_html_e('Answer C', 'vc-flashcards'); ?></option>
        </select>
      </div>

      <div>
        <label for="vc_flashcard_explanation"><?php esc_html_e('Explanation', 'vc-flashcards'); ?></label>
        <textarea id="vc_flashcard_explanation" name="vc_flashcard_explanation" rows="5"><?php echo esc_textarea($explanation); ?></textarea>
      </div>

      <div>
        <label for="vc_flashcard_references"><?php esc_html_e('Documentation References', 'vc-flashcards'); ?></label>
        <textarea id="vc_flashcard_references" name="vc_flashcard_references" rows="4" placeholder="<?php esc_attr_e('One reference per line', 'vc-flashcards'); ?>"><?php echo esc_textarea($references); ?></textarea>
      </div>
    </div>
    <?php
  }

  public function save_flashcard_meta(int $post_id): void {
    if (!isset($_POST['vc_flashcard_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vc_flashcard_meta_nonce'])), 'vc_flashcard_meta')) {
      return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }

    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $map = [
      '_vc_flashcard_question' => isset($_POST['vc_flashcard_question']) ? wp_kses_post(wp_unslash($_POST['vc_flashcard_question'])) : '',
      '_vc_flashcard_answer_a' => isset($_POST['vc_flashcard_answer_a']) ? sanitize_text_field(wp_unslash($_POST['vc_flashcard_answer_a'])) : '',
      '_vc_flashcard_answer_b' => isset($_POST['vc_flashcard_answer_b']) ? sanitize_text_field(wp_unslash($_POST['vc_flashcard_answer_b'])) : '',
      '_vc_flashcard_answer_c' => isset($_POST['vc_flashcard_answer_c']) ? sanitize_text_field(wp_unslash($_POST['vc_flashcard_answer_c'])) : '',
      '_vc_flashcard_correct_answer' => isset($_POST['vc_flashcard_correct_answer']) ? sanitize_key(wp_unslash($_POST['vc_flashcard_correct_answer'])) : '',
      '_vc_flashcard_explanation' => isset($_POST['vc_flashcard_explanation']) ? wp_kses_post(wp_unslash($_POST['vc_flashcard_explanation'])) : '',
      '_vc_flashcard_references' => isset($_POST['vc_flashcard_references']) ? sanitize_textarea_field(wp_unslash($_POST['vc_flashcard_references'])) : '',
    ];

    foreach ($map as $meta_key => $value) {
      update_post_meta($post_id, $meta_key, $value);
    }

    $title = get_the_title($post_id);
    if ($title === '' && !empty($map['_vc_flashcard_question'])) {
      remove_action('save_post_' . self::POST_TYPE, [$this, 'save_flashcard_meta']);
      wp_update_post([
        'ID' => $post_id,
        'post_title' => wp_trim_words(wp_strip_all_tags($map['_vc_flashcard_question']), 8, '...'),
      ]);
      add_action('save_post_' . self::POST_TYPE, [$this, 'save_flashcard_meta']);
    }
  }

  public function filter_terms_checklist_args(array $args): array {
    if (($args['taxonomy'] ?? '') !== self::TAXONOMY) {
      return $args;
    }

    $args['checked_ontop'] = false;
    return $args;
  }

  public function handle_download_sample(): void {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You do not have permission to download this file.', 'vc-flashcards'));
    }

    check_admin_referer('vc_flashcards_download_sample');

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vc-flashcards-sample.csv');

    $output = fopen('php://output', 'w');
    if ($output === false) {
      exit;
    }

    fputcsv($output, ['id', 'question', 'answer_a', 'answer_b', 'answer_c', 'correct_answer', 'explanation', 'references', 'topic', 'subtopic']);
    fputcsv($output, ['', 'What document is used to record maintenance entries?', 'Aircraft logbook', 'Pilot headset', 'Weight and balance sheet', 'a', 'Maintenance actions must be recorded in the aircraft maintenance records.', '14 CFR 43.9|FAA-H-8083-31A Chapter 2', 'General', 'Maintenance Records']);
    fputcsv($output, ['', 'What is the purpose of a rib in a wing structure?', 'Transmit engine torque', 'Maintain the airfoil shape', 'Supply hydraulic pressure', 'b', 'Ribs support the skin and keep the wing profile in the intended aerodynamic shape.', 'FAA-H-8083-31A Chapter 3', 'Airframe', 'Structures']);

    fclose($output);
    exit;
  }

  public function handle_import_csv(): void {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You do not have permission to import flashcards.', 'vc-flashcards'));
    }

    check_admin_referer('vc_flashcards_import_csv', 'vc_flashcards_import_nonce');

    if (
      empty($_FILES['vc_flashcards_csv']['tmp_name'])
      || !is_uploaded_file($_FILES['vc_flashcards_csv']['tmp_name'])
      || !is_readable($_FILES['vc_flashcards_csv']['tmp_name'])
    ) {
      $this->redirect_import_page('error', 0, 0, 0, __('Please upload a valid CSV file.', 'vc-flashcards'));
    }

    $results = $this->import_csv_file($_FILES['vc_flashcards_csv']['tmp_name']);
    $notice = empty($results['fatal']) ? 'success' : 'error';
    $message = empty($results['fatal']) ? '' : (string) $results['fatal'];

    $this->redirect_import_page(
      $notice,
      (int) $results['created'],
      (int) $results['updated'],
      isset($results['errors']) ? count($results['errors']) : 0,
      $message
    );
  }

  public static function create_tables(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;

    $sessions_sql = "CREATE TABLE {$sessions_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      user_id bigint(20) unsigned NOT NULL,
      mode varchar(20) NOT NULL DEFAULT 'random',
      topic_term_id bigint(20) unsigned DEFAULT NULL,
      total_cards smallint(5) unsigned NOT NULL DEFAULT 0,
      correct_answers smallint(5) unsigned NOT NULL DEFAULT 0,
      score_percent decimal(5,2) NOT NULL DEFAULT 0.00,
      started_at datetime NOT NULL,
      completed_at datetime DEFAULT NULL,
      created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY user_id (user_id),
      KEY topic_term_id (topic_term_id),
      KEY completed_at (completed_at)
    ) {$charset_collate};";

    $attempts_sql = "CREATE TABLE {$attempts_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      session_id bigint(20) unsigned NOT NULL,
      user_id bigint(20) unsigned NOT NULL,
      flashcard_id bigint(20) unsigned NOT NULL,
      topic_term_id bigint(20) unsigned DEFAULT NULL,
      selected_answer varchar(1) NOT NULL DEFAULT '',
      correct_answer varchar(1) NOT NULL DEFAULT '',
      is_correct tinyint(1) unsigned NOT NULL DEFAULT 0,
      response_time_ms int(10) unsigned NOT NULL DEFAULT 0,
      answered_at datetime NOT NULL,
      created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY session_id (session_id),
      KEY user_id (user_id),
      KEY flashcard_id (flashcard_id),
      KEY topic_term_id (topic_term_id)
    ) {$charset_collate};";

    dbDelta($sessions_sql);
    dbDelta($attempts_sql);
  }

  public static function seed_default_topics(): void {
    $parents = ['General', 'Airframe', 'Powerplant'];

    foreach ($parents as $parent_name) {
      if (!term_exists($parent_name, self::TAXONOMY)) {
        wp_insert_term($parent_name, self::TAXONOMY);
      }
    }
  }

  private function redirect_import_page(string $notice, int $created, int $updated, int $errors, string $message = ''): void {
    $args = [
      'post_type' => self::POST_TYPE,
      'page' => 'vc-flashcards-import',
      'vc_notice' => $notice,
      'created' => $created,
      'updated' => $updated,
      'errors' => $errors,
    ];

    if ($message !== '') {
      $args['message'] = $message;
    }

    wp_safe_redirect(add_query_arg($args, admin_url('edit.php')));
    exit;
  }

  private function import_csv_file(string $file_path): array {
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
      return [
        'created' => 0,
        'updated' => 0,
        'errors' => [],
        'fatal' => __('The CSV file could not be opened.', 'vc-flashcards'),
      ];
    }

    $delimiter = $this->detect_csv_delimiter($handle);
    $headers = fgetcsv($handle, 0, $delimiter);
    if ($headers === false || empty($headers)) {
      fclose($handle);
      return [
        'created' => 0,
        'updated' => 0,
        'errors' => [],
        'fatal' => __('The CSV file is empty.', 'vc-flashcards'),
      ];
    }

    $normalized_headers = array_map([$this, 'normalize_import_header'], $headers);
    $required_headers = ['question', 'answer_a', 'answer_b', 'answer_c', 'correct_answer', 'topic'];

    foreach ($required_headers as $required_header) {
      if (!in_array($required_header, $normalized_headers, true)) {
        fclose($handle);
        return [
          'created' => 0,
          'updated' => 0,
          'errors' => [],
          'fatal' => sprintf(
            /* translators: %s: missing column name */
            __('Missing required column: %s', 'vc-flashcards'),
            $required_header
          ),
        ];
      }
    }

    $created = 0;
    $updated = 0;
    $errors = [];
    $row_number = 1;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
      $row_number++;

      if ($this->is_import_row_empty($row)) {
        continue;
      }

      $data = [];
      foreach ($normalized_headers as $index => $header) {
        $data[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
      }

      $question = $data['question'] ?? '';
      $answer_a = $data['answer_a'] ?? '';
      $answer_b = $data['answer_b'] ?? '';
      $answer_c = $data['answer_c'] ?? '';
      $correct_answer = strtolower($data['correct_answer'] ?? '');
      $topic = $data['topic'] ?? '';
      $subtopic = $data['subtopic'] ?? '';

      if ($question === '' || $answer_a === '' || $answer_b === '' || $answer_c === '' || $topic === '') {
        $errors[] = sprintf(__('Row %d: required fields are missing.', 'vc-flashcards'), $row_number);
        continue;
      }

      if (!in_array($correct_answer, ['a', 'b', 'c'], true)) {
        $errors[] = sprintf(__('Row %d: correct_answer must be a, b, or c.', 'vc-flashcards'), $row_number);
        continue;
      }

      $requested_id = isset($data['id']) ? absint($data['id']) : 0;
      $is_update = $requested_id > 0 && get_post_type($requested_id) === self::POST_TYPE;

      $post_id = $this->upsert_imported_flashcard([
        'id' => $requested_id,
        'question' => $question,
        'answer_a' => $answer_a,
        'answer_b' => $answer_b,
        'answer_c' => $answer_c,
        'correct_answer' => $correct_answer,
        'explanation' => $data['explanation'] ?? '',
        'references' => $this->normalize_import_references($data['references'] ?? ''),
      ]);

      if ($post_id < 1) {
        $errors[] = sprintf(__('Row %d: the flashcard could not be saved.', 'vc-flashcards'), $row_number);
        continue;
      }

      $term_id = $this->ensure_topic_and_subtopic($topic, $subtopic);
      if ($term_id < 1) {
        $errors[] = sprintf(__('Row %d: the topic or subtopic could not be created.', 'vc-flashcards'), $row_number);
        continue;
      }

      wp_set_object_terms($post_id, [$term_id], self::TAXONOMY, false);

      if ($is_update) {
        $updated++;
      } else {
        $created++;
      }
    }

    fclose($handle);

    return [
      'created' => $created,
      'updated' => $updated,
      'errors' => $errors,
      'fatal' => '',
    ];
  }

  private function detect_csv_delimiter($handle): string {
    $default_delimiter = ',';
    $first_line = fgets($handle);

    if ($first_line === false) {
      rewind($handle);
      return $default_delimiter;
    }

    rewind($handle);

    $comma_count = substr_count($first_line, ',');
    $semicolon_count = substr_count($first_line, ';');

    return $semicolon_count > $comma_count ? ';' : $default_delimiter;
  }

  private function normalize_import_header(string $header): string {
    $header = strtolower(trim($header));
    $header = str_replace([' ', '-'], '_', $header);
    return preg_replace('/[^a-z0-9_]/', '', $header) ?: '';
  }

  private function is_import_row_empty(array $row): bool {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return false;
      }
    }

    return true;
  }

  private function normalize_import_references(string $references): string {
    $items = preg_split('/\s*\|\s*|\r\n|\r|\n/', $references);
    $items = is_array($items) ? array_filter(array_map('trim', $items)) : [];
    return implode("\n", $items);
  }

  private function upsert_imported_flashcard(array $data): int {
    $post_id = !empty($data['id']) ? absint($data['id']) : 0;
    $is_update = $post_id > 0 && get_post_type($post_id) === self::POST_TYPE;

    $post_payload = [
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'post_title' => wp_trim_words(wp_strip_all_tags((string) $data['question']), 8, '...'),
    ];

    if ($is_update) {
      $post_payload['ID'] = $post_id;
      $post_id = wp_update_post($post_payload, true);
    } else {
      $post_id = wp_insert_post($post_payload, true);
    }

    if (is_wp_error($post_id) || !$post_id) {
      return 0;
    }

    update_post_meta($post_id, '_vc_flashcard_question', wp_kses_post((string) $data['question']));
    update_post_meta($post_id, '_vc_flashcard_answer_a', sanitize_text_field((string) $data['answer_a']));
    update_post_meta($post_id, '_vc_flashcard_answer_b', sanitize_text_field((string) $data['answer_b']));
    update_post_meta($post_id, '_vc_flashcard_answer_c', sanitize_text_field((string) $data['answer_c']));
    update_post_meta($post_id, '_vc_flashcard_correct_answer', sanitize_key((string) $data['correct_answer']));
    update_post_meta($post_id, '_vc_flashcard_explanation', wp_kses_post((string) $data['explanation']));
    update_post_meta($post_id, '_vc_flashcard_references', sanitize_textarea_field((string) $data['references']));

    return (int) $post_id;
  }

  private function ensure_topic_and_subtopic(string $topic, string $subtopic = ''): int {
    $parent_id = $this->upsert_term_by_name($topic, 0);
    if ($parent_id < 1) {
      return 0;
    }

    if (trim($subtopic) === '') {
      return $parent_id;
    }

    return $this->upsert_term_by_name($subtopic, $parent_id);
  }

  private function upsert_term_by_name(string $name, int $parent = 0): int {
    $name = trim($name);
    if ($name === '') {
      return 0;
    }

    $existing_in_parent = $this->get_term_by_name_and_parent($name, $parent);
    if ($existing_in_parent instanceof WP_Term) {
      return (int) $existing_in_parent->term_id;
    }

    if ($parent > 0) {
      $orphan_term = $this->get_term_by_name_and_parent($name, 0);
      if ($orphan_term instanceof WP_Term && (int) $orphan_term->parent === 0) {
        $updated = wp_update_term($orphan_term->term_id, self::TAXONOMY, ['parent' => $parent]);
        if (!is_wp_error($updated) && !empty($updated['term_id'])) {
          return (int) $updated['term_id'];
        }
      }
    }

    $created = wp_insert_term($name, self::TAXONOMY, ['parent' => $parent]);
    if (is_wp_error($created) || empty($created['term_id'])) {
      if ($created instanceof WP_Error && $created->get_error_code() === 'term_exists') {
        return (int) $created->get_error_data();
      }

      return 0;
    }

    return (int) $created['term_id'];
  }

  private function get_term_by_name_and_parent(string $name, int $parent = 0): ?WP_Term {
    $terms = get_terms([
      'taxonomy' => self::TAXONOMY,
      'hide_empty' => false,
      'name' => $name,
      'parent' => $parent,
      'number' => 1,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
      return null;
    }

    foreach ($terms as $term) {
      if ($term instanceof WP_Term && strcasecmp($term->name, $name) === 0) {
        return $term;
      }
    }

    return null;
  }

  public function render_flashcards_shortcode($atts = []): string {
    if (!is_user_logged_in()) {
      return '<div class="vc-flashcards-guest">' . esc_html__('Please log in to use the flashcards tool.', 'vc-flashcards') . '</div>';
    }

    wp_enqueue_style(
      'vc-flashcards-style',
      VC_FLASHCARDS_URL . 'assets/flashcards.css',
      [],
      file_exists(VC_FLASHCARDS_DIR . 'assets/flashcards.css') ? (string) filemtime(VC_FLASHCARDS_DIR . 'assets/flashcards.css') : '1.0.0'
    );
    wp_enqueue_script(
      'vc-flashcards-script',
      VC_FLASHCARDS_URL . 'assets/flashcards.js',
      [],
      file_exists(VC_FLASHCARDS_DIR . 'assets/flashcards.js') ? (string) filemtime(VC_FLASHCARDS_DIR . 'assets/flashcards.js') : '1.0.0',
      true
    );

    wp_localize_script('vc-flashcards-script', 'vcFlashcardsData', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce(self::NONCE_ACTION),
      'cardOptions' => [10, 20, 30, 40, 50],
      'labels' => [
        'selectSubtopic' => __('Select a topic or subtopic first.', 'vc-flashcards'),
        'noCards' => __('No flashcards were found for this selection.', 'vc-flashcards'),
        'loading' => __('Preparing your practice session...', 'vc-flashcards'),
        'next' => __('Next card', 'vc-flashcards'),
        'summaryTitle' => __('Session complete', 'vc-flashcards'),
        'restart' => __('Study again', 'vc-flashcards'),
        'backToCategory' => __('Back to category', 'vc-flashcards'),
        'correct' => __('Correct', 'vc-flashcards'),
        'incorrect' => __('Incorrect', 'vc-flashcards'),
        'cardsSelected' => __('Selected cards', 'vc-flashcards'),
        'cardAmount' => __('Number of cards', 'vc-flashcards'),
        'cancel' => __('Cancel', 'vc-flashcards'),
        'start' => __('Start', 'vc-flashcards'),
        'study' => __('Study', 'vc-flashcards'),
        'viewExplanation' => __('View detailed explanation', 'vc-flashcards'),
        'hideExplanation' => __('Hide detailed explanation', 'vc-flashcards'),
        'noSubtopics' => __('No subtopics have been added yet for this category.', 'vc-flashcards'),
        'back' => __('Back', 'vc-flashcards'),
      ],
    ]);

    $user_id = get_current_user_id();
    $stats = $this->get_user_stats($user_id);
    $categories = $this->get_category_cards($user_id);
    $total_flashcards = wp_count_posts(self::POST_TYPE);
    $published_flashcards = $total_flashcards instanceof stdClass ? (int) $total_flashcards->publish : 0;

    ob_start();
    include VC_FLASHCARDS_DIR . 'templates/shortcode-app.php';
    return (string) ob_get_clean();
  }

  public function ajax_start_session(): void {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => __('You must be logged in.', 'vc-flashcards')], 403);
    }

    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'random';
    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $card_limit = isset($_POST['card_limit']) ? absint($_POST['card_limit']) : 10;
    $card_limit = max(1, min(50, $card_limit));

    if (!in_array($mode, ['random', 'category', 'subcategory'], true)) {
      $mode = 'random';
    }

    if (in_array($mode, ['category', 'subcategory'], true) && $term_id < 1) {
      wp_send_json_error(['message' => __('Please select a topic or subtopic.', 'vc-flashcards')], 400);
    }

    $cards = $this->get_flashcards_for_session($mode, $term_id, $card_limit);
    if (empty($cards)) {
      wp_send_json_error(['message' => __('No flashcards were found for this selection.', 'vc-flashcards')], 404);
    }

    global $wpdb;

    $wpdb->insert(
      $wpdb->prefix . self::SESSION_TABLE,
      [
        'user_id' => get_current_user_id(),
        'mode' => $mode,
        'topic_term_id' => $term_id ?: null,
        'total_cards' => count($cards),
        'correct_answers' => 0,
        'score_percent' => 0,
        'started_at' => current_time('mysql'),
      ],
      ['%d', '%s', '%d', '%d', '%d', '%f', '%s']
    );

    wp_send_json_success([
      'sessionId' => (int) $wpdb->insert_id,
      'cards' => $cards,
    ]);
  }

  public function ajax_complete_session(): void {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => __('You must be logged in.', 'vc-flashcards')], 403);
    }

    $session_id = isset($_POST['session_id']) ? absint($_POST['session_id']) : 0;
    $attempts_json = isset($_POST['attempts']) ? wp_unslash($_POST['attempts']) : '[]';
    $attempts = json_decode($attempts_json, true);

    if ($session_id < 1 || !is_array($attempts)) {
      wp_send_json_error(['message' => __('Invalid session payload.', 'vc-flashcards')], 400);
    }

    global $wpdb;

    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $user_id = get_current_user_id();

    $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_table} WHERE id = %d AND user_id = %d", $session_id, $user_id));
    if (!$session) {
      wp_send_json_error(['message' => __('Session not found.', 'vc-flashcards')], 404);
    }

    $wpdb->delete($attempts_table, ['session_id' => $session_id], ['%d']);

    $correct_answers = 0;
    $total_cards = 0;

    foreach ($attempts as $attempt) {
      $flashcard_id = isset($attempt['flashcardId']) ? absint($attempt['flashcardId']) : 0;
      $topic_term_id = isset($attempt['topicTermId']) ? absint($attempt['topicTermId']) : 0;
      $selected_answer = isset($attempt['selectedAnswer']) ? sanitize_key($attempt['selectedAnswer']) : '';
      $correct_answer = isset($attempt['correctAnswer']) ? sanitize_key($attempt['correctAnswer']) : '';
      $is_correct = $selected_answer !== '' && $selected_answer === $correct_answer ? 1 : 0;
      $response_time_ms = isset($attempt['responseTimeMs']) ? absint($attempt['responseTimeMs']) : 0;

      if ($flashcard_id < 1 || !in_array($correct_answer, ['a', 'b', 'c'], true)) {
        continue;
      }

      $total_cards++;
      $correct_answers += $is_correct;

      $wpdb->insert(
        $attempts_table,
        [
          'session_id' => $session_id,
          'user_id' => $user_id,
          'flashcard_id' => $flashcard_id,
          'topic_term_id' => $topic_term_id ?: null,
          'selected_answer' => $selected_answer,
          'correct_answer' => $correct_answer,
          'is_correct' => $is_correct,
          'response_time_ms' => $response_time_ms,
          'answered_at' => current_time('mysql'),
        ],
        ['%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s']
      );
    }

    $score_percent = $total_cards > 0 ? round(($correct_answers / $total_cards) * 100, 2) : 0;

    $wpdb->update(
      $sessions_table,
      [
        'total_cards' => $total_cards,
        'correct_answers' => $correct_answers,
        'score_percent' => $score_percent,
        'completed_at' => current_time('mysql'),
      ],
      [
        'id' => $session_id,
        'user_id' => $user_id,
      ],
      ['%d', '%d', '%f', '%s'],
      ['%d', '%d']
    );

    wp_send_json_success([
      'stats' => $this->get_user_stats($user_id),
      'scorePercent' => $score_percent,
      'correctAnswers' => $correct_answers,
      'totalCards' => $total_cards,
    ]);
  }

  private function get_flashcards_for_session(string $mode, int $term_id, int $limit): array {
    $tax_query = [];
    $include_children = $mode !== 'subcategory';
    $orderby = $mode === 'random' ? 'rand' : 'ID';
    $order = $mode === 'random' ? 'DESC' : 'ASC';

    if ($term_id > 0) {
      $tax_query[] = [
        'taxonomy' => self::TAXONOMY,
        'field' => 'term_id',
        'terms' => [$term_id],
        'include_children' => $include_children,
      ];
    }

    $query = new WP_Query([
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => $limit,
      'orderby' => $orderby,
      'order' => $order,
      'tax_query' => $tax_query,
      'fields' => 'ids',
    ]);

    if (empty($query->posts)) {
      return [];
    }

    $cards = [];
    foreach ($query->posts as $post_id) {
      $card = $this->build_flashcard_payload((int) $post_id);
      if (!empty($card)) {
        $cards[] = $card;
      }
    }

    return $cards;
  }

  private function build_flashcard_payload(int $post_id): array {
    $question = (string) get_post_meta($post_id, '_vc_flashcard_question', true);
    $answer_a = (string) get_post_meta($post_id, '_vc_flashcard_answer_a', true);
    $answer_b = (string) get_post_meta($post_id, '_vc_flashcard_answer_b', true);
    $answer_c = (string) get_post_meta($post_id, '_vc_flashcard_answer_c', true);
    $correct_answer = (string) get_post_meta($post_id, '_vc_flashcard_correct_answer', true);
    $explanation = (string) get_post_meta($post_id, '_vc_flashcard_explanation', true);
    $references = (string) get_post_meta($post_id, '_vc_flashcard_references', true);

    if ($question === '' || $answer_a === '' || $answer_b === '' || $answer_c === '' || !in_array($correct_answer, ['a', 'b', 'c'], true)) {
      return [];
    }

    $term_payload = $this->get_flashcard_term_payload($post_id);

    return [
      'id' => $post_id,
      'question' => wp_strip_all_tags($question),
      'answers' => [
        'a' => $answer_a,
        'b' => $answer_b,
        'c' => $answer_c,
      ],
      'correctAnswer' => $correct_answer,
      'explanation' => wp_kses_post(wpautop($explanation)),
      'references' => $this->parse_references($references),
      'topicTermId' => $term_payload['term_id'],
      'topicLabel' => $term_payload['topic_label'],
      'subtopicLabel' => $term_payload['subtopic_label'],
    ];
  }

  private function parse_references(string $references): array {
    $items = preg_split('/\r\n|\r|\n/', $references);
    $items = is_array($items) ? array_filter(array_map('trim', $items)) : [];
    return array_values($items);
  }

  private function get_flashcard_term_payload(int $post_id): array {
    $terms = get_the_terms($post_id, self::TAXONOMY);
    if (empty($terms) || is_wp_error($terms)) {
      return [
        'term_id' => 0,
        'topic_label' => '',
        'subtopic_label' => '',
      ];
    }

    usort($terms, static function (WP_Term $left, WP_Term $right): int {
      return $right->parent <=> $left->parent;
    });

    $selected = $terms[0];
    $topic_label = $selected->name;
    $subtopic_label = '';

    if ($selected->parent) {
      $parent_term = get_term($selected->parent, self::TAXONOMY);
      if ($parent_term instanceof WP_Term) {
        $topic_label = $parent_term->name;
        $subtopic_label = $selected->name;
      }
    }

    return [
      'term_id' => (int) $selected->term_id,
      'topic_label' => $topic_label,
      'subtopic_label' => $subtopic_label,
    ];
  }

  private function get_topic_tree(): array {
    $parents = get_terms([
      'taxonomy' => self::TAXONOMY,
      'hide_empty' => false,
      'parent' => 0,
      'orderby' => 'name',
      'order' => 'ASC',
    ]);

    if (is_wp_error($parents) || empty($parents)) {
      return [];
    }

    $parents = $this->sort_parent_topics($parents);

    $tree = [];
    foreach ($parents as $parent) {
      $children = get_terms([
        'taxonomy' => self::TAXONOMY,
        'hide_empty' => false,
        'parent' => $parent->term_id,
        'orderby' => 'name',
        'order' => 'ASC',
      ]);

      $tree[] = [
        'id' => (int) $parent->term_id,
        'name' => $parent->name,
        'children' => is_wp_error($children) ? [] : array_map(static function (WP_Term $term): array {
          return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
          ];
        }, $children),
      ];
    }

    return $tree;
  }

  private function get_category_cards(int $user_id): array {
    $parents = get_terms([
      'taxonomy' => self::TAXONOMY,
      'hide_empty' => false,
      'parent' => 0,
      'orderby' => 'name',
      'order' => 'ASC',
    ]);

    if (is_wp_error($parents) || empty($parents)) {
      return [];
    }

    $parents = $this->sort_parent_topics($parents);

    $attempted_ids = $this->get_attempted_flashcard_ids($user_id);
    $categories = [];

    foreach ($parents as $parent) {
      $card_ids = $this->get_flashcard_ids_for_term((int) $parent->term_id);
      $reviewed_count = count(array_intersect($card_ids, $attempted_ids));
      $progress = !empty($card_ids) ? (int) round(($reviewed_count / count($card_ids)) * 100) : 0;
      $children = get_terms([
        'taxonomy' => self::TAXONOMY,
        'hide_empty' => false,
        'parent' => $parent->term_id,
        'orderby' => 'name',
        'order' => 'ASC',
      ]);

      $child_items = [];
      if (!is_wp_error($children) && !empty($children)) {
        foreach ($children as $child) {
          $child_card_ids = $this->get_flashcard_ids_for_term((int) $child->term_id);
          $child_reviewed_count = count(array_intersect($child_card_ids, $attempted_ids));
          $child_progress = !empty($child_card_ids) ? (int) round(($child_reviewed_count / count($child_card_ids)) * 100) : 0;

          $child_items[] = [
            'id' => (int) $child->term_id,
            'name' => $child->name,
            'totalCards' => count($child_card_ids),
            'reviewedCards' => $child_reviewed_count,
            'progress' => $child_progress,
            'status' => $child_progress >= 100 && !empty($child_card_ids) ? __('Mastered', 'vc-flashcards') : '',
            'description' => sprintf(
              /* translators: 1: total cards, 2: reviewed cards */
              __('%1$d cards · %2$d reviewed', 'vc-flashcards'),
              count($child_card_ids),
              $child_reviewed_count
            ),
          ];
        }
      }

      $categories[] = [
        'id' => (int) $parent->term_id,
        'name' => $parent->name,
        'totalCards' => count($card_ids),
        'reviewedCards' => $reviewed_count,
        'progress' => $progress,
        'subtopicCount' => count($child_items),
        'description' => sprintf(
          /* translators: 1: subtopic count, 2: reviewed cards, 3: total cards */
          __('%1$d subtopics · %2$d/%3$d cards reviewed', 'vc-flashcards'),
          count($child_items),
          $reviewed_count,
          count($card_ids)
        ),
        'children' => $child_items,
      ];
    }

    return $categories;
  }

  private function get_user_stats(int $user_id): array {
    global $wpdb;

    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;

    $attempt_summary = $wpdb->get_row($wpdb->prepare(
      "SELECT
        COUNT(*) AS attempts_count,
        COUNT(DISTINCT flashcard_id) AS unique_cards_reviewed
      FROM {$attempts_table}
      WHERE user_id = %d",
      $user_id
    ), ARRAY_A);
    $unique_cards_reviewed = isset($attempt_summary['unique_cards_reviewed']) ? (int) $attempt_summary['unique_cards_reviewed'] : 0;
    $published_flashcards = wp_count_posts(self::POST_TYPE);
    $total_flashcards = $published_flashcards instanceof stdClass ? (int) $published_flashcards->publish : 0;
    $reviewed_coverage = $total_flashcards > 0 ? (int) round(($unique_cards_reviewed / $total_flashcards) * 100) : 0;

    return [
      'correctStreak' => $this->get_current_correct_streak($user_id),
      'studyStreak' => $this->get_study_streak($user_id),
      'reviewedCoverage' => $reviewed_coverage,
      'reviewedCards' => $unique_cards_reviewed,
      'totalFlashcards' => $total_flashcards,
    ];
  }

  private function get_current_correct_streak(int $user_id): int {
    global $wpdb;

    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $attempts = $wpdb->get_col($wpdb->prepare(
      "SELECT is_correct
      FROM {$attempts_table}
      WHERE user_id = %d
      ORDER BY answered_at DESC, id DESC",
      $user_id
    ));

    if (empty($attempts)) {
      return 0;
    }

    $streak = 0;
    foreach ($attempts as $is_correct) {
      if ((int) $is_correct !== 1) {
        break;
      }

      $streak++;
    }

    return $streak;
  }

  private function get_study_streak(int $user_id): int {
    global $wpdb;

    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $dates = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT DATE(completed_at) AS study_date
      FROM {$sessions_table}
      WHERE user_id = %d AND completed_at IS NOT NULL
      ORDER BY study_date DESC",
      $user_id
    ));

    if (empty($dates)) {
      return 0;
    }

    $today = current_time('Y-m-d');
    $yesterday = gmdate('Y-m-d', strtotime($today . ' -1 day'));

    if ($dates[0] !== $today && $dates[0] !== $yesterday) {
      return 0;
    }

    $streak = 0;
    $expected = $dates[0];

    foreach ($dates as $date) {
      if ($date !== $expected) {
        break;
      }

      $streak++;
      $expected = gmdate('Y-m-d', strtotime($expected . ' -1 day'));
    }

    return $streak;
  }

  private function get_attempted_flashcard_ids(int $user_id): array {
    global $wpdb;

    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT DISTINCT flashcard_id FROM {$attempts_table} WHERE user_id = %d",
      $user_id
    ));

    return array_map('intval', $ids);
  }

  private function get_flashcard_ids_for_term(int $term_id): array {
    $query = new WP_Query([
      'post_type' => self::POST_TYPE,
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'tax_query' => [
        [
          'taxonomy' => self::TAXONOMY,
          'field' => 'term_id',
          'terms' => [$term_id],
          'include_children' => true,
        ],
      ],
    ]);

    return array_map('intval', $query->posts);
  }

  private function sort_parent_topics(array $terms): array {
    $order_map = array_flip(self::DEFAULT_TOPIC_ORDER);

    usort($terms, static function ($left, $right) use ($order_map): int {
      $left_name = $left instanceof WP_Term ? $left->name : '';
      $right_name = $right instanceof WP_Term ? $right->name : '';
      $left_index = $order_map[$left_name] ?? PHP_INT_MAX;
      $right_index = $order_map[$right_name] ?? PHP_INT_MAX;

      if ($left_index === $right_index) {
        return strcasecmp($left_name, $right_name);
      }

      return $left_index <=> $right_index;
    });

    return $terms;
  }
}
