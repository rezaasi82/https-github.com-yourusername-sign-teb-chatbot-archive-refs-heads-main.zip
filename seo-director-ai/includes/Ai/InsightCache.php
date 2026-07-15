<?php
/**
 * Evidence-hash-keyed cache backed by the insights table: a cache hit costs
 * zero AI tokens. Keyed by (type, evidence hash, language) so identical
 * evidence never re-spends, and re-rendering never re-calls the model.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

use SEODirector\Data\Repository\InsightRepository;

defined( 'ABSPATH' ) || exit;

final class InsightCache {

	public function __construct( private InsightRepository $insights ) {}

	/**
	 * @return array<string, mixed>|null Cached payload, or null on miss.
	 */
	public function get( string $type, PromptEnvelope $envelope ): ?array {
		$row = $this->insights->find_by_cache( $type, bin2hex( $envelope->cache_hash() ), $envelope->lang );

		return $row['payload'] ?? null;
	}
}
