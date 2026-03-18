<?php
/**
 * Plugin Name: VC Flashcards
 * Description: Flashcards practice engine with configurable topics, subtopics, sessions, attempts, and progress metrics.
 * Version: 1.0.0
 * Author: VC Studio
 */

if (!defined('ABSPATH')) {
  exit;
}

if (!defined('VC_FLASHCARDS_FILE')) {
  define('VC_FLASHCARDS_FILE', __FILE__);
}

if (!defined('VC_FLASHCARDS_DIR')) {
  define('VC_FLASHCARDS_DIR', plugin_dir_path(__FILE__));
}

if (!defined('VC_FLASHCARDS_URL')) {
  define('VC_FLASHCARDS_URL', plugin_dir_url(__FILE__));
}

require_once VC_FLASHCARDS_DIR . 'includes/class-vc-flashcards-plugin.php';

register_activation_hook(VC_FLASHCARDS_FILE, ['VC_Flashcards_Plugin', 'activate']);
register_deactivation_hook(VC_FLASHCARDS_FILE, ['VC_Flashcards_Plugin', 'deactivate']);

VC_Flashcards_Plugin::instance();
