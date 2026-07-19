<?php
/**
 * OpenAI adapter (Chat Completions with JSON mode).
 *
 * @package SEODirector
 */

namespace SEODirector\Ai\Providers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\AiProviderInterface;
use SEODirector\Ai\PromptEnvelope;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class OpenAiProvider implements AiProviderInterface {

	private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	public function __construct( private readonly RetryingHttpClient $http ) {}

	public function slug(): string {
		return 'openai';
	}

	public function complete( PromptEnvelope $envelope, string $model, string $api_key ): array|WP_Error {
		$response = $this->http->post_json(
			self::ENDPOINT,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
				'body'    => array(
					'model'           => $model,
					'max_tokens'      => $envelope->max_tokens,
					'response_format' => array( 'type' => 'json_object' ),
					'messages'        => array(
						array(
							'role'    => 'system',
							'content' => $envelope->system_prompt,
						),
						array(
							'role'    => 'user',
							'content' => $envelope->render_user_message(),
						),
					),
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['code'] ) {
			$message = (string) ( $response['body']['error']['message'] ?? 'OpenAI API error.' );
			return new WP_Error( 'sda_ai_openai_' . $response['code'], $message );
		}

		return array(
			'text'   => (string) ( $response['body']['choices'][0]['message']['content'] ?? '' ),
			'tokens' => (int) ( $response['body']['usage']['total_tokens'] ?? 0 ),
		);
	}
}
