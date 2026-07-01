<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-study-sessions-app" data-categories="<?php echo esc_attr(wp_json_encode($categories)); ?>">
  <?php /* Area comun para mensajes de error, carga o feedback contextual. */ ?>
  <p class="vc-study-sessions-feedback" data-vc-study-sessions-feedback hidden></p>

  <?php /* Vista inicial con las categorias disponibles para estudiar. */ ?>
  <section class="vc-study-sessions-view vc-study-sessions-home" data-vc-study-sessions-view="home">
    <?php /* Dashboard stats. These are independent from category cards and subtopic row metrics. */ ?>
    <section class="vc-study-sessions-stats">
      <article class="vc-study-sessions-stat-card">
        <div class="vc-study-sessions-stat-card-content">
          <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Best.svg'); ?>" alt="" width="24" height="24">
          </span>
          <div class="vc-study-sessions-stat-card-copy">
            <small><?php esc_html_e('Cards Mastered', 'vc-study-sessions'); ?></small>
            <?php /* Dashboard metric: latest session score. Category cards below own the 0/3 mastered logic. */ ?>
            <strong data-vc-study-sessions-stat="cards-mastered"><?php echo esc_html((string) ($stats['latestSessionScorePercent'] ?? 0)); ?>%</strong>
          </div>
        </div>
      </article>
      <article class="vc-study-sessions-stat-card">
        <div class="vc-study-sessions-stat-card-content">
          <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Average.svg'); ?>" alt="" width="24" height="24">
          </span>
          <div class="vc-study-sessions-stat-card-copy">
            <small><?php esc_html_e('Total Reviewed', 'vc-study-sessions'); ?></small>
            <strong data-vc-study-sessions-stat="total-reviewed"><?php echo esc_html((string) ($stats['totalReviewed'] ?? '0/0')); ?></strong>
          </div>
        </div>
      </article>
      <article class="vc-study-sessions-stat-card">
        <div class="vc-study-sessions-stat-card-content">
          <span class="vc-study-sessions-stat-card-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Passed.svg'); ?>" alt="" width="24" height="24">
          </span>
          <div class="vc-study-sessions-stat-card-copy">
            <small><?php esc_html_e('Topics Completed', 'vc-study-sessions'); ?></small>
            <strong data-vc-study-sessions-stat="topics-completed"><?php echo esc_html((string) ($stats['topicsCompleted'] ?? '0/0')); ?></strong>
          </div>
        </div>
      </article>
    </section>

    <?php if (empty($categories)): ?>
      <?php /* Estado vacio cuando todavia no hay contenido listo para estudiar. */ ?>
      <article class="vc-study-sessions-empty">
        <p><?php esc_html_e('No categories or questions are available yet.', 'vc-study-sessions'); ?></p>
      </article>
    <?php else: ?>
      <?php /* Rejilla principal de categorias disponibles. */ ?>
      <div class="vc-study-sessions-category-grid">
        <?php foreach ($categories as $category): ?>
          <?php
          $category_total_cards = isset($category['totalCards']) ? (int) $category['totalCards'] : 0;
          $category_mastered_cards = isset($category['masteredCards']) ? (int) $category['masteredCards'] : 0;
          $category_subtopic_count = count($category['children'] ?? []);
          ?>
          <article class="vc-study-sessions-category-card">
            <?php /* Titulo directo de la categoria; la meta vive debajo del progreso. */ ?>
            <div class="vc-study-sessions-category-top">
              <h3><?php echo esc_html($category['name']); ?></h3>
            </div>
            <div class="vc-study-sessions-category-progress-block">
              <div class="vc-study-sessions-category-progress">
                <span><?php esc_html_e('Progress', 'vc-study-sessions'); ?></span>
                <strong><?php echo esc_html((string) $category['progress']); ?>%</strong>
              </div>
              <div class="vc-study-sessions-topic-bar" aria-hidden="true">
                <span style="width: <?php echo esc_attr((string) $category['progress']); ?>%;"></span>
              </div>
            </div>
            <?php /* Category metric only: uses this category's masteredCards/totalCards, not dashboard stats. */ ?>
            <span class="vc-study-sessions-category-meta">
              <?php
              echo esc_html(sprintf(
                /* translators: 1: subtopic count, 2: separator dot, 3: mastered cards, 4: total cards */
                __('%1$d subtopics %2$s %3$d/%4$d mastered', 'vc-study-sessions'),
                $category_subtopic_count,
                html_entity_decode('&middot;', ENT_QUOTES, 'UTF-8'),
                $category_mastered_cards,
                $category_total_cards
              ));
              ?>
            </span>
            <button type="button" class="vc-study-sessions-start vc-study-sessions-start--full" data-vc-study-sessions-open-category="<?php echo esc_attr((string) $category['id']); ?>">
              <span><?php esc_html_e('Study', 'vc-study-sessions'); ?></span>
              <span class="vc-study-sessions-start-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M1.5 6H10.5M7.5 3L10.5 6L7.5 9" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
            </button>
          </article>
        <?php endforeach; ?>
      </div>

      <?php /* Acceso global a practica aleatoria dentro de la vista inicial. */ ?>
      <section class="vc-study-sessions-global-section">
        <article class="vc-study-sessions-global-random">
          <div class="vc-study-sessions-global-random-main">
            <span class="vc-study-sessions-global-random-icon" aria-hidden="true">
              <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/mundo-azul.svg'); ?>" alt="" width="24" height="24">
            </span>
            <div class="vc-study-sessions-global-random-content">
              <div class="vc-study-sessions-global-random-copy">
                <h3><?php esc_html_e('Global Random Practice', 'vc-study-sessions'); ?></h3>
                <p><?php esc_html_e('Mix questions from all categories for a comprehensive review.', 'vc-study-sessions'); ?></p>
              </div>
              <button type="button" class="vc-study-sessions-start vc-study-sessions-global-random-start" data-vc-study-sessions-launch="global-random">
                <span><?php esc_html_e('Start study', 'vc-study-sessions'); ?></span>
              </button>
            </div>
          </div>
        </article>
      </section>
    <?php endif; ?>

  </section>

  <?php /* Vista de detalle donde se elige como estudiar una categoria. */ ?>
  <section class="vc-study-sessions-view" data-vc-study-sessions-view="detail" hidden>
    <?php /* Cabecera contextual con regreso y datos de la categoria activa. */ ?>
    <header class="vc-study-sessions-detail-header">
      <button type="button" class="vc-study-sessions-back" data-vc-study-sessions-back>
        <span class="vc-study-sessions-back-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10.5 6H1.5M4.5 3L1.5 6L4.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span><?php esc_html_e('Back', 'vc-study-sessions'); ?></span>
      </button>
      <div>
        <h2 data-vc-study-sessions-category-title></h2>
        <p class="vc-study-sessions-category-total" data-vc-study-sessions-category-total></p>
      </div>
    </header>

    <?php /* Rejilla de modos de estudio disponibles para la categoria activa. */ ?>
    <div class="vc-study-sessions-config-grid">


      <?php /* Opcion para lanzar una practica aleatoria dentro de la categoria. */ ?>
      <article class="vc-study-sessions-config-card vc-study-sessions-config-card--aligned" data-vc-study-sessions-launch-card tabindex="0" role="button" aria-label="<?php esc_attr_e('Study random', 'vc-study-sessions'); ?>">
        <div class="vc-study-sessions-config-copy">
          <h3 class="vc-study-sessions-card-cardtitle"><?php esc_html_e('Random practice', 'vc-study-sessions'); ?></h3>
          <p class="vc-study-sessions-card-cardsubtitle"><?php esc_html_e('Mix questions from all General subtopics', 'vc-study-sessions'); ?></p>
        </div>
        <button type="button" class="vc-study-sessions-start vc-study-sessions-start--random vc-study-sessions-config-card-action" data-vc-study-sessions-launch="random" aria-label="<?php esc_attr_e('Study random', 'vc-study-sessions'); ?>">
          <span class="vc-study-sessions-start-random-icon vc-study-sessions-config-card-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/aletorio.svg'); ?>" alt="" width="24" height="24">
          </span>
        </button>
        <?php /* Icono derecho inline: el color lo controla CSS segun el estado de la card. */ ?>
        <span class="vc-study-sessions-config-card-trailing-icon" aria-hidden="true">
          <svg class="icono-flecha" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M1 19L10 10L1 1" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </article>

      <?php /* Opcion para estudiar toda la categoria en orden secuencial. */ ?>
      <article class="vc-study-sessions-config-card vc-study-sessions-config-card--aligned" data-vc-study-sessions-launch-card tabindex="0" role="button" aria-label="<?php esc_attr_e('Study full category', 'vc-study-sessions'); ?>">
        <div class="vc-study-sessions-config-copy">
          <h3 class="vc-study-sessions-card-cardtitle"><?php esc_html_e('Study by ACS Code', 'vc-study-sessions'); ?></h3>
          <p class="vc-study-sessions-card-cardsubtitle"><?php esc_html_e('Target specific FAA exam areas', 'vc-study-sessions'); ?></p>
        </div>
        <button type="button" class="vc-study-sessions-start vc-study-sessions-start--category vc-study-sessions-config-card-action" data-vc-study-sessions-launch="category" aria-label="<?php esc_attr_e('Study full category', 'vc-study-sessions'); ?>">
          <span class="vc-study-sessions-start-list-icon vc-study-sessions-config-card-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Icon.svg'); ?>" alt="" width="24" height="24">
          </span>
        </button>
        <?php /* Icono derecho inline: el color lo controla CSS segun el estado de la card. */ ?>
        <span class="vc-study-sessions-config-card-trailing-icon" aria-hidden="true">
          <svg class="icono-flecha" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M1 19L10 10L1 1" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </article>
    </div>

    <?php /* Encabezado independiente de la seccion de subtemas. */ ?>
    <section class="vc-study-sessions-subtopics-header">
      <div>
        <h3><?php esc_html_e('Study by Subtopic', 'vc-study-sessions'); ?></h3>
      </div>
    </section>

    <?php /* Contenedor reservado para la lista de subtemas renderizada por JS. */ ?>
    <section class="vc-study-sessions-subtopics-card">
      <div class="vc-study-sessions-subtopics-list" data-vc-study-sessions-subtopics></div>
    </section>
  </section>

  <?php /* Vista de sesion activa donde se responde cada flashcard. */ ?>
  <section class="vc-study-sessions-session" data-vc-study-sessions-session hidden>
    <?php /* Cabecera de sesion con regreso y progreso actual. */ ?>
    <div class="vc-study-sessions-session-header">
      <button type="button" class="vc-study-sessions-back" data-vc-study-sessions-back>
        <span class="vc-study-sessions-back-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10.5 6H1.5M4.5 3L1.5 6L4.5 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <?php esc_html_e('Back', 'vc-study-sessions'); ?>
      </button>
      <?php /* Capsula compacta con el progreso textual de la sesion. */ ?>
      <div class="vc-study-sessions-session-progress">
        <strong data-vc-study-sessions-progress-count><?php esc_html_e('Question 1 de 10', 'vc-study-sessions'); ?></strong>
      </div>
    </div>
    <?php /* Barra visual sincronizada con el avance de preguntas. */ ?>
    <div class="vc-study-sessions-session-bar" aria-hidden="true">
      <span data-vc-study-sessions-session-bar-fill style="width: 0%;"></span>
    </div>

    <?php /* Tarjeta principal con pregunta, respuestas y acciones de apoyo. */ ?>
    <article class="vc-study-sessions-card">
      <?php /* Fix: este contenedor debe tener data-vc-study-sessions-kicker para que Study sessions.js pinte el topic/subtopic real de cada tarjeta. */ ?>
      <div class="vc-study-sessions-session-context" data-vc-study-sessions-kicker aria-label="<?php esc_attr_e('Study session topic context', 'vc-study-sessions'); ?>"></div>
      <h4 data-vc-study-sessions-question></h4>
      <button type="button" class="vc-study-sessions-reference-image reference_image" data-vc-study-sessions-reference-image data-vc-study-sessions-reference-image-fallback="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/referencia.png'); ?>" hidden>
        <span class="vc-study-sessions-reference-image-control">
          <span class="vc-study-sessions-reference-image-icon-wrap" aria-hidden="true">
            <svg
              class="image-icon"
              viewBox="0 0 24 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path d="M18.9944 2.99902H4.99845C3.8942 2.99902 2.99902 3.8942 2.99902 4.99845V18.9944C2.99902 20.0987 3.8942 20.9938 4.99845 20.9938H18.9944C20.0987 20.9938 20.9938 20.0987 20.9938 18.9944V4.99845C20.9938 3.8942 20.0987 2.99902 18.9944 2.99902Z" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M8.99747 10.9969C10.1017 10.9969 10.9969 10.1017 10.9969 8.99747C10.9969 7.89322 10.1017 6.99805 8.99747 6.99805C7.89322 6.99805 6.99805 7.89322 6.99805 8.99747C6.99805 10.1017 7.89322 10.9969 8.99747 10.9969Z" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M20.994 14.9957L17.9089 11.9106C17.5339 11.5358 17.0254 11.3252 16.4953 11.3252C15.9651 11.3252 15.4566 11.5358 15.0817 11.9106L5.99829 20.994" stroke="currentColor" stroke-width="1.99943" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="vc-study-sessions-reference-image-copy">
            <span class="vc-study-sessions-reference-image-title"><?php esc_html_e('View reference image', 'vc-study-sessions'); ?></span>
            <span class="vc-study-sessions-reference-image-subtitle"><?php esc_html_e('Click to expand', 'vc-study-sessions'); ?></span>
          </span>

        </span>
        <span class="vc-study-sessions-reference-image-preview" hidden>
          <img data-vc-study-sessions-reference-image-inline alt="<?php esc_attr_e('Reference image', 'vc-study-sessions'); ?>">
        </span>
      </button>
      <div class="vc-study-sessions-answers" data-vc-study-sessions-answers></div>
      <?php /* Fila de acciones auxiliares antes de avanzar a la siguiente pregunta. */ ?>
      <div class="vc-study-sessions-actions">
        <button type="button" class="vc-study-sessions-reveal" data-vc-study-sessions-reveal><?php esc_html_e("Don't know the answer? Reveal answer", 'vc-study-sessions'); ?></button>
        <button type="button" class="vc-study-sessions-session-action vc-study-sessions-explanation-toggle" data-vc-study-sessions-explanation-toggle hidden>
          <span class="vc-study-sessions-explanation-toggle-label"><?php esc_html_e('View detailed explanation', 'vc-study-sessions'); ?></span>
          <span class="vc-study-sessions-next-icon" aria-hidden="true">
            <svg
              class="vc-study-sessions-card-arrow-svg"
              viewBox="0 0 16 16"
              fill="none"
              xmlns="http://www.w3.org/2000/svg">
              <path
                d="M6 3L10 8L6 13"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round" />
            </svg>
          </span>
        </button>
      </div>
      <?php /* Panel expandible donde se inyecta la explicacion de la respuesta. */ ?>
      <div class="vc-study-sessions-explanation" data-vc-study-sessions-explanation hidden></div>
      <?php /* CTA final para avanzar despues de resolver o revelar la respuesta. */ ?>
      <button type="button" class="vc-study-sessions-session-action vc-study-sessions-session-next" data-vc-study-sessions-next hidden disabled>
        <span class="vc-study-sessions-next-label"><?php esc_html_e('Next question', 'vc-study-sessions'); ?></span>
        <?php /* Study sessions next icon: replica la flecha usada por Mock Test para mantener consistencia visual. */ ?>
        <span class="vc-study-sessions-next-icon" aria-hidden="true">
          <svg class="vc-arrow-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
            <path d="M3 10H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            <path d="M11 5L16 10L11 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
      </button>
    </article>
  </section>

  <?php /* Vista final con el resultado consolidado de la sesion. */ ?>
  <section class="vc-study-sessions-summary" data-vc-study-sessions-summary hidden>
    <?php /* Caja principal del resumen con score y siguientes acciones. */ ?>
    <div class="vc-study-sessions-summary-box">
      <?php /* Insignia visual del resumen final con copa centrada sobre un circulo degradado. */ ?>
      <div class="vc-study-sessions-summary-badge" aria-hidden="true">
        <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/copa.svg'); ?>" alt="" width="42" height="42">
      </div>
      <p class="vc-study-sessions-summary-kicker"><?php esc_html_e('Session completed!', 'vc-study-sessions'); ?></p>
      <?php /* Franja superior preparada para futuras metricas o elementos visuales del resumen. */ ?>
      <div class="vc-study-sessions-summary-top">
        <div class="vc-study-sessions-summary-results">
          <div class="vc-study-sessions-summary-result-card vc-study-sessions-summary-result-card--correct">
            <?php /* Contador dinamico de respuestas correctas renderizado al cerrar la sesion. */ ?>
            <strong class="vc-study-sessions-summary-count vc-study-sessions-summary-count--correct" data-vc-study-sessions-correct-count>0</strong>
            <h3><?php esc_html_e('Correct', 'vc-study-sessions'); ?></h3>
          </div>
          <div class="vc-study-sessions-summary-result-card vc-study-sessions-summary-result-card--incorrect">
            <?php /* Contador dinamico de respuestas incorrectas calculado a partir del total de la sesion. */ ?>
            <strong class="vc-study-sessions-summary-count vc-study-sessions-summary-count--incorrect" data-vc-study-sessions-incorrect-count>0</strong>
            <h3><?php esc_html_e('Incorrect', 'vc-study-sessions'); ?></h3>
          </div>
        </div>
        <div class="vc-study-sessions-summary-accuracy-card">
          <?php /* Porcentaje de precision primero para igualar el orden visual de las tarjetas Correct/Incorrect. */ ?>
          <strong data-vc-study-sessions-summary-precision>0%</strong>
          <?php /* Titulo de la tarjeta de precision debajo del porcentaje principal. */ ?>
          <span class="vc-study-sessions-summary-accuracy-title"><?php esc_html_e('Accuracy', 'vc-study-sessions'); ?></span>
        </div>
      </div>
      <h3 class="vc-study-sessions-summary-message">
        <?php esc_html_e('Keep studying.', 'vc-study-sessions'); ?>
      </h3>
      <div class="vc-study-sessions-summary-actions">
        <?php /* Variante visual exclusiva del summary para el retorno al menu. */ ?>
        <button type="button" class="vc-study-sessions-back vc-study-sessions-summary-action vc-study-sessions-summary-action--back" data-vc-study-sessions-summary-back><?php esc_html_e('Back to menu', 'vc-study-sessions'); ?></button>
        <?php /* CTA secundario del summary con icono izquierdo para reiniciar la sesion actual. */ ?>
        <button type="button" class="vc-study-sessions-start vc-study-sessions-summary-action vc-study-sessions-summary-action--restart" data-vc-study-sessions-restart>
          <span class="vc-study-sessions-summary-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/try.svg'); ?>" alt="" width="16" height="16">
          </span>
          <span><?php esc_html_e('Repeat Session', 'vc-study-sessions'); ?></span>
        </button>
      </div>
    </div>
  </section>

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

  <?php include VC_FLASHCARDS_DIR . 'templates/partials/study-sessions-acs-code-workspace.php'; ?>

  <?php /* Reference image modal: desktop muestra la imagen grande sin empujar la card de sesion. */ ?>
  <div class="vc-reference-modal vc-study-sessions-reference-modal" data-vc-study-sessions-reference-modal hidden>
    <button type="button" class="vc-reference-modal__backdrop vc-study-sessions-reference-modal-backdrop" data-vc-study-sessions-reference-modal-close aria-label="<?php esc_attr_e('Close reference image', 'vc-study-sessions'); ?>"></button>
    <?php /* Reference modal convention: vc-reference-modal_* is canonical; vc-study-sessions-reference-modal-dialog stays for legacy QA/client references. */ ?>
    <div class="vc-reference-modal__dialog vc-study-sessions-reference-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Reference image', 'vc-study-sessions'); ?>">
      <?php /* Reference image modal controls: acciones integradas dentro del dialogo desktop. */ ?>
      <div class="vc-reference-modal__controls vc-study-sessions-reference-modal-controls">
        <button type="button" class="vc-reference-modal__zoom vc-study-sessions-reference-modal-zoom" data-vc-study-sessions-reference-modal-zoom aria-pressed="false" aria-label="<?php esc_attr_e('Zoom reference image', 'vc-study-sessions'); ?>">
          <svg class="vc-reference-modal__zoom-icon vc-reference-modal__zoom-icon--in" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
            <path d="M8.5 14.5C11.8137 14.5 14.5 11.8137 14.5 8.5C14.5 5.18629 11.8137 2.5 8.5 2.5C5.18629 2.5 2.5 5.18629 2.5 8.5C2.5 11.8137 5.18629 14.5 8.5 14.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13 13L17.5 17.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8.5 6V11M6 8.5H11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <svg class="vc-reference-modal__zoom-icon vc-reference-modal__zoom-icon--out" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
            <path d="M8.5 14.5C11.8137 14.5 14.5 11.8137 14.5 8.5C14.5 5.18629 11.8137 2.5 8.5 2.5C5.18629 2.5 2.5 5.18629 2.5 8.5C2.5 11.8137 5.18629 14.5 8.5 14.5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M13 13L17.5 17.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M6 8.5H11" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <button type="button" class="vc-reference-modal__close vc-study-sessions-reference-modal-close" data-vc-study-sessions-reference-modal-close aria-label="<?php esc_attr_e('Close reference image', 'vc-study-sessions'); ?>">
          <svg viewBox="0 0 20 20" aria-hidden="true" focusable="false">
            <path d="M5 5L15 15M15 5L5 15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <div class="vc-reference-modal__frame vc-study-sessions-reference-modal-frame">
        <img class="vc-reference-modal__image vc-study-sessions-reference-modal-image" data-vc-study-sessions-reference-modal-image alt="<?php esc_attr_e('Reference image', 'vc-study-sessions'); ?>">
      </div>
    </div>
  </div>
</div>
