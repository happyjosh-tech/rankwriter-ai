<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends safe browser-cache and security-friendly response headers, and
 * generates an opt-in `.htaccess` snippet the user can paste in by hand.
 *
 * We deliberately do NOT auto-write `.htaccess`. That file is the single
 * biggest cause of "white screen of death" reports across the plugin
 * ecosystem; if our snippet is wrong on a specific host's setup, the
 * user's whole site goes down. Generating it as copy-pasteable text
 * keeps the responsibility (and the audit trail) on the human admin.
 */
class RankWriter_AI_Browser_Cache {

	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		if ( empty( $this->settings['browser_cache_enabled'] ) ) {
			return;
		}
		add_action( 'send_headers', array( $this, 'send_cache_headers' ) );
	}

	/**
	 * Adds Cache-Control / Expires hints. WordPress front-end pages are
	 * served by PHP — only static files (CSS/JS/img) get the long-life
	 * cache headers from Apache/nginx, not from us. These headers are a
	 * fallback for hosts that don't ship sane defaults.
	 */
	public function send_cache_headers() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( is_404() ) {
			return;
		}
		$ttl = max( 300, (int) ( $this->settings['browser_cache_html_ttl'] ?? 3600 ) );
		header( 'Cache-Control: public, max-age=' . $ttl . ', s-maxage=' . ( $ttl * 2 ) );
		header( 'Vary: Accept-Encoding' );
	}

	/**
	 * Returns an `.htaccess` block the user can paste. Includes:
	 *   - 1-year cache for fonts, images, CSS, JS
	 *   - Gzip/deflate
	 *   - Keep-alive
	 * Wrapped in IfModule guards so it's a no-op on hosts that don't
	 * have the relevant modules loaded.
	 */
	public static function htaccess_snippet() {
		return <<<HTACCESS
# BEGIN RankWriter Speed Optimizer
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css                 "access plus 1 year"
  ExpiresByType application/javascript   "access plus 1 year"
  ExpiresByType application/x-javascript "access plus 1 year"
  ExpiresByType text/javascript          "access plus 1 year"
  ExpiresByType image/jpeg               "access plus 1 year"
  ExpiresByType image/png                "access plus 1 year"
  ExpiresByType image/gif                "access plus 1 year"
  ExpiresByType image/webp               "access plus 1 year"
  ExpiresByType image/svg+xml            "access plus 1 month"
  ExpiresByType image/x-icon             "access plus 1 year"
  ExpiresByType font/woff                "access plus 1 year"
  ExpiresByType font/woff2               "access plus 1 year"
  ExpiresByType application/font-woff    "access plus 1 year"
  ExpiresByType application/font-woff2   "access plus 1 year"
  ExpiresByType application/vnd.ms-fontobject "access plus 1 year"
  ExpiresByType text/html                "access plus 1 hour"
</IfModule>

<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript application/json application/xml application/rss+xml image/svg+xml
</IfModule>

<IfModule mod_headers.c>
  Header set Connection keep-alive
  <FilesMatch "\.(css|js|woff|woff2|ttf|otf|eot|jpg|jpeg|png|gif|webp|svg|ico)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
</IfModule>
# END RankWriter Speed Optimizer
HTACCESS;
	}
}
