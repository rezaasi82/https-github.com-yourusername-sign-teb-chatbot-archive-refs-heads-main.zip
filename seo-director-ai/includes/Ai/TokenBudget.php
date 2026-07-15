<?php
/**
 * Per-month AI token budget with a visible meter. The router refuses calls
 * once the monthly cap is reached, so a runaway workload can't drain a
 * customer's BYO-key balance. Usage counter resets on month rollover.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class TokenBudget {

	public function __construct( private Settings $settings ) {}

	public function within_budget(): bool {
		$cap = (int) $this->settings->get( 'ai_monthly_token_cap', 500000 );
		if ( $cap <= 0 ) {
			return true; // 0 = uncapped.
		}

		return $this->used_this_month() < $cap;
	}

	public function record( int $tokens ): void {
		if ( $tokens <= 0 ) {
			return;
		}

		$key  = $this->option_key();
		$used = (int) get_option( $key, 0 );
		update_option( $key, $used + $tokens, false );
	}

	public function used_this_month(): int {
		return (int) get_option( $this->option_key(), 0 );
	}

	/**
	 * @return array{used: int, cap: int, remaining: int|null}
	 */
	public function meter(): array {
		$cap  = (int) $this->settings->get( 'ai_monthly_token_cap', 500000 );
		$used = $this->used_this_month();

		return [
			'used'      => $used,
			'cap'       => $cap,
			'remaining' => $cap > 0 ? max( 0, $cap - $used ) : null,
		];
	}

	private function option_key(): string {
		return 'sda_ai_tokens_' . gmdate( 'Ym' );
	}
}
