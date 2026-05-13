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
</div>
