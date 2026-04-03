<?php
/**
 * Pantheon RTC Configuration
 *
 * Drop this file into wp-content/mu-plugins/ to enable WordPress 7.0
 * real-time collaboration via the Cloudflare Workers relay.
 *
 * Set PANTHEON_RTC_WS_URL to your deployed Worker's WebSocket URL.
 */

// Enable real-time collaboration.
if ( ! defined( 'WP_ALLOW_COLLABORATION' ) ) {
	define( 'WP_ALLOW_COLLABORATION', true );
}

// Point the sync provider at your Cloudflare Worker.
if ( ! defined( 'PANTHEON_RTC_WS_URL' ) ) {
	define( 'PANTHEON_RTC_WS_URL', 'wss://YOUR-WORKER-NAME.YOUR-SUBDOMAIN.workers.dev' );
}
