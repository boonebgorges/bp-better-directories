<?php
/*
Plugin Name: BP Better Directories
Plugin URI: http://github.com/boonebgorges/buddypress-docs
Description: Adds collaborative Docs to BuddyPress
Version: 1.0
Author: Boone B Gorges
Author URI: http://boonebgorges.com
Licence: GPLv3
Network: true
*/

define( 'BPBD_VERSION', '1.0' );

/**
 * Loads BP Better Directories files only if BuddyPress is present
 *
 * @package BP Better Directories
 * @since 1.0
 */
function bpbd_init() {
	global $bp_docs, $wpdb;
	
	if ( !function_exists( 'bp_is_active' ) || !bp_is_active( 'xprofile' ) )
		return;
	
	require( dirname( __FILE__ ) . '/bpbd.php' );
	$bpbd = new BPBD;

	if ( is_admin() ) {
		require( dirname( __FILE__ ) . '/includes/admin.php' );
		$bpbd_admin = new BPBD_Admin;
	}
}
add_action( 'bp_include', 'bpbd_init' );
?>
