<?php
/**
 * GapGPT adapter. GapGPT (gapgpt.app) is an OpenAI-compatible gateway popular
 * with Iranian users because it accepts local payment and needs no VPN, while
 * exposing the same Chat Completions API — so this mirrors the OpenAI adapter
 * with GapGPT's base URL and its own key/model settings.
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

final class GapGptProvider implements AiProviderInterface {

	private const ENDPOINT      = 'https://api.gapgpt.app/v1/chat/completions';
	private const DEFAULT_MODEL = 'gpt-4o-mini';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
		private Settings $settings,
	) {}

	public function slug(): string {
		return 'gapgpt';
	}

	public function is_configured(): bool {
		$connection = $this->connections->get( 'gapgpt' );

		return null !== $connection && '' !== (string) ( $connection['credentials']['api_key'] ?? '' );
	}

	public function model(): string {
		return (string) $this->settings->get( 'ai_model_gapgpt', self::DEFAULT_MODEL );
	}

	public function complete( PromptEnvelope $envelope ): array|\WP_Error {
		if ( ! $this->quota->allow( 'ai' ) ) {
			return new \WP_Error( 'sda_quota', 'AI call budget exhausted or circuit breaker open.' );
		}

		$connection = $this->connections->get( 'gapgpt' );
		$api_key    = (string) ( $connection['credentials']['api_key'] ?? '' );
		if ( '' === $api_key ) {
			return new \WP_Error( 'sda_ai_auth', 'GapGPT API key is not configured.' );
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
