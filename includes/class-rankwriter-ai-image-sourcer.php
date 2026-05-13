<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sources a featured image for generated articles, biased by the Category
 * Profile's "default image style" preference.
 *
 * Provider order:
 *   1. Pexels    — if user provides PEXELS_API_KEY (best quality)
 *   2. Unsplash  — if user provides UNSPLASH_ACCESS_KEY
 *   3. Openverse — free, no key required (CC-licensed pool)
 *
 * Downloads the chosen image into the WP media library and sets it as the
 * post's featured image with style-appropriate alt text.
 */
class RankWriter_AI_Image_Sourcer {

	const PEXELS_ENDPOINT    = 'https://api.pexels.com/v1/search';
	const UNSPLASH_ENDPOINT  = 'https://api.unsplash.com/search/photos';
	const OPENVERSE_ENDPOINT = 'https://api.openverse.engineering/v1/images/';

	public function source_and_attach( $post_id, $query, $style = 'realistic' ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return new WP_Error( 'rwai_no_post', __( 'Missing post ID.', 'rankwriter-ai' ) );
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return get_post_thumbnail_id( $post_id );
		}

		$shaped_query = $this->style_query( $query, $style );
		$found        = $this->search( $shaped_query, $style );
		if ( is_wp_error( $found ) || empty( $found['url'] ) ) {
			return $found instanceof WP_Error ? $found : new WP_Error( 'rwai_no_image', __( 'No image found.', 'rankwriter-ai' ) );
		}

		$attach_id = $this->sideload( $found['url'], $post_id, $found['alt'], $found['photographer'] );
		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}
		set_post_thumbnail( $post_id, $attach_id );
		update_post_meta( $post_id, '_rwai_image_source', $found['source'] );
		update_post_meta( $post_id, '_rwai_image_credit', $found['photographer'] );
		return $attach_id;
	}

	private function style_query( $query, $style ) {
		$map = array(
			'realistic'    => '',
			'illustration' => ' illustration vector',
			'infographic'  => ' infographic',
			'screenshot'   => ' screenshot product',
			'cinematic'    => ' cinematic',
			'minimalist'   => ' minimalist flat',
		);
		$suffix = isset( $map[ $style ] ) ? $map[ $style ] : '';
		return trim( $query . $suffix );
	}

	private function search( $query, $style ) {
		$pexels_key = (string) RankWriter_AI_Helpers::get_setting( 'pexels_api_key', '' );
		if ( '' !== $pexels_key ) {
			$r = $this->search_pexels( $query, $pexels_key );
			if ( ! is_wp_error( $r ) && ! empty( $r['url'] ) ) {
				return $r;
			}
		}

		$unsplash_key = (string) RankWriter_AI_Helpers::get_setting( 'unsplash_access_key', '' );
		if ( '' !== $unsplash_key ) {
			$r = $this->search_unsplash( $query, $unsplash_key );
			if ( ! is_wp_error( $r ) && ! empty( $r['url'] ) ) {
				return $r;
			}
		}

		return $this->search_openverse( $query );
	}

	private function search_pexels( $query, $key ) {
		$url = add_query_arg( array( 'query' => $query, 'per_page' => 5, 'orientation' => 'landscape' ), self::PEXELS_ENDPOINT );
		$res = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => $key ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['photos'][0] ) ) {
			return new WP_Error( 'rwai_pexels_empty', 'No Pexels results.' );
		}
		$p = $json['photos'][0];
		return array(
			'source'       => 'pexels',
			'url'          => isset( $p['src']['large'] ) ? $p['src']['large'] : $p['src']['original'],
			'alt'          => isset( $p['alt'] ) && $p['alt'] ? $p['alt'] : $query,
			'photographer' => 'Photo by ' . ( isset( $p['photographer'] ) ? $p['photographer'] : 'Pexels' ) . ' on Pexels',
		);
	}

	private function search_unsplash( $query, $key ) {
		$url = add_query_arg( array( 'query' => $query, 'per_page' => 5, 'orientation' => 'landscape' ), self::UNSPLASH_ENDPOINT );
		$res = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Client-ID ' . $key ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['results'][0] ) ) {
			return new WP_Error( 'rwai_unsplash_empty', 'No Unsplash results.' );
		}
		$p = $json['results'][0];
		return array(
			'source'       => 'unsplash',
			'url'          => isset( $p['urls']['regular'] ) ? $p['urls']['regular'] : $p['urls']['full'],
			'alt'          => isset( $p['alt_description'] ) && $p['alt_description'] ? $p['alt_description'] : $query,
			'photographer' => 'Photo by ' . ( isset( $p['user']['name'] ) ? $p['user']['name'] : 'Unsplash' ) . ' on Unsplash',
		);
	}

	private function search_openverse( $query ) {
		$url = add_query_arg( array(
			'q'             => $query,
			'page_size'     => 5,
			'license_type'  => 'commercial',
			'mature'        => 'false',
		), self::OPENVERSE_ENDPOINT );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['results'][0] ) ) {
			return new WP_Error( 'rwai_openverse_empty', 'No Openverse results.' );
		}
		$p = $json['results'][0];
		return array(
			'source'       => 'openverse',
			'url'          => isset( $p['url'] ) ? $p['url'] : '',
			'alt'          => isset( $p['title'] ) && $p['title'] ? $p['title'] : $query,
			'photographer' => 'Image by ' . ( isset( $p['creator'] ) ? $p['creator'] : 'Openverse' ) . ' via Openverse',
		);
	}

	private function sideload( $url, $post_id, $alt, $credit ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'rwai-image.jpg',
			'tmp_name' => $tmp,
		);
		$attach_id = media_handle_sideload( $file_array, $post_id, $alt );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
			return $attach_id;
		}

		update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
		if ( $credit ) {
			wp_update_post( array(
				'ID'           => $attach_id,
				'post_excerpt' => $credit,
			) );
		}
		return $attach_id;
	}
}
