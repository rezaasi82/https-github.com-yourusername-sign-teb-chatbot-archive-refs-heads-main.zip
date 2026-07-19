<?php
/**
 * Monthly AI token budget meter. Hard stop when exhausted — costs stay bounded.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;

final class TokenBudget {

	public function __construct( private readonly Options $options ) {}

	public function limit(): int {
		return (int) $this->options->get( 'ai_monthly_budget', 500000 );
	}

	public function used_this_month(): int {
		return (int) get_option( $this->usage_key(), 0 );
	}

	public function remaining(): int {
		return max( 0, $this->limit() - $this->used_this_month() );
	}

	public function can_spend( int $estimated_tokens ): bool {
		return $this->remaining() >= $estimated_tokens;
	}

	public function record_spend( int $tokens ): void {
		update_option( $this->usage_key(), $this->used_this_month() + $tokens, false );
	}

	private function usage_key(): string {
		return 'sda_ai_usage_' . gmdate( 'Ym' );
	}
}
