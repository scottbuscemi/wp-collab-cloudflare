# WP Collab Cloudflare

Proof-of-concept that offloads WordPress 7.0's real-time collaboration (RTC) to a Cloudflare Workers relay, replacing the default HTTP polling transport with WebSockets over Durable Objects.

## Why

WordPress 7.0 introduces collaborative editing powered by Yjs. By default it syncs via HTTP polling (every 1-4 seconds). This works, but each poll holds a PHP worker for the duration of the request. On hosts with limited concurrency, no WebSocket support, or stateless containers, this becomes a bottleneck.

This project moves the sync relay to Cloudflare's edge:

- **Durable Objects** coordinate document state with single-threaded consistency
- **WebSocket Hibernation** means idle editing sessions cost nothing
- **PHP workers** are freed from long-polling — they only handle normal page/API requests

## Architecture

```
Browser A ──WebSocket──┐
                        ├── Cloudflare Durable Object (Yjs relay) ──persists to DO storage
Browser B ──WebSocket──┘
```

Four pieces work together:

| Component | Path | Purpose |
|-----------|------|---------|
| **Worker** | [`worker/`](worker/) | Cloudflare Worker + Durable Object running [y-partyserver](https://github.com/y-sweet/y-partyserver) as a Yjs sync relay |
| **Plugin** | [`plugin/wp-collab-cf/`](plugin/wp-collab-cf/) | WordPress plugin that hooks into the `sync.providers` filter to swap HTTP polling for a WebSocket connection to the Worker |
| **MU-Plugin** | [`mu-plugin/`](mu-plugin/) | Enables `WP_ALLOW_COLLABORATION` and sets the `WP_COLLAB_CF_WS_URL` constant that the plugin reads |
| **Demo Plugin** | [`plugin/wp-collab-cf-demo/`](plugin/wp-collab-cf-demo/) | Optional. Magic link that creates temporary guest users restricted to a single demo post, useful for sharing a live demo |

## Setup

### 1. Deploy the Worker

```bash
cd worker
npm install
# Authenticate with Cloudflare (or set CLOUDFLARE_ACCOUNT_ID + CLOUDFLARE_API_TOKEN)
wrangler login
wrangler deploy
```

Note the deployed URL (e.g. `wss://wp-collab-cloudflare.your-subdomain.workers.dev`).

### 2. Configure WordPress

Copy the [mu-plugin](mu-plugin/wp-collab-cf-config.php) to `wp-content/mu-plugins/` and set your Worker URL:

```php
define( 'WP_COLLAB_CF_WS_URL', 'wss://wp-collab-cloudflare.your-subdomain.workers.dev' );
```

### 3. Install the Plugin

```bash
cd plugin/wp-collab-cf
npm install
npm run build
```

Copy the `plugin/wp-collab-cf/` directory (with the `build/` output) into `wp-content/plugins/` and activate it.

### 4. Test

Open the same post in two browser tabs. Edits in one tab should appear in the other in real time.

## Demo Plugin (Optional)

The [demo plugin](plugin/wp-collab-cf-demo/) provides a magic link for sharing a live demo publicly (e.g. on social media). When someone visits the link:

1. A temporary guest user is created automatically (e.g. "Guest A3X9B2")
2. They're logged in and redirected straight to the post editor
3. All admin UI is hidden — they only see the block editor
4. They can only edit the designated demo post, nothing else

### Setup

1. Copy `plugin/wp-collab-cf-demo/` into `wp-content/plugins/` and activate it.
2. Create a post to use as the demo and note its ID.
3. Set the post ID via WP-CLI or in wp-config:

```php
define( 'WP_COLLAB_CF_DEMO_POST_ID', 123 );
```

Or via option: `wp option update wp_collab_cf_demo_post_id 123`

4. Share the magic link: `https://yoursite.com/?wp-collab-demo=1`

## How It Works

1. The **mu-plugin** defines `WP_ALLOW_COLLABORATION` (enabling RTC) and `WP_COLLAB_CF_WS_URL` (the relay endpoint).

2. The **plugin** uses the [`sync.providers`](https://developer.wordpress.org/reference/hooks/sync-providers/) filter to replace WordPress's default HTTP polling provider with a WebSocket provider that connects to the Cloudflare Worker. It reuses WordPress's bundled Yjs instance (via `wp.sync.Y`) to avoid duplicate library issues.

3. The **Worker** uses [y-partyserver](https://github.com/y-sweet/y-partyserver) (built on [PartyServer](https://github.com/threepointone/partyserver)) to run a Yjs sync relay inside a Durable Object. Each post gets its own Durable Object instance, identified by a room name derived from the post type and ID. WebSocket Hibernation keeps idle rooms free.
