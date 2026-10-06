<?php
// Local test helper only: renders the story blocks and filtered content for chapter ?id= as a logged-out reader.
require_once '/wordpress/wp-load.php';
wp_set_current_user( 0 );
header( 'Content-Type: text/plain; charset=utf-8' );
$GLOBALS['post'] = get_post( (int) $_GET['id'] );
setup_postdata( $GLOBALS['post'] );
echo "BREADCRUMB: ", do_blocks( '<!-- wp:nfa-lore/story-breadcrumb /-->' ), "\n";
echo "NAV: ", do_blocks( '<!-- wp:nfa-lore/chapter-nav /-->' ), "\n";
echo "CONTENT: ", apply_filters( 'the_content', $GLOBALS['post']->post_content ), "\n";
