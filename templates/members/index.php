<?php do_action( 'bp_before_directory_members_page' ); ?>

<div id="buddypress">

	<?php do_action( 'bp_before_directory_members' ); ?>

	<?php do_action( 'bp_before_directory_members_content' ); ?>

	<?php /*
	<div id="members-dir-search" class="dir-search" role="search">
		<?php bp_directory_members_search_form(); ?>
	</div><!-- #members-dir-search -->
	*/ ?>

	<div id="members-dir-list" class="members dir-list">
	</div>

	<script id="tmpl-bpbd_member" type="text/template">
		<li>
			<div class="item-avatar">
				<a href="{{ data.domain }}">
					<img class="avatar user-{{ data.user_id }}-avatar" width="50" height="50" src="{{ data.avatar_url }}" />
				</a>
			</div>

			<div class="item">
				<div class="item-title">
					<a href="{{ data.domain }}">{{ data.display_name }}</a>
					<# if ( '' != data.latest_update ) { #>
						- <span class="update">{{{ data.latest_update }}}</span>
					<# } #>
				</div>

				<div class="item-meta">
					<span class="activity">{{ data.last_active_ago }}</span>
				</div>
			</div>
		</li>
	</script>

	<script id="tmpl-bpbd_filters" type="text/html">
		<?php /*
		<input id="bpbd-search" name="bpbd-search" type="text" placeholder="Search" value="{{ data }}">
		*/ ?>

		<?php do_action( 'bpbd_directory_filters' ) ?>
	</script>

	<?php do_action( 'bp_before_directory_members_tabs' ); ?>

	<form action="" method="post" id="members-directory-form" class="dir-form">

		<div class="item-list-tabs" role="navigation">
			<ul>
				<li class="selected" id="members-all"><a href="<?php bp_members_directory_permalink(); ?>"><?php printf( __( 'All Members <span>%s</span>', 'buddypress' ), bp_get_total_member_count() ); ?></a></li>

				<?php if ( is_user_logged_in() && bp_is_active( 'friends' ) && bp_get_total_friend_count( bp_loggedin_user_id() ) ) : ?>
					<li id="members-personal"><a href="<?php echo bp_loggedin_user_domain() . bp_get_friends_slug() . '/my-friends/'; ?>"><?php printf( __( 'My Friends <span>%s</span>', 'buddypress' ), bp_get_total_friend_count( bp_loggedin_user_id() ) ); ?></a></li>
				<?php endif; ?>

				<?php do_action( 'bp_members_directory_member_types' ); ?>

			</ul>
		</div><!-- .item-list-tabs -->


		<div class="item-list-tabs" id="subnav" role="navigation">
			<ul>
				<?php do_action( 'bp_members_directory_member_sub_types' ); ?>

				<li id="members-order-select" class="last filter">
					<label for="members-order-by"><?php _e( 'Order By:', 'buddypress' ); ?></label>
					<select id="members-order-by">
						<option value="active"><?php _e( 'Last Active', 'buddypress' ); ?></option>
						<option value="newest"><?php _e( 'Newest Registered', 'buddypress' ); ?></option>

						<?php if ( bp_is_active( 'xprofile' ) ) : ?>
							<option value="alphabetical"><?php _e( 'Alphabetical', 'buddypress' ); ?></option>
						<?php endif; ?>

						<?php do_action( 'bp_members_directory_order_options' ); ?>
					</select>
				</li>
			</ul>
		</div>

		<div id="members-dir-list" class="members dir-list">
			<div class="backbone-search-line">
				<div id="backbone-person-count"><?php esc_html_e( 'Found: ') ?><span></span></div>
				<div id="bpbd-filters"></div>
			</div>
			<ul id="members-list" class="item-list" role="main"></ul>
		</div><!-- #members-dir-list -->

		<?php do_action( 'bp_directory_members_content' ); ?>

		<?php wp_nonce_field( 'directory_members', '_wpnonce-member-filter' ); ?>

		<?php do_action( 'bp_after_directory_members_content' ); ?>

	</form><!-- #members-directory-form -->

	<?php do_action( 'bp_after_directory_members' ); ?>

</div><!-- #buddypress -->

<?php do_action( 'bp_after_directory_members_page' ); ?>
