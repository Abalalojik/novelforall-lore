<?php
/**
 * Story structure: Story › Arc › Tome › Chapter.
 *
 * A story is a top-level term of the hierarchical `nfa_story` taxonomy; its children are arcs, their
 * children tomes (any depth works). Chapters are posts filed under the deepest term, with a chapter
 * number. Reading order = the term tree (siblings sorted by their order), then chapter number, never
 * the publication date.
 *
 * Term order: the `nfa_order` term meta if set, otherwise the first number in the term name
 * ("Arc 0 — The Intern" → 0, "Tome 1" → 1).
 */

defined( 'ABSPATH' ) || exit;

const NFA_STORY_TAX         = 'nfa_story';
const NFA_META_CHAPTER_NUM  = '_nfa_chapter_number';
const NFA_TERM_META_ORDER   = 'nfa_order';
const NFA_TERM_META_VERSE   = 'nfa_verse';
const NFA_STORY_ORDER_CACHE = 'nfa_story_order_';

function nfa_story_register() {
	register_taxonomy(
		NFA_STORY_TAX,
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Stories', 'nfa-lore' ),
				'singular_name' => __( 'Story', 'nfa-lore' ),
				'menu_name'     => __( 'Stories', 'nfa-lore' ),
				'add_new_item'  => __( 'Add Story, Arc or Tome', 'nfa-lore' ),
				'edit_item'     => __( 'Edit Story, Arc or Tome', 'nfa-lore' ),
				'parent_item'   => __( 'Parent (story or arc)', 'nfa-lore' ),
				'all_items'     => __( 'All Stories', 'nfa-lore' ),
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'stories',
			'rewrite'           => array(
				'slug'         => 'stories',
				'with_front'   => false,
				'hierarchical' => true,
			),
		)
	);

	register_term_meta(
		NFA_STORY_TAX,
		NFA_TERM_META_ORDER,
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) {
				return (int) $value;
			},
		)
	);
	register_term_meta(
		NFA_STORY_TAX,
		NFA_TERM_META_VERSE,
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
		)
	);
	register_post_meta(
		'post',
		NFA_META_CHAPTER_NUM,
		array(
			'type'              => 'number',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $value ) {
				return (float) $value;
			},
			'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
add_action( 'init', 'nfa_story_register' );

/* ---------------------------------------------------------------------------------------------
 * Tree and reading order
 * ------------------------------------------------------------------------------------------- */

function nfa_story_term_order( $term ) {
	$meta = get_term_meta( $term->term_id, NFA_TERM_META_ORDER, true );
	if ( '' !== $meta && null !== $meta ) {
		return (int) $meta;
	}
	return preg_match( '/\d+/', $term->name, $m ) ? (int) $m[0] : 0;
}

function nfa_story_children( $term_id ) {
	$children = get_terms(
		array(
			'taxonomy'   => NFA_STORY_TAX,
			'parent'     => (int) $term_id,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $children ) ) {
		return array();
	}
	usort(
		$children,
		static function ( $a, $b ) {
			return nfa_story_term_order( $a ) <=> nfa_story_term_order( $b ) ?: strcmp( $a->name, $b->name );
		}
	);
	return $children;
}

function nfa_story_root( $term ) {
	$term = get_term( $term, NFA_STORY_TAX );
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	$ancestors = get_ancestors( $term->term_id, NFA_STORY_TAX, 'taxonomy' );
	return $ancestors ? get_term( end( $ancestors ), NFA_STORY_TAX ) : $term;
}

/** The deepest story term a chapter is filed under. */
function nfa_story_chapter_term( $post ) {
	$terms = get_the_terms( $post, NFA_STORY_TAX );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return null;
	}
	usort(
		$terms,
		static function ( $a, $b ) {
			return count( get_ancestors( $b->term_id, NFA_STORY_TAX, 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, NFA_STORY_TAX, 'taxonomy' ) );
		}
	);
	return $terms[0];
}

/** Published chapters filed directly under one term, by chapter number then date. */
function nfa_story_term_chapters( $term_id ) {
	$ids = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'date',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'nfa_internal'     => true,
			'tax_query'        => array(
				array(
					'taxonomy'         => NFA_STORY_TAX,
					'terms'            => (int) $term_id,
					'include_children' => false,
				),
			),
		)
	);
	usort(
		$ids,
		static function ( $a, $b ) {
			return (float) get_post_meta( $a, NFA_META_CHAPTER_NUM, true ) <=> (float) get_post_meta( $b, NFA_META_CHAPTER_NUM, true );
		}
	);
	return $ids;
}

/**
 * Nested tree of a term: [ 'term' => WP_Term, 'chapters' => [ids], 'children' => [ ...same... ] ].
 */
function nfa_story_tree( $term ) {
	$term = get_term( $term, NFA_STORY_TAX );
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	return array(
		'term'     => $term,
		'chapters' => nfa_story_term_chapters( $term->term_id ),
		'children' => array_values( array_filter( array_map( 'nfa_story_tree', nfa_story_children( $term->term_id ) ) ) ),
	);
}

