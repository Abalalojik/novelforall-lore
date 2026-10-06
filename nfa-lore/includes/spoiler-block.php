<?php
/**
 * Spoiler block: its content is only rendered once its chapter is published.
 * Readers see nothing at all (no placeholder); editors see it with a small notice.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_block_type(
			NFA_LORE_DIR . 'blocks/spoiler',
			array(
				'render_callback' => 'nfa_lore_render_spoiler',
			)
		);
	}
);

function nfa_lore_render_spoiler( $attributes, $content ) {
	$chapter = isset( $attributes['chapter'] ) ? (int) $attributes['chapter'] : 0;
	if ( nfa_lore_chapter_is_published( $chapter ) ) {
		return $content;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	$notice = sprintf(
		/* translators: %s: chapter title */
		__( 'Hidden from readers until “%s” is published.', 'nfa-lore' ),
		get_the_title( $chapter )
	);
	return '<div class="nfa-spoiler-preview"><p class="nfa-spoiler-preview__notice">' . esc_html( $notice ) . '</p>' . $content . '</div>';
}

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$data = array( 'chapters' => nfa_lore_chapter_choices() );
		wp_add_inline_script( 'nfa-lore-spoiler-editor-script', 'window.nfaLoreSpoiler = ' . wp_json_encode( $data ) . ';', 'before' );
	}
);
