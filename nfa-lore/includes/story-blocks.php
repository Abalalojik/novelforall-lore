<?php
/**
 * Story blocks: Chapter Navigation, Story Contents, Story Breadcrumb. All server-rendered.
 * Only published chapters ever appear; an arc or tome with no published chapter stays hidden, so
 * future arc and tome names do not spoil anything.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		wp_register_script(
			'nfa-story-blocks',
			NFA_LORE_URL . 'blocks/story-blocks.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
			NFA_LORE_VERSION,
			true
		);
		register_block_type(
			'nfa-lore/chapter-nav',
			array(
				'api_version'     => 3,
				'title'           => __( 'Chapter Navigation', 'nfa-lore' ),
				'category'        => 'theme',
				'editor_script'   => 'nfa-story-blocks',
				'uses_context'    => array( 'postId' ),
				'render_callback' => 'nfa_story_render_chapter_nav',
			)
		);
		register_block_type(
			'nfa-lore/story-breadcrumb',
			array(
				'api_version'     => 3,
				'title'           => __( 'Story Breadcrumb', 'nfa-lore' ),
				'category'        => 'theme',
				'editor_script'   => 'nfa-story-blocks',
				'uses_context'    => array( 'postId' ),
				'render_callback' => 'nfa_story_render_breadcrumb',
			)
		);
		register_block_type(
			'nfa-lore/story-contents',
			array(
				'api_version'     => 3,
				'title'           => __( 'Story Contents', 'nfa-lore' ),
				'category'        => 'theme',
				'editor_script'   => 'nfa-story-blocks',
				'attributes'      => array(
					'storyId'          => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'showDescriptions' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'render_callback' => 'nfa_story_render_contents',
			)
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function () {
		$roots = get_terms(
			array(
				'taxonomy'   => NFA_STORY_TAX,
				'parent'     => 0,
				'hide_empty' => false,
			)
		);
		$data  = array();
		foreach ( is_wp_error( $roots ) ? array() : $roots as $r ) {
			$data[] = array(
				'id'    => $r->term_id,
				'label' => $r->name,
			);
		}
		wp_add_inline_script( 'nfa-story-blocks', 'window.nfaStories = ' . wp_json_encode( $data ) . ';', 'before' );
	}
);

function nfa_story_context_post_id( $block ) {
	if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}
	return (int) get_the_ID();
}

function nfa_story_render_chapter_nav( $attributes, $content, $block ) {
	$post_id = nfa_story_context_post_id( $block );
	$term    = $post_id ? nfa_story_chapter_term( $post_id ) : null;
	$root    = $term ? nfa_story_root( $term ) : null;
	if ( ! $root ) {
		return '';
	}
	$order = nfa_story_reading_order( $root );
	$index = array_search( $post_id, $order, true );
	$prev  = ( false !== $index && $index > 0 ) ? $order[ $index - 1 ] : 0;
	$next  = ( false !== $index && $index < count( $order ) - 1 ) ? $order[ $index + 1 ] : 0;

	$link = static function ( $id, $class, $before, $after ) {
		if ( ! $id ) {
			return '<span class="' . esc_attr( $class ) . ' is-empty"></span>';
		}
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( get_permalink( $id ) ) . '">' . $before . esc_html( nfa_story_chapter_label( $id ) ) . $after . '</a>';
	};
	$contents = get_term_link( $root );

	return '<nav ' . get_block_wrapper_attributes( array( 'class' => 'nfa-chapter-nav' ) ) . ' aria-label="' . esc_attr__( 'Chapter navigation', 'nfa-lore' ) . '">'
		. $link( $prev, 'nfa-chapter-nav__prev', '<span aria-hidden="true">← </span>', '' )
		. ( is_wp_error( $contents ) ? '' : '<a class="nfa-chapter-nav__contents" href="' . esc_url( $contents ) . '">' . esc_html__( 'Contents', 'nfa-lore' ) . '</a>' )
		. $link( $next, 'nfa-chapter-nav__next', '', '<span aria-hidden="true"> →</span>' )
		. '</nav>';
}

function nfa_story_render_breadcrumb( $attributes, $content, $block ) {
	$post_id = nfa_story_context_post_id( $block );
	$term    = $post_id ? nfa_story_chapter_term( $post_id ) : null;
	if ( ! $term ) {
		return '';
	}
	$chain = array_reverse( get_ancestors( $term->term_id, NFA_STORY_TAX, 'taxonomy' ) );
	$chain[] = $term->term_id;
	$parts   = array();
	foreach ( $chain as $id ) {
		$t   = get_term( $id, NFA_STORY_TAX );
		$url = get_term_link( $t );
		$parts[] = is_wp_error( $url ) ? esc_html( $t->name ) : '<a href="' . esc_url( $url ) . '">' . esc_html( $t->name ) . '</a>';
	}
	return '<nav ' . get_block_wrapper_attributes( array( 'class' => 'nfa-story-breadcrumb' ) ) . ' aria-label="' . esc_attr__( 'Breadcrumb', 'nfa-lore' ) . '">'
		. implode( '<span class="nfa-story-breadcrumb__sep" aria-hidden="true"> › </span>', $parts )
		. '</nav>';
}

function nfa_story_render_contents( $attributes ) {
	$node_term = null;
	if ( ! empty( $attributes['storyId'] ) ) {
		$node_term = get_term( (int) $attributes['storyId'], NFA_STORY_TAX );
	} elseif ( is_tax( NFA_STORY_TAX ) ) {
		$node_term = get_queried_object();
	} elseif ( is_singular( 'post' ) ) {
		$t         = nfa_story_chapter_term( get_the_ID() );
		$node_term = $t ? nfa_story_root( $t ) : null;
	}
	if ( ! $node_term || is_wp_error( $node_term ) ) {
		return '';
	}
	$tree = nfa_story_tree( $node_term );
	$html = $tree ? nfa_story_render_node( $tree, 0, ! empty( $attributes['showDescriptions'] ) ) : '';
	if ( '' === $html ) {
		$html = '<p class="nfa-story-contents__empty">' . esc_html__( 'The first chapters are coming soon.', 'nfa-lore' ) . '</p>';
	}
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'nfa-story-contents' ) ) . '>' . $html . '</div>';
}

function nfa_story_node_has_chapters( $node ) {
	if ( $node['chapters'] ) {
		return true;
	}
	foreach ( $node['children'] as $child ) {
		if ( nfa_story_node_has_chapters( $child ) ) {
			return true;
		}
	}
	return false;
}

function nfa_story_render_node( $node, $depth, $descriptions ) {
	if ( ! nfa_story_node_has_chapters( $node ) ) {
		return '';
	}
	$html = '';
	if ( $depth > 0 ) {
		$level = min( 6, 1 + $depth );
		$url   = get_term_link( $node['term'] );
		$name  = esc_html( $node['term']->name );
		$html .= '<h' . $level . ' class="nfa-story-contents__heading nfa-story-contents__heading--depth-' . $depth . '">'
			. ( is_wp_error( $url ) ? $name : '<a href="' . esc_url( $url ) . '">' . $name . '</a>' )
			. '</h' . $level . '>';
		if ( $descriptions && 1 === $depth && '' !== trim( $node['term']->description ) ) {
			$html .= '<div class="nfa-story-contents__description">' . wpautop( wp_kses_post( $node['term']->description ) ) . '</div>';
		}
	}
	if ( $node['chapters'] ) {
		$html .= '<ol class="nfa-story-contents__chapters">';
		foreach ( $node['chapters'] as $id ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( nfa_story_chapter_label( $id ) ) . '</a></li>';
		}
		$html .= '</ol>';
	}
	foreach ( $node['children'] as $child ) {
		$html .= nfa_story_render_node( $child, $depth + 1, $descriptions );
	}
	return '<section class="nfa-story-contents__node">' . $html . '</section>';
}
