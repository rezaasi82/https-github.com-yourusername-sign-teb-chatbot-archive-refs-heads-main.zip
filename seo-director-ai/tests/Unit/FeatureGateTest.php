<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\License\Edition;
use SEODirector\License\LicenseState;

final class FeatureGateTest extends TestCase {

	public function test_free_edition_unlocks_nothing_gated(): void {
		$this->assertFalse( Edition::includes( Edition::FREE, Edition::F_AI_INSIGHTS ) );
		$this->assertFalse( Edition::includes( Edition::FREE, Edition::F_REPORTS ) );
		$this->assertFalse( Edition::includes( Edition::FREE, Edition::F_AGENCY_HUB ) );
	}

	public function test_starter_unlocks_roadmap_only(): void {
		$this->assertTrue( Edition::includes( Edition::STARTER, Edition::F_ROADMAP ) );
		$this->assertFalse( Edition::includes( Edition::STARTER, Edition::F_AI_INSIGHTS ) );
	}

	public function test_pro_unlocks_ai_and_reports_but_not_agency(): void {
		$this->assertTrue( Edition::includes( Edition::PRO, Edition::F_AI_INSIGHTS ) );
		$this->assertTrue( Edition::includes( Edition::PRO, Edition::F_REPORTS ) );
		$this->assertTrue( Edition::includes( Edition::PRO, Edition::F_ADVANCED_DETECTORS ) );
		$this->assertFalse( Edition::includes( Edition::PRO, Edition::F_AGENCY_HUB ) );
		$this->assertFalse( Edition::includes( Edition::PRO, Edition::F_WHITE_LABEL ) );
	}

	public function test_lifetime_matches_pro_features(): void {
		$this->assertTrue( Edition::includes( Edition::LIFETIME, Edition::F_AI_INSIGHTS ) );
		$this->assertFalse( Edition::includes( Edition::LIFETIME, Edition::F_AGENCY_HUB ) );
	}

	public function test_agency_unlocks_everything(): void {
		foreach ( Edition::gated_features() as $feature ) {
			$this->assertTrue( Edition::includes( Edition::AGENCY, $feature ), "agency should include {$feature}" );
		}
	}

	public function test_enterprise_outranks_agency(): void {
		$this->assertGreaterThan( Edition::rank( Edition::AGENCY ), Edition::rank( Edition::ENTERPRISE ) );
	}

	public function test_grace_state_keeps_paid_edition_effective(): void {
		$state = new LicenseState( LicenseState::GRACE, Edition::PRO, '2026-01-01 00:00:00', '2026-01-15 00:00:00' );
		$this->assertSame( Edition::PRO, $state->effective_edition() );
		$this->assertTrue( Edition::includes( $state->effective_edition(), Edition::F_AI_INSIGHTS ) );
	}

	public function test_expired_state_collapses_to_free(): void {
		$state = new LicenseState( LicenseState::EXPIRED, Edition::PRO, '2025-01-01 00:00:00', '2025-01-15 00:00:00' );
		$this->assertSame( Edition::FREE, $state->effective_edition() );
		$this->assertFalse( Edition::includes( $state->effective_edition(), Edition::F_AI_INSIGHTS ) );
	}

	public function test_invalid_edition_is_rejected(): void {
		$this->assertFalse( Edition::is_valid( 'platinum' ) );
		$this->assertTrue( Edition::is_valid( Edition::PRO ) );
	}
}
