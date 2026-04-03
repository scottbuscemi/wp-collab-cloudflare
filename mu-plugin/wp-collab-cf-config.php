<?php
/**
 * WP Collab Cloudflare Configuration
 *
 * Drop this file into wp-content/mu-plugins/ to enable WordPress 7.0
 * real-time collaboration via a Cloudflare Workers relay.
 *
 * Set WP_COLLAB_CF_WS_URL to your deployed Worker's WebSocket URL.
 */

// Enable real-time collaboration.
if ( ! defined( 'WP_ALLOW_COLLABORATION' ) ) {
	define( 'WP_ALLOW_COLLABORATION', true );
}

// Point the sync provider at your Cloudflare Worker.
if ( ! defined( 'WP_COLLAB_CF_WS_URL' ) ) {
	define( 'WP_COLLAB_CF_WS_URL', 'wss://YOUR-WORKER-NAME.YOUR-SUBDOMAIN.workers.dev' );
}