/** All published chapter IDs of a story, in reading order (cached per story). */
function nfa_story_reading_order( $root ) {
	$key   = NFA_STORY_ORDER_CACHE . (int) $root->term_id;
	$order = get_transient( $key );
	if ( false !== $order ) {
		return $order;
	}
	$order = array();
	$walk  = static function ( $node ) use ( &$walk, &$order ) {
		foreach ( $node['chapters'] as $id ) {
			$order[] = (int) $id;
		}
		foreach ( $node['children'] as $child ) {
			$walk( $child );
		}
	};
	$tree = nfa_story_tree( $root );
	if ( $tree ) {
		$walk( $tree );
	}
	set_transient( $key, $order, DAY_IN_SECONDS );
	return $order;
}

function nfa_story_flush_order() {
	$roots = get_terms(
		array(
			'taxonomy'   => NFA_STORY_TAX,
			'parent'     => 0,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	foreach ( is_wp_error( $roots ) ? array() : $roots as $id ) {
		delete_transient( NFA_STORY_ORDER_CACHE . (int) $id );
	}
}
add_action( 'nfa_lore_flush_caches', 'nfa_story_flush_order' );
foreach ( array( 'created_' . NFA_STORY_TAX, 'edited_' . NFA_STORY_TAX, 'delete_' . NFA_STORY_TAX ) as $nfa_term_hook ) {
	add_action( $nfa_term_hook, 'nfa_story_flush_order' );
}
foreach ( array( 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ) as $nfa_term_meta_hook ) {
	add_action( $nfa_term_meta_hook, 'nfa_story_flush_order' );
}

/* ---------------------------------------------------------------------------------------------
 * Labels
 * ------------------------------------------------------------------------------------------- */

function nfa_story_chapter_label( $post_id ) {
	$num   = get_post_meta( $post_id, NFA_META_CHAPTER_NUM, true );
	$title = get_the_title( $post_id );
	if ( '' === (string) $num || 0.0 === (float) $num || false !== stripos( $title, 'chapter' ) ) {
		return $title;
	}
	/* translators: 1: chapter number, 2: chapter title */
	return sprintf( __( 'Chapter %1$s · %2$s', 'nfa-lore' ), rtrim( rtrim( number_format( (float) $num, 2, '.', '' ), '0' ), '.' ), $title );
}

/* ---------------------------------------------------------------------------------------------
 * The world (verse) a chapter belongs to: its own verse terms, else its story's verse, else the
 * site's only verse. Lets the glossary work on chapters without tagging each one.
 * ------------------------------------------------------------------------------------------- */

function nfa_story_post_verse_slugs( $post ) {
	$own = nfa_lore_post_verse_slugs( $post );
	if ( $own ) {
		return $own;
	}
	$term = nfa_story_chapter_term( $post );
	$root = $term ? nfa_story_root( $term ) : null;
	if ( $root ) {
		$verse = get_term( (int) get_term_meta( $root->term_id, NFA_TERM_META_VERSE, true ), NFA_VERSE_TAX );
		if ( $verse && ! is_wp_error( $verse ) ) {
			return array( $verse->slug );
		}
	}
	$all = get_terms(
		array(
			'taxonomy'   => NFA_VERSE_TAX,
			'hide_empty' => false,
			'fields'     => 'slugs',
		)
	);
	return ( ! is_wp_error( $all ) && 1 === count( $all ) ) ? $all : array();
}

/* ---------------------------------------------------------------------------------------------
 * Archives: a story/arc/tome page lists nothing by itself (the Contents block does the work).
 * ------------------------------------------------------------------------------------------- */

add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_tax( NFA_STORY_TAX ) ) {
			$query->set( 'posts_per_page', 1 );
		}
	}
);

/* ---------------------------------------------------------------------------------------------
 * Admin: chapter number box, term fields (order, world).
 * ------------------------------------------------------------------------------------------- */

add_action(
	'add_meta_boxes_post',
	static function () {
		add_meta_box( 'nfa-chapter', __( 'Chapter', 'nfa-lore' ), 'nfa_story_render_chapter_box', 'post', 'side', 'high' );
	}
);

function nfa_story_render_chapter_box( $post ) {
	wp_nonce_field( 'nfa_chapter', 'nfa_chapter_nonce' );
	$num = get_post_meta( $post->ID, NFA_META_CHAPTER_NUM, true );
	?>
	<p>
		<label for="nfa_chapter_number"><?php esc_html_e( 'Chapter number (reading order inside its tome)', 'nfa-lore' ); ?></label>
		<input type="number" step="0.1" min="0" class="widefat" id="nfa_chapter_number" name="nfa_chapter_number" value="<?php echo esc_attr( $num ); ?>" />
	</p>
	<p class="description"><?php esc_html_e( 'File the chapter under its tome in the Stories panel.', 'nfa-lore' ); ?></p>
	<?php
}

