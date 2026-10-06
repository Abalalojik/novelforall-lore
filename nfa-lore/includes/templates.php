<?php
/**
 * Block templates for lore entries and verse wiki homes. They reuse the active theme's header and
 * footer, and can be edited in the Site Editor like any template.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		register_block_template(
			'nfa-lore//single-' . NFA_LORE_TYPE,
			array(
				'title'       => __( 'Lore Entry', 'nfa-lore' ),
				'description' => __( 'A single lore entry of the wiki.', 'nfa-lore' ),
				'content'     => nfa_lore_template_file( 'single-lore.html' ),
			)
		);
		register_block_template(
			'nfa-lore//taxonomy-' . NFA_VERSE_TAX,
			array(
				'title'       => __( 'Verse Wiki', 'nfa-lore' ),
				'description' => __( 'The wiki home of a verse: all its lore entries.', 'nfa-lore' ),
				'content'     => nfa_lore_template_file( 'verse-wiki.html' ),
			)
		);
	}
);

function nfa_lore_template_file( $name ) {
	$path = NFA_LORE_DIR . 'templates/' . $name;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}
