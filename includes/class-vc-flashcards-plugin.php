<?php

if (!defined('ABSPATH')) {
  exit;
}

class VC_Flashcards_Plugin {
  // Nombre interno del Custom Post Type donde vive cada flashcard.
  // WordPress guardara estas tarjetas en wp_posts con post_type = vc_flashcard.
  const POST_TYPE = 'vc_flashcard';

  // Taxonomia jerarquica usada para organizar las tarjetas en topics y subtopics.
  // En la practica aqui viven General, Airframe, Powerplant y sus hijos.
  const TAXONOMY = 'vc_flashcard_topic';

  // Tabla custom donde guardamos una sesion completa de estudio o examen.
  // Aqui se resume el intento: usuario, score, fecha de inicio, fecha de fin, etc.
  const SESSION_TABLE = 'vc_flashcard_sessions';

  // Tabla custom donde guardamos cada respuesta individual de una sesion.
  // Una sesion puede tener muchas filas en esta tabla: una por pregunta respondida.
  const ATTEMPT_TABLE = 'vc_flashcard_attempts';

  // Immutable card set selected for a session. Server-side scoring reads this table,
  // so browser payloads cannot change correct answers or inject foreign flashcards.
  const SESSION_CARD_TABLE = 'vc_flashcard_session_cards';

  // Nonce compartido para validar llamadas AJAX del plugin.
  const NONCE_ACTION = 'vc_flashcards_nonce';

  // Puntaje minimo para considerar aprobado un examen.
  const PASSING_SCORE = 70;

  // Cuantos intentos recientes se muestran en historial o metricas resumidas.
  const EXAM_HISTORY_LIMIT = 5;

  // Tiempo corto de cache para pools de IDs del mock test. La sesion sigue siendo unica por usuario.
  const EXAM_POOL_CACHE_TTL = 600;

  // Version logica para invalidar todos los pools de examen sin buscar transients por prefijo.
  const EXAM_POOL_CACHE_VERSION_OPTION = 'vc_flashcards_exam_pool_cache_version';

  // Versiona cambios de tablas/indices para aplicar migraciones ligeras una sola vez.
  const DB_VERSION = '1.2.1';

  // Orden deseado de topics padre al mostrarlos en frontend.
  // Sirve para que General/Airframe/Powerplant aparezcan siempre en ese orden.
  const DEFAULT_TOPIC_ORDER = ['General', 'Airframe', 'Powerplant'];

  private static $instance = null;

  // Implementa un singleton simple.
  // En vez de crear muchas instancias de la clase, el plugin trabaja con una sola.
  public static function instance(): self {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  // Se ejecuta al activar el plugin desde el admin de WordPress.
  // Aqui preparamos todo lo minimo para que el sistema funcione:
  // 1. registramos CPT y taxonomy
  // 2. creamos tablas propias
  // 3. sembramos topics iniciales
  // 4. refrescamos reglas de URLs
  public static function activate(): void {
    self::register_content_types();
    self::create_tables();
    update_option('vc_flashcards_db_version', self::DB_VERSION);
    self::seed_default_topics();
    flush_rewrite_rules();
  }

  // En desactivacion no borramos datos.
  // Solo refrescamos rewrite rules para limpiar el registro de endpoints/URLs.
  public static function deactivate(): void {
    flush_rewrite_rules();
  }

  // Constructor privado: obliga a usar instance().
  // Aqui se conectan todas las piezas del plugin con WordPress usando hooks.
  private function __construct() {
    // Registra CPT y taxonomy al arrancar WordPress.
    add_action('init', [__CLASS__, 'register_content_types']);

    // Aplica migraciones de tablas/indices para instalaciones donde el plugin ya estaba activo.
    add_action('init', [__CLASS__, 'maybe_upgrade_schema'], 20);

    // Anade paginas/herramientas del admin para importar o gestionar flashcards.
    add_action('admin_menu', [$this, 'register_admin_submenus']);

    // Anade metaboxes al editor del CPT.
    add_action('add_meta_boxes', [$this, 'register_meta_boxes']);

    // Guarda los metadatos de una flashcard cuando se guarda el post.
    add_action('save_post_' . self::POST_TYPE, [$this, 'save_flashcard_meta']);
    add_action('set_object_terms', [$this, 'clear_exam_pool_cache_for_object_terms'], 10, 6);

    // Endpoints del admin para importar CSV y descargar el archivo ejemplo.
    add_action('admin_post_vc_flashcards_import_csv', [$this, 'handle_import_csv']);
    add_action('admin_post_vc_flashcards_download_sample', [$this, 'handle_download_sample']);

    // Ajuste visual/comportamental del checklist de terms en el editor.
    add_filter('wp_terms_checklist_args', [$this, 'filter_terms_checklist_args']);

    // Shortcodes del frontend: app normal de flashcards y alias historico.
    add_shortcode('vc_flashcards_app', [$this, 'render_flashcards_shortcode']);
    add_shortcode('vc_flashcards', [$this, 'render_flashcards_shortcode']);

    // Endpoints AJAX para sesiones normales de estudio.
    add_action('wp_ajax_vc_flashcards_start_session', [$this, 'ajax_start_session']);
    add_action('wp_ajax_vc_flashcards_complete_session', [$this, 'ajax_complete_session']);

    // Shortcode y endpoints AJAX del simulador de examen.
    add_shortcode('vc_exam_simulator', [$this, 'render_exam_shortcode']);
    add_action('wp_ajax_vc_flashcards_start_exam', [$this, 'ajax_start_exam']);
    add_action('wp_ajax_vc_flashcards_get_exam_history', [$this, 'ajax_get_exam_history']);
  }

  // Registra las dos estructuras principales del contenido:
  // - un Custom Post Type para las tarjetas
  // - una taxonomia jerarquica para topics/subtopics
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

    // Taxonomia jerarquica = se comporta como categorias.
    // Eso permite parent/child y encaja bien con topic > subtopic.
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

  // Crea el submenu "Bulk Import" debajo del CPT de Flashcards en el admin.
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

  // Renderiza la pantalla de importacion por CSV en el admin.
  // Aqui solo dibujamos interfaz: instrucciones, sample CSV y formulario de subida.
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
              <tr><td><code>question_image_url</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('Filename of the image already uploaded to the Media Library (e.g., ELE-1.png). The importer will look up the file in your Media Library and use its actual URL. Can also be a full URL if the image is hosted externally.', 'vc-flashcards'); ?></td></tr>
              <tr><td><code>acs_code</code></td><td><?php esc_html_e('No', 'vc-flashcards'); ?></td><td><?php esc_html_e('ACS code associated with this question.', 'vc-flashcards'); ?></td></tr>
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

  // Registra el metabox principal del editor de una flashcard.
  // El post guarda el titulo, pero casi toda la data util vive en meta fields.
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

  // Dibuja el formulario del metabox de una flashcard en el admin.
  // Carga los valores actuales desde post meta y los pinta en inputs/textarea/select.
  public function render_flashcard_meta_box(WP_Post $post): void {
    wp_nonce_field('vc_flashcard_meta', 'vc_flashcard_meta_nonce');

    $question = (string) get_post_meta($post->ID, '_vc_flashcard_question', true);
    $answer_a = (string) get_post_meta($post->ID, '_vc_flashcard_answer_a', true);
    $answer_b = (string) get_post_meta($post->ID, '_vc_flashcard_answer_b', true);
    $answer_c = (string) get_post_meta($post->ID, '_vc_flashcard_answer_c', true);
    $correct_answer = (string) get_post_meta($post->ID, '_vc_flashcard_correct_answer', true);
    $explanation = (string) get_post_meta($post->ID, '_vc_flashcard_explanation', true);
    $references = (string) get_post_meta($post->ID, '_vc_flashcard_references', true);
    $question_image_url = (string) get_post_meta($post->ID, '_vc_flashcard_question_image_url', true);
    $acs_code = (string) get_post_meta($post->ID, '_vc_flashcard_acs_code', true);
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
      .vc-flashcards-admin-grid input[type="url"],
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

      <div>
        <label for="vc_flashcard_question_image_url"><?php esc_html_e('Question Image URL', 'vc-flashcards'); ?></label>
        <input id="vc_flashcard_question_image_url" type="url" name="vc_flashcard_question_image_url" value="<?php echo esc_url($question_image_url); ?>">
      </div>

      <div>
        <label for="vc_flashcard_acs_code"><?php esc_html_e('ACS Code', 'vc-flashcards'); ?></label>
        <input id="vc_flashcard_acs_code" type="text" name="vc_flashcard_acs_code" value="<?php echo esc_attr($acs_code); ?>">
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

  // Guarda los metadatos de la flashcard cuando el post se guarda.
  // Tambien autocompleta el post_title usando la pregunta si el titulo esta vacio.
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
      '_vc_flashcard_question_image_url' => isset($_POST['vc_flashcard_question_image_url']) ? esc_url_raw(wp_unslash($_POST['vc_flashcard_question_image_url'])) : '',
      '_vc_flashcard_acs_code' => isset($_POST['vc_flashcard_acs_code']) ? sanitize_text_field(wp_unslash($_POST['vc_flashcard_acs_code'])) : '',
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

    $this->clear_exam_pool_cache();
  }

  public function clear_exam_pool_cache_for_object_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void {
    if ($taxonomy !== self::TAXONOMY || get_post_type((int) $object_id) !== self::POST_TYPE) {
      return;
    }

    $this->clear_exam_pool_cache();
  }

  // Ajuste menor del checklist de terms para que WordPress no suba los seleccionados al tope.
  // Es mas comodo para admins cuando manejan arboles grandes de topics/subtopics.
  public function filter_terms_checklist_args(array $args): array {
    if (($args['taxonomy'] ?? '') !== self::TAXONOMY) {
      return $args;
    }

    $args['checked_ontop'] = false;
    return $args;
  }

  // Descarga un CSV de ejemplo para que el admin vea el formato correcto de importacion.
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

    fputcsv($output, ['id', 'question', 'question_image_url', 'acs_code', 'answer_a', 'answer_b', 'answer_c', 'correct_answer', 'explanation', 'references', 'topic', 'subtopic']);
    fputcsv($output, ['', 'What document is used to record maintenance entries?', '', 'ACS-001', 'Aircraft logbook', 'Pilot headset', 'Weight and balance sheet', 'a', 'Maintenance actions must be recorded in the aircraft maintenance records.', '14 CFR 43.9|FAA-H-8083-31A Chapter 2', 'General', 'Maintenance Records']);
    fputcsv($output, ['', 'What is the purpose of a rib in a wing structure?', 'https://example.com/reference-image.jpg', 'ACS-002', 'Transmit engine torque', 'Maintain the airfoil shape', 'Supply hydraulic pressure', 'b', 'Ribs support the skin and keep the wing profile in the intended aerodynamic shape.', 'FAA-H-8083-31A Chapter 3', 'Airframe', 'Structures']);

    fclose($output);
    exit;
  }

