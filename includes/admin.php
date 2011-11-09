<?php

class BPBD_Admin {
	/**
	 * PHP 4 constructor
	 *
	 * @package BP Better Directories
	 * @since 1.0
	 */
	function bpbd_admin() {
		$this->__construct();
	}

	/**
	 * PHP 5 constructor
	 *
	 * @package BP Better Directories
	 * @since 1.0
	 */	
	function __construct() {
		// Add the admin menu
		// todo: change this when BP 1.3 is out
		add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'add_admin_page' ) );
	}
	
	function add_admin_page() {
		$plugin_page = add_submenu_page( 'bp-general-settings', __( 'BP Better Directories', 'bpbd' ), __( 'BP Better Directories', 'bpbd' ), 'manage_options', 'bpbd', array( $this, 'admin_page_markup' ) );
	
		add_action( "admin_print_scripts-$plugin_page", array( $this, 'admin_scripts' ) );
		add_action( "admin_print_styles-$plugin_page", array( $this, 'admin_styles' ) );
	}
	
	function admin_page_markup() {
		$this->catch_form_save();
		
		$saved_fields = get_blog_option( BP_ROOT_BLOG, 'bpdb_fields' );

		// Which fields should be checked?
		// I am not thrilled about this mode of rejiggering data. But I've optimized for
		// front-end use
		$checked_fields = array();
		foreach( $saved_fields as $saved_field ) {
			$id = $saved_field['id'];
			$checked_fields[$id] = array(
				'id'	=> $id,
				'type'	=> $saved_field['type']
			);
		}
		
		$groups = BP_XProfile_Group::get( array(
			'fetch_fields' => true
		));

		?>
		<form action="" method="post" id="bpbd-form">
		
		<ul>
		<?php foreach ( $groups as $group ) : ?>
			<li>
				<h4><?php echo esc_html( $group->name ) ?></h4>
				
				<?php if ( !empty( $group->fields ) ) : ?>
					<ul>
					<?php foreach ( $group->fields as $field ) : ?>
						<?php 
						
						$checked = isset( $checked_fields[$field->id] ) !== false ? 'checked="checked" ' : ''; 
						$type = isset( $checked_fields[$field->id]['type'] ) ? $checked_fields[$field->id]['type'] : false;
						
						?>
						
						<li>
							<input type="checkbox" name="fields[<?php echo $field->id ?>]" id="field-<?php echo $field->id ?>" class="field field-group-<?php $group->id ?>" <?php echo $checked ?>/> <?php echo esc_html( $field->name ) ?>
							
							<?php if ( $checked ) : ?>
								<?php $options = $this->field_type_options( $field->type ) ?>
										
								<div class="field-type-box">
									<select name="field_types[<?php echo $field->id ?>]">
									<?php foreach ( $options as $name => $title ) : ?>
										<option value="<?php echo esc_attr( $name ) ?>" <?php selected( $type, $name ) ?>><?php echo esc_html( $title ) ?></option>
									<?php endforeach ?>
									</select>
								</div>
							<?php endif ?>
						</li>
					<?php endforeach ?>
					</ul>
				<?php endif ?>
			</li>
		<?php endforeach ?>
		</ul>
		
		<input type="submit" name="bpbd_submit" class="button-primary" value="<?php _e( 'Save', 'bpbd' ) ?>" />
		
		</form>
		<?php

	}
	
	/**
	 * Which filter types should be available for this field type?
	 */
	function field_type_options( $type ) {
		switch ( $type ) {
			case 'textbox' :
			case 'textarea' :
				$types = array(
					'textbox' => __( 'Text search', 'bpbd' )
				);
				break;
			
			default :
				$types = array(
					//'radio' 	 => __( 'Radio buttons', 'bpbd' ),
					//'selectbox' 	 => __( 'Dropdown', 'bpbd' ),
					//'multiselectbox' => __( 'Multiple select box', 'bpbd' ),
					'checkbox'       => __( 'Checkboxes', 'bpbd' ),
					'textbox'	 => __( 'Text search', 'bpbd' )
				);
				break;
		}
	
		return $types;
	}
	
	function catch_form_save() {
		if ( isset( $_POST['bpbd_submit'] ) ) {
			$this->save();
		}
	}
	
	function save() {
		$fields = array();
		
		if ( !empty( $_POST['fields'] ) ) {
			foreach( $_POST['fields'] as $field_id => $on ) {
				$field = new BP_XProfile_Field( $field_id );
				
				$title = sanitize_title( $field->name );
				
				// MySQL needs underscores for the column aliases
				$title = str_replace( '-', '_', $title );
				
				$type = isset( $_POST['field_types'][$field_id] ) ? $_POST['field_types'][$field_id] : $field->type;
				
				if ( !$type )
					$type = 'textbox';
				
				$fields[$title] = array(
					'id'	=> $field_id,
					'name'	=> $field->name,
					'type'	=> $_POST['field_types'][$field_id],
					'slug'	=> $title
				);	
			}
		}
		
		update_blog_option( BP_ROOT_BLOG, 'bpdb_fields', $fields );
	}
	
	function admin_scripts() {
	
	}
	
	function admin_styles() {
	
	}
}

?>