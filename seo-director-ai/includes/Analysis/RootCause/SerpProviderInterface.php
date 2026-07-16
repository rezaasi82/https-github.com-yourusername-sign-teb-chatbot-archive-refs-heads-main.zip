<?php
/**
 * Contract for a live SERP lookup used to enrich root-cause analysis
 * (Enterprise). Kept in the Analysis namespace as a pure contract so the
 * candidate engine stays free of I/O; concrete HTTP providers live under
 * Integrations and are injected.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\RootCause;

defined( 'ABSPATH' ) || exit;

interface SerpProviderInterface {

	/**
	 * Fetch SERP context for a query, or null when unavailable/not applicable.
	 *
	 * @return array{features: string[], top_domains: string[]}|null
	 */
	public function serp_context( string $query ): ?array;
}
