<?php
/**
 * Orchestrates AI insight generation: cache lookup → budget check → router →
 * schema-valid payload → persist. The determinism boundary lives here: this class
 * builds the evidence packet from stored metrics; the AI only supplies text.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;
use WP_Error;

final class InsightGenerator {

	public function __construct(
		private readonly ProviderRouter $router,
		private readonly PromptLibrary $prompts,
		private readonly InsightCache $cache,
		private readonly TokenBudget $budget,
		private readonly Options $options,
	) {}

	/**
	 * Generate (or return cached) insight of a type from a prepared evidence packet.
	 *
	 * @param array<string, mixed> $evidence Deterministic evidence — the ONLY numbers the AI sees.
	 * @param array<string, mixed> $meta     entity_type, entity_label, period_start, period_end.
	 * @return array{payload: array<string, mixed>, cached: bool}|WP_Error
	 */
	public function generate( string $type, array $evidence, array $meta = array() ): array|WP_Error {
		$language = $this->resolve_language();
		$envelope = $this->prompts->build( $type, $evidence, $language );
		$hash     = $envelope->evidence_hash();

		$cached = $this->cache->get( $type, $hash, $language );
		if ( null !== $cached ) {
			return array( 'payload' => $cached, 'cached' => true );
		}

		if ( ! (bool) $this->options->get( 'ai_enabled', false ) ) {
			return new WP_Error( 'sda_ai_disabled', __( 'AI features are disabled in settings.', 'seo-director-ai' ) );
		}

		// Rough pre-flight budget guard (max_tokens is the ceiling per call).
		if ( ! $this->budget->can_spend( $envelope->max_tokens ) ) {
			return new WP_Error(
				'sda_budget_reached',
				__( 'Monthly AI token budget reached — insights resume next month or raise the cap in settings.', 'seo-director-ai' )
			);
		}

		$result = $this->router->complete( $envelope );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->budget->record_spend( $result->tokens_used );

		$this->cache->put(
			$type,
			$hash,
			$language,
			$result->payload,
			array(
				'entity_type'    => (string) ( $meta['entity_type'] ?? 'site' ),
				'entity_label'   => (string) ( $meta['entity_label'] ?? '' ),
				'period_start'   => (string) ( $meta['period_start'] ?? gmdate( 'Y-m-d' ) ),
				'period_end'     => (string) ( $meta['period_end'] ?? gmdate( 'Y-m-d' ) ),
				'evidence'       => $evidence,
				'ai_provider'    => $result->provider,
				'ai_model'       => $result->model,
				'prompt_version' => PromptLibrary::VERSION,
				'tokens_used'    => $result->tokens_used,
			)
		);

		/**
		 * Fires when a fresh AI insight is generated (cache misses only).
		 *
		 * @param string               $type    Insight type.
		 * @param array<string, mixed> $payload Validated payload.
		 */
		do_action( 'sda_insight_generated', $type, $result->payload );

		return array( 'payload' => $result->payload, 'cached' => false );
	}

	/**
	 * "auto" follows the WordPress locale; otherwise the explicit setting wins.
	 */
	private function resolve_language(): string {
		$configured = (string) $this->options->get( 'ai_language', 'auto' );
		if ( 'auto' !== $configured && '' !== $configured ) {
			return $configured;
		}
		return substr( (string) get_locale(), 0, 2 ) ?: 'en';
	}
}
