<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Language registry + hreflang emitter + per-post language tagging.
 *
 *   languages()                → static registry (6 starters + filter)
 *   default_language()         → English
 *   enabled_codes()            → from settings
 *   get_post_language( $id )   → language code stored on the post
 *   set_post_language( ... )   → save tagging
 *   get_translation_group( $id ) / set_translation_group()
 *   get_translations( $id )    → all sibling translations of this post
 *   register_hooks()           → wp_head hreflang + html-lang dir for RTL
 */
class RankWriter_AI_Language {

	const META_LANG       = '_rwai_lang';
	const META_GROUP      = '_rwai_translation_group';
	const META_SOURCE     = '_rwai_translation_source';
	const META_COUNTRY    = '_rwai_translation_country';

	/**
	 * Built-in language registry. Add more via the `rwai_languages` filter.
	 *
	 * Each entry:
	 *   - name             English label
	 *   - native           Native-script label (shown in UIs)
	 *   - rtl              Right-to-left script
	 *   - default_country  ISO 2-letter country code for keyword research
	 *   - locale           WordPress locale (used for hreflang attribute)
	 */
	public static function languages() {
		$base = array(
			'en' => array( 'name' => 'English',    'native' => 'English',    'rtl' => false, 'default_country' => 'US', 'locale' => 'en_US' ),
			'fr' => array( 'name' => 'French',     'native' => 'Français',   'rtl' => false, 'default_country' => 'FR', 'locale' => 'fr_FR' ),
			'es' => array( 'name' => 'Spanish',    'native' => 'Español',    'rtl' => false, 'default_country' => 'ES', 'locale' => 'es_ES' ),
			'de' => array( 'name' => 'German',     'native' => 'Deutsch',    'rtl' => false, 'default_country' => 'DE', 'locale' => 'de_DE' ),
			'pt' => array( 'name' => 'Portuguese', 'native' => 'Português',  'rtl' => false, 'default_country' => 'BR', 'locale' => 'pt_BR' ),
			'ar' => array( 'name' => 'Arabic',     'native' => 'العربية',     'rtl' => true,  'default_country' => 'SA', 'locale' => 'ar' ),
		);
		return apply_filters( 'rwai_languages', $base );
	}

	public static function language( $code ) {
		$all = self::languages();
		$code = strtolower( (string) $code );
		return isset( $all[ $code ] ) ? $all[ $code ] : null;
	}

	public static function default_language() {
		return 'en';
	}

	/**
	 * Codes the site owner has enabled. Always includes English implicitly.
	 */
	public static function enabled_codes() {
		$saved = (string) RankWriter_AI_Helpers::get_setting( 'enabled_languages', 'en' );
		$codes = array_filter( array_map( function ( $c ) { return strtolower( trim( $c ) ); }, explode( ',', $saved ) ) );
		if ( ! in_array( 'en', $codes, true ) ) {
			array_unshift( $codes, 'en' );
		}
		$valid = array_keys( self::languages() );
		return array_values( array_intersect( $codes, $valid ) );
	}

	/* ============================ Per-post tagging ============================ */

	public static function get_post_language( $post_id ) {
		$lang = (string) get_post_meta( absint( $post_id ), self::META_LANG, true );
		return $lang ?: self::default_language();
	}

	public static function set_post_language( $post_id, $code ) {
		$code = strtolower( (string) $code );
		if ( ! self::language( $code ) ) {
			return false;
		}
		return update_post_meta( absint( $post_id ), self::META_LANG, $code );
	}

	public static function get_translation_group( $post_id ) {
		$group = (string) get_post_meta( absint( $post_id ), self::META_GROUP, true );
		if ( '' === $group ) {
			// Posts without explicit grouping use their own ID as group.
			$group = (string) absint( $post_id );
		}
		return $group;
	}

	public static function set_translation_group( $post_id, $group ) {
		return update_post_meta( absint( $post_id ), self::META_GROUP, (string) $group );
	}

