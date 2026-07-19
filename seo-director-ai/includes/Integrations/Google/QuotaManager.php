<?php
/**
 * Per-API daily quota budgets with a circuit breaker.
 *
 * Sync jobs consult this before every external call; when an API starts
 * rejecting (quota exhausted / repeated 5xx) the breaker opens and jobs
 * reschedule instead of hammering the API for the rest of the window.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

final class QuotaManager {

	private const BREAKER_COOLDOWN = 15 * MINUTE_IN_SECONDS;

	/** @var array<string, int> Daily call budgets per API family (filterable). */
	private const DEFAULT_BUDGETS = [
		'gsc'  => 20000,
		'ga4'  => 20000,
		'psi'  => 400,
		'ai'   => 2000,
		'gbp'  => 1000,
		'gads' => 1000,
	];

	/**
	 * Whether one more call to the API is allowed right now.
	 */
	public function allow( string $api ): bool {
		if ( $this->breaker_open( $api ) ) {
			return false;
		}

		return $this->used_today( $api ) < $this->budget( $api );
	}

	/**
	 * Record a completed call (successful or failed-but-counted).
	 */
	public function record( string $api ): void {
		$key  = $this->counter_key( $api );
		$used = (int) get_transient( $key );
		set_transient( $key, $used + 1, DAY_IN_SECONDS );
	}

	/**
	 * Open the circuit breaker after a quota/availability failure.
	 */
	public function trip( string $api ): void {
		set_transient( 'sda_breaker_' . $api, time(), self::BREAKER_COOLDOWN );
	}

	public function breaker_open( string $api ): bool {
		return false !== get_transient( 'sda_breaker_' . $api );
	}

	public function used_today( string $api ): int {
		return (int) get_transient( $this->counter_key( $api ) );
	}

	public function budget( string $api ): int {
		$budgets = self::DEFAULT_BUDGETS;

		/**
		 * Filters daily API call budgets.
		 *
		 * @param array<string, int> $budgets Budget per API family.
		 */
		$budgets = apply_filters( 'sda_api_budgets', $budgets );

		return (int) ( $budgets[ $api ] ?? 1000 );
	}

	private function counter_key( string $api ): string {
		return 'sda_quota_' . $api . '_' . gmdate( 'Ymd' );
	}
}
