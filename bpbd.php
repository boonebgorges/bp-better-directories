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
		
		add_action( 'wp_print_styles', array( $this, 'enqueue_styles' ) );
		
		define( 'BPBD_INSTALL_URL', plugins_url() . '/bp-better-directories/' );
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
				if ( is_array( $_GET[$filterable_key] ) ) {
					$values = array();
					foreach( $_GET[$filterable_key] as $key => $value ) {
						$values[$key] = urldecode( $value );
					}
				} else {
					$values = urldecode( $_GET[$filterable_key] );
				}
				
				$this->get_params[$field_id]['value'] = $values;
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
		
		//var_dump( $this->get_params ); die();
		
		// Build the additional queries
		foreach( $this->get_params as $field_id => $field ) {
			$table_shortname = 'bpbd' . $counter;
		
			// Since we're already doing the join, let's bring the extra content into
			// the template. This'll be unset in the total_users filter
			$bpbd_select[] = $wpdb->prepare( ", {$table_shortname}.value as {$field['slug']}" );
			
			$bpbd_from[] = $wpdb->prepare( "INNER JOIN {$bp->profile->table_name_data} {$table_shortname} ON ({$table_shortname}.user_id = u.ID)" );
			
			// Figure out the right operators and values for the WHERE clause
			if ( 'textbox' == $field['type'] ) {
				// 'textbox' always gets LIKE
				$op = "LIKE";
				$value = $wpdb->prepare( "%s", '%%' . like_escape( $field['value'] ) . '%%' );
				$where = $op . ' ' . $value;
				
			} else if ( 'multiselectbox' == $field['type'] || 'checkbox' == $field['type'] ) {
				// Multiselect and checkbox values may be stored as arrays, so we
				// have to do multiple LIKEs. Hack alert
				$clauses = array();
				foreach( $field['value'] as $val ) {
					$clauses[] = 'LIKE "%%' . $val . '%%"';
				}
				$where = implode( ' OR ', $clauses );
			} else {
				// Everything else is an exact match
				$op = '=';
				$value = $wpdb->prepare( "%s", $field['value'] );
				$where = $op . ' ' . $value;
			}
			
			$bpbd_where[] = $wpdb->prepare( "AND {$table_shortname}.field_id = %d AND {$table_shortname}.value {$where}", $field['id'] );
			
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
	
		<form id="bpbd-filter-form" method="get" action="<?php bp_root_domain() ?>/<?php bp_members_root_slug() ?>">
		
		<div id="bpbd-filters">
			<h4><?php _e( 'Member Filter', 'bpbd' ) ?></h4>
		
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
		?>
		
		<label for="<?php echo esc_attr( $field['slug'] ) ?>"><?php echo esc_html( $field['slug'] ) ?></label>
		
		<?php
		
		$field_data = new BP_XProfile_Field( $field['id'] );

		$options = $field_data->get_children();

		// Get the current value for this item, if any, out of the $_GET params
		$value = isset( $this->get_params[$field['id']] ) ? $this->get_params[$field['id']]['value'] : false;

		// Display the field based on type
		switch ( $field['type'] ) {
			case 'radio' :
				?>
				
				<ul>
				<?php foreach ( $options as $option ) : ?>
					<li>
						<input type="radio" name="<?php echo esc_attr( $field['slug'] ) ?>" value="<?php echo urlencode( $option->name ) ?>" <?php checked( $value, $option->name, true ) ?>/> <?php echo esc_html( $option->name ) ?>
					</li>
				<?php endforeach ?>
				</ul>
				
				<?php
				break;
			case 'selectbox' :
				?>
				
				<select name="<?php echo esc_attr( $field['slug'] ) ?>">
					<option value="">--------</option>
					<?php foreach( $options as $option ) : ?>
						<option <?php selected( $value, $option->name, true ) ?>><?php echo $option->name ?></option>
					<?php endforeach ?>
				</select>
				
				<?php
				
				break;
			case 'multiselectbox' :
				?>
				
				<select name="<?php echo esc_attr( $field['slug'] ) ?>[]" multiple="multiple">
					<?php foreach( $options as $option ) : ?>
						<option <?php if ( is_array( $value ) && in_array( $option->name, $value ) ) : ?>selected="selected"<?php endif ?>/><?php echo $option->name ?></option>
					<?php endforeach ?>
				</select>
				
				<?php
				
				break;
			case 'checkbox' :
				?>
				
				<ul>
				<?php foreach ( (array)$options as $option ) : ?>
					<li>
						<input type="checkbox" name="<?php echo esc_attr( $field['slug'] ) ?>[]" value="<?php echo urlencode( $option->name ) ?>" <?php if ( is_array( $value ) && in_array( $option->name, $value ) ) : ?>checked="checked"<?php endif ?>/> <?php echo esc_html( $option->name ) ?>
					</li>
				<?php endforeach ?>
				</ul>
				
				<?php
				break;
			case 'textbox' :
				?>

				<input id="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" type="text" name="<?php echo esc_attr( $field['slug'] ) ?>" value="<?php echo esc_html( $value ) ?>"/>
				
				<?php
				
				break;
		}
	}
	
	function enqueue_styles() {
		if ( bp_is_directory() && bp_is_members_component() ) {
			wp_enqueue_style( 'bpbd', BPBD_INSTALL_URL . '/includes/css/style.css' ); 
		}
	}
	

}

?>