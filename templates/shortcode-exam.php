<?php
if (!defined('ABSPATH')) {
  exit;
}
?>
<div class="vc-exam-app is-booting">
  <p class="vc-exam-feedback" data-vc-exam-feedback hidden></p>

  <?php include VC_FLASHCARDS_DIR . 'templates/exam/home.php'; ?>
  <?php include VC_FLASHCARDS_DIR . 'templates/exam/session.php'; ?>
  <?php include VC_FLASHCARDS_DIR . 'templates/exam/summary.php'; ?>
</div>
