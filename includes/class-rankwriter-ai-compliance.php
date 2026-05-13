<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Actively enforces the two "policy" fields from each Category Profile:
 *   - banned topics / words
 *   - AdSense compliance (Google's Publisher Restrictions)
 *
 * Outputs a structured issues array and persists it on the generated post
 * so the editor can see violations before publish.
 */
class RankWriter_AI_Compliance {

	const META_KEY = '_rwai_compliance_report';

	/**
	 * AdSense / Google Publisher prohibited content signals.
	 * https://support.google.com/adsense/answer/9335564
	 */
	private static function adsense_prohibited_signals() {
		return array(
			'illegal-drugs'      => array( 'cocaine', 'meth ', 'heroin', 'crack pipe', 'how to buy drugs', 'illegal drugs', 'recreational drug' ),
			'weapons'            => array( 'how to build a gun', 'how to buy a gun illegally', 'untraceable firearm', '3d printed gun', 'ghost gun' ),
			'adult-explicit'     => array( 'porn', 'xxx', 'nude photos', 'explicit nudity', 'sex tape' ),
			'gambling'           => array( 'online casino', 'best betting site', 'online poker real money', 'sports betting tips' ),
			'tobacco'            => array( 'buy cigarettes online', 'vape juice for sale', 'tobacco discount' ),
			'shocking-violence'  => array( 'graphic violence', 'beheading', 'execution video' ),
			'hateful'            => array( 'racial slur', 'genocide of', 'ethnic cleansing of' ),
			'misinformation'     => array( 'covid hoax', 'vaccine causes autism', 'flat earth proof' ),
			'fake-news-signals'  => array( 'doctors hate this one trick', 'shocking secret they don\'t want you to know' ),
		);
	}

	/**
	 * Run all checks on generated content for a given Category Profile.
	 *
	 * @return array {
	 *     @type bool  $passed
	 *     @type array $issues  Each: { severity: 'error'|'warn', rule, message, hits }
	 *     @type array $stats   Word count, paragraph count, heading count, etc.
	 * }
	 */
	public function check( $content, array $profile ) {
		$plain = wp_strip_all_tags( $content );
		$lower = strtolower( $plain );

		$issues = array();
		$stats  = array(
			'word_count'     => RankWriter_AI_Helpers::word_count( $content ),
			'h2_count'       => preg_match_all( '/<h2[\s>]/i', $content ),
			'h3_count'       => preg_match_all( '/<h3[\s>]/i', $content ),
			'list_count'     => preg_match_all( '/<(ul|ol)[\s>]/i', $content ),
			'paragraph_avg'  => $this->avg_paragraph_words( $content ),
			'image_count'    => preg_match_all( '/<img\b/i', $content ),
			'link_count'     => preg_match_all( '/<a\s+[^>]*href=/i', $content ),
		);

		// 1) Banned terms from Category Profile.
		$banned_raw = isset( $profile['banned_terms'] ) ? (string) $profile['banned_terms'] : '';
		if ( '' !== trim( $banned_raw ) ) {
			$terms = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $banned_raw ) ) );
			$hits  = array();
			foreach ( $terms as $t ) {
				if ( '' === $t ) {
					continue;
				}
				if ( false !== strpos( $lower, strtolower( $t ) ) ) {
					$hits[] = $t;
				}
			}
			if ( ! empty( $hits ) ) {
				$issues[] = array(
					'severity' => 'error',
					'rule'     => 'banned-terms',
					'message'  => __( 'Content contains terms banned by this category profile.', 'rankwriter-ai' ),
					'hits'     => $hits,
				);
			}
		}

		// 2) AdSense prohibited content signals.
		foreach ( self::adsense_prohibited_signals() as $rule_key => $phrases ) {
			$hit = array();
			foreach ( $phrases as $p ) {
				if ( false !== strpos( $lower, $p ) ) {
					$hit[] = $p;
				}
			}
			if ( ! empty( $hit ) ) {
				$issues[] = array(
					'severity' => 'error',
					'rule'     => 'adsense:' . $rule_key,
					'message'  => sprintf(
						/* translators: %s: rule key */
						__( 'AdSense risk — content matches %s policy signals.', 'rankwriter-ai' ),
						$rule_key
					),
					'hits'     => $hit,
				);
			}
		}

		// 3) Readability heuristics (AdSense quality + general readability).
		if ( $stats['word_count'] < 600 ) {
			$issues[] = array(
				'severity' => 'warn',
				'rule'     => 'thin-content',
				'message'  => sprintf( __( 'Article is only %d words — AdSense penalizes thin content.', 'rankwriter-ai' ), $stats['word_count'] ),
				'hits'     => array(),
			);
		}
		if ( 0 === $stats['h2_count'] ) {
			$issues[] = array(
				'severity' => 'warn',
				'rule'     => 'no-headings',
				'message'  => __( 'No H2 headings detected — readability and SEO are hurt.', 'rankwriter-ai' ),
				'hits'     => array(),
			);
		}
		if ( $stats['paragraph_avg'] > 100 ) {
			$issues[] = array(
				'severity' => 'warn',
				'rule'     => 'long-paragraphs',
				'message'  => sprintf( __( 'Average paragraph length is %d words — break long paragraphs for readability.', 'rankwriter-ai' ), $stats['paragraph_avg'] ),
				'hits'     => array(),
			);
		}
		if ( 0 === $stats['link_count'] ) {
			$issues[] = array(
				'severity' => 'warn',
				'rule'     => 'no-links',
				'message'  => __( 'No links found — internal linking improves topical authority.', 'rankwriter-ai' ),
				'hits'     => array(),
			);
		}

		// 4) AI tell-tales / placeholder leakage.
		$tells = array( 'as an ai language model', 'as an ai,', 'i cannot provide', 'lorem ipsum', '[insert', '[your text here]' );
		foreach ( $tells as $t ) {
			if ( false !== strpos( $lower, $t ) ) {
				$issues[] = array(
					'severity' => 'error',
					'rule'     => 'ai-tell',
					'message'  => __( 'AI placeholder text leaked into the article.', 'rankwriter-ai' ),
					'hits'     => array( $t ),
				);
			}
		}

		$errors = 0;
		foreach ( $issues as $i ) {
			if ( 'error' === $i['severity'] ) {
				$errors++;
			}
		}

		return array(
			'passed' => 0 === $errors,
			'issues' => $issues,
			'stats'  => $stats,
		);
	}

	private function avg_paragraph_words( $content ) {
		if ( ! preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
			return 0;
		}
		$total = 0;
		$n     = 0;
		foreach ( $m[1] as $para ) {
			$words = str_word_count( wp_strip_all_tags( $para ) );
			if ( $words > 0 ) {
				$total += $words;
				$n++;
			}
		}
		return $n ? (int) round( $total / $n ) : 0;
	}

	public function save_report( $post_id, array $report ) {
		update_post_meta( $post_id, self::META_KEY, $report );
	}

	public function get_report( $post_id ) {
		$r = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $r ) ? $r : array();
	}

	/**
	 * Optionally strip banned terms from output (last-line defence; the
	 * primary fix is to regenerate, but stripping protects against
	 * accidental leaks).
	 */
	public function redact_banned( $content, $banned_csv ) {
		if ( '' === trim( $banned_csv ) ) {
			return $content;
		}
		$terms = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $banned_csv ) ) );
		foreach ( $terms as $t ) {
			if ( '' === $t ) {
				continue;
			}
			$content = preg_replace( '/\b' . preg_quote( $t, '/' ) . '\b/i', '[redacted]', $content );
		}
		return $content;
	}
}
