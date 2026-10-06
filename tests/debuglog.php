<?php
// Local test helper only: prints the Playground debug log.
header( 'Content-Type: text/plain' );
$f = '/wordpress/wp-content/debug.log';
echo is_readable( $f ) ? implode( '', array_slice( file( $f ), -40 ) ) : 'no log';
