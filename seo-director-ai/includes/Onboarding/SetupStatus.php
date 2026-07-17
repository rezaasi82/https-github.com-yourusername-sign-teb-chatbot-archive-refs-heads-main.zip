<?php
/**
 * Turns the current connection/sync state into an ordered onboarding
 * checklist for the dashboard. Pure — the controller passes in the booleans it
 * already computed, so this stays trivially testable.
 *
 * @package SEODirector
 */

namespace SEODirector\Onboarding;

defined( 'ABSPATH' ) || exit;

final class SetupStatus {

	/**
	 * @return array{steps: array<int, array{key:string, label:string, done:bool}>, complete: bool, has_data: bool}
	 */
	public function build( bool $google_connected, bool $gsc_property, bool $ai_ready, bool $has_synced ): array {
		$steps = [
			[ 'key' => 'google', 'label' => __( 'Connect Google', 'seo-director-ai' ), 'done' => $google_connected ],
			[ 'key' => 'property', 'label' => __( 'Choose a Search Console property', 'seo-director-ai' ), 'done' => $gsc_property ],
			[ 'key' => 'ai', 'label' => __( 'Add an AI provider (optional)', 'seo-director-ai' ), 'done' => $ai_ready ],
			[ 'key' => 'sync', 'label' => __( 'First data sync', 'seo-director-ai' ), 'done' => $has_synced ],
		];

		// "Complete" ignores the optional AI step.
		$complete = $google_connected && $gsc_property && $has_synced;

		return [
			'steps'    => $steps,
			'complete' => $complete,
			'has_data' => $has_synced,
		];
	}
}
