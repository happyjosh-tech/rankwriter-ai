<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end image hardening + optional bulk back-end optimization.
 *
 * Front-end (runs on every page render):
 *   - Adds loading="lazy" + decoding="async" to <img> tags, BUT skips
 *     the first content image (so we don't lazy-load the LCP element).
 *   - Adds width / height attributes when missing — biggest single
 *     contributor to CLS scores.
 *   - Swaps to WebP when a same-name .webp version exists alongside
 *     the original (we generate those via the bulk batch).
 *
 * Back-end (manual, runs from the admin button):
 *   - bulk_generate_webp(): walks the media library, makes a .webp next
 *     to every JPEG / PNG that doesn't have one. Always keeps the
 *     original (rollback-safe).
 *
 * We deliberately never destructively re-compress originals. If you
 * need lossy compression beyond WebP, hand off to an image-CDN — but
 * the originals stay untouched on disk.
 */
class RankWriter_AI_Image_Optimizer {

	const OPTION_STATS = 'rwai_speed_image_stats';

	private $settings;
	private $first_image_seen = false;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks() {
		if ( is_admin() || is_user_logged_in() ) {
			return;
		}
		if ( ! empty( $this->settings['image_lazyload'] ) || ! empty( $this->settings['image_dims'] ) || ! empty( $this->settings['image_webp'] ) ) {
			add_filter( 'the_content',         array( $this, 'process_html' ), 999 );
			add_filter( 'post_thumbnail_html', array( $this, 'process_html' ), 999 );
			add_filter( 'widget_text',         array( $this, 'process_html' ), 999 );
		}
	}

	/**
	 * One-shot HTML processor. Walks every <img> and applies the
	 * enabled tweaks in a single pass.
	 */
	public function process_html( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<img' ) ) {
			return $html;
		}
		$lazy = ! empty( $this->settings['image_lazyload'] );
		$dims = ! empty( $this->settings['image_dims'] );
		$webp = ! empty( $this->settings['image_webp'] );

		return preg_replace_callback( '#<img\b([^>]*)>#is', function ( $m ) use ( $lazy, $dims, $webp ) {
			$attrs = $m[1];

			// 1) WebP swap — only when a same-name .webp exists locally.
			if ( $webp ) {
				$attrs = $this->swap_to_webp( $attrs );
			}

			// 2) Width / height — only when both are missing AND we can
			//    cheaply read the dimensions from the local file.
			if ( $dims && ! preg_match( '/\bwidth\s*=/i', $attrs ) && ! preg_match( '/\bheight\s*=/i', $attrs ) ) {
				$attrs = $this->inject_dimensions( $attrs );
			}

			// 3) Lazy-load — skip the first image (likely LCP) plus any
			//    image that already has loading= specified or has the
			//    `data-no-lazy` opt-out marker.
			if ( $lazy ) {
				if ( $this->first_image_seen
					|| preg_match( '/\bloading\s*=/i', $attrs )
					|| preg_match( '/\bdata-no-lazy/i', $attrs )
					|| preg_match( '/\bfetchpriority\s*=\s*[\'"]high[\'"]/i', $attrs ) ) {
					// First image needs eager loading to win LCP; mark and skip.
					$this->first_image_seen = true;
				} else {
					$attrs .= ' loading="lazy" decoding="async"';
					$this->first_image_seen = true;
				}
			}

			return '<img' . $attrs . '>';
		}, $html );
	}

	private function swap_to_webp( $attrs ) {
		if ( ! preg_match( '/\bsrc\s*=\s*"([^"]+)"/i', $attrs, $src ) ) {
			return $attrs;
		}
		$webp_path = $this->local_webp_for( $src[1] );
		if ( ! $webp_path ) {
			return $attrs;
		}
		$webp_url = $this->url_for_local( $webp_path );
		if ( ! $webp_url ) {
			return $attrs;
		}
		return preg_replace( '#\bsrc\s*=\s*"[^"]+"#i', 'src="' . esc_url( $webp_url ) . '"', $attrs, 1 );
	}

	private function inject_dimensions( $attrs ) {
		if ( ! preg_match( '/\bsrc\s*=\s*"([^"]+)"/i', $attrs, $src ) ) {
			return $attrs;
		}
		$local = $this->path_for_url( $src[1] );
		if ( ! $local || ! file_exists( $local ) ) {
			return $attrs;
		}
		$size = @getimagesize( $local );
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			return $attrs;
		}
		return $attrs . ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
	}

	private function path_for_url( $url ) {
		$uploads = wp_get_upload_dir();
		if ( 0 !== strpos( $url, $uploads['baseurl'] ) ) {
			return null;
		}
		return $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );
	}

	private function url_for_local( $path ) {
		$uploads = wp_get_upload_dir();
		if ( 0 !== strpos( $path, $uploads['basedir'] ) ) {
			return null;
		}
		return $uploads['baseurl'] . substr( $path, strlen( $uploads['basedir'] ) );
	}

	private function local_webp_for( $url ) {
		$local = $this->path_for_url( $url );
		if ( ! $local ) {
			return null;
		}
		$webp = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $local );
		if ( $webp === $local ) {
			return null;
		}
		return file_exists( $webp ) ? $webp : null;
	}

	/* ============================ Bulk WebP generator ============================ */

	/**
	 * Walk the media library and create a .webp next to every JPEG/PNG
	 * that doesn't already have one. Returns a stats array so the
	 * admin can show a "X images converted" toast.
	 */
	public function bulk_generate_webp( $limit = 50 ) {
		if ( ! $this->webp_supported() ) {
			return new WP_Error( 'rwai_no_webp', __( 'This server does not have GD or Imagick with WebP support.', 'rankwriter-ai' ) );
		}
		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'post_mime_type' => array( 'image/jpeg', 'image/png' ),
			'post_status'    => 'inherit',
			'posts_per_page' => max( 1, (int) $limit ),
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_rwai_webp_generated',
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		$stats = (array) get_option( self::OPTION_STATS, array(
			'webp_generated' => 0,
			'webp_skipped'   => 0,
			'last_run_at'    => '',
		) );

		foreach ( $attachments as $id ) {
			$path = get_attached_file( $id );
			if ( ! $path || ! file_exists( $path ) ) {
				update_post_meta( $id, '_rwai_webp_generated', 'missing' );
				$stats['webp_skipped']++;
				continue;
			}
			$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $path );
			if ( $webp_path === $path ) {
				update_post_meta( $id, '_rwai_webp_generated', 'unsupported_ext' );
				$stats['webp_skipped']++;
				continue;
			}
			if ( file_exists( $webp_path ) ) {
				update_post_meta( $id, '_rwai_webp_generated', 'already_existed' );
				$stats['webp_generated']++;
				continue;
			}
			$ok = $this->convert_to_webp( $path, $webp_path );
			if ( $ok ) {
				update_post_meta( $id, '_rwai_webp_generated', 'ok' );
				$stats['webp_generated']++;
			} else {
				update_post_meta( $id, '_rwai_webp_generated', 'failed' );
				$stats['webp_skipped']++;
			}
		}

		$stats['last_run_at'] = current_time( 'mysql' );
		update_option( self::OPTION_STATS, $stats, false );

		return array(
			'processed' => count( $attachments ),
			'stats'     => $stats,
		);
	}

	public function webp_supported() {
		if ( function_exists( 'imagewebp' ) ) {
			return true;
		}
		if ( class_exists( 'Imagick' ) ) {
			$im = new Imagick();
			$formats = $im->queryFormats();
			return in_array( 'WEBP', $formats, true );
		}
		return false;
	}

	private function convert_to_webp( $src, $dst, $quality = 82 ) {
		$type = wp_check_filetype( $src );
		$mime = isset( $type['type'] ) ? $type['type'] : '';
		if ( function_exists( 'imagewebp' ) ) {
			$img = null;
			if ( 'image/jpeg' === $mime ) {
				$img = @imagecreatefromjpeg( $src );
			} elseif ( 'image/png' === $mime ) {
				$img = @imagecreatefrompng( $src );
				if ( $img ) {
					imagepalettetotruecolor( $img );
					imagealphablending( $img, true );
					imagesavealpha( $img, true );
				}
			}
			if ( ! $img ) { return false; }
			$ok = @imagewebp( $img, $dst, $quality );
			imagedestroy( $img );
			return $ok;
		}
		if ( class_exists( 'Imagick' ) ) {
			try {
				$im = new Imagick( $src );
				$im->setImageFormat( 'webp' );
				$im->setImageCompressionQuality( $quality );
				$ok = $im->writeImage( $dst );
				$im->clear();
				return (bool) $ok;
			} catch ( Exception $e ) {
				return false;
			}
		}
		return false;
	}

	public function get_stats() {
		return (array) get_option( self::OPTION_STATS, array(
			'webp_generated' => 0,
			'webp_skipped'   => 0,
			'last_run_at'    => '',
		) );
	}
}