  // Recibe el CSV del admin, valida permisos/nonce/archivo y delega la importacion real.
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

  // Crea o actualiza las tablas SQL propias del plugin usando dbDelta.
  // No crea una base de datos nueva: usa la misma DB de WordPress y anade dos tablas.
  public static function create_tables(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Obtiene charset/collation actual de WordPress para que las tablas queden compatibles.
    $charset_collate = $wpdb->get_charset_collate();

    // Arma nombres completos usando el prefijo real de la instalacion, por ejemplo wp_.
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $session_cards_table = $wpdb->prefix . self::SESSION_CARD_TABLE;

    // Tabla resumen de cada sesion.
    // Una fila = una sesion completa de estudio o examen.
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
      KEY completed_at (completed_at),
      KEY user_mode_completed_id (user_id, mode, completed_at, id)
    ) {$charset_collate};";

    // Tabla detalle de respuestas.
    // Una fila = una pregunta respondida dentro de una sesion.
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
      KEY user_flashcard_latest (user_id, flashcard_id, id),
      KEY topic_term_id (topic_term_id)
    ) {$charset_collate};";

    // Snapshot inmutable del set de tarjetas elegido para cada sesion.
    // La correccion se valida contra esta tabla, no contra datos enviados por el navegador.
    $session_cards_sql = "CREATE TABLE {$session_cards_table} (
      id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
      session_id bigint(20) unsigned NOT NULL,
      user_id bigint(20) unsigned NOT NULL,
      flashcard_id bigint(20) unsigned NOT NULL,
      topic_term_id bigint(20) unsigned DEFAULT NULL,
      correct_answer varchar(1) NOT NULL DEFAULT '',
      card_position smallint(5) unsigned NOT NULL DEFAULT 0,
      created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      UNIQUE KEY session_flashcard (session_id, flashcard_id),
      KEY session_id (session_id),
      KEY user_id (user_id),
      KEY flashcard_id (flashcard_id),
      KEY topic_term_id (topic_term_id)
    ) {$charset_collate};";

    // dbDelta crea la tabla si no existe y la ajusta si la estructura cambio.
    dbDelta($sessions_sql);
    dbDelta($attempts_sql);
    dbDelta($session_cards_sql);
  }

  // Ejecuta dbDelta solo cuando cambia la version del esquema.
  // Evita correr migraciones en cada request y mantiene indices existentes al dia.
  public static function maybe_upgrade_schema(): void {
    if (get_option('vc_flashcards_db_version') === self::DB_VERSION) {
      return;
    }

    self::create_tables();
    update_option('vc_flashcards_db_version', self::DB_VERSION);
  }

  // Si el plugin arranca vacio, crea los topics padre basicos.
  // Esto deja listo el arbol principal sin obligar al admin a crearlo manualmente.
  public static function seed_default_topics(): void {
    $parents = ['General', 'Airframe', 'Powerplant'];

    foreach ($parents as $parent_name) {
      if (!term_exists($parent_name, self::TAXONOMY)) {
        wp_insert_term($parent_name, self::TAXONOMY);
      }
    }
  }

  // Redirige de vuelta a la pagina de import con parametros de resultado.
  // Asi el admin ve mensajes de exito o error despues del POST.
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

  // Lee el archivo CSV completo y convierte sus filas en creacion/actualizacion de flashcards.
  // Esta funcion coordina validacion de columnas, recorrido de filas y reporte final.
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
      // CSV import contract: question_image_url stores the optional reference image URL.
      $question_image_url = $data['question_image_url'] ?? '';
      $acs_code = $data['acs_code'] ?? '';

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
        'question_image_url' => $this->get_media_url_by_filename($question_image_url),
        'acs_code' => $acs_code,
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

    if ($created > 0 || $updated > 0) {
      $this->clear_exam_pool_cache();
    }

    return [
      'created' => $created,
      'updated' => $updated,
      'errors' => $errors,
      'fatal' => '',
    ];
  }

  // Detecta si el CSV usa coma o punto y coma como separador principal.
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

  // Normaliza el nombre de una columna para compararlo de forma estable.
  private function normalize_import_header(string $header): string {
    $header = strtolower(trim($header));
    $header = str_replace([' ', '-'], '_', $header);
    return preg_replace('/[^a-z0-9_]/', '', $header) ?: '';
  }

  // Devuelve true si toda la fila esta vacia y debe ignorarse.
  private function is_import_row_empty(array $row): bool {
    foreach ($row as $value) {
      if (trim((string) $value) !== '') {
        return false;
      }
    }

    return true;
  }

  // Convierte referencias del CSV al formato de texto que usa el plugin internamente.
  private function normalize_import_references(string $references): string {
    $items = preg_split('/\s*\|\s*|\r\n|\r|\n/', $references);
    $items = is_array($items) ? array_filter(array_map('trim', $items)) : [];
    return implode("\n", $items);
  }

  // Busca un archivo en la biblioteca de medios por nombre y retorna su URL.
  // Si no lo encuentra, retorna una string vacia.
  private function get_media_url_by_filename(string $filename): string {
    if (empty($filename)) {
      return '';
    }

    // Si ya es una URL completa, devolverla como está
    if (filter_var($filename, FILTER_VALIDATE_URL)) {
      return esc_url_raw($filename);
    }

    global $wpdb;

    // Remover extensión para buscar por nombre base
    $name_without_ext = pathinfo($filename, PATHINFO_FILENAME);
    $name_without_ext_lower = strtolower($name_without_ext);

    // Buscar directamente en la base de datos por post_title (nombre del archivo)
    $query = $wpdb->prepare(
      "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND LOWER(post_title) = %s LIMIT 1",
      $name_without_ext_lower
    );

    $attachment_id = $wpdb->get_var($query);
    if ($attachment_id) {
      $url = wp_get_attachment_url($attachment_id);
      return $url ? esc_url_raw($url) : '';
    }

    return '';
  }

  // Hace "upsert" de una flashcard importada:
  // si existe, actualiza; si no, crea una nueva y la vincula a su topic/subtopic.
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
    update_post_meta($post_id, '_vc_flashcard_question_image_url', esc_url_raw((string) $data['question_image_url']));
    update_post_meta($post_id, '_vc_flashcard_acs_code', sanitize_text_field((string) $data['acs_code']));
    return (int) $post_id;
  }

  // Garantiza que existan topic y subtopic para una fila importada.
  // Devuelve el term_id final que se asociara con la flashcard.
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

  // Inserta un term nuevo o reutiliza uno ya existente con el mismo nombre y parent.
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

  // Busca un term exacto por nombre y parent para evitar duplicados incorrectos.
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

  // Renderiza la app principal de flashcards.
  // Aqui se calculan stats, se encolan assets y se carga el template PHP.
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

  // Inicia una sesion de practica normal y devuelve al frontend las tarjetas seleccionadas.
  public function ajax_start_session(): void {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => __('You must be logged in.', 'vc-flashcards')], 403);
    }

    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'random';
    $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $card_limit = isset($_POST['card_limit']) ? absint($_POST['card_limit']) : 10;
    $card_limit = max(1, min(50, $card_limit));

    if (!in_array($mode, ['random', 'global-random', 'category', 'subcategory'], true)) {
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

    $session_id = (int) $wpdb->insert_id;
    if ($session_id < 1) {
      wp_send_json_error(['message' => __('Could not start the session. Please try again.', 'vc-flashcards')], 500);
    }

    $stored_cards = $this->store_session_card_snapshot($session_id, get_current_user_id(), $cards);
    if ($stored_cards !== count($cards)) {
      $wpdb->delete($wpdb->prefix . self::SESSION_TABLE, ['id' => $session_id], ['%d']);
      $wpdb->delete($wpdb->prefix . self::SESSION_CARD_TABLE, ['session_id' => $session_id], ['%d']);
      wp_send_json_error(['message' => __('Could not prepare the session. Please try again.', 'vc-flashcards')], 500);
    }

    wp_send_json_success([
      'sessionId' => $session_id,
      'cards' => $cards,
    ]);
  }

  // Guarda el set exacto de tarjetas que conforman una sesion.
  // Esta tabla es la fuente confiable para validar scoring al completar.
  private function store_session_card_snapshot(int $session_id, int $user_id, array $cards): int {
    if ($session_id < 1 || $user_id < 1 || empty($cards)) {
      return 0;
    }

    global $wpdb;

    $table = $wpdb->prefix . self::SESSION_CARD_TABLE;
    $wpdb->delete($table, ['session_id' => $session_id, 'user_id' => $user_id], ['%d', '%d']);

    $stored_count = 0;
    foreach (array_values($cards) as $index => $card) {
      $flashcard_id = isset($card['id']) ? absint($card['id']) : 0;
      $correct_answer = isset($card['correctAnswer']) ? sanitize_key((string) $card['correctAnswer']) : '';

      if ($flashcard_id < 1 || !in_array($correct_answer, ['a', 'b', 'c'], true)) {
        continue;
      }

      $inserted = $wpdb->insert(
        $table,
        [
          'session_id' => $session_id,
          'user_id' => $user_id,
          'flashcard_id' => $flashcard_id,
          'topic_term_id' => isset($card['topicTermId']) ? absint($card['topicTermId']) : null,
          'correct_answer' => $correct_answer,
          'card_position' => $index,
        ],
        ['%d', '%d', '%d', '%d', '%s', '%d']
      );

      if ($inserted !== false) {
        $stored_count++;
      }
    }

    return $stored_count;
  }

  // Devuelve el snapshot server-side de tarjetas de una sesion, indexado por flashcard_id.
  private function get_session_card_snapshot(int $session_id, int $user_id): array {
    global $wpdb;

    $table = $wpdb->prefix . self::SESSION_CARD_TABLE;
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT flashcard_id, topic_term_id, correct_answer, card_position
      FROM {$table}
      WHERE session_id = %d
        AND user_id = %d
      ORDER BY card_position ASC, id ASC",
      $session_id,
      $user_id
    ), ARRAY_A);

    $snapshot = [];
    foreach ($rows as $row) {
      $flashcard_id = isset($row['flashcard_id']) ? absint($row['flashcard_id']) : 0;
      $correct_answer = isset($row['correct_answer']) ? sanitize_key((string) $row['correct_answer']) : '';
      if ($flashcard_id < 1 || !in_array($correct_answer, ['a', 'b', 'c'], true)) {
        continue;
      }

      $snapshot[$flashcard_id] = [
        'flashcard_id' => $flashcard_id,
        'topic_term_id' => isset($row['topic_term_id']) ? absint($row['topic_term_id']) : 0,
        'correct_answer' => $correct_answer,
        'card_position' => isset($row['card_position']) ? absint($row['card_position']) : 0,
      ];
    }

    return $snapshot;
  }

  // Removes server-only answer keys before sending exam cards to the browser.
  private function strip_correct_answers_from_cards(array $cards): array {
    return array_map(static function (array $card): array {
      unset($card['correctAnswer']);
      return $card;
    }, $cards);
  }

  // Completa una sesion, recalcula score en servidor y persiste cada intento.
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

    $session_cards = $this->get_session_card_snapshot($session_id, $user_id);
    if (empty($session_cards)) {
      wp_send_json_error(['message' => __('This session can no longer be verified. Please start a new one.', 'vc-flashcards')], 409);
    }

    // Only selected answer and timing are accepted from the browser. Correct answers
    // and valid flashcard membership always come from the server-side snapshot.
    $attempts_by_flashcard = [];
    foreach ($attempts as $attempt) {
      if (!is_array($attempt)) {
        continue;
      }

      $flashcard_id = isset($attempt['flashcardId']) ? absint($attempt['flashcardId']) : 0;
      if ($flashcard_id < 1 || !isset($session_cards[$flashcard_id])) {
        continue;
      }

      $selected_answer = isset($attempt['selectedAnswer']) ? sanitize_key((string) $attempt['selectedAnswer']) : '';
      if (!in_array($selected_answer, ['a', 'b', 'c'], true)) {
        $selected_answer = '';
      }

      $attempts_by_flashcard[$flashcard_id] = [
        'selected_answer' => $selected_answer,
        'response_time_ms' => isset($attempt['responseTimeMs']) ? absint($attempt['responseTimeMs']) : 0,
      ];
    }

    // Replace the browser payload with a trusted payload built from the session snapshot.
    // Unanswered snapshot cards are kept with an empty selected answer and count as incorrect.
    $trusted_attempts = [];
    foreach ($session_cards as $flashcard_id => $session_card) {
      $client_attempt = $attempts_by_flashcard[$flashcard_id] ?? [];
      $trusted_attempts[] = [
        'flashcardId' => $flashcard_id,
        'topicTermId' => $session_card['topic_term_id'],
        'selectedAnswer' => $client_attempt['selected_answer'] ?? '',
        'correctAnswer' => $session_card['correct_answer'],
        'responseTimeMs' => $client_attempt['response_time_ms'] ?? 0,
      ];
    }
    $attempts = $trusted_attempts;

    $wpdb->delete($attempts_table, ['session_id' => $session_id], ['%d']);

    /* Inicializa los contadores finales de la sesion antes de recorrer los intentos enviados por JS. */
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

      /* Cada intento valido suma una tarjeta respondida al total de la sesion. */
      $total_cards++;
      /* is_correct vale 1 si acerto y 0 si fallo, por eso puede acumularse directamente. */
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

    /* Calcula el porcentaje final de la sesion usando: respuestas correctas / total de tarjetas validas * 100. */
    /* Si no hubo tarjetas validas respondidas, devuelve 0 para evitar division entre cero. */
    $incorrect_answers = max(0, $total_cards - $correct_answers);
    $precision_percent = $total_cards > 0 ? round(($correct_answers / $total_cards) * 100, 2) : 0;
    $score_percent = $total_cards > 0 ? round(($correct_answers / $total_cards) * 100, 2) : 0;

    $updated = $wpdb->update(
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

    if ($updated === false) {
      wp_send_json_error(['message' => __('Could not save the session results. Please try again.', 'vc-flashcards')], 500);
    }

    /* Devuelve al frontend las metricas finales para pintar el resumen de la sesion. */
    wp_send_json_success([
      'stats' => $this->get_user_stats($user_id),
      'examHomeStats' => $this->get_exam_home_stats($user_id),
      'precisionPercent' => $precision_percent,
      'scorePercent' => $score_percent,
      'correctAnswers' => $correct_answers,
      'incorrectAnswers' => $incorrect_answers,
      'totalCards' => $total_cards,
    ]);
  }

  // Reune el pool base de tarjetas disponibles segun el modo de practica y el term elegido.
  private function get_flashcards_for_session(string $mode, int $term_id, int $limit): array {
    $pool_cache_key = $this->get_session_cards_pool_cache_key($mode, $term_id);
    $cached_pool = get_transient($pool_cache_key);
    $is_random_mode = in_array($mode, ['random', 'global-random'], true);

    if (is_array($cached_pool)) {
      return $this->select_cards_from_pool($cached_pool, $limit, $is_random_mode);
    }

    $tax_query = [];
    $include_children = $mode !== 'subcategory';

    if ($mode !== 'global-random' && $term_id > 0) {
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
      'posts_per_page' => -1,
      'orderby' => 'ID',
      'order' => 'ASC',
      'tax_query' => $tax_query,
      'fields' => 'ids',
      'no_found_rows' => true,
    ]);

    if (empty($query->posts)) {
      return [];
    }

    $selected_ids = $query->posts;

    update_meta_cache('post', $selected_ids);
    update_object_term_cache($selected_ids, self::POST_TYPE);

    $cards_pool = [];
    foreach ($selected_ids as $post_id) {
      $card = $this->build_flashcard_payload((int) $post_id);
      if (!empty($card)) {
        $cards_pool[] = $card;
      }
    }

    set_transient($pool_cache_key, $cards_pool, 5 * MINUTE_IN_SECONDS);

    return $this->select_cards_from_pool($cards_pool, $limit, $is_random_mode);
  }

  /* Selecciona el subset final sin volver a consultar ni reconstruir el pool base de cards. */
  // A partir del pool disponible, elige exactamente las tarjetas que usara la sesion.
  private function select_cards_from_pool(array $cards_pool, int $limit, bool $is_random_mode): array {
    if ($is_random_mode) {
      shuffle($cards_pool);
    }

    return array_slice($cards_pool, 0, $limit);
  }

  // Convierte un post de WordPress a un payload simple que entiende el frontend JS.
  private function build_flashcard_payload(int $post_id): array {
    /* Lee el meta completo una sola vez para evitar multiples accesos al mismo post. */
    $meta = get_post_meta($post_id);
    $question = $this->get_first_meta_value($meta, '_vc_flashcard_question');
    $answer_a = $this->get_first_meta_value($meta, '_vc_flashcard_answer_a');
    $answer_b = $this->get_first_meta_value($meta, '_vc_flashcard_answer_b');
    $answer_c = $this->get_first_meta_value($meta, '_vc_flashcard_answer_c');
    $correct_answer = $this->get_first_meta_value($meta, '_vc_flashcard_correct_answer');
    $explanation = $this->get_first_meta_value($meta, '_vc_flashcard_explanation');
    $references = $this->get_first_meta_value($meta, '_vc_flashcard_references');
    $question_image_url = $this->get_first_meta_value($meta, '_vc_flashcard_question_image_url');
    $acs_code = $this->get_first_meta_value($meta, '_vc_flashcard_acs_code');

    if ($question === '' || $answer_a === '' || $answer_b === '' || $answer_c === '' || !in_array($correct_answer, ['a', 'b', 'c'], true)) {
      return [];
    }

    $term_payload = $this->get_flashcard_term_payload($post_id);

    return [
      'id' => $post_id,
      'question' => wp_strip_all_tags($question),
      'questionImageUrl' => esc_url_raw($question_image_url),
      'answers' => [
        'a' => $answer_a,
        'b' => $answer_b,
        'c' => $answer_c,
      ],
      'acsCode' => $acs_code,
      'correctAnswer' => $correct_answer,
      'explanation' => wp_kses_post(wpautop($explanation)),
      'references' => $this->parse_references($references),
      'topicTermId' => $term_payload['term_id'],
      'topicLabel' => $term_payload['topic_label'],
      'subtopicLabel' => $term_payload['subtopic_label'],
    ];
  }

  /* Devuelve el primer valor escalar de una clave de meta ya precargada. */
  // Extrae un valor de meta de forma segura aunque WordPress lo devuelva como array.
  private function get_first_meta_value(array $meta, string $key): string {
    if (!isset($meta[$key][0])) {
      return '';
    }

    return (string) maybe_unserialize($meta[$key][0]);
  }

  /* Genera la clave del pool base por modo y termino para reutilizarlo entre distintos limits. */
  // Genera una clave de cache para reusar pools de tarjetas por modo y term.
  private function get_session_cards_pool_cache_key(string $mode, int $term_id): string {
    return 'vc_flashcards_session_cards_pool_' . md5($mode . '|' . $term_id);
  }

  // Convierte el texto de referencias guardado en una lista usable por frontend.
  private function parse_references(string $references): array {
    $items = preg_split('/\r\n|\r|\n/', $references);
    $items = is_array($items) ? array_filter(array_map('trim', $items)) : [];
    return array_values($items);
  }

  // Resuelve topic y subtopic de una flashcard y los empaqueta para la UI.
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

  // Construye el arbol completo de topics/subtopics usado en el home de flashcards.
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

  // Arma las cards de categorias del home con progreso, conteos y metadata del usuario.
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

    $mastered_ids = $this->get_mastered_flashcard_ids($user_id);
    $categories = [];

    foreach ($parents as $parent) {
      $card_ids = $this->get_flashcard_ids_for_term((int) $parent->term_id);
      $mastered_count = count(array_intersect($card_ids, $mastered_ids));
      $progress = !empty($card_ids) ? (int) round(($mastered_count / count($card_ids)) * 100) : 0;
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
          $child_mastered_count = count(array_intersect($child_card_ids, $mastered_ids));
          $child_progress = !empty($child_card_ids) ? (int) round(($child_mastered_count / count($child_card_ids)) * 100) : 0;

          $child_items[] = [
            'id' => (int) $child->term_id,
            'name' => $child->name,
            'totalCards' => count($child_card_ids),
            'masteredCards' => $child_mastered_count,
            'progress' => $child_progress,
            'status' => $child_progress >= 100 && !empty($child_card_ids) ? __('Mastered', 'vc-flashcards') : '',
            // Subtopic row metric only.
            // Do not reuse dashboard metrics here: this text must stay tied to this subtopic's own card totals.
            'description' => $this->format_subtopic_mastery_description($child_mastered_count, count($child_card_ids)),
          ];
        }
      }

      $categories[] = [
        'id' => (int) $parent->term_id,
        'name' => $parent->name,
        'totalCards' => count($card_ids),
        'masteredCards' => $mastered_count,
        'progress' => $progress,
        'subtopicCount' => count($child_items),
        'description' => sprintf(
          /* translators: 1: subtopic count, 2: mastered cards, 3: total cards */
          __('%1$d subtopics · %2$d/%3$d cards mastered', 'vc-flashcards'),
          count($child_items),
          $mastered_count,
          count($card_ids)
        ),
        'children' => $child_items,
      ];
    }

    return $categories;
  }

  // Formats the secondary text below a subtopic title.
  // This belongs only to subtopic rows; dashboard and category cards have separate metric contracts.
  private function format_subtopic_mastery_description(int $mastered_cards, int $total_cards): string {
    $safe_total = max(0, $total_cards);
    $safe_mastered = max(0, min($mastered_cards, $safe_total));

    return sprintf(
      /* translators: 1: total cards in subtopic, 2: mastered cards in subtopic */
      __('%1$d cards · %2$d completed', 'vc-flashcards'),
      $safe_total,
      $safe_mastered
    );
  }

  // Calcula todas las metricas del home de flashcards para el usuario actual.
  private function get_user_stats(int $user_id): array {
    $total_flashcards = $this->get_total_published_flashcard_count();
    $viewed_flashcards = $this->get_viewed_flashcard_count($user_id);
    $viewed_flashcards_label = $this->format_progress_count($viewed_flashcards, $total_flashcards);
    $total_subtopics = $this->get_total_subtopic_count();
    $viewed_subtopics = $this->get_viewed_subtopic_count($user_id);
    $viewed_subtopics_label = $this->format_progress_count($viewed_subtopics, $total_subtopics);

    $latest_score = $this->get_latest_flashcards_score($user_id);

    // Dashboard-only metrics.
    // Keep these separate from category/subtopic card metrics:
    // - dashboard uses latestSessionScorePercent, totalReviewed, topicsCompleted
    // - category cards use progress/masteredCards/totalCards
    // - subtopic rows use their own description built from subtopic totals
    return [
      // Header metric: latest session accuracy.
      // Do not replace this with masteredCards/progress; those belong to category cards.
      'latestSessionScorePercent' => $latest_score,
      'totalReviewed' => $viewed_flashcards_label,
      'topicsCompleted' => $viewed_subtopics_label,
    ];
  }

  // Devuelve el porcentaje de la ultima sesion normal completada.
  // No usa MAX(): si el usuario baja de 100% a 30%, el dashboard debe mostrar 30%.
  private function get_latest_flashcards_score(int $user_id): int {
    global $wpdb;

    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $latest_score = $wpdb->get_var($wpdb->prepare(
      "SELECT score_percent
      FROM {$sessions_table}
      WHERE user_id = %d
        AND completed_at IS NOT NULL
        AND mode <> %s
      ORDER BY completed_at DESC, id DESC
      LIMIT 1",
      $user_id,
      'exam'
    ));

    return $latest_score !== null ? (int) round((float) $latest_score) : 0;
  }

  // Cuenta el total actual de flashcards publicadas.
  // Centralizarlo evita que cada metrica interprete distinto que significa "total".
  private function get_total_published_flashcard_count(): int {
    $published_flashcards = wp_count_posts(self::POST_TYPE);

    return $published_flashcards instanceof stdClass ? (int) $published_flashcards->publish : 0;
  }

  // Cuenta cuantas tarjetas publicadas vio el usuario en sesiones normales.
  // Una tarjeta vista es cualquier intento guardado, ya sea respondido o revelado.
  private function get_viewed_flashcard_count(int $user_id): int {
    global $wpdb;

    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $posts_table = $wpdb->posts;

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT attempt.flashcard_id)
      FROM {$attempts_table} attempt
      INNER JOIN {$sessions_table} study_session
        ON study_session.id = attempt.session_id
        AND study_session.user_id = attempt.user_id
        AND study_session.completed_at IS NOT NULL
        AND study_session.mode <> %s
      INNER JOIN {$posts_table} flashcard
        ON flashcard.ID = attempt.flashcard_id
        AND flashcard.post_type = %s
        AND flashcard.post_status = %s
      WHERE attempt.user_id = %d",
      'exam',
      self::POST_TYPE,
      'publish',
      $user_id
    ));
  }

  // Cuenta los subtopics que tienen al menos una flashcard publicada.
  // Esto evita mostrar progreso contra subcategorias vacias que el usuario no puede estudiar.
  private function get_total_subtopic_count(): int {
    global $wpdb;

    $term_taxonomy_table = $wpdb->term_taxonomy;
    $term_relationships_table = $wpdb->term_relationships;
    $posts_table = $wpdb->posts;

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT subtopic.term_id)
      FROM {$term_taxonomy_table} subtopic
      INNER JOIN {$term_relationships_table} relation
        ON relation.term_taxonomy_id = subtopic.term_taxonomy_id
      INNER JOIN {$posts_table} flashcard
        ON flashcard.ID = relation.object_id
        AND flashcard.post_type = %s
        AND flashcard.post_status = %s
      WHERE subtopic.taxonomy = %s
        AND subtopic.parent <> 0",
      self::POST_TYPE,
      'publish',
      self::TAXONOMY
    ));
  }

  // Cuenta cuantos subtopics distintos vio el usuario en sesiones normales completadas.
  // Se usa DISTINCT porque volver a estudiar el mismo subtopic no debe inflar el progreso.
  // Tambien validamos contra posts publicados para ignorar intentos historicos de contenido eliminado.
  private function get_viewed_subtopic_count(int $user_id): int {
    global $wpdb;

    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $term_taxonomy_table = $wpdb->term_taxonomy;
    $posts_table = $wpdb->posts;

    return (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(DISTINCT subtopic.term_id)
      FROM {$attempts_table} attempt
      INNER JOIN {$sessions_table} study_session
        ON study_session.id = attempt.session_id
        AND study_session.user_id = attempt.user_id
        AND study_session.completed_at IS NOT NULL
        AND study_session.mode <> %s
      INNER JOIN {$term_taxonomy_table} subtopic
        ON subtopic.term_id = attempt.topic_term_id
        AND subtopic.taxonomy = %s
        AND subtopic.parent <> 0
      INNER JOIN {$posts_table} flashcard
        ON flashcard.ID = attempt.flashcard_id
        AND flashcard.post_type = %s
        AND flashcard.post_status = %s
      WHERE attempt.user_id = %d",
      'exam',
      self::TAXONOMY,
      self::POST_TYPE,
      'publish',
      $user_id
    ));
  }

  // Formatea conteos tipo "vistas / total" y protege la UI ante datos antiguos o inconsistentes.
  private function format_progress_count(int $current, int $total): string {
    $safe_total = max(0, $total);
    $safe_current = max(0, min($current, $safe_total));

    return $safe_current . '/' . $safe_total;
  }

  // Devuelve las tarjetas dominadas por el usuario.
  // Regla de negocio: una tarjeta cuenta como mastered solo si su ultimo intento publicado fue correcto.
  // La tabla de intentos es append-only, por eso MAX(id) identifica el intento mas reciente por flashcard.
  private function get_mastered_flashcard_ids(int $user_id): array {
    global $wpdb;

    static $cache = [];
    if (isset($cache[$user_id])) {
      return $cache[$user_id];
    }

    $attempts_table = $wpdb->prefix . self::ATTEMPT_TABLE;
    $posts_table = $wpdb->posts;
    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT latest_attempt.flashcard_id
      FROM {$attempts_table} latest_attempt
      INNER JOIN (
        SELECT flashcard_id, MAX(id) AS latest_id
        FROM {$attempts_table}
        WHERE user_id = %d
        GROUP BY flashcard_id
      ) latest ON latest.latest_id = latest_attempt.id
      INNER JOIN {$posts_table} flashcard
        ON flashcard.ID = latest_attempt.flashcard_id
        AND flashcard.post_type = %s
        AND flashcard.post_status = %s
      WHERE latest_attempt.user_id = %d
        AND latest_attempt.is_correct = 1",
      $user_id,
      self::POST_TYPE,
      'publish',
      $user_id
    ));

    $cache[$user_id] = array_map('intval', $ids);
    return $cache[$user_id];
  }

  // Devuelve todos los IDs de flashcards asociados a un term concreto.
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

  // Ordena topics padre en el orden deseado del producto.
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

  /* Exam Simulator ------------------------------------------------------- */

  // Renderiza el shortcode del simulador de examen.
  // Aqui se preparan assets, labels y datos iniciales del home del mock test.
  public function render_exam_shortcode($atts = []): string {
    if (!is_user_logged_in()) {
      return '<div class="vc-flashcards-guest">' . esc_html__('Please log in to use the exam simulator.', 'vc-flashcards') . '</div>';
    }

    $user_id = get_current_user_id();
    $exam_config = [
      'totalQuestions'   => 100,
      'timeLimitSeconds' => 900,
      'passingScore'     => self::PASSING_SCORE,
    ];
    $exam_time_limit_label = $this->format_exam_time_limit_label($exam_config['timeLimitSeconds']);
    $exam_home_stats = $this->get_exam_home_stats($user_id);

    wp_enqueue_style(
      'vc-flashcards-style',
      VC_FLASHCARDS_URL . 'assets/flashcards.css',
      [],
      file_exists(VC_FLASHCARDS_DIR . 'assets/flashcards.css') ? (string) filemtime(VC_FLASHCARDS_DIR . 'assets/flashcards.css') : '1.0.0'
    );

    wp_enqueue_style(
      'vc-exam-style',
      VC_FLASHCARDS_URL . 'assets/exam.css',
      ['vc-flashcards-style'],
      file_exists(VC_FLASHCARDS_DIR . 'assets/exam.css') ? (string) filemtime(VC_FLASHCARDS_DIR . 'assets/exam.css') : '1.0.0'
    );

    wp_enqueue_script(
      'vc-exam-script',
      VC_FLASHCARDS_URL . 'assets/exam.js',
      [],
      file_exists(VC_FLASHCARDS_DIR . 'assets/exam.js') ? (string) filemtime(VC_FLASHCARDS_DIR . 'assets/exam.js') : '1.0.0',
      true
    );

    wp_localize_script('vc-exam-script', 'vcExamData', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce'   => wp_create_nonce(self::NONCE_ACTION),
      'examConfig' => $exam_config,
      'labels' => [
        'loading'      => __('Preparing your exam', 'vc-flashcards'),
        'noCards'      => __('No questions were found for this category.', 'vc-flashcards'),
        'question'     => __('Question', 'vc-flashcards'),
        'of'           => __('of', 'vc-flashcards'),
        'answered'     => __('answered', 'vc-flashcards'),
        'outOf'        => __('out of', 'vc-flashcards'),
        'next'         => __('Next question', 'vc-flashcards'),
        'finish'       => __('Finish exam', 'vc-flashcards'),
        'passed'       => __('Passed!', 'vc-flashcards'),
        'failed'       => __('Failed', 'vc-flashcards'),
        'approved'     => __('Approved', 'vc-flashcards'),
        'notApproved'  => __('Not approved', 'vc-flashcards'),
        'timeExpired'  => __('Time expired', 'vc-flashcards'),
        'tryAgain'     => __('Try again', 'vc-flashcards'),
        'backToMenu'   => __('Back to menu', 'vc-flashcards'),
        'examComplete' => __('Exam complete!', 'vc-flashcards'),
        'correct'      => __('Correct', 'vc-flashcards'),
        'incorrect'    => __('Incorrect', 'vc-flashcards'),
        'passingScore' => __('Passing score', 'vc-flashcards'),
        'timeUsed'     => __('Time used', 'vc-flashcards'),
        'congratulations' => __('Congratulations! You passed the exam.', 'vc-flashcards'),
        'keepStudying'    => __('Keep studying. You need 70% to pass.', 'vc-flashcards'),
        'confirmAbandon'  => __('Are you sure you want to abandon the exam? Your progress will be lost.', 'vc-flashcards'),
        'saveFailed'      => __('We could not save your exam. Please try again.', 'vc-flashcards'),
      ],
    ]);

    $exam_categories = $this->get_exam_categories();
    $exam_history = $this->get_exam_history($user_id, self::EXAM_HISTORY_LIMIT);
    $exam_history_subtitle = $this->get_exam_history_subtitle(count($exam_history));

    ob_start();
    include VC_FLASHCARDS_DIR . 'templates/shortcode-exam.php';
    return (string) ob_get_clean();
  }

  // Calcula las metricas que aparecen en el home del examen:
  // best score, average y passed attempts de los intentos recientes.
  private function get_exam_home_stats(int $user_id): array {
    global $wpdb;

    // Tabla de sesiones: aqui filtramos solo las sesiones completadas del simulador de examen.
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    // Resume el rendimiento historico del usuario solo en modo exam.
    // Calcula mejor score y promedio para los badges superiores del home del examen.
    $exam_scores = $wpdb->get_row($wpdb->prepare(
      "SELECT
        MAX(score_percent) AS best_score,
        AVG(score_percent) AS average_score
      FROM {$sessions_table}
      WHERE user_id = %d
        AND mode = %s
        AND completed_at IS NOT NULL",
      $user_id,
      'exam'
    ), ARRAY_A);
    // Recupera los ultimos intentos de examen para contar cuantos aprobaron.
    // El resultado se presenta como x/5 en el badge "Passed attempts".
    $recent_exam_scores = $wpdb->get_col($wpdb->prepare(
      "SELECT score_percent
      FROM {$sessions_table}
      WHERE user_id = %d
        AND mode = %s
        AND completed_at IS NOT NULL
      ORDER BY completed_at DESC, id DESC
      LIMIT %d",
      $user_id,
      'exam',
      self::EXAM_HISTORY_LIMIT
    ));
    $passed_attempts = 0;
    foreach ($recent_exam_scores as $score_value) {
      if ((float) $score_value >= self::PASSING_SCORE) {
        $passed_attempts++;
      }
    }

    // Devuelve un set pequeno de metricas pensado solo para la home del mock test.
    return [
      'bestScore' => isset($exam_scores['best_score']) ? (int) round((float) $exam_scores['best_score']) : 0,
      'averageScore' => isset($exam_scores['average_score']) ? (int) round((float) $exam_scores['average_score']) : 0,
      'passedAttempts' => $passed_attempts . '/' . self::EXAM_HISTORY_LIMIT,
    ];
  }

  // Inicia un examen nuevo.
  // Crea la sesion, selecciona preguntas del topic elegido y devuelve payload al frontend.
  public function ajax_start_exam(): void {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => __('You must be logged in.', 'vc-flashcards')], 403);
    }

    $topic_term_id = isset($_POST['topic_term_id']) ? absint($_POST['topic_term_id']) : 0;

    if ($topic_term_id < 1) {
      wp_send_json_error(['message' => __('Please select a topic.', 'vc-flashcards')], 400);
    }

    $cards = $this->get_exam_cards_for_topic($topic_term_id);

    if (empty($cards)) {
      wp_send_json_error(['message' => __('No flashcards were found for this topic.', 'vc-flashcards')], 404);
    }

    global $wpdb;

    $wpdb->insert(
      $wpdb->prefix . self::SESSION_TABLE,
      [
        'user_id'         => get_current_user_id(),
        'mode'            => 'exam',
        'topic_term_id'   => $topic_term_id,
        'total_cards'     => count($cards),
        'correct_answers' => 0,
        'score_percent'   => 0,
        'started_at'      => current_time('mysql'),
      ],
      ['%d', '%s', '%d', '%d', '%d', '%f', '%s']
    );

    $session_id = (int) $wpdb->insert_id;
    if ($session_id < 1) {
      wp_send_json_error(['message' => __('Could not start the exam. Please try again.', 'vc-flashcards')], 500);
    }

    $stored_cards = $this->store_session_card_snapshot($session_id, get_current_user_id(), $cards);
    if ($stored_cards !== count($cards)) {
      $wpdb->delete($wpdb->prefix . self::SESSION_TABLE, ['id' => $session_id], ['%d']);
      $wpdb->delete($wpdb->prefix . self::SESSION_CARD_TABLE, ['session_id' => $session_id], ['%d']);
      wp_send_json_error(['message' => __('Could not prepare the exam. Please try again.', 'vc-flashcards')], 500);
    }

    wp_send_json_success([
      'sessionId'      => $session_id,
      'cards'          => $this->strip_correct_answers_from_cards($cards),
      'totalQuestions' => count($cards),
    ]);
  }

  // Devuelve el HTML del historial del examen por AJAX para refrescarlo sin recargar la pagina.
  public function ajax_get_exam_history(): void {
    check_ajax_referer(self::NONCE_ACTION, 'nonce');

    if (!is_user_logged_in()) {
      wp_send_json_error(['message' => __('You must be logged in.', 'vc-flashcards')], 403);
    }

    $user_id = get_current_user_id();

    // Devuelve solo el HTML del historial del examen para refrescar el bloque por AJAX.
    // Esto evita recargar toda la pagina despues de completar un mock test.
    wp_send_json_success([
      'html' => $this->render_exam_history_content($user_id),
    ]);
  }

  /* Builds a pool of up to 100 questions distributed proportionally across subtopics. */
  // Devuelve las preguntas que formaran parte del examen para un topic padre.
  // Intenta llenar hasta el total pedido recorriendo sus subtopics/hijos.
  private function get_exam_cards_for_topic(int $topic_term_id, int $total = 100): array {
    $pool = $this->get_exam_id_pool_for_topic($topic_term_id);
    $parent_ids = isset($pool['parent_ids']) && is_array($pool['parent_ids'])
      ? array_values(array_map('intval', $pool['parent_ids']))
      : [];
    $subtopic_order = isset($pool['subtopic_order']) && is_array($pool['subtopic_order'])
      ? array_values(array_map('intval', $pool['subtopic_order']))
      : [];
    $subtopic_ids = isset($pool['subtopic_ids']) && is_array($pool['subtopic_ids'])
      ? $pool['subtopic_ids']
      : [];

    if (empty($parent_ids)) {
      return [];
    }

    if (empty($subtopic_order)) {
      shuffle($parent_ids);
      return $this->build_exam_cards_from_ids(array_slice($parent_ids, 0, $total), $total);
    }

    /*
     * Distribute total questions across subtopics.
     * Examples: 2 subtopics = 50 / 50, 3 subtopics = 33 / 33 / 34.
     */
    $n             = count($subtopic_order);
    $base          = intdiv($total, $n);
    $remainder     = $total % $n;

    $quotas           = array_fill(0, $n, $base);
    $quotas[$n - 1]  += $remainder;

    $selected_ids = [];

    foreach ($subtopic_order as $index => $subtopic_id) {
      $quota = $quotas[$index];
      $ids = isset($subtopic_ids[$subtopic_id]) && is_array($subtopic_ids[$subtopic_id])
        ? array_values(array_map('intval', $subtopic_ids[$subtopic_id]))
        : [];
      if (empty($ids)) {
        continue;
      }

      shuffle($ids);
      $selected = array_slice($ids, 0, $quota);
      $selected_ids = array_merge($selected_ids, $selected);
    }

    // Some subtopics may not have enough cards to fill their proportional quota.
    // Backfill from the whole parent topic so the exam reaches the configured total when possible.
    if (count($selected_ids) < $total) {
      $remaining_slots = $total - count($selected_ids);
      $selected_lookup = array_fill_keys(array_map('intval', $selected_ids), true);
      $backfill_ids = array_values(array_filter($parent_ids, static function (int $post_id) use ($selected_lookup): bool {
        return !isset($selected_lookup[$post_id]);
      }));
      shuffle($backfill_ids);
      $backfill_ids = array_slice($backfill_ids, 0, $remaining_slots);

      if (!empty($backfill_ids)) {
        $selected_ids = array_merge($selected_ids, $backfill_ids);
      }
    }

    shuffle($selected_ids);
    return $this->build_exam_cards_from_ids(array_slice(array_values(array_unique($selected_ids)), 0, $total), $total);
  }

  private function get_exam_id_pool_for_topic(int $topic_term_id): array {
    $cache_key = $this->get_exam_id_pool_cache_key($topic_term_id);
    $cached_pool = get_transient($cache_key);
    if (is_array($cached_pool)) {
      return $cached_pool;
    }

    $subtopics = get_terms([
      'taxonomy'   => self::TAXONOMY,
      'hide_empty' => false,
      'parent'     => $topic_term_id,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]);

    $parent_ids = $this->query_flashcard_ids_for_term($topic_term_id, true);
    $pool = [
      'parent_ids' => $parent_ids,
      'subtopic_order' => [],
      'subtopic_ids' => [],
    ];

    if (!is_wp_error($subtopics) && !empty($subtopics)) {
      foreach (array_values($subtopics) as $subtopic) {
        $subtopic_id = (int) $subtopic->term_id;
        $ids = $this->query_flashcard_ids_for_term($subtopic_id, false);
        $pool['subtopic_order'][] = $subtopic_id;
        $pool['subtopic_ids'][$subtopic_id] = $ids;
      }
    }

    set_transient($cache_key, $pool, self::EXAM_POOL_CACHE_TTL);
    return $pool;
  }

  private function query_flashcard_ids_for_term(int $term_id, bool $include_children): array {
    $query = new WP_Query([
      'post_type'      => self::POST_TYPE,
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'no_found_rows'  => true,
      'tax_query'      => [[
        'taxonomy'         => self::TAXONOMY,
        'field'            => 'term_id',
        'terms'            => [$term_id],
        'include_children' => $include_children,
      ]],
    ]);

    return array_values(array_map('intval', $query->posts ?: []));
  }

  private function build_exam_cards_from_ids(array $selected_ids, int $total): array {
    $selected_ids = array_values(array_unique(array_map('intval', $selected_ids)));
    if (empty($selected_ids)) {
      return [];
    }

    update_meta_cache('post', $selected_ids);
    update_object_term_cache($selected_ids, self::POST_TYPE);

    $cards = [];
    foreach ($selected_ids as $post_id) {
      $card = $this->build_flashcard_payload($post_id);
      if (!empty($card)) {
        $cards[] = $card;
      }
    }

    shuffle($cards);
    return array_slice($cards, 0, $total);
  }

  private function get_exam_id_pool_cache_key(int $topic_term_id): string {
    return 'vc_flashcards_exam_id_pool_' . $this->get_exam_pool_cache_version() . '_' . $topic_term_id;
  }

  private function get_exam_pool_cache_version(): int {
    $version = (int) get_option(self::EXAM_POOL_CACHE_VERSION_OPTION, 1);
    return max(1, $version);
  }

  private function clear_exam_pool_cache(): void {
    $version = (int) get_option(self::EXAM_POOL_CACHE_VERSION_OPTION, 1);
    update_option(self::EXAM_POOL_CACHE_VERSION_OPTION, max(1, $version) + 1, false);
  }

  /* Returns parent topics enriched with subtopic count and total card count for the exam selector. */
  // Construye las categorias que se muestran en el home del examen:
  // General, Airframe y Powerplant con sus conteos y labels auxiliares.
  private function get_exam_categories(): array {
    $parents = get_terms([
      'taxonomy'   => self::TAXONOMY,
      'hide_empty' => false,
      'parent'     => 0,
      'orderby'    => 'name',
      'order'      => 'ASC',
    ]);

    if (is_wp_error($parents) || empty($parents)) {
      return [];
    }

    $parents    = $this->sort_parent_topics($parents);
    $categories = [];

    foreach ($parents as $parent) {
      $children = get_terms([
        'taxonomy'   => self::TAXONOMY,
        'hide_empty' => false,
        'parent'     => $parent->term_id,
        'orderby'    => 'name',
        'order'      => 'ASC',
      ]);

      $subtopic_count = is_wp_error($children) ? 0 : count($children);
      $total_cards    = count($this->get_flashcard_ids_for_term((int) $parent->term_id));

      $categories[] = [
        'id'           => (int) $parent->term_id,
        'name'         => $parent->name,
        'totalCards'   => $total_cards,
        'subtopicCount' => $subtopic_count,
      ];
    }

    return $categories;
  }

  /* Devuelve los intentos completados del examen ya formateados para pintar el historial. */
  // Lee intentos de examen ya terminados y los transforma al formato del historial visual.
  private function get_exam_history(int $user_id, int $limit = self::EXAM_HISTORY_LIMIT): array {
    global $wpdb;

    // Tabla de sesiones: desde aqui salen fecha, duracion y score de cada intento de examen.
    $sessions_table = $wpdb->prefix . self::SESSION_TABLE;
    $limit = max(1, $limit);
    // Recupera solo examenes completados del usuario, ordenados del mas reciente al mas antiguo.
    $rows = $wpdb->get_results($wpdb->prepare(
      "SELECT started_at, completed_at, score_percent
      FROM {$sessions_table}
      WHERE user_id = %d
        AND mode = %s
        AND completed_at IS NOT NULL
      ORDER BY completed_at DESC, id DESC
      LIMIT %d",
      $user_id,
      'exam',
      $limit
    ), ARRAY_A);

    if (empty($rows)) {
      return [];
    }

    $history = [];
    foreach ($rows as $row) {
      $started_at = isset($row['started_at']) ? strtotime((string) $row['started_at']) : false;
      $completed_at = isset($row['completed_at']) ? strtotime((string) $row['completed_at']) : false;
      if (!$completed_at) {
        continue;
      }

      // Calcula una duracion legible para el historial a partir de started_at y completed_at.
      $duration_label = '0 min';
      if ($started_at && $completed_at >= $started_at) {
        $elapsed_seconds = max(0, $completed_at - $started_at);
        $elapsed_minutes = max(1, (int) round($elapsed_seconds / 60));
        $hours = intdiv($elapsed_minutes, 60);
        $minutes = $elapsed_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
          $duration_label = sprintf('%dh %02d min', $hours, $minutes);
        } elseif ($hours > 0) {
          $duration_label = sprintf('%dh', $hours);
        } else {
          $duration_label = sprintf('%d min', $minutes);
        }
      }

      $score_value = isset($row['score_percent']) ? (float) $row['score_percent'] : 0.0;
      $score_percent = (int) round($score_value);

      // Normaliza cada intento en un array listo para la vista:
      // fecha, duracion, score, estado visual e icono correspondiente.
      $history[] = [
        'date' => date_i18n('d M Y', $completed_at),
        'duration' => $duration_label,
        'score' => $score_percent . '%',
        'passed' => $score_percent >= self::PASSING_SCORE,
        'status_label' => $score_percent >= self::PASSING_SCORE ? __('Passed', 'vc-flashcards') : __('Failed', 'vc-flashcards'),
        'icon_url' => VC_FLASHCARDS_URL . 'assets/icons/' . ($score_percent >= self::PASSING_SCORE ? 'correctoHist.svg' : 'incorrectoHist.svg'),
      ];
    }

    return $history;
  }

  /* Renderiza el historial del examen para reutilizar el mismo markup en carga inicial y en AJAX. */
  // Renderiza solo el bloque parcial del historial para reutilizarlo en carga inicial y AJAX.
  private function render_exam_history_content(int $user_id): string {
    $exam_history = $this->get_exam_history($user_id, self::EXAM_HISTORY_LIMIT);
    $exam_history_subtitle = $this->get_exam_history_subtitle(count($exam_history));

    ob_start();
    include VC_FLASHCARDS_DIR . 'templates/partials/exam-history-content.php';
    return (string) ob_get_clean();
  }

  /* Construye el subtitulo del historial en funcion de cuantos intentos se muestran. */
  // Genera el subtitulo tipo "Your last 5 attempts" segun cuantos intentos se muestran.
  private function get_exam_history_subtitle(int $attempt_count): string {
    return sprintf(
      /* translators: %d: number of exam attempts shown in history */
      __('Your last %d attempts', 'vc-flashcards'),
      $attempt_count
    );
  }

  // Formatea el tiempo limite del examen para mantener el copy sincronizado con la config real.
  private function format_exam_time_limit_label(int $seconds): string {
    $safe_seconds = max(0, $seconds);
    $minutes = (int) floor($safe_seconds / 60);

    if ($minutes >= 60) {
      $hours = intdiv($minutes, 60);
      $remaining_minutes = $minutes % 60;

      if ($remaining_minutes > 0) {
        return sprintf(
          /* translators: 1: hours, 2: minutes */
          __('%1$d hr %2$d min time limit', 'vc-flashcards'),
          $hours,
          $remaining_minutes
        );
      }

      return sprintf(
        /* translators: %d: hours */
        _n('%d hour time limit', '%d hours time limit', $hours, 'vc-flashcards'),
        $hours
      );
    }

    return sprintf(
      /* translators: %d: minutes */
      _n('%d minute time limit', '%d minutes time limit', $minutes, 'vc-flashcards'),
      $minutes
    );
  }
}
