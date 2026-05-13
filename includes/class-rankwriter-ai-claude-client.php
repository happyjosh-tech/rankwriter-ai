<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the Anthropic Messages API.
 *
 * Endpoint: https://api.anthropic.com/v1/messages
 * Model:    claude-opus-4-7 (configurable via settings)
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

	public function is_configured() {
		return '' !== $this->api_key;
	}

	/**
	 * Send a messages request.
	 *
	 * @param string $system   System prompt.
	 * @param array  $messages Array of { role, content } entries.
	 * @return string|WP_Error Assistant text on success.
	 */
	public function send( $system, array $messages ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'rwai_no_key', __( 'Claude API key is not configured.', 'rankwriter-ai' ) );
		}

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
