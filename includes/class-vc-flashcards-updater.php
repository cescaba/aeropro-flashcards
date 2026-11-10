<?php

if (!defined('ABSPATH')) exit;

require_once VC_FLASHCARDS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

/**
 * Actualizaciones desde GitHub: WordPress avisa "hay una versión nueva" en Plugins
 * leyendo los tags del repo (v1.2.0) y descarga el zip desde ahi.
 *
 * El token es opcional mientras el repo sea publico (evita el limite de 60 consultas/hora
 * por IP); si el repo pasa a privado es obligatorio. Se guarda en Ajustes > Actualizaciones VC Flashcards.
 */
if (!class_exists('VC_Flashcards_Updater')) {
  class VC_Flashcards_Updater {
    const REPO = 'cescaba/aeropro-flashcards';
    const BRANCH = 'main';
    const SLUG = 'vc-flashcards';
    const OPTION_TOKEN = 'vc_flashcards_github_token';
    const SETTINGS_SLUG = 'vc-flashcards-updates';

    /** @var \YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker */
    private $checker;

    public function __construct() {
      $this->checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/' . self::REPO . '/',
        VC_FLASHCARDS_FILE,
        self::SLUG
      );

      // Sin tags (o antes del primer release) lee la version desde la rama principal.
      $this->checker->setBranch(self::BRANCH);

      $token = self::token();
      if ($token !== '') {
        $this->checker->setAuthentication($token);
      }

      add_action('admin_menu', [$this, 'register_settings_page']);
    }

    /** Token activo: el guardado en el backoffice; si no hay, la constante VC_FLASHCARDS_GITHUB_TOKEN (opcional). */
    public static function token(): string {
      $token = (string) get_option(self::OPTION_TOKEN, '');

      if ($token === '' && defined('VC_FLASHCARDS_GITHUB_TOKEN')) {
        $token = (string) VC_FLASHCARDS_GITHUB_TOKEN;
      }

      return $token;
    }

    private static function installed_version(): string {
      $data = get_file_data(VC_FLASHCARDS_FILE, ['Version' => 'Version']);
      return (string) $data['Version'];
    }

    public function register_settings_page() {
      add_options_page(
        'Actualizaciones VC Flashcards',
        'Actualizaciones VC Flashcards',
        'manage_options',
        self::SETTINGS_SLUG,
        [$this, 'render_settings_page']
      );
    }

    public function render_settings_page() {
      if (!current_user_can('manage_options')) return;

      $message = '';
      $type = 'success';

      if (isset($_POST['vc_flashcards_updates_action'])) {
        check_admin_referer('vc_flashcards_updates', 'vc_flashcards_updates_nonce');

        $action = sanitize_key(wp_unslash($_POST['vc_flashcards_updates_action']));

        if ($action === 'save') {
          $new = preg_replace('/\s+/', '', (string) wp_unslash($_POST['vc_flashcards_github_token'] ?? ''));

          if ($new === '') {
            $message = 'Pega un token para guardarlo.';
            $type = 'warning';
          } else {
            update_option(self::OPTION_TOKEN, $new, false);
            $this->checker->setAuthentication($new);
            $message = 'Token guardado.';
          }
        } elseif ($action === 'delete') {
          delete_option(self::OPTION_TOKEN);
          $message = 'Token eliminado.';
        } elseif ($action === 'test') {
          [$type, $message] = $this->test_connection();
        } elseif ($action === 'check') {
          [$type, $message] = $this->check_now();
        }
      }

      $installed = self::installed_version();
      $has_saved_token = (string) get_option(self::OPTION_TOKEN, '') !== '';
      $active_token = self::token();
      ?>
      <div class="wrap">
        <h1>Actualizaciones VC Flashcards</h1>

        <?php if ($message !== ''): ?>
          <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>

        <p>
          Versión instalada: <strong><?php echo esc_html($installed); ?></strong>.
          Las versiones nuevas se buscan en <code><?php echo esc_html(self::REPO); ?></code> (tags <code>vX.Y.Z</code>).
        </p>

        <form method="post">
          <?php wp_nonce_field('vc_flashcards_updates', 'vc_flashcards_updates_nonce'); ?>
          <table class="form-table" role="presentation">
            <tr>
              <th scope="row"><label for="vc_flashcards_github_token">Token de GitHub</label></th>
              <td>
                <input type="password" name="vc_flashcards_github_token" id="vc_flashcards_github_token" class="regular-text" autocomplete="off"
                  placeholder="<?php echo esc_attr($has_saved_token ? 'Token guardado (escribe uno nuevo para reemplazarlo)' : 'github_pat_... (opcional)'); ?>">
                <p class="description">
                  Opcional mientras el repositorio sea público; obligatorio si pasa a privado. Usa un token fine-grained de solo lectura
                  (Contents: Read-only) limitado a este repositorio. Se guarda en la base de datos de este sitio.
                </p>
                <?php if (!$has_saved_token && $active_token !== ''): ?>
                  <p class="description">Se está usando el token definido en wp-config.php (VC_FLASHCARDS_GITHUB_TOKEN).</p>
                <?php endif; ?>
              </td>
            </tr>
          </table>

          <p>
            <button type="submit" name="vc_flashcards_updates_action" value="save" class="button button-primary">Guardar token</button>
            <button type="submit" name="vc_flashcards_updates_action" value="test" class="button">Probar conexión</button>
            <button type="submit" name="vc_flashcards_updates_action" value="check" class="button">Buscar actualizaciones ahora</button>
            <?php if ($has_saved_token): ?>
              <button type="submit" name="vc_flashcards_updates_action" value="delete" class="button button-link-delete">Eliminar token</button>
            <?php endif; ?>
          </p>
        </form>

        <p><a href="<?php echo esc_url(admin_url('plugins.php')); ?>">Ir a Plugins</a> (ahí puedes activar las actualizaciones automáticas de este plugin).</p>
      </div>
      <?php
    }

    /** @return array{0:string,1:string} [tipo de aviso, mensaje] */
    private function check_now(): array {
      $this->checker->checkForUpdates();
      $update = $this->checker->getUpdate();
      $installed = self::installed_version();

      if ($update !== null) {
        return ['warning', sprintf('Hay una versión nueva: %s (instalada: %s). Actualiza desde Plugins.', $update->version, $installed)];
      }

      return ['success', sprintf('Estás al día (versión %s).', $installed)];
    }

    /** @return array{0:string,1:string} [tipo de aviso, mensaje] */
    private function test_connection(): array {
      $token = self::token();
      $headers = [
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'vc-flashcards-updater',
      ];
      if ($token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
      }

      $response = wp_remote_get('https://api.github.com/repos/' . self::REPO, ['timeout' => 15, 'headers' => $headers]);

      if (is_wp_error($response)) {
        return ['error', 'No se pudo conectar con GitHub: ' . $response->get_error_message()];
      }

      $code = (int) wp_remote_retrieve_response_code($response);

      if ($code === 200) {
        return ['success', $token !== ''
          ? 'Conexión correcta: el token puede leer el repositorio.'
          : 'Conexión correcta: el repositorio es público y se lee sin token.'];
      }
      if ($code === 401) {
        return ['error', 'GitHub rechazó el token (401). Está mal copiado, vencido o revocado.'];
      }
      if ($code === 403) {
        return ['error', 'GitHub respondió 403: límite de consultas alcanzado. Guarda un token para ampliar el límite.'];
      }
      if ($code === 404) {
        return ['error', 'GitHub respondió 404: el repositorio es privado y falta un token, o el token no tiene acceso (permiso Contents: Read-only).'];
      }

      return ['error', sprintf('GitHub respondió con el código %d.', $code)];
    }
  }
}
