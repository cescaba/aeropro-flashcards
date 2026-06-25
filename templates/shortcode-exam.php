<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<?php /* Exam app shell: JS switches between home, session and summary views. */ ?>
<div class="vc-exam-app is-booting">
  <?php /* Shared feedback node for AJAX errors and contextual exam messages. */ ?>
  <p class="vc-exam-feedback" data-vc-exam-feedback hidden></p>

  <?php /* Home view: category selection and historical stats. */ ?>
  <?php include VC_FLASHCARDS_DIR . 'templates/exam/home.php'; ?>

  <?php /* Session view: timer, question, answers and navigation. */ ?>
  <?php include VC_FLASHCARDS_DIR . 'templates/exam/session.php'; ?>

  <?php /* Summary view: score, pass/fail state and retry/back actions. */ ?>
  <?php include VC_FLASHCARDS_DIR . 'templates/exam/summary.php'; ?>

  <?php /* Reference image modal: desktop muestra la imagen grande sin convertirla en dropdown. */ ?>
  <div class="vc-reference-modal vc-exam-reference-modal" data-vc-exam-reference-modal hidden>
    <button type="button" class="vc-reference-modal__backdrop vc-exam-reference-modal-backdrop" data-vc-exam-reference-modal-close aria-label="<?php esc_attr_e('Close reference image', 'vc-flashcards'); ?>"></button>
    <?php /* Reference modal convention: vc-reference-modal_* is canonical; vc-study-sessions-reference-modal-dialog stays for legacy QA/client references. */ ?>
    <div class="vc-reference-modal__dialog vc-exam-reference-modal-dialog vc-study-sessions-reference-modal-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Reference image', 'vc-flashcards'); ?>">
      <div class="vc-reference-modal__controls vc-exam-reference-modal-controls">
        <button type="button" class="vc-reference-modal__zoom vc-exam-reference-modal-zoom" data-vc-exam-reference-modal-zoom aria-pressed="false" aria-label="<?php esc_attr_e('Zoom reference image', 'vc-flashcards'); ?>">
          <svg class="vc-reference-modal__zoom-icon vc-reference-modal__zoom-icon--in" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 15l5 5M10.5 17a6.5 6.5 0 1 1 0-13 6.5 6.5 0 0 1 0 13Zm0-9v5M8 10.5h5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <svg class="vc-reference-modal__zoom-icon vc-reference-modal__zoom-icon--out" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 15l5 5M10.5 17a6.5 6.5 0 1 1 0-13 6.5 6.5 0 0 1 0 13ZM8 10.5h5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button type="button" class="vc-reference-modal__close vc-exam-reference-modal-close" data-vc-exam-reference-modal-close aria-label="<?php esc_attr_e('Close reference image', 'vc-flashcards'); ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
      </div>
      <div class="vc-reference-modal__frame vc-exam-reference-modal-frame">
        <img class="vc-reference-modal__image vc-exam-reference-modal-image" data-vc-exam-reference-modal-image alt="<?php esc_attr_e('Reference image', 'vc-flashcards'); ?>">
      </div>
    </div>
  </div>
</div>
