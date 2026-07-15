<?php
/**
 * Anthropic Claude adapter (Messages API).
 *
 * Endpoint POST https://api.anthropic.com/v1/messages with headers
 * x-api-key + anthropic-version: 2023-06-01. No temperature/prefill (rejected
 * on current models). Model is admin-configurable; default follows the
 * product architecture doc (Sonnet tier for analysis quality).
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

final class ClaudeProvider implements AiProviderInterface {

	private const ENDPOINT      = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION   = '2023-06-01';
	private const DEFAULT_MODEL = 'claude-sonnet-5';

	public function __construct(
		private ConnectionsRepository $connections,
		private RetryingHttpClient $http,
		private QuotaManager $quota,
		private Settings $settings,
	) {}

	public function slug(): string {
		return 'claude';
	}

	public function is_configured(): bool {
		$connection = $this->connections->get( 'claude' );

		return null !== $connection && '' !== (string) ( $connection['credentials']['api_key'] ?? '' );
	}

	public function model(): string {
		return (string) $this->settings->get( 'ai_model_claude', self::DEFAULT_MODEL );
	}

	public function complete( PromptEnvelope $envelope ): array|\WP_Error {
		if ( ! $this->quota->allow( 'ai' ) ) {
			return new \WP_Error( 'sda_quota', 'AI call budget exhausted or circuit breaker open.' );
		}

		$connection = $this->connections->get( 'claude' );
		$api_key    = (string) ( $connection['credentials']['api_key'] ?? '' );
		if ( '' === $api_key ) {
			return new \WP_Error( 'sda_ai_auth', 'Claude API key is not configured.' );
		}

		$result = $this->http->post(
			self::ENDPOINT,
			[
				'timeout' => 60,
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				],
				'body'    => [
					'model'      => $this->model(),
					'max_tokens' => $envelope->max_tokens,
					'system'     => $envelope->system,
					'messages'   => [
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

		// Concatenate text blocks (skip thinking/tool blocks).
		$text = '';
		foreach ( (array) ( $data['content'] ?? [] ) as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= (string) ( $block['text'] ?? '' );
			}
		}

		$tokens = (int) ( $data['usage']['input_tokens'] ?? 0 ) + (int) ( $data['usage']['output_tokens'] ?? 0 );

		return [ 'text' => $text, 'tokens' => $tokens ];
	}
}
