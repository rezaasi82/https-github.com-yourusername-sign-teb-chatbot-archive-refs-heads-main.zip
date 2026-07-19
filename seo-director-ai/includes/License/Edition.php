<?php
/**
 * Editions and the feature matrix. Single source of truth for what each tier unlocks.
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

final class Edition {

	public const FREE       = 'free';
	public const STARTER    = 'starter';
	public const PRO        = 'pro';
	public const AGENCY     = 'agency';
	public const LIFETIME   = 'lifetime';
	public const ENTERPRISE = 'enterprise';

	// Feature slugs referenced by FeatureGate throughout the plugin.
	public const F_AI_INSIGHTS       = 'ai_insights';
	public const F_ADVANCED_DETECTORS = 'advanced_detectors';
	public const F_REPORTS            = 'reports';
	public const F_SCHEDULED_REPORTS  = 'scheduled_reports';
	public const F_ROADMAP            = 'roadmap';
	public const F_AGENCY_HUB         = 'agency_hub';
	public const F_WHITE_LABEL        = 'white_label';

	/**
	 * Editions ranked low→high; a feature available at tier N is available at every tier above it.
	 *
	 * @var array<string, int>
	 */
	private const RANK = array(
		self::FREE       => 0,
		self::STARTER    => 1,
		self::PRO        => 2,
		self::LIFETIME   => 2, // Lifetime == Pro-level features, no expiry.
		self::AGENCY     => 3,
		self::ENTERPRISE => 4,
	);

	/**
	 * Minimum edition rank each feature requires.
	 *
	 * @var array<string, string>
	 */
	private const MIN_EDITION = array(
		self::F_ROADMAP            => self::STARTER,
		self::F_AI_INSIGHTS        => self::PRO,
		self::F_ADVANCED_DETECTORS => self::PRO,
		self::F_REPORTS            => self::PRO,
		self::F_SCHEDULED_REPORTS  => self::PRO,
		self::F_AGENCY_HUB         => self::AGENCY,
		self::F_WHITE_LABEL        => self::AGENCY,
	);

	public static function rank( string $edition ): int {
		return self::RANK[ $edition ] ?? 0;
	}

	public static function is_valid( string $edition ): bool {
		return isset( self::RANK[ $edition ] );
	}

	/**
	 * Does an edition include a feature? Unknown features default to available
	 * (they're baseline), unknown editions to FREE.
	 */
	public static function includes( string $edition, string $feature ): bool {
		$required = self::MIN_EDITION[ $feature ] ?? self::FREE;
		return self::rank( $edition ) >= self::rank( $required );
	}

	/** @return string[] All gated feature slugs. */
	public static function gated_features(): array {
		return array_keys( self::MIN_EDITION );
	}
}
