<?php
/**
 * Google Gemini adapter (generateContent API). Requests JSON mime type;
 * validation/repair happens one layer up.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai\Providers;

use SEODirector\Ai\AiProviderInterface;
use SEODirector\Ai\PromptEnvelope;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Google\QuotaManager;
use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class GeminiProvider implements AiProviderInterface {

	private const ENDPOINT_TPL  = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
	private const DEFAULT_MODEL = 'gemini-2.0-flash';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
		private Settings $settings,
	) {}

	public function slug(): string {
		return 'gemini';
	}

	public function is_configured(): bool {
		$connection = $this->connections->get( 'gemini' );

		return null !== $connection && '' !== (string) ( $connection['credentials']['api_key'] ?? '' );
	}

	public function model(): string {
		return (string) $this->settings->get( 'ai_model_gemini', self::DEFAULT_MODEL );
	}

	public function complete( PromptEnvelope $envelope ): array|\WP_Error {
		if ( ! $this->quota->allow( 'ai' ) ) {
			return new \WP_Error( 'sda_quota', 'AI call budget exhausted or circuit breaker open.' );
		}

		$connection = $this->connections->get( 'gemini' );
		$api_key    = (string) ( $connection['credentials']['api_key'] ?? '' );
		if ( '' === $api_key ) {
			return new \WP_Error( 'sda_ai_auth', 'Gemini API key is not configured.' );
		}

		$url = add_query_arg( 'key', rawurlencode( $api_key ), sprintf( self::ENDPOINT_TPL, rawurlencode( $this->model() ) ) );

		$result = $this->http->post(
			$url,
			[
				'timeout' => 60,
				'headers' => [ 'content-type' => 'application/json' ],
				'body'    => [
					'systemInstruction' => [ 'parts' => [ [ 'text' => $envelope->system ] ] ],
					'contents'          => [
						[ 'role' => 'user', 'parts' => [ [ 'text' => $envelope->user_message() ] ] ],
					],
					'generationConfig'  => [
						'maxOutputTokens'  => $envelope->max_tokens,
						'responseMimeType' => 'application/json',
					],
				],
			]
		);

		$this->quota->record( 'ai' );

		if ( 429 === $result->status || $result->status >= 500 ) {
			$this->quota->trip( 'ai' );
		}

		$data = $result->json();
		if ( ! $result->ok() || ! is_array( $data ) ) {
			return new \WP_Error( 'sda_ai_api', (string) ( $data['error']['message'] ?? $result->error ?? ( 'HTTP ' . $result->status ) ) );
		}

		$text = '';
		foreach ( (array) ( $data['candidates'][0]['content']['parts'] ?? [] ) as $part ) {
			$text .= (string) ( $part['text'] ?? '' );
		}

		$tokens = (int) ( $data['usageMetadata']['totalTokenCount'] ?? 0 );

		return [ 'text' => $text, 'tokens' => $tokens ];
	}
}
