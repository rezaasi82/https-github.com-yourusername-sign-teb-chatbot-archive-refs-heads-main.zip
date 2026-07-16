<?php
/**
 * Enterprise brand-voice profile. Turns admin-configured tone/audience/notes
 * into a single directive that is appended to the AI system prompt, so
 * generated explanations, summaries, and meta copy read in the customer's
 * house style. Only active on editions that unlock the brand_voice feature;
 * otherwise directive() returns an empty string and prompts are unchanged.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class BrandVoice {

	private const TONES = [ 'professional', 'friendly', 'authoritative', 'playful', 'concise', 'technical' ];

	public function __construct(
		private Settings $settings,
		private FeatureGate $gate,
	) {}

	public function is_active(): bool {
		return $this->gate->allows( 'brand_voice' ) && '' !== $this->directive();
	}

	/**
	 * Build the brand-voice directive appended to the system prompt. Empty when
	 * the feature is locked or nothing is configured.
	 */
	public function directive(): string {
		if ( ! $this->gate->allows( 'brand_voice' ) ) {
			return '';
		}

		$parts = [];

		$tone = (string) $this->settings->get( 'brand_voice_tone', '' );
		if ( in_array( $tone, self::TONES, true ) ) {
			$parts[] = sprintf( 'Write in a %s tone.', $tone );
		}

		$audience = trim( (string) $this->settings->get( 'brand_voice_audience', '' ) );
		if ( '' !== $audience ) {
			$parts[] = sprintf( 'Address this audience: %s.', $audience );
		}

		$notes = trim( (string) $this->settings->get( 'brand_voice_notes', '' ) );
		if ( '' !== $notes ) {
			$parts[] = sprintf( 'Style notes: %s', $notes );
		}

		$avoid = trim( (string) $this->settings->get( 'brand_voice_avoid', '' ) );
		if ( '' !== $avoid ) {
			$parts[] = sprintf( 'Avoid these words or phrases: %s.', $avoid );
		}

		return '' === implode( '', $parts ) ? '' : 'Brand voice — ' . implode( ' ', $parts );
	}

	/**
	 * @return string[] Allowed tone slugs, for the settings UI.
	 */
	public static function tones(): array {
		return self::TONES;
	}
}
