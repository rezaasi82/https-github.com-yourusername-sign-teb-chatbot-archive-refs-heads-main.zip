<?php
/**
 * Orchestrates one AI insight: cache lookup → build envelope → route through
 * providers → persist with its evidence snapshot. The single entry point the
 * REST layer and analysis job call; enforces the determinism boundary
 * (numbers from evidence, text from AI) and never re-spends on a cache hit.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

use SEODirector\Data\Repository\InsightRepository;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class InsightService {

	public function __construct(
		private ProviderRouter $router,
		private PromptLibrary $prompts,
		private InsightCache $cache,
		private InsightRepository $insights,
		private Settings $settings,
	) {}

	public function is_available(): bool {
		return $this->router->is_available();
	}

	/**
	 * Generate (or return cached) insight text for a type + evidence packet.
	 *
	 * @param array<string, mixed>  $evidence
	 * @param array<string, mixed>  $meta     entity_type, entity_hash_hex, entity_label, period_start/end.
	 * @return array{payload: array<string, mixed>, cached: bool}|\WP_Error
	 */
	public function generate( string $type, array $evidence, array $meta = [] ): array|\WP_Error {
		$lang     = $this->lang();
		$envelope = $this->prompts->build( $type, $evidence, $this->site_context(), $lang );

		$cached = $this->cache->get( $type, $envelope );
		if ( null !== $cached ) {
			return [ 'payload' => $cached, 'cached' => true ];
		}

		$result = $this->router->complete( $type, $envelope );
		if ( ! $result->ok() ) {
			return new \WP_Error( 'sda_ai', $result->error ?? __( 'AI generation failed.', 'seo-director-ai' ) );
		}

		$this->insights->save(
			array_merge(
				$meta,
				[
					'type'              => $type,
					'evidence'          => $evidence,
					'evidence_hash_hex' => bin2hex( $envelope->cache_hash() ),
					'payload'           => $result->payload,
					'ai_provider'       => $result->provider,
					'ai_model'          => $result->model,
					'prompt_version'    => $envelope->prompt_version,
					'lang'              => $lang,
					'tokens_used'       => $result->tokens_used,
				]
			)
		);

		/**
		 * Fires when a new AI insight is generated.
		 *
		 * @param string               $type    Insight type.
		 * @param array<string, mixed> $payload AI payload.
		 */
		do_action( 'sda_insight_generated', $type, $result->payload );

		return [ 'payload' => $result->payload, 'cached' => false ];
	}

	private function lang(): string {
		$locale = get_user_locale();

		return str_starts_with( $locale, 'fa' ) ? 'fa' : ( str_starts_with( $locale, 'ar' ) ? 'ar' : 'en' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function site_context(): array {
		return [
			'niche' => $this->settings->get( 'site_niche', '' ),
			'goals' => $this->settings->get( 'site_goals', '' ),
		];
	}
}
