<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Onboarding\DemoDataProvider;
use SEODirector\Onboarding\SetupStatus;

final class OnboardingTest extends TestCase {

	public function test_demo_overview_has_full_shape(): void {
		$demo = ( new DemoDataProvider() )->overview();

		$this->assertArrayHasKey( 'health', $demo );
		$this->assertArrayHasKey( 'traffic', $demo );
		$this->assertArrayHasKey( 'opportunities', $demo );
		$this->assertArrayHasKey( 'risks', $demo );
		$this->assertSame( 28, count( $demo['traffic']['series'] ) );
	}

	public function test_demo_series_is_deterministic(): void {
		$a = ( new DemoDataProvider() )->overview()['traffic']['series'];
		$b = ( new DemoDataProvider() )->overview()['traffic']['series'];

		$this->assertSame( $a[0]['clicks'], $b[0]['clicks'] );
		$this->assertGreaterThan( 0, $a[0]['clicks'] );
	}

	public function test_demo_ids_are_negative_to_avoid_real_collisions(): void {
		$demo = ( new DemoDataProvider() )->overview();

		$this->assertLessThan( 0, $demo['opportunities'][0]['id'] );
		$this->assertLessThan( 0, $demo['risks'][0]['id'] );
	}

	public function test_setup_complete_ignores_optional_ai(): void {
		$setup = ( new SetupStatus() )->build( true, true, false, true );

		$this->assertTrue( $setup['complete'] );
		$this->assertCount( 4, $setup['steps'] );
	}

	public function test_setup_incomplete_without_sync(): void {
		$setup = ( new SetupStatus() )->build( true, true, true, false );

		$this->assertFalse( $setup['complete'] );
		$this->assertFalse( $setup['has_data'] );
	}
}
