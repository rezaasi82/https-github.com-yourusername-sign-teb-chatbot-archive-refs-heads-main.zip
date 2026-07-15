<?php
/**
 * The single source of truth for what each edition unlocks. Every module
 * asks FeatureGate::allows(); there is no scattered edition checking. During
 * the grace period the effective edition holds; after grace it drops to
 * 'starter' (data stays readable, PRO features pause) — never to nothing.
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

final class FeatureGate {

	/** Features unlocked per edition (cumulative). */
	private const MATRIX = [
		'starter'    => [
			'core_detectors',       // striking-distance, low-CTR, near-top
			'health_score',
			'movers',
			'alerts_email',
			'ai_explain',
			'roadmap_monthly',
			'reports_pdf',
		],
		'pro'        => [
			'all_detectors',        // + snippet, faq, schema, cannibalization, internal links
			'root_cause_full',
			'content_strategist',
			'reports_all_formats',
			'reports_schedule',
			'alert_channels',       // webhook / slack / telegram
			'roadmap_weekly',
			'roadmap_quarterly',
		],
		'agency'     => [
			'agency_hub',
			'white_label',
			'client_access',
		],
		'lifetime'   => [],         // Lifetime maps to its purchased tier's features.
		'enterprise' => [
			'saas_hub',
			'pooled_ai_keys',
			'custom_reports',
		],
	];

	/** Ordering for cumulative unlock. */
	private const RANK = [ 'starter' => 0, 'pro' => 1, 'agency' => 2, 'enterprise' => 3 ];

	public function __construct( private LicenseManager $license ) {}

	/**
	 * Whether the current effective edition unlocks a feature.
	 */
	public function allows( string $feature ): bool {
		$edition = $this->effective_edition();

		$rank = self::RANK[ $edition ] ?? 0;
		foreach ( self::MATRIX as $tier => $features ) {
			if ( ( self::RANK[ $tier ] ?? 99 ) <= $rank && in_array( $feature, $features, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Effective edition after grace/lock resolution. Starter is always the floor.
	 */
	public function effective_edition(): string {
		$status  = $this->license->status();
		$edition = $status['edition'];

		// After grace (full lock) PRO features pause but the base stays usable.
		if ( 'expired' === $status['state'] ) {
			return 'starter';
		}

		if ( 'lifetime' === $edition ) {
			return (string) ( $status['tier'] ?? 'pro' );
		}

		return isset( self::RANK[ $edition ] ) ? $edition : 'starter';
	}

	/**
	 * @return array<string, bool> Feature => allowed, for the SPA to gate UI.
	 */
	public function snapshot(): array {
		$all = [];
		foreach ( self::MATRIX as $features ) {
			foreach ( $features as $feature ) {
				$all[ $feature ] = $this->allows( $feature );
			}
		}

		return $all;
	}
}
