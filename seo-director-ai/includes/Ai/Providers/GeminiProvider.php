<?php
/**
 * Google Gemini adapter (generateContent with JSON response mime).
 *
 * @package SEODirector
 */

namespace SEODirector\Ai\Providers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\AiProviderInterface;
use SEODirector\Ai\PromptEnvelope;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class GeminiProvider implements AiProviderInterface {

	private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	public function __construct( private readonly RetryingHttpClient $http ) {}

	public function slug(): string {
		return 'gemini';
	}

	public function complete( PromptEnvelope $envelope, string $model, string $api_key ): array|WP_Error {
		$url = sprintf( self::ENDPOINT, rawurlencode( $model ) );

		$response = $this->http->post_json(
			$url,
			array(
				'headers' => array( 'x-goog-api-key' => $api_key ),
				'body'    => array(
					'systemInstruction' => array(
						'parts' => array( array( 'text' => $envelope->system_prompt ) ),
					),
					'contents'          => array(
						array(
							'role'  => 'user',
							'parts' => array( array( 'text' => $envelope->render_user_message() ) ),
						),
					),
					'generationConfig'  => array(
						'maxOutputTokens'  => $envelope->max_tokens,
						'responseMimeType' => 'application/json',
					),
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['code'] ) {
			$message = (string) ( $response['body']['error']['message'] ?? 'Gemini API error.' );
			return new WP_Error( 'sda_ai_gemini_' . $response['code'], $message );
		}

		$text  = (string) ( $response['body']['candidates'][0]['content']['parts'][0]['text'] ?? '' );
		$usage = (array) ( $response['body']['usageMetadata'] ?? array() );

		return array(
			'text'   => $text,
			'tokens' => (int) ( $usage['totalTokenCount'] ?? 0 ),
		);
	}
}
