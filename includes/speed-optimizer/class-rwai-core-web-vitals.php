<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Targeted Core-Web-Vitals nudges that don't fit cleanly into the
 * other sub-modules:
 *
 *   - LCP: hint the first content image with fetchpriority="high" so
 *     the browser starts downloading it before parsing the rest of the
 *     <head>.
 *   - LCP: add `<link rel="preload">` for the featured image of the
 *     current post (if there is one) since that's almost always the
 *     LCP element on a single-post page.
 *   - CLS: handled by the Image Optimizer's width/height injection.
 *   - FCP: preload a user-provided list of fonts so the first paint
 *     doesn't FOUT.
 *
 * Everything here is additive (extra <link> tags, extra HTML
 * attributes) — never removes or rewrites existing markup.
 */
class RankWriter_AI_Core_Web_Vitals {

	private $settings;
	private $hero_marked = false;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( ! empty( $this->settings['cwv_preload_featured'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_featured_image' ), 1 );
		}
		if ( ! empty( $this->settings['cwv_preload_fonts'] ) ) {
			add_action( 'wp_head', array( $this, 'preload_fonts' ), 1 );
		}
		if ( ! empty( $this->settings['cwv_fetchpriority_lcp'] ) ) {
			// 5 must run BEFORE the Image_Optimizer's filters (which run at
			// 999) so the first image gets the priority hint before
			// loading="lazy" is added (those are mutually exclusive
			// optimizations on the same element).
			add_filter( 'the_content',         array( $this, 'mark_first_image_high_priority' ), 5 );
			add_filter( 'post_thumbnail_html', array( $this, 'mark_first_image_high_priority' ), 5 );
		}
	}

	public function preload_featured_image() {
		if ( ! is_singular() ) {
			return;
		}
		$thumb_id = get_post_thumbnail_id();
		if ( ! $thumb_id ) {
			return;
		}
		$src = wp_get_attachment_image_src( $thumb_id, 'large' );
		if ( ! $src || empty( $src[0] ) ) {
			return;
		}
		echo "\n<link rel=\"preload\" as=\"image\" href=\"" . esc_url( $src[0] ) . "\" fetchpriority=\"high\">\n";
	}

	public function preload_fonts() {
		$urls = isset( $this->settings['preload_font_urls'] ) ? (string) $this->settings['preload_font_urls'] : '';
		foreach ( preg_split( "/\r?\n/", $urls ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$ext = strtolower( pathinfo( wp_parse_url( $line, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
			$type = 'font/woff2';
			if ( 'woff' === $ext ) { $type = 'font/woff'; }
			elseif ( 'ttf' === $ext ) { $type = 'font/ttf'; }
			elseif ( 'otf' === $ext ) { $type = 'font/otf'; }
			echo "\n<link rel=\"preload\" as=\"font\" type=\"" . esc_attr( $type ) . "\" crossorigin href=\"" . esc_url( $line ) . "\">";
		}
	}

	public function mark_first_image_high_priority( $html ) {
		if ( $this->hero_marked || ! is_string( $html ) ) {
			return $html;
		}
		$replaced = preg_replace_callback( '#<img\b([^>]*)>#i', function ( $m ) {
			if ( $this->hero_marked ) {
				return $m[0];
			}
			$attrs = $m[1];
			// Don't double-mark if the theme already set fetchpriority.
			if ( ! preg_match( '/\bfetchpriority\s*=/i', $attrs ) ) {
				$attrs .= ' fetchpriority="high"';
			}
			// Make sure it isn't lazy-loaded (LCP element must load eager).
			$attrs = preg_replace( '/\bloading\s*=\s*"(lazy)"/i', 'loading="eager"', $attrs );
			$this->hero_marked = true;
			return '<img' . $attrs . '>';
		}, $html, 1 );
		return is_string( $replaced ) ? $replaced : $html;
	}
}
