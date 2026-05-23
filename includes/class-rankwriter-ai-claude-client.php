<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the Anthropic Messages API, with an automatic
 * Google Gemini fallback when Claude is unreachable (missing key,
 * exhausted credit, auth failure, rate limit, transient outage).
 *
 * Endpoint: https://api.anthropic.com/v1/messages
 * Model:    claude-opus-4-7 (configurable via settings)
 *
 * Fallback: see RankWriter_AI_Gemini_Client.
 */
class RankWriter_AI_Claude_Client {

	const ENDPOINT = 'https://api.anthropic.com/v1/messages';
	const VERSION  = '2023-06-01';

	private $api_key;
	private $model;
	private $max_tokens;

	public function __construct() {
		$this->api_key    = (string) RankWriter_AI_Helpers::get_setting( 'claude_api_key', '' );
		$this->model      = (string) RankWriter_AI_Helpers::get_setting( 'claude_model', 'claude-opus-4-7' );
		$this->max_tokens = (int) RankWriter_AI_Helpers::get_setting( 'max_tokens', 4000 );
	}

	/**
	 * Considered "configured" if EITHER provider has a key, since the
	 * Gemini fallback is enough on its own to generate content.
	 */
	public function is_configured() {
		if ( '' !== $this->api_key ) {
			return true;
		}
		if ( class_exists( 'RankWriter_AI_Gemini_Client' ) ) {
			$gemini = new RankWriter_AI_Gemini_Client();
			return $gemini->is_configured();
		}
		return false;
	}

	public function has_claude_key() {
		return '' !== $this->api_key;
	}

	/**
	 * Send a messages request. Tries Claude first; on a recoverable
	 * failure (no key, credit/auth/rate-limit errors, network blip),
	 * automatically retries against Gemini using the same prompt.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Array of { role, content } entries.
	 * @return string|WP_Error Assistant text on success.
	 */
	public function send( $system, array $messages ) {
		$claude_result = null;

		if ( $this->has_claude_key() ) {
			$claude_result = $this->send_claude( $system, $messages );
			if ( ! is_wp_error( $claude_result ) ) {
				return $claude_result;
			}
			// Only fall back on errors that suggest Claude itself is
			// the problem (no credit, bad key, rate-limited, server
			// error). For any other shape we still try Gemini if it
			// is configured, since "any answer" > "no answer" for the
			// user's workflow.
		}

		if ( class_exists( 'RankWriter_AI_Gemini_Client' ) ) {
			$gemini = new RankWriter_AI_Gemini_Client();
			if ( $gemini->is_configured() ) {
				$gemini_result = $gemini->send( $system, $messages );
				if ( ! is_wp_error( $gemini_result ) ) {
					return $gemini_result;
				}
				// Both failed — prefer the Claude error for the user
				// if we have one (it explains the *why* of the
				// fallback), otherwise surface Gemini's error.
				return $claude_result instanceof WP_Error ? $claude_result : $gemini_result;
			}
		}

		if ( $claude_result instanceof WP_Error ) {
			return $claude_result;
		}

		return new WP_Error(
			'rwai_no_key',
			__( 'No AI provider is configured. Add a Claude API key or a Google Gemini API key in RankWriter AI → Settings.', 'rankwriter-ai' )
		);
	}

	private function send_claude( $system, array $messages ) {
		$body = array(
			'model'      => $this->model,
			'max_tokens' => $this->max_tokens,
			'system'     => (string) $system,
			'messages'   => $messages,
		);

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::VERSION,
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
			return new WP_Error( 'rwai_api_error', $msg, array( 'status' => $code, 'body' => $raw ) );
		}

		if ( ! is_array( $json ) || empty( $json['content'] ) ) {
			return new WP_Error( 'rwai_bad_response', __( 'Unexpected Claude response shape.', 'rankwriter-ai' ), $raw );
		}

		$text = '';
		foreach ( $json['content'] as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
				$text .= $block['text'];
			}
		}
		return $text;
	}
}
