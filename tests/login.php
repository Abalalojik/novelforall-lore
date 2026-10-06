<?php
// Local Playground test helper only: logs the browser in as the test admin, then goes to ?to=.
require_once '/wordpress/wp-load.php';
wp_set_auth_cookie( 1, false );
wp_safe_redirect( admin_url( isset( $_GET['to'] ) ? ltrim( wp_unslash( $_GET['to'] ), '/' ) : '' ) );
exit;
