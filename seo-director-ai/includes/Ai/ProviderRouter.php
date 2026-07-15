<?php
/**
 * Selects the configured AI provider, validates output against the envelope schema,
 * retries once with a "fix to schema" instruction, and falls back across providers.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

use SEODirector\Ai\Providers\ClaudeProvider;
use SEODirector\Ai\Providers\GeminiProvider;
use SEODirector\Ai\Providers\OpenAiProvider;
use SEODirector\Core\Options;
use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class ProviderRouter {

	/** Default model per provider when the admin has not overridden it. */
	private const DEFAULT_MODELS = array(
		'claude' => 'claude-sonnet-5',
		'openai' => 'gpt-4.1-mini',
		'gemini' => 'gemini-2.0-flash',
	);

	/** @var array<string, AiProviderInterface> */
	private array $providers;

	public function __construct(
		private readonly Options $options,
		RetryingHttpClient $http,
		private readonly ConnectionsRepository $connections,
		private readonly SchemaValidator $validator,
	) {
		$providers = array(
			'claude' => new ClaudeProvider( $http ),
			'openai' => new OpenAiProvider( $http ),
			'gemini' => new GeminiProvider( $http ),
		);

		/**
		 * Register additional AI providers.
		 *
		 * @param array<string, AiProviderInterface> $providers slug => provider.
		 */
		$this->providers = (array) apply_filters( 'sda_register_ai_providers', $providers );
	}

	/**
	 * Complete an envelope with schema-valid output.
	 * Order: preferred provider → other providers with stored keys.
	 */
	public function complete( PromptEnvelope $envelope ): AiResult|WP_Error {
		if ( ! (bool) $this->options->get( 'ai_enabled', false ) ) {
			return new WP_Error( 'sda_ai_disabled', __( 'AI features are disabled in settings.', 'seo-director-ai' ) );
		}

		$preferred = (string) $this->options->get( 'ai_provider', 'claude' );
		$order     = array_unique( array_merge( array( $preferred ), array_keys( $this->providers ) ) );
		$last      = new WP_Error( 'sda_ai_unavailable', __( 'No AI provider is connected.', 'seo-director-ai' ) );

		foreach ( $order as $slug ) {
			$provider = $this->providers[ $slug ] ?? null;
			if ( ! $provider ) {
				continue;
			}
			$api_key = $this->connections->get_api_key( $slug );
			if ( null === $api_key ) {
				continue;
			}

			$model  = $slug === $preferred
				? (string) $this->options->get( 'ai_model', self::DEFAULT_MODELS[ $slug ] ?? '' )
				: ( self::DEFAULT_MODELS[ $slug ] ?? '' );
			$result = $this->attempt( $provider, $envelope, $model, $api_key );

			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			$last = $result;
		}

		return $last;
	}

	private function attempt( AiProviderInterface $provider, PromptEnvelope $envelope, string $model, string $api_key ): AiResult|WP_Error {
		$response = $provider->complete( $envelope, $model, $api_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tokens  = (int) $response['tokens'];
		$payload = $this->validator->parse_and_validate( (string) $response['text'], $envelope->output_schema );

		// One repair retry: feed the invalid output back with a strict instruction.
		if ( null === $payload ) {
			$repair_envelope = new PromptEnvelope(
				$envelope->prompt_id,
				$envelope->prompt_version,
				$envelope->system_prompt,
				"Your previous output was not valid for the required JSON Schema. Previous output:\n"
					. mb_substr( (string) $response['text'], 0, 4000 )
					. "\n\nReturn ONLY corrected JSON matching the schema.",
				$envelope->evidence,
				$envelope->output_schema,
				$envelope->language,
				$envelope->max_tokens
			);
			$retry = $provider->complete( $repair_envelope, $model, $api_key );
			if ( ! is_wp_error( $retry ) ) {
				$tokens += (int) $retry['tokens'];
				$payload = $this->validator->parse_and_validate( (string) $retry['text'], $envelope->output_schema );
			}
		}

		if ( null === $payload ) {
			return new WP_Error( 'sda_ai_schema', __( 'AI output failed schema validation.', 'seo-director-ai' ) );
		}

		return new AiResult( $payload, $provider->slug(), $model, $tokens );
	}
}
