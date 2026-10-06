<?php
/**
 * Spoiler control: a lore entry stays invisible to readers until its "visible from" chapter is published.
 *
 * Invisible means: absent from lists, search, sitemaps and the REST API; links to it render as plain
 * text; its glossary tooltip is not used; a direct visit redirects to the verse's wiki home.
 * Users who can edit the entry still see everything.
 */

defined( 'ABSPATH' ) || exit;

const NFA_META_VISIBLE_FROM = '_nfa_visible_from';
const NFA_LOCKED_CACHE      = 'nfa_lore_locked_ids';

/**
 * A chapter unlocks content once it is published (a scheduled chapter does not count yet).
 */
function nfa_lore_chapter_is_published( $chapter_id ) {
	$chapter_id = (int) $chapter_id;
	if ( ! $chapter_id ) {
		return true;
	}
	return 'publish' === get_post_status( $chapter_id );
}

function nfa_lore_entry_is_unlocked( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return false;
	}
	return nfa_lore_chapter_is_published( get_post_meta( $post->ID, NFA_META_VISIBLE_FROM, true ) );
}

/**
 * Whether the current visitor may see the entry.
 */
function nfa_lore_entry_is_visible( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	return nfa_lore_entry_is_unlocked( $post ) || current_user_can( 'edit_post', $post->ID );
}

/**
 * IDs of published entries whose chapter is not published yet.
 */
function nfa_lore_locked_ids() {
	$ids = get_transient( NFA_LOCKED_CACHE );
	if ( false !== $ids ) {
		return $ids;
	}
	$candidates = get_posts(
		array(
			'post_type'        => NFA_LORE_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'meta_key'         => NFA_META_VISIBLE_FROM,
			'meta_compare'     => '>',
			'meta_value'       => 0,
			'meta_type'        => 'NUMERIC',
			'suppress_filters' => true,
			'nfa_internal'     => true,
		)
	);
	$ids = array_values(
		array_filter(
			$candidates,
			static function ( $id ) {
				return ! nfa_lore_chapter_is_published( get_post_meta( $id, NFA_META_VISIBLE_FROM, true ) );
			}
		)
	);
	set_transient( NFA_LOCKED_CACHE, $ids, DAY_IN_SECONDS );
	return $ids;
}

function nfa_lore_flush_caches() {
	delete_transient( NFA_LOCKED_CACHE );
	do_action( 'nfa_lore_flush_caches' );
}
// A chapter going live (including a scheduled one) or any entry change can unlock or lock entries.
add_action( 'transition_post_status', 'nfa_lore_flush_caches' );
add_action( 'save_post', 'nfa_lore_flush_caches' );
add_action( 'deleted_post', 'nfa_lore_flush_caches' );
add_action( 'set_object_terms', 'nfa_lore_flush_caches' );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $nfa_meta_hook ) {
	add_action(
		$nfa_meta_hook,
		static function ( $meta_ids, $object_id, $meta_key ) {
			if ( 0 === strpos( (string) $meta_key, '_nfa_' ) ) {
				nfa_lore_flush_caches();
			}
		},
		10,
		3
	);
}

/**
 * Keep locked entries out of every front-end query (lists, search, wiki home).
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		// Our own lookups (locked IDs, indexes) must see everything, and must not recurse into this filter.
		if ( is_admin() || $query->get( 'nfa_internal' ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		$is_lore_query = in_array( NFA_LORE_TYPE, $types, true ) || in_array( 'any', $types, true )
			|| $query->is_search() || $query->is_tax( NFA_VERSE_TAX );
		if ( ! $is_lore_query || $query->is_singular() ) {
			return;
		}
		$locked = nfa_lore_locked_ids();
		if ( $locked ) {
			$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $locked ) );
		}
	}
);

/**
 * A direct visit to a locked entry leads to the verse's wiki home instead of a 404.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( NFA_LORE_TYPE ) ) {
			return;
		}
		$post = get_queried_object();
		if ( nfa_lore_entry_is_visible( $post ) ) {
			return;
		}
		$term   = get_term_by( 'slug', nfa_lore_entry_verse_slug( $post ), NFA_VERSE_TAX );
		$target = $term ? get_term_link( $term ) : home_url( '/' );
		wp_safe_redirect( is_wp_error( $target ) ? home_url( '/' ) : $target, 302 );
		exit;
	}
);

/**
 * REST API: readers cannot list or fetch locked entries.
 */
add_filter(
	'rest_' . NFA_LORE_TYPE . '_query',
	static function ( $args ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return $args;
		}
		$locked = nfa_lore_locked_ids();
		if ( $locked ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $locked );
		}
		return $args;
	}
);

add_filter(
	'rest_prepare_' . NFA_LORE_TYPE,
	static function ( $response, $post ) {
		if ( nfa_lore_entry_is_visible( $post ) ) {
			return $response;
		}
		return new WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'nfa-lore' ), array( 'status' => 404 ) );
	},
	10,
	2
);

/**
 * Sitemaps (core and Jetpack).
 */
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $post_type ) {
		if ( NFA_LORE_TYPE === $post_type ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), nfa_lore_locked_ids() );
		}
		return $args;
	},
	10,
	2
);

add_filter(
	'jetpack_sitemap_skip_post',
	static function ( $skip, $post ) {
		if ( $post && NFA_LORE_TYPE === $post->post_type && ! nfa_lore_entry_is_unlocked( $post->ID ) ) {
			return true;
		}
		return $skip;
	},
	10,
	2
);
