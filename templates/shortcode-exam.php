<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-exam-app is-booting">

  <?php /* Mensaje compartido entre todas las vistas del examen para errores, carga o feedback general. */ ?>
  <p class="vc-flashcards-feedback" data-vc-exam-feedback hidden></p>

  <?php /* ── HOME: category selection ─────────────────────────────── */ ?>
  <?php /* Vista home del examen: resumen superior, categorias disponibles, historial y detalles del test. */ ?>
  <section data-vc-exam-view="home" aria-labelledby="vc-exam-home-title">

    <?php /* Bloque introductorio del home: badges superiores con metricas rapidas del mock test. */ ?>
    <div class="vc-exam-intro">
      <h2 id="vc-exam-home-title" class="screen-reader-text"><?php esc_html_e('Exam Simulator', 'vc-flashcards'); ?></h2>
      <p class="screen-reader-text"><?php esc_html_e('Select a category to start your final exam simulation.', 'vc-flashcards'); ?></p>
      <ul class="vc-exam-intro-badges" aria-label="<?php esc_attr_e('Exam overview', 'vc-flashcards'); ?>">
        <?php /* Badge 1: mejor porcentaje historico del usuario en modo examen. */ ?>
        <li class="vc-exam-badge">
          <div class="vc-exam-badge-content">
            <?php /* Icono visual del badge. */ ?>
            <span class="vc-exam-badge-icon" aria-hidden="true">
              <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Best.svg'); ?>" alt="" width="28" height="28">
            </span>
            <?php /* Texto del badge: label pequena + valor principal. */ ?>
            <div class="vc-exam-badge-copy">
              <small><?php esc_html_e('Best score', 'vc-flashcards'); ?></small>
              <strong><?php echo esc_html((string) ($exam_home_stats['bestScore'] ?? 0)); ?>%</strong>
            </div>
          </div>
        </li>
        <?php /* Badge 2: promedio historico del usuario en examenes completados. */ ?>
        <li class="vc-exam-badge">
          <div class="vc-exam-badge-content">
            <span class="vc-exam-badge-icon" aria-hidden="true">
              <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Average.svg'); ?>" alt="" width="28" height="28">
            </span>
            <div class="vc-exam-badge-copy">
              <small><?php esc_html_e('Average', 'vc-flashcards'); ?></small>
              <strong><?php echo esc_html((string) ($exam_home_stats['averageScore'] ?? 0)); ?>%</strong>
            </div>
          </div>
        </li>
        
        <?php /* Badge 3: intentos aprobados recientes mostrados como x/5. */ ?>
        <li class="vc-exam-badge">
          <div class="vc-exam-badge-content">
            <span class="vc-exam-badge-icon" aria-hidden="true">
              <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/Passed.svg'); ?>" alt="" width="28" height="28">
            </span>
            <div class="vc-exam-badge-copy">
              <small><?php esc_html_e('Passed attempts', 'vc-flashcards'); ?></small>
              <strong><?php echo esc_html((string) ($exam_home_stats['passedAttempts'] ?? '0/5')); ?></strong>
            </div>
          </div>
        </li>
        <?php /* Badge 4: racha actual compartida con las metricas generales del usuario. */ ?>
        <li class="vc-exam-badge">
          <div class="vc-exam-badge-content">
            <span class="vc-exam-badge-icon" aria-hidden="true">
              <svg width="28" height="28" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M8.15 1.8 4.75 6.4h2.2L5.85 12l4.25-5.8H7.8l.35-4.4Z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="vc-exam-badge-copy">
              <small><?php esc_html_e('Streak', 'vc-flashcards'); ?></small>
              <strong><?php echo esc_html((string) ($stats['studyStreak'] ?? 0)); ?> <?php esc_html_e('days', 'vc-flashcards'); ?></strong>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <?php /* Si no hay categorias preparadas para examen, se muestra un estado vacio. */ ?>
    <?php if (empty($exam_categories)): ?>
      <article class="vc-flashcards-empty">
        <p><?php esc_html_e('No categories or flashcards are available yet.', 'vc-flashcards'); ?></p>
      </article>
    <?php else: ?>
      <?php /* Grilla principal del home: cards por categoria para iniciar el examen por tema. */ ?>
      <div class="vc-flashcards-category-grid vc-flashcards-category-grid--mock">
        <?php foreach ($exam_categories as $category): ?>
          <?php /* Card individual de categoria: nombre, metadata, pill informativo y CTA Start Exam. */ ?>
          <article
            class="vc-flashcards-category-card"
            aria-labelledby="vc-exam-category-<?php echo esc_attr((string) $category['id']); ?>-title"
          >

            <div class="vc-flashcards-category-top">
              <?php /* Nombre visible de la categoria de examen. */ ?>
              <h3 id="vc-exam-category-<?php echo esc_attr((string) $category['id']); ?>-title"><?php echo esc_html($category['name']); ?></h3>
            </div>

            <div class="vc-exam-category-meta">
              <?php /* Metadata resumida: numero de subtopics y total de cards disponibles. */ ?>
              <p>
                <?php echo esc_html(
                  sprintf(
                    /* translators: 1: subtopic count, 2: total cards */
                    _n('%2$d subtopic', '%1$d subtopics', $category['subtopicCount'], 'vc-flashcards'),
                    $category['subtopicCount'],
                    $category['subtopicCount']
                  ) . ' · ' . sprintf(
                    /* translators: %d: total cards */
                    _n('%d card available', '%d cards available', $category['totalCards'], 'vc-flashcards'),
                    $category['totalCards']
                  )
                ); ?>
              </p>
            </div>

            <div class="vc-flashcards-category-meta">
              <?php /* Boton que dispara el inicio del examen para esta categoria concreta. */ ?>
              <button
                type="button"
                class="vc-flashcards-start vc-flashcards-start--full"
                data-vc-exam-start="<?php echo esc_attr((string) $category['id']); ?>"
              >
                <span><?php esc_html_e('Start Exam', 'vc-flashcards'); ?></span>
                <span class="vc-flashcards-start-icon" aria-hidden="true">
                  <svg width="16" height="16" viewBox="0 0 12 12" fill="none">
                    <path d="M1.5 6H10.5M7.5 3L10.5 6L7.5 9" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              </button>
            </div>

          </article>
        <?php endforeach; ?>
      </div>

      <?php /* Historial de examenes completados por el usuario actual. */ ?>
      <?php /* Zona inferior del home: historial de examenes a la izquierda y detalles fijos del test a la derecha. */ ?>
      <section class="vc-exam-history">
        <?php /* Contenedor que carga el historial inicial y luego puede refrescarse por AJAX. */ ?>
        <section class="vc-exam-history-content" aria-labelledby="vc-exam-history-title">
          <?php include VC_FLASHCARDS_DIR . 'templates/partials/exam-history-content.php'; ?>
        </section>

        <?php /* Card lateral con reglas del mock test: preguntas, tiempo, score minimo y review. */ ?>
        <section class="vc-exam-history-test-details" aria-labelledby="vc-exam-test-details-title">
          <h3 id="vc-exam-test-details-title"><?php esc_html_e('Test Details', 'vc-flashcards'); ?></h3>
          <ul class="vc-exam-history-test-details-list">
            <li class="vc-exam-history-test-details-item">
              <span class="vc-exam-history-test-details-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                  <circle cx="8" cy="8" r="1.25" fill="currentColor"/>
                </svg>
              </span>
              <span><?php esc_html_e('100 random questions', 'vc-flashcards'); ?></span>
            </li>
            <li class="vc-exam-history-test-details-item">
              <span class="vc-exam-history-test-details-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M8 4.75V8l2.25 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
              <span><?php esc_html_e('2-hour time limit', 'vc-flashcards'); ?></span>
            </li>
            <li class="vc-exam-history-test-details-item">
              <span class="vc-exam-history-test-details-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M4 2.75h8v2a4 4 0 0 1-2.5 3.69V10.5H11a1.25 1.25 0 0 1 0 2.5H5a1.25 1.25 0 0 1 0-2.5h1.5V8.44A4 4 0 0 1 4 4.75v-2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                  <path d="M4 4.25H2.75a1.25 1.25 0 0 0 0 2.5H4m8-2.5h1.25a1.25 1.25 0 0 1 0 2.5H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
              </span>
              <span><?php esc_html_e('70% minimum to pass', 'vc-flashcards'); ?></span>
            </li>
            <li class="vc-exam-history-test-details-item">
              <span class="vc-exam-history-test-details-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <circle cx="8" cy="8" r="5.5" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M5.5 8.25 7.1 9.85 10.75 6.2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
              <span><?php esc_html_e('Answer review allowed', 'vc-flashcards'); ?></span>
            </li>
          </ul>
        </section>
      </section>
    <?php endif; ?>

  </section>

  <?php /* ── SESSION: question answering + timer ─────────────────── */ ?>
  <?php /* Vista session: header vivo del examen, barra de progreso, pregunta actual y navegacion. */ ?>
  <section data-vc-exam-view="session" hidden aria-labelledby="vc-exam-session-title">
    <h2 id="vc-exam-session-title" class="screen-reader-text"><?php esc_html_e('Exam session', 'vc-flashcards'); ?></h2>

    <?php /* Header funcional de la sesion: progreso, boton Finish y cronometro. */ ?>
    <div class="vc-exam-session-header">
      <?php /* Label fijo que introduce el estado de progreso. */ ?>
      <p class="vc-exam-progress-label"><?php esc_html_e('Progress', 'vc-flashcards'); ?></p>

      <?php /* CTA para terminar manualmente el examen antes de responder todo. */ ?>
      <button type="button" class="vc-flashcards-back vc-exam-finish-btn">
        <span><?php esc_html_e('Finish exam', 'vc-flashcards'); ?></span>
      </button>

      <?php /* Contador dinamico de respuestas contestadas sobre el total. */ ?>
      <strong data-vc-exam-progress><?php esc_html_e('Question 1 of 100', 'vc-flashcards'); ?></strong>

      <?php /* Cronometro vivo del examen; JS actualiza su valor y estados visuales. */ ?>
      <div class="vc-exam-timer" data-vc-exam-timer aria-live="polite" aria-label="<?php esc_attr_e('Time remaining', 'vc-flashcards'); ?>">
        <span class="vc-exam-timer-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 14 14" fill="none">
            <circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.5"></circle>
            <path d="M7 4v3.5l2 1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
          </svg>
        </span>
        <span data-vc-exam-timer-value>0:01:00</span>
      </div>
    </div>

    <?php /* Barra visual que refleja el avance del usuario dentro del total de preguntas. */ ?>
    <div class="vc-flashcards-session-bar" aria-hidden="true">
      <span data-vc-exam-bar-fill style="width: 0%;"></span>
    </div>

    <?php /* Card principal de la sesion: contexto, pregunta, respuestas y acciones Previous/Next. */ ?>
    <article class="vc-flashcards-card vc-exam-card">
      <?php /* Contexto visible de la pregunta actual: tema principal y numero de pregunta. */ ?>
      <div class="vc-flashcards-session-context">
        <?php /* Tema principal de la pregunta actual. */ ?>
        <p class="vc-flashcards-session-topic" data-vc-exam-topic-label></p>
        <?php /* Etiqueta secundaria con el numero de pregunta actual. */ ?>
        <p class="vc-flashcards-session-subtopic" data-vc-exam-subtopic-label></p>
      </div>

      <?php /* Enunciado principal de la pregunta actual. */ ?>
      <h4 data-vc-exam-question></h4>

      <?php /* Contenedor donde JS inyecta dinamicamente las respuestas de la pregunta actual. */ ?>
      <div class="vc-flashcards-answers" data-vc-exam-answers></div>

      <?php /* Footer de navegacion de la card: permite retroceder o avanzar entre preguntas. */ ?>
      <div class="vc-exam-session-actions">
        <?php /* Boton para volver a la pregunta anterior dentro de la misma sesion. */ ?>
        <button
          type="button"
          class="vc-flashcards-session-action vc-flashcards-session-next vc-exam-session-prev"
          data-vc-exam-prev
        >
          <span class="vc-flashcards-next-icon" aria-hidden="true">&lt;</span>
          <span><?php esc_html_e('Previous', 'vc-flashcards'); ?></span>
        </button>

        <?php /* Boton para avanzar a la siguiente pregunta o finalizar en la ultima. */ ?>
        <button
          type="button"
          class="vc-flashcards-session-action vc-flashcards-session-next"
          data-vc-exam-next
        >
          <span data-vc-exam-next-label><?php esc_html_e('Next question', 'vc-flashcards'); ?></span>
          <span class="vc-flashcards-next-icon" aria-hidden="true">&gt;</span>
        </button>
      </div>
    </article>

  </section>
  
  <?php /* Vista summary: resultado final del examen, desglose por categoria y acciones posteriores. */ ?>
  <section data-vc-exam-view="summary" hidden aria-labelledby="vc-exam-summary-title">
    <?php /* Wrapper general del resumen final; agrupa intro y card principal de resultados. */ ?>
    <article class="vc-exam-summary-dialog" aria-labelledby="vc-exam-summary-title">
      <?php /* Franja superior del resumen: titulo principal y texto introductorio de cierre. */ ?>
      <div class="vc-exam-summary-intro vc-exam-summary-section vc-exam-summary-section--intro">
        <?php /* Titulo principal del resumen final. */ ?>
        <h2 id="vc-exam-summary-title" class="vc-exam-summary-eyebrow"><?php esc_html_e('Resultados del Simulador A&P', 'vc-flashcards'); ?></h2>
        <?php /* Texto secundario que confirma el cierre del mock test. */ ?>
        <p class="vc-exam-summary-description"><?php esc_html_e('You have completed the mock exam', 'vc-flashcards'); ?></p>
      </div>

      <?php /* Card blanca principal del summary: resultado, metricas, breakdown y botones finales. */ ?>
      <div class="vc-exam-summary-section vc-exam-summary-section--content">
        <?php /* Cabecera del resultado: icono, estado textual y requisito minimo para aprobar. */ ?>
      <div class="vc-exam-result-header">
        <?php /* Badge central que cambia entre aprobado y reprobado segun el resultado. */ ?>
        <div class="vc-exam-result-badge" data-vc-exam-result-badge aria-hidden="true">
          <img
            data-pass-src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/checkverde.svg'); ?>"
            data-fail-src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/incorrectoHist.svg'); ?>"
            data-vc-exam-result-icon
            src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/checkverde.svg'); ?>"
            width="40"
            height="40"
            alt=""
          >
        </div>
        <?php /* Estado textual principal del resultado, por ejemplo Approved o Not approved. */ ?>
        <p class="vc-flashcards-summary-kicker" data-vc-exam-result-kicker><?php esc_html_e('Not approved', 'vc-flashcards'); ?></p>
        <?php /* Texto auxiliar con el umbral minimo necesario para aprobar. */ ?>
        <p class="vc-exam-result-requirement"><?php esc_html_e('70% is required to pass', 'vc-flashcards'); ?></p>
      </div>

      <?php /* Bloque central del summary: cards de resultado y desglose por categoria. */ ?>
      <div class="vc-flashcards-summary-top">
        <?php /* Cards de resultado rapido: correctas, incorrectas y unanswered. */ ?>
        <div class="vc-flashcards-summary-results">
          <?php /* Card de respuestas correctas. */ ?>
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--correct">
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--correct" data-vc-exam-correct-count>0</strong>
            <h3><?php esc_html_e('Correct', 'vc-flashcards'); ?></h3>
          </div>
          <?php /* Card de respuestas incorrectas. */ ?>
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--incorrect">
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--incorrect" data-vc-exam-incorrect-count>0</strong>
            <h3><?php esc_html_e('Incorrect', 'vc-flashcards'); ?></h3>
          </div>
          <?php /* Card de preguntas que quedaron sin responder. */ ?>
          <div class="vc-flashcards-summary-result-card vc-flashcards-summary-result-card--unanswered">
            <strong class="vc-flashcards-summary-count vc-flashcards-summary-count--unanswered" data-vc-exam-unanswered-count>0</strong>
            <h3><?php esc_html_e('Unanswered', 'vc-flashcards'); ?></h3>
          </div>
        </div>

        <?php /* Desglose por categoria para revisar el rendimiento por tema. */ ?>
        <section class="vc-exam-summary-breakdown" aria-labelledby="vc-exam-summary-breakdown-title">
          <h3 id="vc-exam-summary-breakdown-title" class="vc-exam-summary-breakdown-title"><?php esc_html_e('Desglose por categoría', 'vc-flashcards'); ?></h3>

          <?php /* Lista de filas del breakdown; JS actualiza estos datos al terminar el examen. */ ?>
          <div class="vc-exam-summary-breakdown-list">
            <?php /* Fila de General: texto auxiliar y porcentaje final de esa categoria. */ ?>
            <div class="vc-exam-summary-breakdown-item">
              <div class="vc-exam-summary-breakdown-copy">
                <strong><?php esc_html_e('General', 'vc-flashcards'); ?></strong>
                <small data-vc-exam-breakdown-general-meta><?php esc_html_e('0 of 0 correct', 'vc-flashcards'); ?></small>
              </div>
              <strong class="vc-exam-summary-breakdown-score" data-vc-exam-breakdown-general-score>0%</strong>
            </div>

            <?php /* Fila de Airframe: texto auxiliar y porcentaje final de esa categoria. */ ?>
            <div class="vc-exam-summary-breakdown-item">
              <div class="vc-exam-summary-breakdown-copy">
                <strong><?php esc_html_e('Airframe', 'vc-flashcards'); ?></strong>
                <small data-vc-exam-breakdown-airframe-meta><?php esc_html_e('0 of 0 correct', 'vc-flashcards'); ?></small>
              </div>
              <strong class="vc-exam-summary-breakdown-score" data-vc-exam-breakdown-airframe-score>0%</strong>
            </div>

            <?php /* Fila de Powerplant: texto auxiliar y porcentaje final de esa categoria. */ ?>
            <div class="vc-exam-summary-breakdown-item">
              <div class="vc-exam-summary-breakdown-copy">
                <strong><?php esc_html_e('Powerplant', 'vc-flashcards'); ?></strong>
                <small data-vc-exam-breakdown-powerplant-meta><?php esc_html_e('0 of 0 correct', 'vc-flashcards'); ?></small>
              </div>
              <strong class="vc-exam-summary-breakdown-score" data-vc-exam-breakdown-powerplant-score>0%</strong>
            </div>
          </div>
        </section>
      </div>

      <?php /* Acciones finales del summary: volver al menu o reiniciar el examen. */ ?>
      <div class="vc-flashcards-summary-actions">
        <?php /* Boton para cerrar el resumen y volver al menu principal del examen. */ ?>
        <button
          type="button"
          class="vc-flashcards-back vc-flashcards-summary-action vc-flashcards-summary-action--back"
          data-vc-exam-summary-back
        ><?php esc_html_e('Back to menu', 'vc-flashcards'); ?></button>

        <?php /* Boton para lanzar un nuevo intento del examen usando la misma categoria. */ ?>
        <button
          type="button"
          class="vc-flashcards-start vc-flashcards-summary-action vc-flashcards-summary-action--restart"
          data-vc-exam-retry
        >
          <span class="vc-flashcards-summary-action-icon" aria-hidden="true">
            <img src="<?php echo esc_url(VC_FLASHCARDS_URL . 'assets/icons/try.svg'); ?>" alt="" width="16" height="16">
          </span>

          <span><?php esc_html_e('Try again', 'vc-flashcards'); ?></span>
        </button>
      </div>
      </div>
    </article>
  </section>

</div>
