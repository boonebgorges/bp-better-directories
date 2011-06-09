<?php

class BPBD {
	var $get_params = array();
	var $filterable_fields = array();
	
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
			add_filter( 'bp_core_get_paged_users_sql', array( $this, 'users_sql_filter' ), 10, 2 );
			add_filter( 'bp_core_get_total_users_sql', array( $this, 'users_sql_filter' ), 10, 2 );
			
			// Add the filter UI
			add_action( 'bp_before_members_loop', array( $this, 'filter_ui' ) );
		}
	}
	
	function setup_get_params() {
		$filterable_fields = get_blog_option( BP_ROOT_BLOG, 'bpdb_fields' );
		
		// Set so it can be used object-wide
		$this->filterable_fields = $filterable_fields;
		
		if ( is_array( $filterable_fields ) ) {
			$filterable_keys = array_keys( $filterable_fields );
		}
		
		foreach ( (array)$filterable_keys as $filterable_key ) {
			if ( !empty( $_GET[$filterable_key] ) ) {
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
	
	function users_sql_filter( $s, $sql ) {
		global $bp, $wpdb;
		
		$bpbd_select = array();
		$bpbd_from = array();
		$bpbd_where = array();
		$counter = 1;
		
		// Build the additional queries
		foreach( $this->get_params as $field_id => $field ) {
			$table_shortname = 'bpbd' . $counter;
						
			// Since we're already doing the join, let's bring the extra content into
			// the template. This'll be unset in the total_users filter
			$bpbd_select[] = $wpdb->prepare( ", {$table_shortname}.value as {$field['slug']}" );
			
			$bpbd_from[] = $wpdb->prepare( "INNER JOIN {$bp->profile->table_name_data} {$table_shortname} ON ({$table_shortname}.user_id = u.ID)" );
			
			// todo: LIKE vs IN vs = (maybe with NOTs as well?)
			$bpbd_where[] = $wpdb->prepare( "AND {$table_shortname}.field_id = %s AND {$table_shortname}.value = %s", $field['id'], $field['value'] );
			
			$counter++;
		}

		if ( !empty( $bpbd_from ) && !empty( $bpbd_where ) ) {
			// The total_sql query won't have this index
			if ( isset( $sql['select_main'] ) )
				$sql['select_main'] .= ' ' . implode( ' ', $bpbd_select );			
			
			$sql['from'] .= ' ' . implode( ' ', $bpbd_from );
			$sql['where'] .= ' ' . implode( ' ', $bpbd_where );
					
			$s = join( ' ', (array)$sql );
		}
		
		return $s;
	}	
	
	function filter_ui() {
		if ( empty( $this->filterable_fields ) ) {
			// Nothing to see here.
			return;
		}
		
		?>
		
		<form id="bpbd-filter-form" method="get" action="http://boone.cool/yolanda/members-2/">
		
		<div id="bpbd-filters" style="color:#000;height: 200px">
			<ul>
			<?php foreach ( $this->filterable_fields as $slug => $field ) : ?>
				<li>
					<?php $this->render_field( $field ) ?>
				</li>
			<?php endforeach ?>
			</ul>
		
			<input type="submit" class="button" value="<?php _e( 'Submit', 'bpdb' ) ?>" />
		</div>
		
		</form>
		<?php
	}
	
	function render_field( $field ) {
		//var_dump( $field );
		
		// Display the field based on type
		// In the future you'll be able to override this
		switch ( $field['type'] ) {
			case 'textbox' :
				$this->render_field_textbox( $field );
				break;
			case 'selectbox' :
				$this->render_field_selectbox( $field );
				break;
		}
	}
	
	function render_field_textbox( $field ) {
		?>
		
		<label for="<?php echo esc_attr( $field['slug'] ) ?>"><?php echo esc_html( $field['name'] ) ?></label> <input id="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" type="text" name="<?php echo esc_attr( $field['slug'] ) ?>" />
		
		<?php
	}
	
	function render_field_selectbox( $new_field ) {
		global $field;
		
		// Cheating, so that I can use the old profile filters
		$old_field = $field;
		$field = new BP_XProfile_Field( $new_field['id'] );
		
		?>
		
		<label for="<?php echo esc_attr( $new_field['slug'] ) ?>"><?php echo esc_html( $new_field['slug'] ) ?></label>
		<select name="<?php echo esc_attr( $new_field['slug'] ) ?>">
			<?php bp_the_profile_field_options( 'selectbox' ) ?>
		</select>
		
		<?php
		
		// Put right what once went wrong
		$field = $old_field;
	}
}

?>