	/**
	 * Return every post that shares the translation group with $post_id.
	 *
	 * @return array of { post_id, lang, locale, url, title, is_self }
	 */
	public static function get_translations( $post_id ) {
		$post_id = absint( $post_id );
		$group   = self::get_translation_group( $post_id );
		$ids     = get_posts( array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::META_GROUP,
					'value' => $group,
				),
			),
		) );
		// Always include the seed post even if it lacks the meta yet.
		if ( ! in_array( $post_id, $ids, true ) ) {
			$ids[] = $post_id;
		}
		$out = array();
		foreach ( $ids as $id ) {
			$lang = self::get_post_language( $id );
			$cfg  = self::language( $lang );
			$out[] = array(
				'post_id' => (int) $id,
				'lang'    => $lang,
				'locale'  => $cfg ? $cfg['locale'] : 'en_US',
				'native'  => $cfg ? $cfg['native'] : $lang,
				'url'     => get_permalink( $id ),
				'title'   => get_the_title( $id ),
				'is_self' => $id === $post_id,
				'status'  => get_post_status( $id ),
			);
		}
		// De-dupe by id (in case the seed got included twice).
		$seen = array();
		$out  = array_values( array_filter( $out, function ( $r ) use ( &$seen ) {
			if ( isset( $seen[ $r['post_id'] ] ) ) { return false; }
			$seen[ $r['post_id'] ] = true;
			return true;
		} ) );
		return $out;
	}

	/* ============================ hreflang + html lang ============================ */

	public static function register_hooks() {
		add_action( 'wp_head', array( __CLASS__, 'output_hreflang' ), 12 );
		add_filter( 'language_attributes', array( __CLASS__, 'override_language_attr' ), 10, 2 );
	}

	public static function output_hreflang() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post_id      = get_the_ID();
		$translations = self::get_translations( $post_id );
		if ( count( $translations ) < 2 ) {
			return;
		}
		echo "\n<!-- RankWriter AI hreflang -->\n";
		foreach ( $translations as $t ) {
			if ( 'publish' !== $t['status'] ) {
				continue;
			}
			$cfg    = self::language( $t['lang'] );
			$locale = $cfg ? str_replace( '_', '-', $cfg['locale'] ) : $t['lang'];
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $locale ),
				esc_url( $t['url'] )
			);
		}
		// x-default → English version if present, otherwise the first published translation.
		$default = null;
		foreach ( $translations as $t ) {
			if ( 'publish' === $t['status'] && 'en' === $t['lang'] ) {
				$default = $t;
				break;
			}
		}
		if ( ! $default ) {
			foreach ( $translations as $t ) {
				if ( 'publish' === $t['status'] ) {
					$default = $t;
					break;
				}
			}
		}
		if ( $default ) {
			printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $default['url'] ) );
		}
	}

	/**
	 * Override <html lang="..."> on singular post pages so each translation
	 * declares its own language to the browser + accessibility tools.
	 */
	public static function override_language_attr( $output, $doctype ) {
		if ( 'html' !== $doctype || ! is_singular( 'post' ) ) {
			return $output;
		}
		global $post;
		if ( ! $post ) {
			return $output;
		}
		$lang = self::get_post_language( $post->ID );
		$cfg  = self::language( $lang );
		if ( ! $cfg ) {
			return $output;
		}
		$locale = str_replace( '_', '-', $cfg['locale'] );
		$output = preg_replace( '/\blang="[^"]*"/', 'lang="' . esc_attr( $locale ) . '"', $output );
		if ( ! empty( $cfg['rtl'] ) ) {
			if ( false === strpos( $output, 'dir=' ) ) {
				$output .= ' dir="rtl"';
			} else {
				$output = preg_replace( '/\bdir="[^"]*"/', 'dir="rtl"', $output );
			}
		}
		return $output;
	}
}
