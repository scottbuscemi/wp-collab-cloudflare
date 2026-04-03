<?php
/**
 * Plugin Name: Pantheon RTC POC
 * Description: Routes WordPress real-time collaboration through a Cloudflare Workers relay instead of HTTP polling.
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set your deployed Worker URL in wp-config.php:
 *
 *   define( 'PANTHEON_RTC_WS_URL', 'wss://pantheon-rtc-poc.YOUR-SUBDOMAIN.workers.dev' );
 */

add_action( 'admin_enqueue_scripts', 'pantheon_rtc_enqueue_scripts' );

function pantheon_rtc_enqueue_scripts( $hook ) {
	if ( ! defined( 'PANTHEON_RTC_WS_URL' ) ) {
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
		'pantheon-rtc',
		plugin_dir_url( __FILE__ ) . 'build/index.js',
		array_merge( $asset['dependencies'], array( 'wp-sync' ) ),
		$asset['version'],
		array( 'in_footer' => false )
	);

	wp_localize_script( 'pantheon-rtc', 'pantheonRtc', array(
		'wsUrl' => PANTHEON_RTC_WS_URL,
	) );
}
