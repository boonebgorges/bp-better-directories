<?php

class BPBD {
	public $get_params;
	public $filterable_fields;

	/**
	 * PHP 5 constructor
	 *
	 * @package BP Better Directories
	 * @since 1.0
	 */
	public function __construct() {
		define( 'BPBD_INSTALL_DIR', trailingslashit( dirname(__FILE__) ) );
		define( 'BPBD_INSTALL_URL', plugins_url() . '/bp-better-directories/' );

		if ( ! class_exists( 'BP_XProfile_Query' ) ) {
			require( BPBD_INSTALL_DIR . 'includes/class-bp-xprofile-query.php' );
		}

		// WP-API endpoint
		require( BPBD_INSTALL_DIR . 'includes/api.php' );

		add_action( 'bp_pre_user_query_construct', array( $this, 'filter_user_query' ) );

		add_filter( 'bp_get_template_stack', array( $this, 'add_template_stack_location' ) );

//		add_action( 'bp_actions', array( $this, 'catch_post_request' ) );

		// Add the filter UI
		add_action( 'bpbd_directory_filters', array( $this, 'filter_ui' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_template_stack_location( $stack ) {
		$stack = array_merge( array( BPBD_INSTALL_DIR . 'templates' ), $stack );
		return $stack;
	}

	/**
	 * We catch search requests in the POST, convert to a URL with GET params, and redirect.
	 *
	 * This allows for cleaner URLs.
	 */
	public function catch_post_request() {
		if ( ! empty( $_POST['bpbd-submit'] ) ) {
			$redirect = bp_get_requested_url();

			foreach ( $_POST as $pkey => $pvalue ) {
				if ( 0 !== strpos( $pkey, 'bpbd-filter-' ) ) {
					continue;
				}

				if ( is_array( $pvalue ) ) {
					foreach ( $pvalue as $pv ) {
						$redirect = add_query_arg( $pkey . '[]', $pv );
					}
				} else {
					$redirect = add_query_arg( $pkey, $pvalue );
				}
			}

			bp_core_redirect( $redirect );
			die();
		}
	}

	public function get_filterable_fields() {
		if ( null === $this->filterable_fields ) {
			$filterable_fields = bp_get_option( 'bpdb_fields' );

			foreach ( $filterable_fields as &$ff ) {
				if ( ! isset( $ff['is_xprofile_field'] ) ) {
					$ff['is_xprofile_field'] = true;
				}
			}

			$this->filterable_fields = apply_filters( 'bpbd_filterable_fields', $filterable_fields );
		}

		return $this->filterable_fields;
	}

	public function get_get_params() {
		if ( null !== $this->get_params ) {
			return $this->get_params;
		}

		$filterable_fields = $this->get_filterable_fields();

		$filterable_keys = array();
		if ( is_array( $filterable_fields ) ) {
			$filterable_keys = array_keys( $filterable_fields );
		}

		$potential_fields = isset( $_GET ) ? $_GET : array();

		foreach ( (array) $filterable_keys as $filterable_key ) {
			$get_key = 'bpbd-filter-' . $filterable_key;
			if ( ! empty( $potential_fields[ $get_key ] ) ) {

				// Get the field id for keying the array
				$field_id = $filterable_fields[ $filterable_key ]['id'];

				// Put the field data into the get_params property array
				$this->get_params[ $field_id ] = $filterable_fields[ $filterable_key ];

				// Add the filtered value from $_GET
				if ( is_array( $potential_fields[ $get_key ] ) ) {
					$values = array();
					foreach( $potential_fields[ $get_key ] as $key => $value ) {
						$values[ $key ] = urldecode( $value );
					}
				} else {
					$values = urldecode( $potential_fields[ $get_key ] );
				}

				$this->get_params[ $field_id ]['value'] = $values;
			}
		}

		return $this->get_params;

		// Todo: sort order?
	}

	public function filter_user_query( BP_User_Query $user_query ) {
		$xprofile_query = array();
		if ( isset( $user_query->query_vars['xprofile_query'] ) ) {
			$xprofile_query = (array) $user_query->query_vars['xprofile_query'];
		}

		$get_params = $this->get_get_params();

		// Filter out get_params that are not related to xprofile.
		foreach ( $get_params as $gp_key => $gp ) {
			if ( empty( $gp['is_xprofile_field'] ) ) {
				unset( $get_params[ $gp_key ] );
			}
		}

		foreach ( (array) $get_params as $field_id => $field ) {
			switch ( $field['type'] ) {
				case 'textbox' :
					$xprofile_query[] = array(
						'field_id' => $field_id,
						'value' => $field['value'],
						'compare' => 'LIKE',
					);

					break;

				// This data is stored serialized so we do
				// multiple LIKE clauses
				case 'checkbox' :
					foreach ( (array) $field['value'] as $v ) {
						$xprofile_query[] = array(
							'field_id' => $field_id,
							'value' => $v,
							'compare' => 'LIKE',
						);
					}
					break;
			}
		}

		$user_query->query_vars['xprofile_query'] = $xprofile_query;

		return;
	}

	public function filter_ui() {
		$filterable_fields = $this->get_filterable_fields();

		if ( empty( $filterable_fields ) ) {
			// Nothing to see here.
			return;
		}

		?>

		<form id="bpbd-filter-form" method="post" action="<?php bp_root_domain() ?>/<?php bp_members_root_slug() ?>">

		<div id="bpbd-filters">
			<h4><?php _e( 'Narrow Results', 'bpbd' ) ?> <span id="bpbd-clear-all"><a href="#">Clear All</a></span></h4>

			<ul>
			<?php foreach ( $filterable_fields as $slug => $field ) : ?>
				<li id="bpbd-filter-crit-<?php echo esc_attr( $field['slug'] ) ?>" class="bpbd-filter-crit bpbd-filter-crit-type-<?php echo esc_attr( $field['type'] ) ?>">
					<?php $this->render_field( $field ) ?>
				</li>
			<?php endforeach ?>
			</ul>

			<input id="bpbd-submit" name="bpbd-submit" type="submit" class="button" value="<?php _e( 'Submit', 'bpdb' ) ?>" />
		</div>

		</form>
		<?php
	}

	public function render_field( $field ) {
		// Bypass in your own plugin. Barf.
		if ( apply_filters( 'bpbd_pre_render_field', false, $field, $this ) ) {
			return;
		}

		$get_params = $this->get_get_params();

		?>

		<label for="<?php echo esc_attr( $field['slug'] ) ?>"><?php echo esc_html( $field['name'] ) ?> <span class="bpbd-clear-this"><a href="#">Clear</a></span></label>

		<?php

		$field_data = new BP_XProfile_Field( $field['id'] );

		$options = $field_data->get_children();

		// Get the current value for this item, if any, out of the $_GET params
		$value = isset( $get_params[$field['id']] ) ? $get_params[$field['id']]['value'] : false;

		// Display the field based on type
		switch ( $field['type'] ) {
			case 'radio' :
				?>

				<ul>
				<?php foreach ( $options as $option ) : ?>
					<li>
						<input type="radio" name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" value="<?php echo urlencode( $option->name ) ?>" <?php checked( $value, $option->name, true ) ?>/> <?php echo esc_html( $option->name ) ?>
					</li>
				<?php endforeach ?>
				</ul>

				<?php
				break;
			case 'selectbox' :
				?>

				<select name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>">
					<option value="">--------</option>
					<?php foreach( $options as $option ) : ?>
						<option <?php selected( $value, $option->name, true ) ?>><?php echo $option->name ?></option>
					<?php endforeach ?>
				</select>

				<?php

				break;
			case 'multiselectbox' :
				?>

				<select name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>[]" multiple="multiple">
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
						<input type="checkbox" name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>[]" value="<?php echo urlencode( $option->name ) ?>" <?php if ( is_array( $value ) && in_array( $option->name, $value ) ) : ?>checked="checked"<?php endif ?>/> <?php echo esc_html( $option->name ) ?>
					</li>
				<?php endforeach ?>
				</ul>

				<?php
				break;
			case 'textbox' :
			default :
				?>

				<input id="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" type="text" name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" value=""/>

				<ul class="bpbd-search-terms">
				<?php if ( is_array( $value ) && !empty( $value ) ) : ?>
					<?php foreach ( (array)$value as $sterm ) : ?>
						<?php if ( !trim( $sterm ) ) continue; ?>
						<li id="bpbd-value-<?php echo sanitize_title( $sterm ) ?>"><span class="bpbd-remove"><a href="#">x</a></span> <?php echo esc_html( $sterm ) ?></li>
					<?php endforeach ?>
				<?php endif ?>
				</ul>

				<input class="bpbd-hidden-value" id="bpbd-filter-value-<?php echo esc_attr( $field['slug'] ) ?>" type="hidden" name="bpbd-filter-<?php echo esc_attr( $field['slug'] ) ?>" value="<?php echo esc_attr( implode( ',', (array)$value ) ) ?>" />

				<?php

				break;
		}
	}

	public function enqueue_styles() {
		if ( bp_is_directory() && bp_is_members_component() ) {
//			wp_enqueue_style( 'jquery-loadmask-css', BPBD_INSTALL_URL . '/includes/lib/jquery.loadmask/jquery.loadmask.css' );
			wp_enqueue_style( 'bpbd-css', BPBD_INSTALL_URL . '/includes/css/style.css' );
		}
	}

	public function enqueue_scripts() {
		if ( bp_is_directory() && bp_is_members_component() ) {
			wp_enqueue_script( 'bpbd-js', BPBD_INSTALL_URL . '/includes/js/bp-better-directories.js', array( 'wp-backbone' ) );

			wp_localize_script( 'bpbd-js', 'BPBD', array(
				'api_url' => bp_get_root_domain() . '/wp-json/bpbd/members/',
			) );
		}
	}
}
