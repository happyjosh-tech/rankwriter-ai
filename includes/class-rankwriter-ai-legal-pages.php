<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-click generator for the mandatory legal / informational pages every
 * AdSense-monetized blog needs:
 *
 *   - About Us
 *   - Contact Us
 *   - Privacy Policy (AdSense + analytics + cookies aware, jurisdiction-tuned)
 *   - Terms of Service / Terms & Conditions
 *   - Disclaimer (niche-aware: medical/financial/legal/affiliate)
 *   - Affiliate Disclosure (only if site uses affiliate links)
 *   - Cookie Policy (only if site uses cookies/analytics)
 *   - DMCA / Copyright
 *
 * Each page is saved as a WordPress Page (not a post). The Privacy Policy
 * is registered with core via `wp_page_for_privacy_policy`. Pages can be
 * regenerated; existing IDs are reused so footer links stay stable.
 */
class RankWriter_AI_Legal_Pages {

	const META_TYPE = '_rwai_legal_page_type';
	const META_GENERATED = '_rwai_legal_generated_at';

	public static function page_types() {
		return array(
			'about_us' => array(
				'title'        => 'About Us',
				'slug'         => 'about-us',
				'requires'     => array(),
				'description'  => 'Tells visitors who runs the site, the niche, the editorial mission, and why they should trust the content.',
				'word_target'  => 600,
			),
			'contact_us' => array(
				'title'        => 'Contact Us',
				'slug'         => 'contact-us',
				'requires'     => array( 'business_email' ),
				'description'  => 'Lists contact channels (email, address if applicable, response time expectations).',
				'word_target'  => 350,
			),
			'privacy_policy' => array(
				'title'        => 'Privacy Policy',
				'slug'         => 'privacy-policy',
				'requires'     => array( 'business_email' ),
				'description'  => 'GDPR / CCPA / NDPR compliant privacy policy. Covers data collection, cookies, third-party services (AdSense, Analytics, affiliate networks), user rights, retention, contact for data requests.',
				'word_target'  => 1500,
				'wp_privacy'   => true,
			),
			'terms_of_service' => array(
				'title'        => 'Terms of Service',
				'slug'         => 'terms-of-service',
				'requires'     => array( 'business_email' ),
				'description'  => 'Use, prohibited uses, IP ownership, limitation of liability, indemnity, governing law.',
				'word_target'  => 1200,
			),
			'disclaimer' => array(
				'title'        => 'Disclaimer',
				'slug'         => 'disclaimer',
				'requires'     => array(),
				'description'  => 'General disclaimer of warranty + niche-specific disclaimers (medical / financial / legal advice).',
				'word_target'  => 600,
			),
			'affiliate_disclosure' => array(
				'title'        => 'Affiliate Disclosure',
				'slug'         => 'affiliate-disclosure',
				'requires'     => array(),
				'only_if'      => 'uses_affiliate_links',
				'description'  => 'FTC-compliant affiliate disclosure stating the site earns commissions from qualifying links.',
				'word_target'  => 400,
			),
			'cookie_policy' => array(
				'title'        => 'Cookie Policy',
				'slug'         => 'cookie-policy',
				'requires'     => array( 'business_email' ),
				'only_if'      => 'uses_cookies',
				'description'  => 'Detailed cookie categories (essential, analytics, advertising), opt-out instructions, third-party cookie sources.',
				'word_target'  => 800,
			),
			'dmca' => array(
				'title'        => 'DMCA / Copyright Notice',
				'slug'         => 'dmca-copyright',
				'requires'     => array( 'business_email' ),
				'description'  => 'DMCA takedown procedure, designated agent contact, counter-notice procedure.',
				'word_target'  => 600,
			),
		);
	}

