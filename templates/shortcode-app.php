<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-flashcards-app" data-categories="<?php echo esc_attr(wp_json_encode($categories)); ?>">
  <?php /* Resumen rapido de metricas visibles antes de iniciar una sesion. */ ?>
  <section class="vc-flashcards-stats">
    <article class="vc-flashcards-stat-card">
      <div class="vc-flashcards-stat-card-content">
        <span class="vc-flashcards-stat-card-icon" aria-hidden="true">
          <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Best.svg'); ?>" alt="" width="24" height="24">
        </span>
        <div class="vc-flashcards-stat-card-copy">
          <small><?php esc_html_e('Best score', 'vc-flashcards'); ?></small>
          <strong data-vc-flashcards-stat="best-score"><?php echo esc_html((string) ($stats['bestScore'] ?? 0)); ?>%</strong>
        </div>
      </div>
    </article>
    <article class="vc-flashcards-stat-card">
      <div class="vc-flashcards-stat-card-content">
        <span class="vc-flashcards-stat-card-icon" aria-hidden="true">
          <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Average.svg'); ?>" alt="" width="24" height="24">
        </span>
        <div class="vc-flashcards-stat-card-copy">
          <small><?php esc_html_e('Average', 'vc-flashcards'); ?></small>
          <strong data-vc-flashcards-stat="average-score"><?php echo esc_html((string) ($stats['averageScore'] ?? 0)); ?>%</strong>
        </div>
      </div>
    </article>
    <article class="vc-flashcards-stat-card">
      <div class="vc-flashcards-stat-card-content">
        <span class="vc-flashcards-stat-card-icon" aria-hidden="true">
          <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Passed.svg'); ?>" alt="" width="24" height="24">
        </span>
        <div class="vc-flashcards-stat-card-copy">
          <small><?php esc_html_e('Passed attempts', 'vc-flashcards'); ?></small>
          <strong data-vc-flashcards-stat="passed-attempts"><?php echo esc_html((string) ($stats['passedAttempts'] ?? '0/5')); ?></strong>
        </div>
      </div>
    </article>
  </section>

  <?php /* Area comun para mensajes de error, carga o feedback contextual. */ ?>
  <p class="vc-flashcards-feedback" data-vc-flashcards-feedback hidden></p>

  <?php /* Vista inicial con las categorias disponibles para estudiar. */ ?>
  <section class="vc-flashcards-view" data-vc-flashcards-view="home">
    <?php if (empty($categories)): ?>
      <?php /* Estado vacio cuando todavia no hay contenido listo para estudiar. */ ?>
      <article class="vc-flashcards-empty">
        <p><?php esc_html_e('No categories or flashcards are available yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <?php /* Rejilla principal de categorias disponibles. */ ?>
      <div class="vc-flashcards-category-grid">
        <?php foreach ($categories as $category): ?>
          <article class="vc-flashcards-category-card">
            <div class="vc-flashcards-category-top">
              <h3><?php echo esc_html($category['name']); ?></h3>
            </div>
            <div class="vc-flashcards-category-progress">
              <span><?php esc_html_e('Progress', 'vc-flashcards'); ?></span>
              <strong><?php echo esc_html((string) $category['progress']); ?>%</strong>
            </div>
            <div class="vc-flashcards-topic-bar" aria-hidden="true">
              <span style="width: <?php echo esc_attr((string) $category['progress']); ?>%;"></span>
            </div>
            <div class="vc-flashcards-category-meta">
              <span><?php echo count($category['children'] ?? []) . ' subtopics · ' . $category['progress'] . ' reviewed'; ?></span>
              <button type="button" class="vc-flashcards-start vc-flashcards-start--full" data-vc-flashcards-open-category="<?php echo esc_attr((string) $category['id']); ?>">
                <span><?php esc_html_e('Study', 'vc-flashcards'); ?></span>
                <span class="vc-flashcards-start-icon" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1.5 6H10.5M7.5 3L10.5 6L7.5 9" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              </button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php /* Acceso global a practica aleatoria dentro de la vista inicial. */ ?>
      <section class="vc-flashcards-global-section">
        <article class="vc-flashcards-global-random">
          <div class="vc-flashcards-global-random-main">
            <span class="vc-flashcards-global-random-icon" aria-hidden="true">
              <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/mundo.svg'); ?>" alt="" width="24" height="24">
            </span>
            <div class="vc-flashcards-global-random-content">
              <div class="vc-flashcards-global-random-copy">
                <h3><?php esc_html_e('Global Random Practice', 'vc-flashcards'); ?></h3>
                <p><?php esc_html_e('Mix cards from all categories for a comprehensive review.', 'vc-flashcards'); ?></p>
              </div>
              <button type="button" class="vc-flashcards-start vc-flashcards-global-random-start" data-vc-flashcards-launch="global-random">
                <span><?php esc_html_e('Start study', 'vc-flashcards'); ?></span>
              </button>
            </div>
          </div>
        </article>
      </section>
    <?php endif; ?>
  </section>

  <?php /* Vista de detalle donde se elige como estudiar una categoria. */ ?>
  <section class="vc-flashcards-view" data-vc-flashcards-view="detail" hidden>
    <?php /* Cabecera contextual con regreso y datos de la categoria activa. */ ?>
    <header class="vc-flashcards-detail-header">
      <button type="button" class="vc-flashcards-back" data-vc-flashcards-back>
        <span class="vc-flashcards-back-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10.5 6H1.5M4.5 3L1.5 6L4.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span><?php esc_html_e('Back', 'vc-flashcards'); ?></span>
      </button>
      <div>
        <h2 data-vc-flashcards-category-title></h2>
        <p class="vc-flashcards-category-total" data-vc-flashcards-category-total></p>
      </div>
    </header>

    <?php /* Rejilla de modos de estudio disponibles para la categoria activa. */ ?>
    <div class="vc-flashcards-config-grid">
      <?php /* Opcion para estudiar toda la categoria en orden secuencial. */ ?>
      <article class="vc-flashcards-config-card vc-flashcards-config-card--aligned" data-vc-flashcards-launch-card tabindex="0" role="button" aria-label="<?php esc_attr_e('Study full category', 'vc-flashcards'); ?>">
        <div class="vc-flashcards-config-copy">
          <h3 class="vc-flashcards-card-cardtitle"><?php esc_html_e('Study Full Category', 'vc-flashcards'); ?></h3>
          <p class="vc-flashcards-card-cardsubtitle"><?php esc_html_e('Study all General cards in order', 'vc-flashcards'); ?></p>
        </div>
        <button type="button" class="vc-flashcards-start vc-flashcards-start--icon vc-flashcards-config-card-action" data-vc-flashcards-launch="category" aria-label="<?php esc_attr_e('Study full category', 'vc-flashcards'); ?>">
          <span class="vc-flashcards-start-list-icon vc-flashcards-config-card-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Icon.svg'); ?>" alt="" width="24" height="24">
          </span>
        </button>
      </article>

      <?php /* Opcion para lanzar una practica aleatoria dentro de la categoria. */ ?>
      <article class="vc-flashcards-config-card vc-flashcards-config-card--aligned" data-vc-flashcards-launch-card tabindex="0" role="button" aria-label="<?php esc_attr_e('Study random', 'vc-flashcards'); ?>">
        <div class="vc-flashcards-config-copy">
          <h3 class="vc-flashcards-card-cardtitle"><?php esc_html_e('Random practice', 'vc-flashcards'); ?></h3>
          <p class="vc-flashcards-card-cardsubtitle"><?php esc_html_e('Mix cards from all General subtopics', 'vc-flashcards'); ?></p>
        </div>
        <button type="button" class="vc-flashcards-start vc-flashcards-start--secondary vc-flashcards-config-card-action" data-vc-flashcards-launch="random" aria-label="<?php esc_attr_e('Study random', 'vc-flashcards'); ?>">
          <span class="vc-flashcards-start-random-icon vc-flashcards-config-card-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/aletorio.svg'); ?>" alt="" width="24" height="24">
          </span>
        </button>
      </article>
    </div>

    <?php /* Encabezado independiente de la seccion de subtemas. */ ?>
    <section class="vc-flashcards-subtopics-header">
      <div>
        <h3><?php esc_html_e('Study by Subtopic', 'vc-flashcards'); ?></h3>
      </div>
    </section>

    <?php /* Contenedor reservado para la lista de subtemas renderizada por JS. */ ?>
    <section class="vc-flashcards-subtopics-card">
      <div class="vc-flashcards-subtopics-list" data-vc-flashcards-subtopics></div>
    </section>
  </section>

  <?php /* Vista de sesion activa donde se responde cada flashcard. */ ?>
  <section class="vc-flashcards-session" data-vc-flashcards-session hidden>
    <?php /* Cabecera de sesion con regreso y progreso actual. */ ?>
    <div class="vc-flashcards-session-header">
      <button type="button" class="vc-flashcards-back" data-vc-flashcards-back>
        <span class="vc-flashcards-back-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10.5 6H1.5M4.5 3L1.5 6L4.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <?php esc_html_e('Back', 'vc-flashcards'); ?>
      </button>
      <?php /* Capsula compacta con el progreso textual de la sesion. */ ?>
      <div class="vc-flashcards-session-progress">
        <strong data-vc-flashcards-progress-count><?php esc_html_e('Question 1 de 10', 'vc-flashcards'); ?></strong>
      </div>
    </div>
    <?php /* Barra visual sincronizada con el avance de preguntas. */ ?>
    <div class="vc-flashcards-session-bar" aria-hidden="true">
      <span data-vc-flashcards-session-bar-fill style="width: 0%;"></span>
    </div>

    <?php /* Tarjeta principal con pregunta, respuestas y acciones de apoyo. */ ?>
    <article class="vc-flashcards-card">
      <div class="vc-flashcards-session-context">
        <p class="vc-flashcards-session-topic">General</p>
        <p class="vc-flashcards-session-subtopic">Hand tools</p>
      </div>
      <h4 data-vc-flashcards-question></h4>
      <div class="vc-flashcards-answers" data-vc-flashcards-answers></div>
      <?php /* Fila de acciones auxiliares antes de avanzar a la siguiente pregunta. */ ?>
      <div class="vc-flashcards-actions">
        <button type="button" class="vc-flashcards-reveal" data-vc-flashcards-reveal><?php esc_html_e("Don't know the answer? Reveal answer", 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-session-action vc-flashcards-explanation-toggle" data-vc-flashcards-explanation-toggle hidden>
          <span class="vc-flashcards-explanation-toggle-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/libro.svg'); ?>" alt="" width="16" height="16">
          </span>
          <span class="vc-flashcards-explanation-toggle-label"><?php esc_html_e('View detailed explanation', 'vc-flashcards'); ?></span>
        </button>
      </div>
      <?php /* Panel expandible donde se inyecta la explicacion de la respuesta. */ ?>
      <div class="vc-flashcards-explanation" data-vc-flashcards-explanation hidden></div>
      <?php /* CTA final para avanzar despues de resolver o revelar la respuesta. */ ?>
      <button type="button" class="vc-flashcards-session-action vc-flashcards-session-next" data-vc-flashcards-next hidden disabled>
        <span class="vc-flashcards-next-label"><?php esc_html_e('Next question', 'vc-flashcards'); ?></span>
        <span class="vc-flashcards-next-icon" aria-hidden="true">&gt;</span>
      </button>
    </article>
  </section>

  <?php /* Vista final con el resultado consolidado de la sesion. */ ?>
  <section class="vc-flashcards-summary" data-vc-flashcards-summary hidden>
    <?php /* Caja principal del resumen con score y siguientes acciones. */ ?>
    <div class="vc-flashcards-summary-box">
      <div class="vc-flashcards-summary-header">
        <?php /* Cierre rapido del overlay sin cambiar el flujo principal del resumen. */ ?>
        <button type="button" class="vc-flashcards-close-button vc-flashcards-summary-close" data-vc-flashcards-summary-close aria-label="<?php esc_attr_e('Close summary', 'vc-flashcards'); ?>">
          <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/cerrar.svg'); ?>" alt="" width="20" height="20">
        </button>
      </div>
      <?php /* Insignia visual del resumen final con copa centrada sobre un circulo degradado. */ ?>
      <div class="vc-flashcards-summary-badge" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/copa.svg'); ?>" alt="" width="42" height="42">
      </div>
      <p class="vc-flashcards-summary-kicker"><?php esc_html_e('Session completed!', 'vc-flashcards'); ?></p>
      <?php /* Franja superior preparada para futuras metricas o elementos visuales del resumen. */ ?>
      <div class="vc-flashcards-summary-top">
        <div class="vc-flashcards-summary-results">
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--correct">
            <?php /* Contador dinámico de respuestas correctas renderizado al cerrar la sesión. */ ?>
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--correct" data-vc-flashcards-correct-count>0</strong>
            <h3><?php esc_html_e('Correct', 'vc-flashcards'); ?></h3>
          </div>
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--incorrect">
            <?php /* Contador dinámico de respuestas incorrectas calculado a partir del total de la sesión. */ ?>
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--incorrect" data-vc-flashcards-incorrect-count>0</strong>
            <h3><?php esc_html_e('Incorrect', 'vc-flashcards'); ?></h3>
          </div>
        </div>
        <div class="vc-flashcards-summary-accuracy-card">
          <?php /* Titulo de la tarjeta de precision encima del porcentaje principal. */ ?>
          <span class="vc-flashcards-summary-accuracy-title"><?php esc_html_e('Accuracy', 'vc-flashcards'); ?></span>
          <strong data-vc-flashcards-summary-precision>0%</strong>
          <?php /* Barra visual que representa el porcentaje de precision de la sesion. */ ?>
          <span class="vc-flashcards-summary-accuracy-bar">
            <span data-vc-flashcards-summary-precision-bar></span>
          </span>
        </div>
      </div>
      <h3 class="vc-flashcards-summary-message"><?php esc_html_e('Keep studying. Practice makes perfect.', 'vc-flashcards'); ?></h3>
      <div class="vc-flashcards-summary-actions">
        <?php /* Variante visual exclusiva del summary para el retorno al menú. */ ?>
        <button type="button" class="vc-flashcards-back vc-flashcards-summary-action vc-flashcards-summary-action--back" data-vc-flashcards-summary-back><?php esc_html_e('Back to menu', 'vc-flashcards'); ?></button>
        <?php /* CTA secundario del summary con icono izquierdo para reiniciar la sesión actual. */ ?>
        <button type="button" class="vc-flashcards-start vc-flashcards-summary-action vc-flashcards-summary-action--restart" data-vc-flashcards-restart>
          <span class="vc-flashcards-summary-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/repeat-session.svg'); ?>" alt="" width="16" height="16">
          </span>
          <span><?php esc_html_e('Repeat session', 'vc-flashcards'); ?></span>
        </button>
      </div>
    </div>
  </section>

  <?php /* Modal reutilizable para configurar la cantidad de tarjetas antes de iniciar. */ ?>
  <div class="vc-flashcards-modal" data-vc-flashcards-modal hidden>
    <?php /* Backdrop clicable para cerrar el modal fuera del dialogo. */ ?>
    <div class="vc-flashcards-modal-backdrop" data-vc-flashcards-close></div>
    <?php /* Dialogo principal con titulo, selector de cantidad y acciones finales. */ ?>
    <div class="vc-flashcards-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vc-flashcards-modal-title">
      <div class="vc-flashcards-modal-header">
        <div class="vc-flashcards-modal-heading">
          <h3 id="vc-flashcards-modal-title" data-vc-flashcards-modal-title></h3>
          <p class="vc-flashcards-modal-copy" data-vc-flashcards-modal-copy></p>
        </div>
        <button type="button" class="vc-flashcards-close-button vc-flashcards-modal-close" data-vc-flashcards-close aria-label="<?php esc_attr_e('Close', 'vc-flashcards'); ?>">
          <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/cerrar.svg'); ?>" alt="" width="20" height="20">
        </button>
      </div>
  
      <?php /* Resumen numerico de la cantidad de tarjetas seleccionada. */ ?>
      <div class="vc-flashcards-modal-count">
        <strong data-vc-flashcards-count-display>20</strong>
        <span><?php esc_html_e('Selected cards', 'vc-flashcards'); ?></span>
      </div>

      <?php /* Grupo del slider con rango permitido y valor contextual. */ ?>
      <div class="vc-flashcards-modal-slider-wrap">
        <div class="vc-flashcards-modal-range-meta">
          <span><?php esc_html_e('Number of cards', 'vc-flashcards'); ?></span>
          <strong data-vc-flashcards-range-label>10 - 50</strong>
        </div>
        <input type="range" min="1" max="50" value="20" step="1" data-vc-flashcards-range>
      </div>

      <?php /* Accesos rapidos a cantidades predefinidas generadas por JS. */ ?>
      <div class="vc-flashcards-modal-options" data-vc-flashcards-options></div>

      <?php /* Acciones finales del modal para cancelar o confirmar el inicio. */ ?>
      <div class="vc-flashcards-modal-actions">
        <button type="button" class="vc-flashcards-modal-cancel" data-vc-flashcards-close><?php esc_html_e('Cancel', 'vc-flashcards'); ?></button>
        <button type="button" class="vc-flashcards-modal-confirm" data-vc-flashcards-confirm><?php esc_html_e('Start', 'vc-flashcards'); ?></button>
      </div>
    </div>
  </div>
</div>
