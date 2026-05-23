<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the Google Gemini generateContent API.
 *
 * Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 * Default model: gemini-2.5-flash (configurable via settings)
 *
 * Used as a fallback when Claude is unavailable (no credit, rate limit,
 * key missing, transient outage) so generation keeps working without
 * an Anthropic subscription.
 */
class RankWriter_AI_Gemini_Client {

	const ENDPOINT_BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private $api_key;
	private $model;
	private $max_tokens;

	public function __construct() {
		$this->api_key    = (string) RankWriter_AI_Helpers::get_setting( 'gemini_api_key', '' );
		$this->model      = (string) RankWriter_AI_Helpers::get_setting( 'gemini_model', 'gemini-2.5-flash' );
		$this->max_tokens = (int) RankWriter_AI_Helpers::get_setting( 'max_tokens', 4000 );
	}

	public function is_configured() {
		return '' !== $this->api_key;
	}

	/**
	 * Send a generation request using the same shape as the Claude client.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Array of { role, content } entries (Anthropic-style).
	 * @return string|WP_Error Assistant text on success.
	 */
	public function send( $system, array $messages ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'rwai_no_gemini_key', __( 'Gemini API key is not configured.', 'rankwriter-ai' ) );
		}

		$contents = array();
		foreach ( $messages as $msg ) {
			$role = isset( $msg['role'] ) ? (string) $msg['role'] : 'user';
			$text = isset( $msg['content'] ) ? $this->stringify_content( $msg['content'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$contents[] = array(
				'role'  => ( 'assistant' === $role ) ? 'model' : 'user',
				'parts' => array( array( 'text' => $text ) ),
			);
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'maxOutputTokens' => max( 256, (int) $this->max_tokens ),
			),
		);

		if ( '' !== trim( (string) $system ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => (string) $system ) ),
			);
		}

		$url = self::ENDPOINT_BASE . rawurlencode( $this->model ) . ':generateContent?key=' . rawurlencode( $this->api_key );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $json['error']['message'] ) ? $json['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'rwai_gemini_api_error', $msg, array( 'status' => $code, 'body' => $raw ) );
		}

		if ( ! is_array( $json ) || empty( $json['candidates'] ) ) {
			return new WP_Error( 'rwai_gemini_bad_response', __( 'Unexpected Gemini response shape.', 'rankwriter-ai' ), $raw );
		}

		$text = '';
		foreach ( $json['candidates'] as $cand ) {
			if ( empty( $cand['content']['parts'] ) || ! is_array( $cand['content']['parts'] ) ) {
				continue;
			}
			foreach ( $cand['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$text .= $part['text'];
				}
			}
			// Gemini returns one candidate by default; if more, the first is enough.
			if ( '' !== $text ) {
				break;
			}
		}

		if ( '' === $text ) {
			return new WP_Error( 'rwai_gemini_empty', __( 'Gemini returned no text content.', 'rankwriter-ai' ), $raw );
		}

		return $text;
	}

	/**
	 * Anthropic's "content" field can be a string OR an array of typed
	 * blocks. Flatten to plain text for Gemini.
	 */
	private function stringify_content( $content ) {
		if ( is_string( $content ) ) {
			return $content;
		}
		if ( ! is_array( $content ) ) {
			return '';
		}
		$out = '';
		foreach ( $content as $block ) {
			if ( is_string( $block ) ) {
				$out .= $block;
				continue;
			}
			if ( is_array( $block ) && isset( $block['text'] ) ) {
				$out .= (string) $block['text'];
			}
		}
		return $out;
	}
}
