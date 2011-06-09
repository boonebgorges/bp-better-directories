<?php

class BPBD {
	var $get_params = array();
	
	/**
	 * PHP 4 constructor
	 *
	 * @package BP Better Directories
	 * @since 1.0
	 */
	function bpbd() {
		$this->__construct();
	}

	/**
	 * PHP 5 constructor
	 *
	 * @package BP Better Directories
	 * @since 1.0
	 */	
	function __construct() {
		$this->setup_get_params();
		
		add_action( 'wp', array( $this, 'setup' ), 1 );
	}
	
	function setup() {
		global $bp;
		
		// Temporary backpat for 1.2
		if ( function_exists( 'bp_is_current_component' ) ) {
			$is_members = bp_is_current_component( 'members' );
		} else {
			$is_members = $bp->members->slug == bp_current_component();
		}
		
		if ( $is_members && !bp_is_single_item() ) {			
			// Add the sql filters
			add_filter( 'bp_core_get_paged_users_sql', array( $this, 'paged_users_sql_filter' ), 10, 2 );
			add_filter( 'bp_core_get_total_users_sql', array( $this, 'total_users_sql_filter' ), 10, 2 );
		}
	}
	
	function setup_get_params() {
		$filterable_fields = get_blog_option( BP_ROOT_BLOG, 'bpdb_fields' );
		
		if ( is_array( $filterable_fields ) ) {
			$filterable_keys = array_keys( $filterable_fields );
		}
		
		foreach ( (array)$filterable_keys as $filterable_key ) {
			if ( isset( $_GET[$filterable_key] ) ) {
				// Get the field id for keying the array
				$field_id = $filterable_fields[$filterable_key]['id'];
				
				// Put the field data into the get_params property array
				$this->get_params[$field_id] = $filterable_fields[$filterable_key];
				
				// Add the filtered value from $_GET
				$this->get_params[$field_id]['value'] = urldecode( $_GET[$filterable_key] );
			}
		}
		
		// Todo: sort order?
	}
	
	function paged_users_sql_filter( $s, $sql ) {
		global $bp, $wpdb;
		
		$bpbd_from = array();
		$bpbd_where = array();
		$counter = 1;
		
		foreach( $this->get_params as $field_id => $field ) {
			$table_shortname = 'bpbd' . $counter;
			
			$bpbd_from[] = $wpdb->prepare( "INNER JOIN {$bp->profile->table_name_data} {$table_shortname} ON ({$table_shortname}.user_id = u.ID)" );
			
			// todo: like vs in vs equals!
			$bpbd_where[] = $wpdb->prepare( "AND {$table_shortname}.field_id = %s AND {$table_shortname}.value = %s", $field['id'], $field['value'] );
			
			$counter++;
		}
		
		if ( !empty( $bpbd_from ) && !empty( $bpbd_where ) ) {
			$sql['from'] .= ' ' . implode( ' ', $bpbd_from );
			$sql['where'] .= ' ' . implode( ' ', $bpbd_where );
			
			$s = join( ' ', (array)$sql );
		}
		
		return $s;
	}
	
	function total_users_sql_filter( $s, $sql ) {
		return $s;
	}
	
	
}

?>