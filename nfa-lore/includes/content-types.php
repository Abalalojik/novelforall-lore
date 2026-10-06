<?php
/**
 * Lore entries (nfa_lore) and verses (nfa_verse).
 *
 * URLs: /wiki/{verse}/{entry}/ for an entry, /wiki/{verse}/ for a verse's wiki home.
 */

defined( 'ABSPATH' ) || exit;

const NFA_LORE_TYPE  = 'nfa_lore';
const NFA_VERSE_TAX  = 'nfa_verse';
const NFA_NO_VERSE   = 'general';

function nfa_lore_register_content_types() {
	register_taxonomy(
		NFA_VERSE_TAX,
		array( NFA_LORE_TYPE, 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Verses', 'nfa-lore' ),
				'singular_name' => __( 'Verse', 'nfa-lore' ),
				'add_new_item'  => __( 'Add New Verse', 'nfa-lore' ),
				'edit_item'     => __( 'Edit Verse', 'nfa-lore' ),
				'search_items'  => __( 'Search Verses', 'nfa-lore' ),
				'all_items'     => __( 'All Verses', 'nfa-lore' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'verses',
			'rewrite'           => array(
				'slug'       => 'wiki',
				'with_front' => false,
			),
		)
	);

	add_rewrite_tag( '%' . NFA_VERSE_TAX . '%', '([^/]+)', NFA_VERSE_TAX . '=' );

	register_post_type(
		NFA_LORE_TYPE,
		array(
			'labels'        => array(
				'name'               => __( 'Lore Entries', 'nfa-lore' ),
				'singular_name'      => __( 'Lore Entry', 'nfa-lore' ),
				'menu_name'          => __( 'Lore', 'nfa-lore' ),
				'add_new_item'       => __( 'Add New Lore Entry', 'nfa-lore' ),
				'edit_item'          => __( 'Edit Lore Entry', 'nfa-lore' ),
				'new_item'           => __( 'New Lore Entry', 'nfa-lore' ),
				'view_item'          => __( 'View Lore Entry', 'nfa-lore' ),
				'search_items'       => __( 'Search Lore Entries', 'nfa-lore' ),
				'not_found'          => __( 'No lore entries found.', 'nfa-lore' ),
				'all_items'          => __( 'All Lore Entries', 'nfa-lore' ),
			),
			'public'        => true,
			'has_archive'   => false,
			'menu_icon'     => 'dashicons-book-alt',
			'menu_position' => 21,
			'show_in_rest'  => true,
			'rest_base'     => 'lore',
			'supports'      => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
			'taxonomies'    => array( NFA_VERSE_TAX ),
			'rewrite'       => array(
				'slug'       => 'wiki/%' . NFA_VERSE_TAX . '%',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'nfa_lore_register_content_types' );

/**
 * The verse an entry belongs to: its first verse term, or "general".
 */
function nfa_lore_entry_verse_slug( $post ) {
	$terms = get_the_terms( $post, NFA_VERSE_TAX );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return NFA_NO_VERSE;
	}
	return $terms[0]->slug;
}

add_filter(
	'post_type_link',
	static function ( $link, $post ) {
		if ( NFA_LORE_TYPE !== $post->post_type || false === strpos( $link, '%' . NFA_VERSE_TAX . '%' ) ) {
			return $link;
		}
		return str_replace( '%' . NFA_VERSE_TAX . '%', nfa_lore_entry_verse_slug( $post ), $link );
	},
	10,
	2
);

/**
 * A verse's wiki home lists its lore entries only (not its chapters), all of them, by title.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_tax( NFA_VERSE_TAX ) ) {
			return;
		}
		$query->set( 'post_type', NFA_LORE_TYPE );
		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
);

/**
 * The verses a post belongs to, as slugs.
 */
function nfa_lore_post_verse_slugs( $post ) {
	$terms = get_the_terms( $post, NFA_VERSE_TAX );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}
	return wp_list_pluck( $terms, 'slug' );
}
