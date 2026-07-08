<?php
/**
 * Study Sessions config modal: quantity picker before starting a session.
 */
if (!defined('ABSPATH')) {
  exit;
}
?>

<?php /* Modal reutilizable para configurar la cantidad de tarjetas antes de iniciar. */ ?>
<div class="vc-study-sessions-modal" data-vc-study-sessions-modal hidden>
  <?php /* Backdrop clicable para cerrar el modal fuera del dialogo. */ ?>
  <div class="vc-study-sessions-modal-backdrop" data-vc-study-sessions-close></div>
  <?php /* Dialogo principal con titulo, selector de cantidad y acciones finales. */ ?>
  <div class="vc-study-sessions-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vc-study-sessions-modal-title">
    <div class="vc-study-sessions-modal-header">
      <?php /* Bloque textual del modal; CSS controla su gap frente al boton de cierre. */ ?>
      <div class="vc-study-sessions-modal-heading">
        <h3 id="vc-study-sessions-modal-title" data-vc-study-sessions-modal-title></h3>
        <p class="vc-study-sessions-modal-copy" data-vc-study-sessions-modal-copy></p>
      </div>
      <?php /* Cierre compacto del modal; en 480px CSS lo fija a 26x26. */ ?>
      <button type="button" class="vc-study-sessions-close-button vc-study-sessions-modal-close" data-vc-study-sessions-close aria-label="<?php esc_attr_e('Close', 'vc-study-sessions'); ?>">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/cerrar.svg'); ?>" alt="" width="20" height="20">
      </button>
    </div>

    <?php /* Resumen numerico de la cantidad de tarjetas seleccionada. */ ?>
    <div class="vc-study-sessions-modal-count">
      <strong data-vc-study-sessions-count-display>20</strong>
      <span><?php esc_html_e('Selected questions', 'vc-study-sessions'); ?></span>
    </div>

    <?php /* Grupo del slider con rango permitido y valor contextual. */ ?>
    <div class="vc-study-sessions-modal-slider-wrap">
      <div class="vc-study-sessions-modal-range-meta">
        <span><?php esc_html_e('Number of questions', 'vc-study-sessions'); ?></span>
        <strong data-vc-study-sessions-range-label>10 - 50</strong>
      </div>
      <input type="range" min="1" max="50" value="20" step="1" data-vc-study-sessions-range>
    </div>

    <?php /* Accesos rapidos a cantidades predefinidas generadas por JS. */ ?>
    <div class="vc-study-sessions-modal-options" data-vc-study-sessions-options></div>

    <?php /* Acciones finales del modal para cancelar o confirmar el inicio. */ ?>
    <div class="vc-study-sessions-modal-actions">
      <button type="button" class="vc-study-sessions-modal-cancel" data-vc-study-sessions-close><?php esc_html_e('Cancel', 'vc-study-sessions'); ?></button>
      <button type="button" class="vc-study-sessions-modal-confirm" data-vc-study-sessions-confirm><?php esc_html_e('Start', 'vc-study-sessions'); ?></button>
    </div>
  </div>
</div>