add_action(
	'save_post_post',
	static function ( $post_id ) {
		if ( ! isset( $_POST['nfa_chapter_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nfa_chapter_nonce'] ) ), 'nfa_chapter' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['nfa_chapter_number'] ) ? sanitize_text_field( wp_unslash( $_POST['nfa_chapter_number'] ) ) : '';
		update_post_meta( $post_id, NFA_META_CHAPTER_NUM, '' === $raw ? 0 : (float) $raw );
	}
);

function nfa_story_term_fields( $term = null ) {
	$order  = $term ? get_term_meta( $term->term_id, NFA_TERM_META_ORDER, true ) : '';
	$verse  = $term ? (int) get_term_meta( $term->term_id, NFA_TERM_META_VERSE, true ) : 0;
	$verses = get_terms(
		array(
			'taxonomy'   => NFA_VERSE_TAX,
			'hide_empty' => false,
		)
	);
	wp_nonce_field( 'nfa_story_term', 'nfa_story_term_nonce' );
	$wrap_open  = $term ? '<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>' : '<div class="form-field"><label for="%1$s">%2$s</label>';
	$wrap_close = $term ? '</td></tr>' : '</div>';

	printf( $wrap_open, 'nfa_order', esc_html__( 'Order', 'nfa-lore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="number" id="nfa_order" name="nfa_order" value="' . esc_attr( $order ) . '" />';
	echo '<p class="description">' . esc_html__( 'Position among its siblings (arc or tome number). Empty: the first number in the name.', 'nfa-lore' ) . '</p>';
	echo $wrap_close; // phpcs:ignore WordPress.Security.EscapeOutput

	printf( $wrap_open, 'nfa_verse', esc_html__( 'World (stories only)', 'nfa-lore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<select id="nfa_verse" name="nfa_verse"><option value="0">—</option>';
	foreach ( is_wp_error( $verses ) ? array() : $verses as $v ) {
		echo '<option value="' . esc_attr( $v->term_id ) . '"' . selected( $verse, $v->term_id, false ) . '>' . esc_html( $v->name ) . '</option>';
	}
	echo '</select><p class="description">' . esc_html__( 'Glossary tooltips in this story\'s chapters use this world\'s terms.', 'nfa-lore' ) . '</p>';
	echo $wrap_close; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( NFA_STORY_TAX . '_add_form_fields', 'nfa_story_term_fields' );
add_action( NFA_STORY_TAX . '_edit_form_fields', 'nfa_story_term_fields' );

function nfa_story_save_term_fields( $term_id ) {
	if ( ! isset( $_POST['nfa_story_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nfa_story_term_nonce'] ) ), 'nfa_story_term' ) || ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$order = isset( $_POST['nfa_order'] ) ? sanitize_text_field( wp_unslash( $_POST['nfa_order'] ) ) : '';
	if ( '' === $order ) {
		delete_term_meta( $term_id, NFA_TERM_META_ORDER );
	} else {
		update_term_meta( $term_id, NFA_TERM_META_ORDER, (int) $order );
	}
	update_term_meta( $term_id, NFA_TERM_META_VERSE, isset( $_POST['nfa_verse'] ) ? absint( $_POST['nfa_verse'] ) : 0 );
}
add_action( 'created_' . NFA_STORY_TAX, 'nfa_story_save_term_fields' );
add_action( 'edited_' . NFA_STORY_TAX, 'nfa_story_save_term_fields' );

/* ---------------------------------------------------------------------------------------------
 * Spoilers: an arc or tome with no published chapter does not exist for readers (its name alone
 * could spoil). Its page redirects to the story; the REST API hides it.
 * ------------------------------------------------------------------------------------------- */

function nfa_story_term_is_public( $term ) {
	$tree = nfa_story_tree( $term );
	return $tree && nfa_story_node_has_chapters( $tree );
}

add_action(
	'template_redirect',
	static function () {
		if ( ! is_tax( NFA_STORY_TAX ) || current_user_can( 'edit_posts' ) ) {
			return;
		}
		$term = get_queried_object();
		if ( nfa_story_term_is_public( $term ) ) {
			return;
		}
		$root   = nfa_story_root( $term );
		$target = ( $root && $root->term_id !== $term->term_id && nfa_story_term_is_public( $root ) ) ? get_term_link( $root ) : home_url( '/' );
		wp_safe_redirect( is_wp_error( $target ) ? home_url( '/' ) : $target, 302 );
		exit;
	}
);

add_filter(
	'rest_' . NFA_STORY_TAX . '_query',
	static function ( $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			$args['hide_empty'] = true;
		}
		return $args;
	}
);

add_filter(
	'rest_prepare_' . NFA_STORY_TAX,
	static function ( $response, $term ) {
		if ( current_user_can( 'edit_posts' ) || nfa_story_term_is_public( $term ) ) {
			return $response;
		}
		return new WP_Error( 'rest_term_invalid', __( 'Term does not exist.', 'nfa-lore' ), array( 'status' => 404 ) );
	},
	10,
	2
);
