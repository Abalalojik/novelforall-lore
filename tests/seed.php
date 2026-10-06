<?php
/**
 * Test data for a local WordPress Playground: one verse, a published and a scheduled chapter,
 * glossary entries, a locked entry and a spoiler block. Run once after the plugin is active.
 */

require_once '/wordpress/wp-load.php';

$verse = wp_insert_term( 'The Intern', 'nfa_verse', array( 'slug' => 'the-intern' ) );
$verse = is_wp_error( $verse ) ? get_term_by( 'slug', 'the-intern', 'nfa_verse' )->term_id : $verse['term_id'];

$ch1 = wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_title'   => 'Chapter 1',
	'post_content' => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">The seren</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Her father had a seren once. Another seren came later. Serens are common. What are ġē doing here?</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>Status: seren none</p></blockquote>\n<!-- /wp:quote -->\n\n<!-- wp:paragraph -->\n<p>The Double Crown ruled. A secret was kept. Tom &amp; Jerry &lt;3.</p>\n<!-- /wp:paragraph -->",
) );
wp_set_object_terms( $ch1, array( $verse ), 'nfa_verse' );

$ch2 = wp_insert_post( array(
	'post_type'    => 'post',
	'post_status'  => 'future',
	'post_date'    => gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
	'post_title'   => 'Chapter 2',
	'post_content' => '<!-- wp:paragraph --><p>Later.</p><!-- /wp:paragraph -->',
) );
wp_set_object_terms( $ch2, array( $verse ), 'nfa_verse' );

function nfa_test_entry( $title, $content, $excerpt, $verse, $glossary, $aliases, $from ) {
	$id = wp_insert_post( array(
		'post_type'    => 'nfa_lore',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
	) );
	wp_set_object_terms( $id, array( $verse ), 'nfa_verse' );
	update_post_meta( $id, '_nfa_glossary', $glossary );
	update_post_meta( $id, '_nfa_aliases', $aliases );
	update_post_meta( $id, '_nfa_visible_from', $from );
	return $id;
}

nfa_test_entry( 'Seren', '<!-- wp:paragraph --><p>The secondary spouse of a consort or concubine.</p><!-- /wp:paragraph -->', 'The secondary spouse of a consort or concubine.', $verse, true, 'serens', 0 );
nfa_test_entry( 'Double Crown', '<!-- wp:paragraph --><p>The joint rule of the Emperor and the Supreme Priest.</p><!-- /wp:paragraph -->', 'The joint rule of the Emperor and the Supreme Priest.', $verse, true, '', 0 );
nfa_test_entry( 'Secret', '<!-- wp:paragraph --><p>Something revealed in chapter 2.</p><!-- /wp:paragraph -->', 'SPOILER definition that must never show.', $verse, true, '', $ch2 );
nfa_test_entry(
	'Ancient Speech',
	"<!-- wp:paragraph -->\n<p>Ancients keep old pronouns. See [[Seren]], [[Seren|a seren]], [[Secret]], [[Nobody Here]] and [[Ancient Speech#Pronouns|the table]].</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Pronouns</h2>\n<!-- /wp:heading -->\n\n<!-- wp:nfa-lore/spoiler {\"chapter\":$ch2} -->\n<!-- wp:paragraph -->\n<p>SPOILER paragraph that must stay hidden.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:nfa-lore/spoiler -->\n\n<!-- wp:nfa-lore/spoiler {\"chapter\":$ch1} -->\n<!-- wp:paragraph -->\n<p>Unlocked paragraph visible to all.</p>\n<!-- /wp:paragraph -->\n<!-- /wp:nfa-lore/spoiler -->",
	'How ancients speak.',
	$verse,
	false,
	'',
	0
);

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
echo "seeded: ch1=$ch1 ch2=$ch2\n";
