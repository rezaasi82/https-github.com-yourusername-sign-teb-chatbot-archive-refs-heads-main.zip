<?php
/**
 * Known Google algorithm/core update dates. Seeded locally and refreshable
 * from the license/update server so proximity detection stays current
 * without a plugin release. Pure lookup.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\RootCause;

defined( 'ABSPATH' ) || exit;

final class CoreUpdateCalendar {

	/** @var array<string, string> ISO date => label. */
	private const SEED = [
		'2025-03-13' => 'March 2025 Core Update',
		'2025-06-20' => 'June 2025 Core Update',
		'2025-11-10' => 'November 2025 Core Update',
		'2026-03-05' => 'March 2026 Core Update',
		'2026-06-12' => 'June 2026 Core Update',
	];

	/**
	 * The update whose start date is within ±$window days of $date, or null.
	 */
	public function near( string $date, int $window = 5 ): ?string {
		$target = strtotime( $date );
		if ( false === $target ) {
			return null;
		}

		$calendar = self::SEED;
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the core-update calendar (remotely updatable).
			 *
			 * @param array<string, string> $calendar ISO date => label.
			 */
			$calendar = apply_filters( 'sda_core_update_calendar', $calendar );
		}

		foreach ( $calendar as $update_date => $label ) {
			$diff = abs( $target - strtotime( $update_date ) );
			if ( $diff <= $window * DAY_IN_SECONDS ) {
				return $label;
			}
		}

		return null;
	}
}
