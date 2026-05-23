<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RankWriter_AI_Helpers {

	public static function get_settings() {
		$defaults = array(
			'claude_api_key'         => '',
			'claude_model'           => 'claude-opus-4-7',
			'gemini_api_key'         => '',
			'gemini_model'           => 'gemini-2.5-flash',
			'max_tokens'             => 8000,
			'analyze_post_limit'     => 200,
			'auto_analyze_frequency' => 'weekly',
			'default_image_style'    => 'realistic',
			'default_word_count'     => 1500,
			'humanize_pass'          => 1,
			'serpapi_key'            => '',
			'dataforseo_login'       => '',
			'dataforseo_password'    => '',
			'competitor_domains'     => '',
			'default_country'        => 'US',
			'pexels_api_key'         => '',
			'unsplash_access_key'    => '',
			'pinterest_auto_generate'      => 0,
			'pinterest_pins_per_post'      => 3,
			'pinterest_auto_render_images' => 1,
			'pinterest_font_path'          => '',
			'enabled_languages'            => 'en',
			'auto_translate_on_publish'    => 0,
			'humanize_strength'            => 'medium',
			'humanize_tone'                => 'professional',
			'humanize_personality'         => 'experienced_practitioner',
			'humanize_readability'         => 'off',
		);
		$saved = get_option( 'rwai_settings', array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	public static function get_setting( $key, $fallback = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
	}

	public static function update_settings( array $values ) {
		$current = self::get_settings();
		$merged  = array_merge( $current, $values );
		update_option( 'rwai_settings', $merged );
		return $merged;
	}

	public static function stopwords() {
		return array(
			'the','and','for','with','that','this','from','your','have','will','was','were','been','are',
			'you','but','not','can','all','any','our','out','one','two','their','they','them','his','her',
			'its','about','into','these','those','than','then','what','when','where','which','who','how',
			'a','an','as','at','be','by','do','he','if','in','is','it','of','on','or','so','to','up','we',
			'i','my','me','am','no','yes','if','also','more','most','some','such','only','own','same','too',
			'very','just','now','also','like','use','using','used','make','made','many','much','other',
		);
	}

	public static function tokenize( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9\s\-]/', ' ', $text );
		$parts = preg_split( '/\s+/', trim( (string) $text ) );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$stop = array_flip( self::stopwords() );
		$out  = array();
		foreach ( $parts as $p ) {
			if ( strlen( $p ) < 4 ) {
				continue;
			}
			if ( isset( $stop[ $p ] ) ) {
				continue;
			}
			$out[] = $p;
		}
		return $out;
	}

	public static function word_count( $text ) {
		$clean = wp_strip_all_tags( (string) $text );
		$clean = preg_replace( '/\s+/', ' ', $clean );
		if ( '' === trim( (string) $clean ) ) {
			return 0;
		}
		return str_word_count( $clean );
	}

	public static function ext_link_host( $url ) {
		$parsed = wp_parse_url( $url );
		return isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
	}

	public static function site_host() {
		return self::ext_link_host( home_url() );
	}

	public static function format_number( $n ) {
		return number_format_i18n( (float) $n );
	}

	public static function admin_url( $page, $args = array() ) {
		$base = admin_url( 'admin.php?page=' . $page );
		if ( ! empty( $args ) ) {
			$base = add_query_arg( $args, $base );
		}
		return $base;
	}
}
