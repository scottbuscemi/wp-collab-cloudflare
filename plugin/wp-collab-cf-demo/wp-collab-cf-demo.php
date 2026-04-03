<?php
/**
 * Plugin Name: WP Collab CF Demo
 * Description: Magic link that creates temporary users restricted to a single collaborative editing demo post.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set the demo post ID in wp-config.php or an mu-plugin:
 *
 *   define( 'WP_COLLAB_CF_DEMO_POST_ID', 123 );
 *
 * The magic link will be: https://yoursite.com/?wp-collab-demo=1
 */

class WP_Collab_CF_Demo {

	const ROLE         = 'wp_collab_demo';
	const QUERY_VAR    = 'wp-collab-demo';
	const USER_PREFIX  = 'demo-';
	const META_KEY     = '_wp_collab_demo_user';
	const DEMO_POST_ID_OPTION = 'wp_collab_cf_demo_post_id';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_role' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_magic_link' ] );
		add_action( 'admin_init', [ __CLASS__, 'restrict_admin_access' ] );
		add_action( 'admin_menu', [ __CLASS__, 'strip_admin_menus' ], 999 );
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'strip_admin_bar' ], 999 );
		add_filter( 'map_meta_cap', [ __CLASS__, 'restrict_post_caps' ], 10, 4 );
		add_action( 'pre_get_posts', [ __CLASS__, 'restrict_post_list' ] );
		add_action( 'admin_head', [ __CLASS__, 'hide_ui_chrome' ] );
	}

	/**
	 * Get the demo post ID from constant or option.
	 */
	public static function get_demo_post_id() {
		if ( defined( 'WP_COLLAB_CF_DEMO_POST_ID' ) ) {
			return (int) WP_COLLAB_CF_DEMO_POST_ID;
		}
		return (int) get_option( self::DEMO_POST_ID_OPTION, 0 );
	}

	/**
	 * Check if the current user is a demo user.
	 */
	public static function is_demo_user( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$user = get_userdata( $user_id );
		return $user && in_array( self::ROLE, (array) $user->roles, true );
	}

	/**
	 * Register the minimal demo role.
	 */
	public static function register_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, 'Collab Demo', [
				'read'                 => true,
				'edit_posts'           => true,
				'edit_others_posts'    => true,
				'edit_published_posts' => true,
				'upload_files'         => true,
				'edit_theme_options'   => true,
			] );
		}
	}

	/**
	 * Handle the magic link: create user, log in, redirect to post editor.
	 */
	public static function handle_magic_link() {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		$post_id = self::get_demo_post_id();
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_die( 'Demo post not configured. Set WP_COLLAB_CF_DEMO_POST_ID.' );
		}

		// If already a logged-in demo user, just redirect.
		if ( is_user_logged_in() && self::is_demo_user() ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
			exit;
		}

		// Generate a unique demo user.
		$suffix   = wp_generate_password( 6, false );
		$username = self::USER_PREFIX . $suffix;
		$email    = $username . '@demo.invalid';
		$password = wp_generate_password( 24 );

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( 'Could not create demo user: ' . $user_id->get_error_message() );
		}

		// Assign role and mark as demo user.
		$user = get_userdata( $user_id );
		$user->set_role( self::ROLE );
		update_user_meta( $user_id, self::META_KEY, true );

		// Give them a display name that's friendlier.
		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => 'Guest ' . strtoupper( $suffix ),
			'nickname'     => 'Guest ' . strtoupper( $suffix ),
		] );

		// Log them in.
		wp_set_auth_cookie( $user_id, false );
		wp_set_current_user( $user_id );

		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		exit;
	}

	/**
	 * Restrict demo users to only the post editor for the demo post.
	 */
	public static function restrict_admin_access() {
		if ( ! self::is_demo_user() ) {
			return;
		}

		$post_id = self::get_demo_post_id();
		$screen  = isset( $_GET['action'] ) ? $_GET['action'] : '';

		// Allow the post editor and admin-ajax.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		global $pagenow;

		// Allow post.php with the correct post ID.
		if ( 'post.php' === $pagenow
			&& isset( $_GET['post'] )
			&& (int) $_GET['post'] === $post_id
			&& 'edit' === $screen
		) {
			return;
		}

		// Allow admin-post.php and other required endpoints.
		$allowed = [ 'admin-ajax.php', 'admin-post.php' ];
		if ( in_array( $pagenow, $allowed, true ) ) {
			return;
		}

		// Everything else: redirect to the demo post.
		wp_safe_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
		exit;
	}

	/**
	 * Remove all admin menus for demo users.
	 */
	public static function strip_admin_menus() {
		if ( ! self::is_demo_user() ) {
			return;
		}

		global $menu, $submenu;
		$menu    = [];
		$submenu = [];
	}

	/**
	 * Strip admin bar items for demo users.
	 */
	public static function strip_admin_bar() {
		if ( ! self::is_demo_user() ) {
			return;
		}

		global $wp_admin_bar;
		$nodes = $wp_admin_bar->get_nodes();
		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				if ( 'top-secondary' !== $node->parent ) {
					$wp_admin_bar->remove_node( $node->id );
				}
			}
		}
	}

	/**
	 * Prevent demo users from editing/viewing any post except the demo post.
	 */
	public static function restrict_post_caps( $caps, $cap, $user_id, $args ) {
		if ( ! self::is_demo_user( $user_id ) ) {
			return $caps;
		}

		$post_id = self::get_demo_post_id();

		// For post-specific capabilities, only restrict regular posts.
		// Allow wp_global_styles, wp_template, wp_navigation, etc. so the editor renders properly.
		$post_caps = [ 'edit_post', 'delete_post', 'read_post' ];
		if ( in_array( $cap, $post_caps, true ) && ! empty( $args[0] ) ) {
			$target_post = get_post( (int) $args[0] );
			if ( $target_post && 'post' === $target_post->post_type && (int) $args[0] !== $post_id ) {
				return [ 'do_not_allow' ];
			}
		}

		// Block creating new posts.
		if ( 'create_posts' === $cap || 'publish_posts' === $cap || 'delete_posts' === $cap ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}

	/**
	 * If a demo user somehow reaches the post list, only show the demo post.
	 */
	public static function restrict_post_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! self::is_demo_user() ) {
			return;
		}

		$post_id = self::get_demo_post_id();
		$query->set( 'post__in', [ $post_id ] );
	}

	/**
	 * Hide unnecessary UI elements for demo users.
	 */
	public static function hide_ui_chrome() {
		if ( ! self::is_demo_user() ) {
			return;
		}
		?>
		<style>
			#adminmenumain,
			#wpfooter,
			.edit-post-fullscreen-mode-close { display: none !important; }
			#wpcontent { margin-left: 0 !important; }
		</style>
		<?php
	}
}

WP_Collab_CF_Demo::init();
