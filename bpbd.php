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
		define( 'BPBD_INSTALL_DIR', trailingslashit( dirname(__FILE__) ) );
		define( 'BPBD_INSTALL_URL', plugins_url() . '/bp-better-directories/' );

		if ( version_compare( BP_VERSION, '1.3', '<' ) ) {
			require( BPBD_INSTALL_DIR . 'includes/1.5-abstraction.php' );
		}

		$this->setup_get_params();

		add_action( 'init', array( $this, 'setup' ) );

		add_action( 'wp_ajax_members_filter', array( $this, 'filter_ajax_requests' ), 1 );

		add_action( 'wp_print_styles', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
			add_action( 'bp_before_directory_members', array( $this, 'filter_ui' ) );
		}
	}

	function setup_get_params() {
		//$filterable_fields = get_blog_option( BP_ROOT_BLOG, 'bpdb_fields' );

		/**
		 * 'type' - The input type
		 * 'vtype' - 'post_id' will do a get_posts(). Otherwise direct text usermeta query
		 */
		$filterable_fields = array(
			'school' => array(
				'type' => 'checkbox',
				'vtype' => 'post_id'
			),
			'program' => array(
				'type' => 'checkbox',
				'vtype' => 'post_id'
			)
		);

		// Set so it can be used object-wide
		$this->filterable_fields = $filterable_fields;

		if ( is_array( $filterable_fields ) ) {
			$filterable_keys = array_keys( $filterable_fields );
		} else {
			$filterable_keys = array();
		}

		$potential_fields = isset( $_GET ) ? $_GET : array();

		$cookie_fields = isset( $_COOKIE['bpbd-filters'] ) ? (array)json_decode( stripslashes( $_COOKIE['bpbd-filters'] ) ) : null;

		if ( isset( $_COOKIE['bpbd-filters'] ) )
			$potential_fields = array_merge( $potential_fields, $cookie_fields );

		foreach ( (array)$filterable_keys as $filterable_key ) {
			if ( !empty( $potential_fields[$filterable_key] ) ) {

				// Add the filtered value from $_GET
				if ( is_array( $potential_fields[$filterable_key] ) ) {
					$values = array();
					foreach( $potential_fields[$filterable_key] as $key => $value ) {
						$values[$key] = urldecode( $value );
					}
				} else {
					$values = urldecode( $potential_fields[$filterable_key] );
				}

				$this->get_params[$filterable_key]['value'] = $values;
				$this->get_params[$filterable_key]['type']  = $filterable_fields[$filterable_key]['type'];
				$this->get_params[$filterable_key]['vtype']  = $filterable_fields[$filterable_key]['vtype'];
			}
		}

		// Some parameter values must be converted from post ids to post titles
		$post_ids = array();
		$post_types = array();
		foreach( $this->get_params as $gp_key => $ff ) {
			if ( 'post_id' != $ff['vtype'] ) {
				continue;
			}

			if ( is_array( $ff['value'] ) ) {
				$post_ids = array_merge( $post_ids, wp_parse_id_list( $ff['value'] ) );
			} else {
				$post_ids[] = (int) $ff['value'];
			}

			$post_types[] = $gp_key;
		}
		$post_ids = array_unique( $post_ids );

		$raw_post_data = get_posts( array( 'post__in' => $post_ids, 'post_type' => $post_types ) );

		// Rekey
		$post_data = array();
		foreach( $raw_post_data as $rpd ) {
			$post_data[$rpd->ID] = $rpd;
		}

		foreach( $this->get_params as $gp_key => $ff ) {
			if ( 'post_id' != $ff['vtype'] ) {
				continue;
			}

			if ( is_array( $ff['value'] ) ) {
				$new_value = array();
				foreach( $ff['value'] as $post_id ) {
					$new_value[] = isset( $post_data[$post_id] ) ? $post_data[$post_id]->post_title : '';
				}
			} else {
				$post_id = $ff['value'];
				$new_value = isset( $post_data[$post_id] ) ? $post_data[$post_id]->post_title : '';
			}

			$this->get_params[$gp_key]['value'] = $new_value;
		}
	}

	function users_sql_filter( $s, $sql ) {
		global $bp, $wpdb;

		$bpbd_select = array();
		$bpbd_from = array();
		$bpbd_where = array();
		$counter = 1;

		$get_ids_sql = array(
			'select' => "SELECT user_id FROM ",
			'from'   => array(),
			'where'  => array()
		);

		// Build the query array
		foreach( $this->get_params as $field_id => $field ) {
			$table_shortname = 'bpbd' . $counter;

			if ( 1 == $counter ) {
				$get_ids_sql['select'] = "SELECT {$table_shortname}.user_id FROM ";
			}

			$get_ids_sql['from'][]  = 1 == $counter ? "$wpdb->usermeta as $table_shortname" : "$wpdb->usermeta as $table_shortname ON ({$table_shortname}.user_id = bpbd1.user_id)";

			if ( is_array( $field['value'] ) ) {
				// Multiselect and checkbox values may be stored as arrays, so we
				// have to do multiple LIKEs. Hack alert
				$clauses = array();
				foreach( (array)$field['value'] as $val ) {
					$clauses[] = $table_shortname . '.meta_value LIKE "%%' . $val . '%%"';
				}
				$where = implode( ' OR ', $clauses );
			} else {
				// Everything else is an exact match
				$op = '=';
				$value = $wpdb->prepare( "%s", $field['value'] );
				$where = $table_shortname . '.value' . $op . ' ' . $value;
			}

			$get_ids_sql['where'][] = $wpdb->prepare( "{$table_shortname}.meta_key = %s AND ({$where})", $field_id );
			$counter++;
		}

		// If we have data here (that is, if we are indeed limiting by usermeta), we'll
		// need to modify the main BP user queries
		if ( !empty( $get_ids_sql['where'] ) ) {
			// Collapse into a usermeta query
			$get_ids_sql_sql = $get_ids_sql['select'] . implode( ' JOIN ', $get_ids_sql['from'] ) . ' WHERE ' . implode( ' AND ', $get_ids_sql['where'] );

			// Get the user id whitelist
			$user_ids = $wpdb->get_col( $get_ids_sql_sql );

			// If the query yields results, limit our query to within these results.
			// Otherwise, make sure to return no results (no matches)
			if ( !empty( $user_ids ) ) {
				$sql['where'] .= $wpdb->prepare( " AND u.ID IN (" . implode( ',', $user_ids ) . ")" ) ;
			} else {
				$sql['where'] = $wpdb->prepare( "WHERE 1=0" );
			}

			$s = join( ' ', (array)$sql );
		}

		return $s;
	}

	function filter_ajax_requests() {
		header("Cache-Control: no-cache, must-revalidate");
		add_filter( 'bp_core_get_paged_users_sql', array( $this, 'users_sql_filter' ), 10, 2 );
		add_filter( 'bp_core_get_total_users_sql', array( $this, 'users_sql_filter' ), 10, 2 );
	}

	function filter_ui() {

		?>

		<form id="bpbd-filter-form" method="get" action="<?php bp_root_domain() ?>/<?php bp_members_root_slug() ?>">

		<div id="bpbd-filters">
			<h4><?php _e( 'Narrow Results', 'bpbd' ) ?> <span id="bpbd-clear-all"><a href="#">Clear All</a></span></h4>

			<ul>
				<li id="bpbd-filter-crit-school" class="bpbd-filter-crit bpbd-filter-crit-type-checkbox">
					<?php $this->render_field( 'school' ) ?>
				</li>
			</ul>

			<input type="submit" class="button" value="<?php _e( 'Submit', 'bpdb' ) ?>" />
		</div>

		</form>
		<?php
	}

	function render_field( $field ) {
		switch ( $field ) {
			case 'school' :
				$name = 'Schools & Programs';
				$data = zp_get_school_programs_walk();
				$options_markup = $this->build_checkboxes( $data, '' );

				break;

		}

		?>
		<label for="<?php echo esc_attr( $field ) ?>"><?php echo esc_html( $name ) ?> <span class="bpbd-clear-this"><a href="#">Clear</a></span></label>

		<?php echo $options_markup ?>
		<?php
	}

	/**
	 * Build checkboxes out of post array
	 *
	 * Works with nested items (in the 'programs' property)
	 *
	 * @param $value Preset values for this field (for checked())
	 */
	function build_checkboxes( $data, $value, $echo = false ) {

		if ( !$echo ) {
			ob_start();
		}

		// A hack to get the post type from the first data item
		// Won't work if $data contains more than one post type
		$first_key = array_pop( array_reverse( array_keys( $data ) ) );
		$post_type = $data[$first_key]->post_type;

		?>

		<ul class="bpbd-filter-option bpbd-filter-option-checkbox" id="bpbd-filter-option-<?php echo esc_attr( $post_type ) ?>">
		<?php foreach ( (array)$data as $post ) : ?>
			<li>
				<input type="checkbox" name="<?php echo esc_attr( $post_type ) ?>" value="<?php echo urlencode( $post->ID ) ?>" <?php if ( is_array( $value ) && in_array( $post->ID, $value ) ) : ?>checked="checked"<?php endif ?>/> <?php echo esc_html( $post->post_title ) ?>

				<?php if ( !empty( $post->programs ) ) : ?>
					<?php $this->build_checkboxes( $post->programs, '', true ) ?>
				<?php endif ?>
			</li>
		<?php endforeach ?>
		</ul>

		<?php

		if ( !$echo ) {
			$retval = ob_get_contents();
			ob_end_clean();
			return $retval;
		}
	}

	function xx_render_field( $field ) {
		?>

		<label for="<?php echo esc_attr( $field['slug'] ) ?>"><?php echo esc_html( $field['name'] ) ?> <span class="bpbd-clear-this"><a href="#">Clear</a></span></label>

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
			default :
				?>

				<input id="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" type="text" name="<?php echo esc_attr( $field['slug'] ) ?>" value=""/>

				<ul class="bpbd-search-terms">
				<?php if ( is_array( $value ) && !empty( $value ) ) : ?>
					<?php foreach ( (array)$value as $sterm ) : ?>
						<?php if ( !trim( $sterm ) ) continue; ?>
						<li id="bpbd-value-<?php echo sanitize_title( $sterm ) ?>"><span class="bpbd-remove"><a href="#">x</a></span> <?php echo esc_html( $sterm ) ?></li>
					<?php endforeach ?>
				<?php endif ?>
				</ul>

				<?php /* Comma-separated string */ ?>
				<input class="bpbd-hidden-value" id="bpbd-filter-value-<?php echo esc_attr( $field['slug'] ) ?>" type="hidden" name="bpbd-filter-value-<?php echo esc_attr( $field['slug'] ) ?>" value="<?php echo esc_attr( implode( ',', (array)$value ) ) ?>" />

				<?php

				break;
		}
	}

	function enqueue_styles() {
		if ( bp_is_directory() && bp_is_members_component() ) {
			wp_enqueue_style( 'jquery-loadmask-css', BPBD_INSTALL_URL . '/includes/lib/jquery.loadmask/jquery.loadmask.css' );
			wp_enqueue_style( 'bpbd-css', BPBD_INSTALL_URL . '/includes/css/style.css' );
		}
	}

	function enqueue_scripts() {
		if ( bp_is_directory() && bp_is_members_component() ) {
			wp_enqueue_script( 'jquery-loadmask', BPBD_INSTALL_URL . '/includes/lib/jquery.loadmask/jquery.loadmask.min.js', array( 'jquery' ) );
			wp_enqueue_script( 'bpbd-js', BPBD_INSTALL_URL . '/includes/js/bpbd.js', array( 'jquery', 'dtheme-ajax-js', 'jquery-loadmask' ) );
		}
	}


}

?>
