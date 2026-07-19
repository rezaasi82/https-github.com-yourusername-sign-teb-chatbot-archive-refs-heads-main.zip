<?php
/**
 * Anthropic Claude adapter (Messages API).
 *
 * @package SEODirector
 */

namespace SEODirector\Ai\Providers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\AiProviderInterface;
use SEODirector\Ai\PromptEnvelope;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class ClaudeProvider implements AiProviderInterface {

	private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	public function __construct( private readonly RetryingHttpClient $http ) {}

	public function slug(): string {
		return 'claude';
	}

	public function complete( PromptEnvelope $envelope, string $model, string $api_key ): array|WP_Error {
		$response = $this->http->post_json(
			self::ENDPOINT,
			array(
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
				),
				'body'    => array(
					'model'      => $model,
					'max_tokens' => $envelope->max_tokens,
					'system'     => $envelope->system_prompt,
					'messages'   => array(
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
			$message = (string) ( $response['body']['error']['message'] ?? 'Claude API error.' );
			return new WP_Error( 'sda_ai_claude_' . $response['code'], $message );
		}

		$text = '';
		foreach ( (array) ( $response['body']['content'] ?? array() ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) $block['text'];
			}
		}

		$usage  = (array) ( $response['body']['usage'] ?? array() );
		$tokens = (int) ( $usage['input_tokens'] ?? 0 ) + (int) ( $usage['output_tokens'] ?? 0 );

		return array(
			'text'   => $text,
			'tokens' => $tokens,
		);
	}
}
