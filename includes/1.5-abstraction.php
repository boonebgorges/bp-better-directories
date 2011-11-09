<?php

/**
 * BP 1.5+ functions that allow the plugin to be run in earlier environments
 */

if ( !function_exists( 'bp_is_members_component' ) ) :
	function bp_is_members_component() {
		global $bp;
		
		return $bp->members->slug == $bp->current_component;
	}
endif;

?>