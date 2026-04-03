<?php
/**
 * Plugin Name: WP Collab Cloudflare
 * Description: Routes WordPress 7.0 real-time collaboration through a Cloudflare Workers relay instead of HTTP polling.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set your deployed Worker URL in wp-config.php or an mu-plugin:
 *
 *   define( 'WP_COLLAB_CF_WS_URL', 'wss://wp-collab-cloudflare.YOUR-SUBDOMAIN.workers.dev' );
 */

add_action( 'admin_enqueue_scripts', 'wp_collab_cf_enqueue_scripts' );

function wp_collab_cf_enqueue_scripts( $hook ) {
	if ( ! defined( 'WP_COLLAB_CF_WS_URL' ) ) {
		return;
	}

	// Only load on the post editor screen.
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$asset_file = __DIR__ . '/build/index.asset.php';
	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'wp-collab-cf',
		plugin_dir_url( __FILE__ ) . 'build/index.js',
		array_merge( $asset['dependencies'], array( 'wp-sync' ) ),
		$asset['version'],
		array( 'in_footer' => false )
	);

	wp_localize_script( 'wp-collab-cf', 'wpCollabCf', array(
		'wsUrl' => WP_COLLAB_CF_WS_URL,
	) );
}
