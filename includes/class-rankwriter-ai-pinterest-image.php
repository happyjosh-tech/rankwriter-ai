<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side pin image renderer using PHP GD.
 *
 * Produces a 1000×1500 (Pinterest 2:3) image by:
 *   1. Loading the post's featured image (or a niche-tinted solid background
 *      if no featured image exists) and resizing/cropping to 1000×1500.
 *   2. Painting a semi-transparent overlay band across the bottom 60%.
 *   3. Rendering the overlay headline + optional secondary line in bold
 *      typography on the overlay.
 *   4. Adding a small site footer (site name + "Read more →").
 *   5. Saving as a JPEG attachment in the WP media library and tagging it
 *      back to the pin row.
 *
 * Falls back gracefully when:
 *   - GD isn't available  → returns WP_Error
 *   - No TTF font found   → uses GD's bitmap fonts (lower quality but works)
 */
class RankWriter_AI_Pinterest_Image {

	const WIDTH  = 1000;
	const HEIGHT = 1500;

	public static function is_available() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Render the overlay image for a pin and attach to the media library.
	 *
	 * @return int|WP_Error Attachment ID on success.
	 */
	public function render_for_pin( $pin_id ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'rwai_no_gd', __( 'PHP GD is not available on this server.', 'rankwriter-ai' ) );
		}
		$engine = new RankWriter_AI_Pinterest_Engine();
		$pin    = $engine->get_pin( $pin_id );
		if ( ! $pin ) {
			return new WP_Error( 'rwai_no_pin', __( 'Pin not found.', 'rankwriter-ai' ) );
		}

		$this->current_niche = isset( $pin['niche'] ) ? $pin['niche'] : 'general';

		$canvas = $this->create_canvas( $pin );
		if ( ! $canvas ) {
			return new WP_Error( 'rwai_no_canvas', __( 'Could not create canvas.', 'rankwriter-ai' ) );
		}

		$this->draw_overlay_band( $canvas, $pin['niche'] );
		$this->draw_headline( $canvas, $pin['overlay_text'], $pin['overlay_secondary'] );
		$this->draw_footer( $canvas );

		$attach_id = $this->save_to_media( $canvas, $pin );
		imagedestroy( $canvas );

		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}
		$engine->update_pin( $pin_id, array( 'image_attachment_id' => $attach_id ) );
		update_post_meta( $attach_id, '_rwai_pinterest_pin_id', $pin_id );
		return $attach_id;
	}

	/**
	 * Build the 1000×1500 base canvas. Uses the related post's featured
	 * image if available; otherwise a niche-tinted gradient.
	 */
	private function create_canvas( $pin ) {
		$canvas = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( ! $canvas ) {
			return null;
		}

		$thumb_id = 0;
		if ( ! empty( $pin['post_id'] ) ) {
			$thumb_id = get_post_thumbnail_id( $pin['post_id'] );
		}

		$drawn_image = false;
		if ( $thumb_id ) {
			$path = get_attached_file( $thumb_id );
			if ( $path && file_exists( $path ) ) {
				$src = $this->load_image_from_path( $path );
				if ( $src ) {
					$this->draw_cover_image( $canvas, $src );
					imagedestroy( $src );
					$drawn_image = true;
				}
			}
		}

		if ( ! $drawn_image ) {
			$this->draw_niche_background( $canvas, $pin['niche'] );
		}
		return $canvas;
	}

	private function load_image_from_path( $path ) {
		$info = @getimagesize( $path );
		if ( ! $info ) {
			return null;
		}
		switch ( $info[2] ) {
			case IMAGETYPE_JPEG: return @imagecreatefromjpeg( $path );
			case IMAGETYPE_PNG:  return @imagecreatefrompng( $path );
			case IMAGETYPE_GIF:  return @imagecreatefromgif( $path );
			case IMAGETYPE_WEBP:
				if ( function_exists( 'imagecreatefromwebp' ) ) {
					return @imagecreatefromwebp( $path );
				}
				return null;
		}
		return null;
	}

	/**
	 * Center-crop-resize the source image to cover the 1000×1500 canvas.
	 */
	private function draw_cover_image( $canvas, $src ) {
		$sw = imagesx( $src );
		$sh = imagesy( $src );
		$canvas_ratio = self::WIDTH / self::HEIGHT;
		$src_ratio    = $sw / $sh;

		if ( $src_ratio > $canvas_ratio ) {
			$crop_w = (int) round( $sh * $canvas_ratio );
			$crop_h = $sh;
			$crop_x = (int) round( ( $sw - $crop_w ) / 2 );
			$crop_y = 0;
		} else {
			$crop_w = $sw;
			$crop_h = (int) round( $sw / $canvas_ratio );
			$crop_x = 0;
			$crop_y = (int) round( ( $sh - $crop_h ) / 2 );
		}
		imagecopyresampled( $canvas, $src, 0, 0, $crop_x, $crop_y, self::WIDTH, self::HEIGHT, $crop_w, $crop_h );
	}

	private function niche_palette( $niche ) {
		// Each niche → [top color RGB, bottom color RGB] for a vertical gradient.
		$p = array(
			'fashion'      => array( array( 244, 220, 230 ), array( 207, 170, 187 ) ),
			'pets'         => array( array( 255, 238, 210 ), array( 226, 167, 124 ) ),
			'recipes'      => array( array( 255, 230, 200 ), array( 220, 130,  80 ) ),
			'quotes'       => array( array(  50,  50,  70 ), array(  20,  20,  35 ) ),
			'scholarships' => array( array( 230, 240, 255 ), array(  90, 130, 200 ) ),
			'travel'       => array( array( 200, 220, 240 ), array(  50, 110, 170 ) ),
			'fitness'      => array( array( 220, 240, 230 ), array(  40, 130,  90 ) ),
			'hairstyles'   => array( array( 245, 235, 225 ), array( 210, 180, 160 ) ),
			'home_decor'   => array( array( 240, 235, 225 ), array( 180, 160, 140 ) ),
			'motivation'   => array( array(  40,  50,  70 ), array(  10,  15,  30 ) ),
			'general'      => array( array( 230, 230, 240 ), array(  90,  90, 120 ) ),
		);
		return isset( $p[ $niche ] ) ? $p[ $niche ] : $p['general'];
	}

	private function draw_niche_background( $canvas, $niche ) {
		list( $top, $bottom ) = $this->niche_palette( $niche );
		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$ratio = $y / self::HEIGHT;
			$r     = (int) round( $top[0] + ( $bottom[0] - $top[0] ) * $ratio );
			$g     = (int) round( $top[1] + ( $bottom[1] - $top[1] ) * $ratio );
			$b     = (int) round( $top[2] + ( $bottom[2] - $top[2] ) * $ratio );
			$color = imagecolorallocate( $canvas, $r, $g, $b );
			imageline( $canvas, 0, $y, self::WIDTH, $y, $color );
		}
	}

	/**
	 * Paint a semi-opaque dark band across the bottom 60% so overlay text
	 * reads cleanly regardless of background image content.
	 */
	private function draw_overlay_band( $canvas, $niche ) {
		$band_top    = (int) round( self::HEIGHT * 0.40 );
		$band_height = self::HEIGHT - $band_top;
		// Use imagefilledrectangle with a semi-transparent layer.
		$layer = imagecreatetruecolor( self::WIDTH, $band_height );
		// Dark for typography niches, light for image-led niches.
		$light_niches = array( 'recipes', 'pets', 'fashion', 'hairstyles', 'home_decor', 'travel', 'fitness', 'scholarships' );
		if ( in_array( $niche, $light_niches, true ) ) {
			$bg = imagecolorallocate( $layer, 255, 255, 255 );
		} else {
			$bg = imagecolorallocate( $layer, 10, 10, 30 );
		}
		imagefill( $layer, 0, 0, $bg );
		imagecopymerge( $canvas, $layer, 0, $band_top, 0, 0, self::WIDTH, $band_height, 78 );
		imagedestroy( $layer );
	}

	private function pick_text_color( $niche ) {
		$light_niches = array( 'recipes', 'pets', 'fashion', 'hairstyles', 'home_decor', 'travel', 'fitness', 'scholarships' );
		return in_array( $niche, $light_niches, true ) ? array( 30, 30, 50 ) : array( 250, 250, 245 );
	}

	private function draw_headline( $canvas, $primary, $secondary ) {
		$primary   = trim( (string) $primary );
		$secondary = trim( (string) $secondary );
		if ( '' === $primary ) {
			return;
		}

		$niche = $this->niche_for_canvas( $canvas );
		list( $tr, $tg, $tb ) = $this->pick_text_color( $niche );
		$text_color = imagecolorallocate( $canvas, $tr, $tg, $tb );

		$font_path = $this->find_font();

		// Primary headline area: y = 650 to y = 1200, full width with 80px padding.
		if ( $font_path ) {
			$this->render_wrapped_ttf( $canvas, $primary, $font_path, 72, $text_color, 80, 720, self::WIDTH - 160, 12 );
			if ( '' !== $secondary ) {
				$this->render_wrapped_ttf( $canvas, $secondary, $font_path, 36, $text_color, 80, 1100, self::WIDTH - 160, 6 );
			}
		} else {
			$this->render_bitmap_text( $canvas, $primary, 80, 760, $text_color );
			if ( '' !== $secondary ) {
				$this->render_bitmap_text( $canvas, $secondary, 80, 1080, $text_color );
			}
		}
	}

	/**
	 * Word-wrap text to fit within max_width at the given font size, then
	 * render the lines vertically anchored at start_y. Lines that overflow
	 * beyond ~4 lines get truncated with an ellipsis.
	 */
	private function render_wrapped_ttf( $canvas, $text, $font, $size, $color, $start_x, $start_y, $max_width, $extra_leading = 8 ) {
		$words = preg_split( '/\s+/', $text );
		$lines = array();
		$current = '';
		foreach ( $words as $w ) {
			$try = '' === $current ? $w : ( $current . ' ' . $w );
			$bbox = imagettfbbox( $size, 0, $font, $try );
			$width = $bbox[2] - $bbox[0];
			if ( $width > $max_width && '' !== $current ) {
				$lines[]   = $current;
				$current = $w;
			} else {
				$current = $try;
			}
		}
		if ( '' !== $current ) {
			$lines[] = $current;
		}

		$line_h = $size + $extra_leading;
		$max_lines = 5;
		if ( count( $lines ) > $max_lines ) {
			$lines           = array_slice( $lines, 0, $max_lines );
			$lines[ count( $lines ) - 1 ] = rtrim( $lines[ count( $lines ) - 1 ], '.' ) . '…';
		}
		$y = $start_y;
		foreach ( $lines as $line ) {
			imagettftext( $canvas, $size, 0, $start_x, $y, $color, $font, $line );
			$y += $line_h;
		}
	}

	private function render_bitmap_text( $canvas, $text, $x, $y, $color ) {
		// imagestring tops out at font size 5 (~9px wide chars); pad with
		// imagestring repeats for a chunky effect.
		$font = 5;
		imagestring( $canvas, $font, $x, $y, $text, $color );
	}

	private function draw_footer( $canvas ) {
		$niche = $this->niche_for_canvas( $canvas );
		list( $tr, $tg, $tb ) = $this->pick_text_color( $niche );
		$footer_color = imagecolorallocate( $canvas, $tr, $tg, $tb );
		$font_path    = $this->find_font();
		$site_name    = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$footer       = mb_strtoupper( substr( $site_name, 0, 28 ) ) . '  ·  READ MORE →';
		if ( $font_path ) {
			imagettftext( $canvas, 20, 0, 80, self::HEIGHT - 70, $footer_color, $font_path, $footer );
		} else {
			imagestring( $canvas, 4, 80, self::HEIGHT - 80, $footer, $footer_color );
		}
	}

	/**
	 * Best-effort font discovery. Trades off quality vs portability:
	 *   1. User-set custom font in plugin settings
	 *   2. Common Linux truetype paths (DejaVu, Liberation)
	 *   3. Common macOS paths (Helvetica)
	 *   4. NULL → caller falls back to bitmap text
	 */
	private function find_font() {
		$user_font = (string) RankWriter_AI_Helpers::get_setting( 'pinterest_font_path', '' );
		if ( $user_font && is_readable( $user_font ) ) {
			return $user_font;
		}
		$candidates = array(
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
			'/Library/Fonts/HelveticaNeue.dfont',
			'/System/Library/Fonts/Helvetica.ttc',
			'/Library/Fonts/Arial Bold.ttf',
			'C:\\Windows\\Fonts\\arialbd.ttf',
		);
		foreach ( $candidates as $f ) {
			if ( is_readable( $f ) ) {
				return $f;
			}
		}
		return '';
	}

	private function niche_for_canvas( $canvas ) {
		// Stash niche on the canvas via attached_object isn't possible in
		// GD — we just re-query via the most-recently-rendered pin instead.
		// In practice this method is called inline with draw_headline /
		// draw_footer, so we pass it through a property.
		return isset( $this->current_niche ) ? $this->current_niche : 'general';
	}

	private $current_niche = 'general';

	private function save_to_media( $canvas, $pin ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'rwai_no_uploads', $uploads['error'] );
		}
		$filename = 'rwai-pin-' . $pin['id'] . '-' . wp_generate_password( 6, false, false ) . '.jpg';
		$path     = trailingslashit( $uploads['path'] ) . $filename;
		$url      = trailingslashit( $uploads['url'] ) . $filename;

		if ( ! imagejpeg( $canvas, $path, 88 ) ) {
			return new WP_Error( 'rwai_jpeg_fail', __( 'Could not write JPEG to disk.', 'rankwriter-ai' ) );
		}

		$attachment = array(
			'guid'           => $url,
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Pin: ' . mb_substr( $pin['title'], 0, 100 ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $path, $pin['post_id'] ?: 0 );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return new WP_Error( 'rwai_attach_fail', __( 'Could not insert attachment.', 'rankwriter-ai' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attach_id, $path );
		wp_update_attachment_metadata( $attach_id, $metadata );
		update_post_meta( $attach_id, '_wp_attachment_image_alt', $pin['overlay_text'] );
		return (int) $attach_id;
	}

}
