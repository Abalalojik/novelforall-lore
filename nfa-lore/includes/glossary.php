<?php
/**
 * Glossary tooltips: in chapters and lore entries, the first occurrence of each glossary term of the
 * same verse gets a tooltip with the term's definition (its entry's excerpt). Tooltips are not links.
 *
 * Skipped: headings, links, code, buttons, and blockquotes (status windows are written as quotes).
 */

defined( 'ABSPATH' ) || exit;

const NFA_GLOSSARY_CACHE = 'nfa_lore_glossary';

/**
 * Unlocked glossary terms, per verse: [ verse => [ [ 'id', 'names' => [...], 'definition' ], ... ] ].
 */
function nfa_lore_glossary_terms() {
	$terms = get_transient( NFA_GLOSSARY_CACHE );
	if ( false !== $terms ) {
		return $terms;
	}
	$terms = array();
	$ids   = get_posts(
		array(
			'post_type'        => NFA_LORE_TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'meta_key'         => NFA_META_GLOSSARY,
			'meta_value'       => '1',
			'suppress_filters' => true,
			'nfa_internal'     => true,
		)
	);
	foreach ( $ids as $id ) {
		if ( ! nfa_lore_entry_is_unlocked( $id ) ) {
			continue;
		}
		$definition = trim( wp_strip_all_tags( get_post_field( 'post_excerpt', $id ) ) );
		if ( '' === $definition ) {
			// Fallback without spoiler blocks: excerpt_remove_blocks() keeps plain text blocks only.
			$definition = wp_trim_words( wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( get_post_field( 'post_content', $id ) ) ) ), 30 );
		}
		if ( '' === $definition ) {
			continue;
		}
		$names = array_merge( array( get_the_title( $id ) ), nfa_lore_entry_aliases( $id ) );
		$names = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'wp_strip_all_tags', $names ) ), 'strlen' ) ) );
		$terms[ nfa_lore_entry_verse_slug( $id ) ][] = array(
			'id'         => $id,
			'names'      => $names,
			'definition' => html_entity_decode( $definition, ENT_QUOTES, 'UTF-8' ),
		);
	}
	set_transient( NFA_GLOSSARY_CACHE, $terms, DAY_IN_SECONDS );
	return $terms;
}
add_action(
	'nfa_lore_flush_caches',
	static function () {
		delete_transient( NFA_GLOSSARY_CACHE );
	}
);

/**
 * Terms that apply to the current post: those of its verses, minus the post itself.
 * Longest names first, so "Double Crown" wins over "Crown".
 */
function nfa_lore_terms_for_post( $post ) {
	$verses = NFA_LORE_TYPE === $post->post_type ? array( nfa_lore_entry_verse_slug( $post ) ) : nfa_lore_post_verse_slugs( $post );
	if ( ! $verses ) {
		return array();
	}
	$all   = nfa_lore_glossary_terms();
	$found = array();
	foreach ( $verses as $verse ) {
		foreach ( isset( $all[ $verse ] ) ? $all[ $verse ] : array() as $term ) {
			if ( (int) $term['id'] === (int) $post->ID ) {
				continue;
			}
			foreach ( $term['names'] as $name ) {
				$found[] = array(
					'id'         => $term['id'],
					'name'       => $name,
					'definition' => $term['definition'],
				);
			}
		}
	}
	usort(
		$found,
		static function ( $a, $b ) {
			return mb_strlen( $b['name'], 'UTF-8' ) - mb_strlen( $a['name'], 'UTF-8' );
		}
	);
	return $found;
}

