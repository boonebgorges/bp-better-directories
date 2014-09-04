<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * initializes api class for activity and creates endpoints
 *
 * @access public
 * @return void
 */
function bpbd_api_init() {
	global $bpbd_api_activity;

	$bpbd_api_activity = new BPBD_API_Activity();
	add_filter( 'json_endpoints', array( $bpbd_api_activity, 'register_routes' ) );
}
add_action( 'wp_json_server_before_serve', 'bpbd_api_init' );

class BPBD_API_Activity {

	/**
	 * register_routes function.
	 *
	 * @access public
	 * @param mixed $routes
	 * @return void
	 */
	public function register_routes( $routes ) {

		$routes['/bpbd/members'] = array(
			array( array( $this, 'get_members'), WP_JSON_Server::READABLE )
		);

		return $routes;
	}


	/**
	 * get_members function.
	 *
	 * @access public
	 * @return void
	 */
	public function get_members() {
		global $bp;

		$args = $_GET;

		$members = array();
		if ( bp_has_members( $args ) ) {

			while ( bp_members() ) {

				bp_the_member();
				global $members_template;

				$member = array(
					'user_id'   		=> bp_get_member_user_id(),
					'display_name' => bp_get_member_name(),
					'login' => bp_get_member_user_login(),
					'avatar_url' => bp_core_fetch_avatar( array(
						'item_id' => bp_get_member_user_id(),
						'type' => 'thumb',
						'html' => 'false',
					) ),
					'domain' => bp_get_member_permalink(),
					'last_active_timestamp' => $members_template->member->last_activity,
					'last_active_ago' => bp_get_member_last_active(),
					'latest_update_content' => '',
					'latest_update_url' => '',
					'latest_update' => bp_get_member_latest_update(),
				);

				// Reproduce some logic in bp_get_member_latest_update()
				// @todo fix upstream
				if ( ! empty( $members_template->member->latest_update ) ) {
					$latest_update = maybe_unserialize( $members_template->member->latest_update );
					if ( ! empty( $latest_update['content'] ) ) {
						// double strip tags because we can't pass markup to template
						// groan
						$member['latest_update_content'] = strip_tags( apply_filters( 'bp_get_activity_latest_update_excerpt', trim( strip_tags( bp_create_excerpt( $latest_update['content'], 225 ) ) ) ) );
						$member['latest_update_url'] = bp_activity_get_permalink( $latest_update['id'] );
					}
				}

				$members[] = $member;

			}

			return $members;
		} else {
			return wp_send_json_error();
		}


	}

	public function create_activity() {
		return 'create activity';
	}

	public function edit_activity() {
		return 'edit activity';
	}

	public function delete_activity() {
		return 'delete activity';
	}

}
