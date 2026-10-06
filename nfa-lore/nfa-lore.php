<?php
/**
 * Plugin Name:       Novel For All Lore
 * Description:       Novel For All: story structure (story, arcs, tomes, chapters) with continuous chapter navigation and contents, plus a world wiki with [[wiki links]], glossary tooltips, and spoiler control tied to published chapters.
 * Version:           0.2.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Djenny Floro
 * License:           GPL-2.0-or-later
 * Text Domain:       nfa-lore
 */

defined( 'ABSPATH' ) || exit;

define( 'NFA_LORE_VERSION', '0.2.0' );
define( 'NFA_LORE_FILE', __FILE__ );
define( 'NFA_LORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'NFA_LORE_URL', plugin_dir_url( __FILE__ ) );

require_once NFA_LORE_DIR . 'includes/content-types.php';
require_once NFA_LORE_DIR . 'includes/visibility.php';
require_once NFA_LORE_DIR . 'includes/entry-settings.php';
require_once NFA_LORE_DIR . 'includes/wikilinks.php';
require_once NFA_LORE_DIR . 'includes/glossary.php';
require_once NFA_LORE_DIR . 'includes/spoiler-block.php';
require_once NFA_LORE_DIR . 'includes/stories.php';
require_once NFA_LORE_DIR . 'includes/story-blocks.php';
require_once NFA_LORE_DIR . 'includes/templates.php';

register_activation_hook(
	__FILE__,
	static function () {
		nfa_lore_register_content_types();
		nfa_story_register();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'nfa-lore', NFA_LORE_URL . 'assets/nfa-lore.css', array(), NFA_LORE_VERSION );
	}
);
