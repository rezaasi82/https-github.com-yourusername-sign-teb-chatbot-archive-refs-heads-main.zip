<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Alerts\EscalationPolicy;

final class EscalationPolicyTest extends TestCase {

	private EscalationPolicy $policy;

	protected function setUp(): void {
		$this->policy = new EscalationPolicy();
	}

	private function alert( array $overrides = [] ): array {
		return array_merge(
			[ 'id' => 1, 'severity' => 'critical', 'raised_at' => '2026-01-01 00:00:00', 'escalated_at' => null ],
			$overrides
		);
	}

	public function test_escalates_critical_open_past_threshold(): void {
		$now      = strtotime( '2026-01-02 01:00:00 UTC' ); // 25h after raised.
		$breaches = $this->policy->breaches( [ $this->alert() ], 24, $now );

		$this->assertCount( 1, $breaches );
		$this->assertSame( 1, $breaches[0]['id'] );
	}

	public function test_does_not_escalate_before_threshold(): void {
		$now = strtotime( '2026-01-01 12:00:00 UTC' ); // 12h after raised.

		$this->assertCount( 0, $this->policy->breaches( [ $this->alert() ], 24, $now ) );
	}

	public function test_skips_already_escalated(): void {
		$now   = strtotime( '2026-01-05 00:00:00 UTC' );
		$alert = $this->alert( [ 'escalated_at' => '2026-01-02 00:00:00' ] );

		$this->assertCount( 0, $this->policy->breaches( [ $alert ], 24, $now ) );
	}

	public function test_ignores_medium_and_low_severity(): void {
		$now    = strtotime( '2026-02-01 00:00:00 UTC' );
		$alerts = [
			$this->alert( [ 'id' => 1, 'severity' => 'medium' ] ),
			$this->alert( [ 'id' => 2, 'severity' => 'low' ] ),
			$this->alert( [ 'id' => 3, 'severity' => 'high' ] ),
		];

		$breaches = $this->policy->breaches( $alerts, 24, $now );

		$this->assertCount( 1, $breaches );
		$this->assertSame( 3, $breaches[0]['id'] );
	}

	public function test_handles_malformed_raised_at(): void {
		$now   = strtotime( '2026-02-01 00:00:00 UTC' );
		$alert = $this->alert( [ 'raised_at' => 'not-a-date' ] );

		$this->assertCount( 0, $this->policy->breaches( [ $alert ], 24, $now ) );
	}
}