	public function settings() {
		$defaults = array(
			'business_name'        => get_bloginfo( 'name' ),
			'business_email'       => get_option( 'admin_email' ),
			'business_address'     => '',
			'legal_jurisdiction'   => 'United States',
			'operator_type'        => 'individual',
			'uses_adsense'         => 1,
			'uses_affiliate_links' => 1,
			'uses_cookies'         => 1,
			'uses_analytics'       => 1,
		);
		$saved = get_option( 'rwai_legal_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public function save_settings( array $values ) {
		$current = $this->settings();
		$merged = array_merge( $current, $values );
		$merged['business_name']        = sanitize_text_field( $merged['business_name'] );
		$merged['business_email']       = sanitize_email( $merged['business_email'] );
		$merged['business_address']     = sanitize_textarea_field( $merged['business_address'] );
		$merged['legal_jurisdiction']   = sanitize_text_field( $merged['legal_jurisdiction'] );
		$merged['operator_type']        = in_array( $merged['operator_type'], array( 'individual', 'company' ), true ) ? $merged['operator_type'] : 'individual';
		$merged['uses_adsense']         = ! empty( $merged['uses_adsense'] ) ? 1 : 0;
		$merged['uses_affiliate_links'] = ! empty( $merged['uses_affiliate_links'] ) ? 1 : 0;
		$merged['uses_cookies']         = ! empty( $merged['uses_cookies'] ) ? 1 : 0;
		$merged['uses_analytics']       = ! empty( $merged['uses_analytics'] ) ? 1 : 0;
		update_option( 'rwai_legal_settings', $merged, false );
		return $merged;
	}

	/**
	 * Returns the existing legal page ID for a given type, if any.
	 */
	public function existing_page_id( $type ) {
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => self::META_TYPE,
			'meta_value'     => $type,
			'fields'         => 'ids',
		) );
		return ! empty( $pages ) ? (int) $pages[0] : 0;
	}

	/**
	 * Generate (or regenerate) a single legal page type.
	 *
	 * @return int|WP_Error page ID on success.
	 */
	public function generate( $type ) {
		$types = self::page_types();
		if ( ! isset( $types[ $type ] ) ) {
			return new WP_Error( 'rwai_unknown_type', __( 'Unknown legal page type.', 'rankwriter-ai' ) );
		}
		$cfg = $types[ $type ];

		$settings = $this->settings();

		// Skip pages that depend on a toggle the user has disabled.
		if ( ! empty( $cfg['only_if'] ) && empty( $settings[ $cfg['only_if'] ] ) ) {
			return new WP_Error( 'rwai_disabled', sprintf(
				/* translators: %s: page title */
				__( '%s is disabled in your legal settings.', 'rankwriter-ai' ),
				$cfg['title']
			) );
		}

		foreach ( (array) $cfg['requires'] as $req_key ) {
			if ( empty( $settings[ $req_key ] ) ) {
				return new WP_Error( 'rwai_missing_req', sprintf(
					/* translators: 1: page title, 2: setting key */
					__( '%1$s requires the "%2$s" setting to be filled.', 'rankwriter-ai' ),
					$cfg['title'],
					$req_key
				) );
			}
		}

		$client = new RankWriter_AI_Claude_Client();
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

		$system = $this->build_system_prompt( $type, $cfg, $settings );
		$user   = $this->build_user_prompt( $type, $cfg, $settings );

		$text = $client->send( $system, array(
			array( 'role' => 'user', 'content' => $user ),
		) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$parsed = $this->parse_response( $text, $cfg['title'] );

		$existing_id = $this->existing_page_id( $type );

		$post_arr = array(
			'post_title'   => wp_slash( $parsed['title'] ),
			'post_content' => wp_slash( $parsed['content'] ),
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => $cfg['slug'],
		);

		if ( $existing_id ) {
			$post_arr['ID'] = $existing_id;
			$result_id      = wp_update_post( $post_arr, true );
		} else {
			$result_id = wp_insert_post( $post_arr, true );
		}

		if ( is_wp_error( $result_id ) ) {
			return $result_id;
		}

		update_post_meta( $result_id, self::META_TYPE, $type );
		update_post_meta( $result_id, self::META_GENERATED, current_time( 'mysql' ) );

		// Register with core for Privacy Policy.
		if ( ! empty( $cfg['wp_privacy'] ) ) {
			update_option( 'wp_page_for_privacy_policy', (int) $result_id );
		}

		return (int) $result_id;
	}

	public function list_pages() {
		$out = array();
		foreach ( self::page_types() as $type => $cfg ) {
			$id = $this->existing_page_id( $type );
			$out[ $type ] = array(
				'type'         => $type,
				'config'       => $cfg,
				'id'           => $id,
				'url'          => $id ? get_permalink( $id ) : '',
				'edit_url'     => $id ? get_edit_post_link( $id, 'raw' ) : '',
				'generated_at' => $id ? get_post_meta( $id, self::META_GENERATED, true ) : '',
			);
		}
		return $out;
	}

	private function build_system_prompt( $type, array $cfg, array $settings ) {
		$operator    = 'company' === $settings['operator_type'] ? 'the company' : 'the individual operator';
		$ad_block    = ! empty( $settings['uses_adsense'] ) ? 'The site displays Google AdSense advertising.' : 'The site does not currently display third-party advertising.';
		$aff_block   = ! empty( $settings['uses_affiliate_links'] ) ? 'The site uses affiliate links and earns commissions from qualifying purchases.' : 'The site does not currently use affiliate links.';
		$cookie_block= ! empty( $settings['uses_cookies'] ) ? 'The site uses cookies (essential and tracking).' : 'The site uses only essential cookies.';
		$analytics   = ! empty( $settings['uses_analytics'] ) ? 'Google Analytics or equivalent is in use.' : 'No analytics are in use.';

		$site_block = "## Site facts\n"
			. "- Site name: " . $settings['business_name'] . "\n"
			. "- Site URL: " . home_url( '/' ) . "\n"
			. "- Operator type: " . $settings['operator_type'] . " (refer to as $operator)\n"
			. "- Contact email: " . $settings['business_email'] . "\n"
			. ( $settings['business_address'] ? "- Business address: " . $settings['business_address'] . "\n" : '' )
			. "- Legal jurisdiction / governing law: " . $settings['legal_jurisdiction'] . "\n"
			. "- " . $ad_block . "\n"
			. "- " . $aff_block . "\n"
			. "- " . $cookie_block . "\n"
			. "- " . $analytics . "\n";

		$header = "You are a careful policy/legal writer for small publishers. You write clear, plain-English " . $cfg['title'] . " pages that comply with the named jurisdiction's standards, GDPR/CCPA where applicable, FTC affiliate disclosure rules, and Google's AdSense Publisher Policies.\n\n"
			. "IMPORTANT: You are not providing legal advice. The page must end with a brief note recommending the operator have a licensed attorney review the final wording for their jurisdiction.\n\n";

		$rules = "## Output rules\n"
			. "Return ONLY valid JSON with this exact shape:\n"
			. "{\n"
			. "  \"title\": \"" . $cfg['title'] . "\",\n"
			. "  \"content_html\": \"<full page in clean HTML with <h2>, <h3>, <p>, <ul>, <a>>\"\n"
			. "}\n\n"
			. "Constraints:\n"
			. "- Aim for ~" . $cfg['word_target'] . " words.\n"
			. "- Use H2 section headings; H3 for sub-points.\n"
			. "- Use the contact email from the site facts wherever a contact is needed — never invent one.\n"
			. "- Use the jurisdiction from the site facts for governing-law clauses.\n"
			. "- Reference AdSense, affiliate, cookies, analytics ONLY if the site facts confirm they're in use.\n"
			. "- Use today's date (" . current_time( 'F j, Y' ) . ") for the 'Last updated' line.\n"
			. "- No placeholders like [your name here]; if a fact is missing, omit that section gracefully.\n"
			. "- Do NOT wrap the JSON in markdown code fences.\n";

		return $header . $site_block . "\n" . $rules;
	}

	private function build_user_prompt( $type, array $cfg, array $settings ) {
		$niche_hint = $this->niche_hint();

		$lines = array();
		$lines[] = "Generate the " . $cfg['title'] . " page for this site.";
		$lines[] = "Page intent: " . $cfg['description'];
		if ( $niche_hint ) {
			$lines[] = "Niche context (from existing content): " . $niche_hint;
		}

		switch ( $type ) {
			case 'about_us':
				$lines[] = "Sections to include: mission, what we cover, editorial standards, how to reach us. Make it personable but professional. Reflect the niche.";
				break;
			case 'contact_us':
				$lines[] = "Sections to include: how to reach us (email), what we respond to (and what we don't, e.g., we don't accept guest posts unless you do), response time expectation, business address if provided.";
				break;
			case 'privacy_policy':
				$lines[] = "Required sections: what data we collect (contact form, comments, analytics), how we use it, cookies and tracking technologies, third-party services (AdSense, Google Analytics, affiliate networks if applicable), user rights (GDPR/CCPA where applicable), data retention, children's privacy (COPPA), changes to this policy, contact for data requests. Be specific where the site facts allow.";
				break;
			case 'terms_of_service':
				$lines[] = "Required sections: acceptance of terms, use of the site, intellectual property, user content (comments), prohibited uses, links to other websites, disclaimers, limitation of liability, indemnification, governing law (use the jurisdiction from site facts), changes, contact.";
				break;
			case 'disclaimer':
				$lines[] = "Required sections: general info-only disclaimer, no warranty, external links disclaimer, professional advice disclaimer specific to the niche (medical/financial/legal if relevant), errors and omissions, fair use.";
				break;
			case 'affiliate_disclosure':
				$lines[] = "FTC-compliant disclosure that the site earns commissions from affiliate links. Mention Amazon Associates explicitly if AdSense or US-focused; otherwise generic. Explain that prices for the reader are not affected.";
				break;
			case 'cookie_policy':
				$lines[] = "Required sections: what cookies are, types we use (essential, analytics, advertising), specific third parties (Google AdSense, Google Analytics if applicable), how to control cookies in major browsers, opt-out links (NAI, DAA, YourAdChoices), changes, contact.";
				break;
			case 'dmca':
				$lines[] = "Required sections: how to file a DMCA takedown notice, designated agent contact (use the site contact email), required elements of a notice, counter-notice procedure, repeat infringer policy.";
				break;
		}

		$lines[] = "";
		$lines[] = "Return JSON only.";
		return implode( "\n", $lines );
	}

	/**
	 * Read the persisted Blog Style Profile to pass niche context to the
	 * legal-page prompts (e.g., "this is a pet-care blog" → niche-specific
	 * disclaimer). Optional; no profile is fine.
	 */
	private function niche_hint() {
		$style = new RankWriter_AI_Style_Profile();
		$p     = $style->get();
		if ( empty( $p ) ) {
			return '';
		}
		$bits = array();
		if ( ! empty( $p['dominant_categories'] ) ) {
			$cats = array();
			foreach ( array_slice( $p['dominant_categories'], 0, 3 ) as $c ) {
				$cats[] = $c['name'];
			}
			$bits[] = 'dominant categories: ' . implode( ', ', $cats );
		}
		if ( ! empty( $p['preferred_tone'] ) ) {
			$bits[] = 'tone: ' . $p['preferred_tone'];
		}
		if ( ! empty( $p['audience_intent']['dominant'] ) ) {
			$bits[] = 'audience intent: ' . $p['audience_intent']['dominant'];
		}
		return implode( '; ', $bits );
	}

	private function parse_response( $text, $fallback_title ) {
		$text = trim( (string) $text );
		if ( 0 === strpos( $text, '```' ) ) {
			$text = preg_replace( '/^```(?:json)?\s*/', '', $text );
			$text = preg_replace( '/\s*```$/', '', $text );
			$text = trim( $text );
		}

		$json = null;
		$cand = json_decode( $text, true );
		if ( is_array( $cand ) ) {
			$json = $cand;
		}
		if ( ! $json ) {
			$first = strpos( $text, '{' );
			$last  = strrpos( $text, '}' );
			if ( false !== $first && false !== $last && $last > $first ) {
				$cand = json_decode( substr( $text, $first, $last - $first + 1 ), true );
				if ( is_array( $cand ) ) {
					$json = $cand;
				}
			}
		}
		if ( ! $json && preg_match( '/"content_html"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $m ) ) {
			$json = array(
				'title'        => $fallback_title,
				'content_html' => stripcslashes( $m[1] ),
			);
			if ( preg_match( '/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $tm ) ) {
				$json['title'] = stripcslashes( $tm[1] );
			}
		}

		if ( is_array( $json ) && ! empty( $json['content_html'] ) ) {
			$content = $this->normalize_content_html( (string) $json['content_html'] );
			return array(
				'title'   => sanitize_text_field( isset( $json['title'] ) ? $json['title'] : $fallback_title ),
				'content' => wp_kses_post( $content ),
			);
		}

		return array(
			'title'   => $fallback_title,
			'content' => wp_kses_post( wpautop( $this->normalize_content_html( $text ) ) ),
		);
	}

	/**
	 * Same escape-sequence repair as the main Content Generator. See its
	 * docblock for the symptom / root cause.
	 */
	private function normalize_content_html( $html ) {
		$html = (string) $html;
		$html = str_replace(
			array( "\\n", "\\t", "\\r", '\\"', "\\'" ),
			array( "\n",  "\t",  "\r",  '"',   "'" ),
			$html
		);
		$html = preg_replace( '/^(?:\s*"[a-z_]+"\s*:\s*"[^\n]*",?\s*\n)+/i', '', $html );
		return trim( $html );
	}
}
