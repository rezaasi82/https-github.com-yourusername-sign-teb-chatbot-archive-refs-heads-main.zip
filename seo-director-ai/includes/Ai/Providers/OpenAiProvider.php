<?php
/**
 * OpenAI adapter (Chat Completions API). Uses JSON-mode via
 * response_format to keep output parseable; the SchemaValidator still
 * validates and repairs one layer up.
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

final class OpenAiProvider implements AiProviderInterface {

	private const ENDPOINT      = 'https://api.openai.com/v1/chat/completions';
	private const DEFAULT_MODEL = 'gpt-4.1-mini';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
		private Settings $settings,
	) {}

	public function slug(): string {
		return 'openai';
	}

	public function is_configured(): bool {
		$connection = $this->connections->get( 'openai' );

		return null !== $connection && '' !== (string) ( $connection['credentials']['api_key'] ?? '' );
	}

	public function model(): string {
		return (string) $this->settings->get( 'ai_model_openai', self::DEFAULT_MODEL );
	}

	public function complete( PromptEnvelope $envelope ): array|\WP_Error {
		if ( ! $this->quota->allow( 'ai' ) ) {
			return new \WP_Error( 'sda_quota', 'AI call budget exhausted or circuit breaker open.' );
		}

		$connection = $this->connections->get( 'openai' );
		$api_key    = (string) ( $connection['credentials']['api_key'] ?? '' );
		if ( '' === $api_key ) {
			return new \WP_Error( 'sda_ai_auth', 'OpenAI API key is not configured.' );
		}

		$result = $this->http->post(
			self::ENDPOINT,
			[
				'timeout' => 60,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'content-type'  => 'application/json',
				],
				'body'    => [
					'model'           => $this->model(),
					'max_tokens'      => $envelope->max_tokens,
					'response_format' => [ 'type' => 'json_object' ],
					'messages'        => [
						[ 'role' => 'system', 'content' => $envelope->system ],
						[ 'role' => 'user', 'content' => $envelope->user_message() ],
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

		$text   = (string) ( $data['choices'][0]['message']['content'] ?? '' );
		$tokens = (int) ( $data['usage']['total_tokens'] ?? 0 );

		return [ 'text' => $text, 'tokens' => $tokens ];
	}
}
