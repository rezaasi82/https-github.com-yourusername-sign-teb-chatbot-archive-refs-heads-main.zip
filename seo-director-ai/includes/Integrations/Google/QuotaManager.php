<?php
/**
 * Per-API daily budgets with a transient-backed circuit breaker.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Google;

defined( 'ABSPATH' ) || exit;

final class QuotaManager {

	/** Default daily request budgets per service. */
	private const BUDGETS = array(
		'gsc' => 1800,   // GSC API: 2000/day per site — keep headroom.
		'ga4' => 1500,
		'psi' => 400,
		'ai'  => 2000,
	);

	private const BREAKER_TTL = 15 * MINUTE_IN_SECONDS;

	public function can_request( string $service ): bool {
		if ( get_transient( "sda_breaker_{$service}" ) ) {
			return false;
		}
		return $this->used_today( $service ) < $this->budget( $service );
	}

	public function record_request( string $service, int $count = 1 ): void {
		$key  = $this->counter_key( $service );
		$used = (int) get_transient( $key );
		set_transient( $key, $used + $count, DAY_IN_SECONDS );
	}

	/**
	 * Trip the circuit breaker after hard upstream failures (403 quota / repeated 5xx).
	 */
	public function trip_breaker( string $service ): void {
		set_transient( "sda_breaker_{$service}", 1, self::BREAKER_TTL );
	}

	public function used_today( string $service ): int {
		return (int) get_transient( $this->counter_key( $service ) );
	}

	public function budget( string $service ): int {
		$budget = self::BUDGETS[ $service ] ?? 500;
		/**
		 * Filter the daily API request budget per service.
		 *
		 * @param int    $budget  Requests per day.
		 * @param string $service Service slug.
		 */
		return (int) apply_filters( 'sda_api_daily_budget', $budget, $service );
	}

	private function counter_key( string $service ): string {
		return 'sda_quota_' . $service . '_' . gmdate( 'Ymd' );
	}
}
