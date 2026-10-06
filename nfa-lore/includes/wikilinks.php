<?php
/**
 * [[Wiki links]] inside lore entries (never inside chapters).
 *
 *   [[Seren]]                    → link to the entry titled "Seren" (or with that alias or slug)
 *   [[Seren|a seren]]            → same link, custom text
 *   [[Ancient Speech#Pronouns]]  → link to a section of the entry
 *
 * A link only exists if it leads somewhere: when the target is missing or still locked, readers get the
 * plain text. Editors see it marked so they can spot it.
 */

defined( 'ABSPATH' ) || exit;

const NFA_INDEX_CACHE = 'nfa_lore_title_index';

/**
 * Lookup table: lowercase title / alias / slug → entry ID, per verse.
 */
function nfa_lore_title_index() {
	$index = get_transient( NFA_INDEX_CACHE );
	if ( false !== $index ) {
		return $index;
	}
	$index = array();
	$ids   = get_posts(
		array(
			'post_type'        => NFA_LORE_TYPE,
			'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'nfa_internal'     => true,
		)
	);
	foreach ( $ids as $id ) {
		$verse = nfa_lore_entry_verse_slug( $id );
		$names = array_merge( array( get_the_title( $id ), get_post_field( 'post_name', $id ) ), nfa_lore_entry_aliases( $id ) );
		foreach ( $names as $name ) {
			$key = nfa_lore_normalize_name( $name );
			if ( '' !== $key && ! isset( $index[ $verse ][ $key ] ) ) {
				$index[ $verse ][ $key ] = $id;
			}
		}
	}
	set_transient( NFA_INDEX_CACHE, $index, DAY_IN_SECONDS );
	return $index;
}
add_action(
	'nfa_lore_flush_caches',
	static function () {
		delete_transient( NFA_INDEX_CACHE );
	}
);

function nfa_lore_normalize_name( $name ) {
	$name = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $name ), 'UTF-8' ) : strtolower( trim( $name ) );
}

/**
 * Find an entry by name, preferring the given verse.
 */
function nfa_lore_find_entry( $name, $verse ) {
	$index = nfa_lore_title_index();
	$key   = nfa_lore_normalize_name( $name );
	if ( isset( $index[ $verse ][ $key ] ) ) {
		return $index[ $verse ][ $key ];
	}
	foreach ( $index as $entries ) {
		if ( isset( $entries[ $key ] ) ) {
			return $entries[ $key ];
		}
	}
	return 0;
}

function nfa_lore_render_wikilinks( $content ) {
	if ( false === strpos( $content, '[[' ) ) {
		return $content;
	}
	$post = get_post();
	if ( ! $post || NFA_LORE_TYPE !== $post->post_type ) {
		return $content;
	}
	$verse = nfa_lore_entry_verse_slug( $post );

	return preg_replace_callback(
		'/\[\[([^\]\|#]+)(?:#([^\]\|]+))?(?:\|([^\]]+))?\]\]/u',
		static function ( $m ) use ( $verse ) {
			$target  = trim( $m[1] );
			$section = isset( $m[2] ) ? trim( $m[2] ) : '';
			$label   = isset( $m[3] ) && '' !== trim( $m[3] ) ? trim( $m[3] ) : $target;
			$id      = nfa_lore_find_entry( $target, $verse );

			if ( $id && nfa_lore_entry_is_visible( $id ) ) {
				$url = get_permalink( $id ) . ( '' !== $section ? '#' . sanitize_title( $section ) : '' );
				return '<a class="nfa-wikilink" href="' . esc_url( $url ) . '">' . $label . '</a>';
			}
			if ( current_user_can( 'edit_posts' ) ) {
				$why = $id ? __( 'Locked or unpublished entry', 'nfa-lore' ) : __( 'No entry with this name yet', 'nfa-lore' );
				return '<span class="nfa-wikilink-missing" title="' . esc_attr( $why ) . '">' . $label . '</span>';
			}
			return $label;
		},
		$content
	);
}
add_filter( 'the_content', 'nfa_lore_render_wikilinks', 20 );

/**
 * Give headings in lore entries an id, so [[Entry#Section]] can point at them.
 */
add_filter(
	'render_block_core/heading',
	static function ( $html ) {
		$post = get_post();
		if ( ! $post || NFA_LORE_TYPE !== $post->post_type || preg_match( '/<h[1-6][^>]*\sid=/i', $html ) ) {
			return $html;
		}
		return preg_replace_callback(
			'/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
			static function ( $m ) {
				$id = sanitize_title( wp_strip_all_tags( $m[3] ) );
				return '' === $id ? $m[0] : '<h' . $m[1] . $m[2] . ' id="' . esc_attr( $id ) . '">' . $m[3] . '</h' . $m[1] . '>';
			},
			$html,
			1
		);
	}
);
