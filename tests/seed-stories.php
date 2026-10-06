<?php
/**
 * Story-structure test data: Excellion Chronicles › Arc 0 — The Intern › Tome 1 / Tome 2, plus an empty
 * Arc 1 that must stay hidden. Chapters are created out of date order to check reading order.
 */

require_once '/wordpress/wp-load.php';

$verse = get_term_by( 'slug', 'the-intern', 'nfa_verse' );

$story = wp_insert_term( 'Excellion Chronicles', 'nfa_story', array( 'slug' => 'excellion-chronicles', 'description' => 'The whole saga.' ) );
update_term_meta( $story['term_id'], 'nfa_verse', $verse->term_id );
$arc0  = wp_insert_term( 'Arc 0 — The Intern', 'nfa_story', array( 'parent' => $story['term_id'], 'slug' => 'the-intern', 'description' => 'Lilith Nisswa was born human.' ) );
$arc1  = wp_insert_term( 'Arc 1 — Secret Future Arc', 'nfa_story', array( 'parent' => $story['term_id'], 'slug' => 'arc-1' ) );
$tome2 = wp_insert_term( 'Tome 2', 'nfa_story', array( 'parent' => $arc0['term_id'], 'slug' => 'tome-2' ) );
$tome1 = wp_insert_term( 'Tome 1', 'nfa_story', array( 'parent' => $arc0['term_id'], 'slug' => 'tome-1' ) );

function nfa_test_chapter( $title, $tome, $num, $status, $days_ago, $content = 'Text.' ) {
	$id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => $status,
		'post_title'   => $title,
		'post_date'    => gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS ),
		'post_content' => '<!-- wp:paragraph --><p>' . $content . '</p><!-- /wp:paragraph -->',
	) );
	wp_set_object_terms( $id, array( (int) $tome ), 'nfa_story' );
	update_post_meta( $id, '_nfa_chapter_number', $num );
	return $id;
}

// Created in scrambled date order on purpose.
$c3 = nfa_test_chapter( 'The IADB', $tome1['term_id'], 3, 'publish', 30 );
$c1 = nfa_test_chapter( 'Awakening', $tome1['term_id'], 1, 'publish', 10, 'Her seren was kind. The Double Crown ruled.' );
$c2 = nfa_test_chapter( 'Nerin High School', $tome1['term_id'], 2, 'publish', 20 );
$c4 = nfa_test_chapter( 'Ruiz', $tome2['term_id'], 1, 'publish', 5 );
$c5 = nfa_test_chapter( 'Scheduled', $tome2['term_id'], 2, 'future', -10 );
$c5 = nfa_test_chapter( 'Hidden arc chapter', $arc1['term_id'], 1, 'draft', 1 );

echo "stories seeded: c1=$c1 c2=$c2 c3=$c3 c4=$c4\n";
