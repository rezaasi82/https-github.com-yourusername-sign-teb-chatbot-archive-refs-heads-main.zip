<?php
/**
 * Selects the admin-chosen AI provider, enforces the token budget, runs the
 * completion, validates the JSON against the schema (with one repair retry),
 * and falls back to the next configured provider on failure. Returns an
 * AiResult that is always either schema-valid or a clean failure — the UI
 * never sees malformed AI output.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class ProviderRouter {

	/**
	 * @param array<string, AiProviderInterface> $providers Keyed by slug.
	 */
	public function __construct(
		private array $providers,
		private SchemaValidator $validator,
		private TokenBudget $budget,
		private Settings $settings,
	) {}

	/**
	 * Whether AI features are usable right now (a provider chosen + configured + budget left).
	 */
	public function is_available(): bool {
		return null !== $this->primary() && $this->budget->within_budget();
	}

	/**
	 * Run an envelope through the provider chain.
	 */
	public function complete( string $type, PromptEnvelope $envelope ): AiResult {
		if ( ! $this->budget->within_budget() ) {
			return AiResult::failure( 'none', '', __( 'Monthly AI token budget reached.', 'seo-director-ai' ) );
		}

		$chain = $this->chain();
		if ( [] === $chain ) {
			return AiResult::failure( 'none', '', __( 'No AI provider is configured.', 'seo-director-ai' ) );
		}

		$last_error = 'unknown';

		foreach ( $chain as $provider ) {
			$result = $this->attempt( $provider, $envelope );
			if ( $result->ok() ) {
				return $result;
			}
			$last_error = $result->error ?? 'unknown';
		}

		return AiResult::failure( 'none', '', $last_error );
	}

	private function attempt( AiProviderInterface $provider, PromptEnvelope $envelope ): AiResult {
		$total_tokens = 0;

		for ( $try = 0; $try < 2; $try++ ) {
			$raw = $provider->complete( $envelope );
			if ( is_wp_error( $raw ) ) {
				return AiResult::failure( $provider->slug(), $provider->model(), $raw->get_error_message() );
			}

			$total_tokens += (int) $raw['tokens'];
			$this->budget->record( (int) $raw['tokens'] );

			$decoded = $this->validator->extract( (string) $raw['text'] );
			if ( null !== $decoded ) {
				$valid = $this->validator->validate( $decoded, $envelope->schema );
				if ( true === $valid ) {
					return new AiResult( $decoded, $provider->slug(), $provider->model(), $total_tokens );
				}
				// One repair attempt: re-ask with the specific validation error.
				if ( 0 === $try ) {
					$envelope = $this->repair_envelope( $envelope, is_string( $valid ) ? $valid : 'schema mismatch' );
					continue;
				}
			} elseif ( 0 === $try ) {
				$envelope = $this->repair_envelope( $envelope, 'response was not valid JSON' );
				continue;
			}

			return AiResult::failure( $provider->slug(), $provider->model(), 'AI response did not match the required schema.' );
		}

		return AiResult::failure( $provider->slug(), $provider->model(), 'AI response did not match the required schema.' );
	}

	private function repair_envelope( PromptEnvelope $envelope, string $reason ): PromptEnvelope {
		return new PromptEnvelope(
			$envelope->prompt_version,
			$envelope->system . " Your previous response was invalid ({$reason}). Return only corrected JSON matching the schema.",
			$envelope->evidence,
			$envelope->schema,
			$envelope->lang,
			$envelope->max_tokens
		);
	}

	private function primary(): ?AiProviderInterface {
		$chosen = (string) $this->settings->get( 'ai_provider', '' );
		if ( '' !== $chosen && isset( $this->providers[ $chosen ] ) && $this->providers[ $chosen ]->is_configured() ) {
			return $this->providers[ $chosen ];
		}

		foreach ( $this->providers as $provider ) {
			if ( $provider->is_configured() ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Primary provider first, then the rest (configured only) as fallbacks.
	 *
	 * @return AiProviderInterface[]
	 */
	private function chain(): array {
		$primary = $this->primary();
		if ( null === $primary ) {
			return [];
		}

		$chain = [ $primary ];
		foreach ( $this->providers as $provider ) {
			if ( $provider->slug() !== $primary->slug() && $provider->is_configured() ) {
				$chain[] = $provider;
			}
		}

		return $chain;
	}
}