function nfa_lore_apply_glossary( $content ) {
	if ( is_admin() || is_feed() || '' === trim( $content ) || ! class_exists( 'DOMDocument' ) ) {
		return $content;
	}
	$post = get_post();
	// Excerpts are built from the_content: tooltip text must not leak into them.
	if ( ! $post || ! in_array( $post->post_type, array( 'post', NFA_LORE_TYPE ), true ) || doing_filter( 'get_the_excerpt' ) ) {
		return $content;
	}
	$terms = array_values(
		array_filter(
			nfa_lore_terms_for_post( $post ),
			static function ( $term ) use ( $content ) {
				return false !== mb_stripos( $content, $term['name'], 0, 'UTF-8' );
			}
		)
	);
	if ( ! $terms ) {
		return $content;
	}

	$dom      = new DOMDocument( '1.0', 'UTF-8' );
	$previous = libxml_use_internal_errors( true );
	$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8"><div id="nfa-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$root = $loaded ? $dom->getElementById( 'nfa-root' ) : null;
	if ( ! $root ) {
		return $content;
	}

	$done    = array();
	$skipped = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'kbd', 'script', 'style', 'button', 'textarea', 'blockquote', 'figcaption' );
	$xpath   = new DOMXPath( $dom );
	foreach ( $xpath->query( './/text()', $root ) as $text ) {
		if ( count( $done ) === count( array_unique( array_column( $terms, 'id' ) ) ) ) {
			break;
		}
		for ( $node = $text->parentNode; $node && $node !== $root; $node = $node->parentNode ) {
			if ( in_array( strtolower( $node->nodeName ), $skipped, true ) || ( $node instanceof DOMElement && false !== strpos( ' ' . $node->getAttribute( 'class' ) . ' ', ' nfa-term ' ) ) ) {
				continue 2;
			}
		}
		nfa_lore_mark_text_node( $dom, $text, $terms, $done );
	}

	$html = '';
	foreach ( $root->childNodes as $child ) {
		$html .= $dom->saveHTML( $child );
	}
	return $html;
}
add_filter( 'the_content', 'nfa_lore_apply_glossary', 25 );

/**
 * Wrap the first not-yet-used term found in a text node, then continue in the rest of the node.
 */
function nfa_lore_mark_text_node( DOMDocument $dom, DOMText $text, array $terms, array &$done ) {
	while ( $text ) {
		$value = $text->nodeValue;
		$best  = null;
		foreach ( $terms as $term ) {
			if ( isset( $done[ $term['id'] ] ) ) {
				continue;
			}
			$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $term['name'], '/' ) . '(?![\p{L}\p{N}])/iu';
			if ( preg_match( $pattern, $value, $m, PREG_OFFSET_CAPTURE ) && ( null === $best || $m[0][1] < $best['offset'] ) ) {
				$best = array(
					'offset' => $m[0][1],
					'match'  => $m[0][0],
					'term'   => $term,
				);
			}
		}
		if ( null === $best ) {
			return;
		}
		$done[ $best['term']['id'] ] = true;

		// Byte offsets from preg → split by bytes, then rebuild nodes.
		$before = substr( $value, 0, $best['offset'] );
		$after  = substr( $value, $best['offset'] + strlen( $best['match'] ) );

		$span = $dom->createElement( 'span' );
		$span->setAttribute( 'class', 'nfa-term' );
		$span->setAttribute( 'tabindex', '0' );
		$span->appendChild( $dom->createTextNode( $best['match'] ) );
		$tip = $dom->createElement( 'span' );
		$tip->setAttribute( 'class', 'nfa-term__tip' );
		$tip->setAttribute( 'role', 'tooltip' );
		$tip->appendChild( $dom->createTextNode( $best['term']['definition'] ) );
		$span->appendChild( $tip );

		$parent = $text->parentNode;
		if ( '' !== $before ) {
			$parent->insertBefore( $dom->createTextNode( $before ), $text );
		}
		$parent->insertBefore( $span, $text );
		if ( '' !== $after ) {
			$rest = $dom->createTextNode( $after );
			$parent->insertBefore( $rest, $text );
		}
		$parent->removeChild( $text );
		$text = '' !== $after ? $rest : null;
	}
}
