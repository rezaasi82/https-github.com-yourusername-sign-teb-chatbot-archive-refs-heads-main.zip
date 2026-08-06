<?php
/**
 * Medical entity engine (Medical Pack). A deterministic, dictionary-based
 * detector that finds medical entities — diseases, symptoms, treatments,
 * drugs, specialties, body parts — in post content. Persian-first, but the
 * dictionary is filterable so a clinic can extend it for its own niche.
 *
 * This is the E-E-A-T backbone: knowing which medical concepts a page covers
 * lets the plugin build MedicalWebPage schema, spot coverage gaps, and feed
 * the knowledge graph — without shipping a heavyweight NLP model.
 *
 * @package SEODirector
 */

namespace SEODirector\Medical;

use SEODirector\Support\Settings;
use SEODirector\Vertical\BusinessDictionaries;

defined( 'ABSPATH' ) || exit;

final class MedicalEntityEngine {

	public function __construct( private ?Settings $settings = null ) {}

	/** Active site vertical: 'medical', 'business', or 'none'. */
	public function vertical(): string {
		return null !== $this->settings ? $this->settings->vertical() : 'none';
	}

	/**
	 * The active dictionary preset, namespaced by vertical so cache keys never
	 * collide (e.g. "medical:gastro_hepatology", "business:salon").
	 */
	public function active_preset(): string {
		if ( 'business' === $this->vertical() ) {
			$preset = null !== $this->settings ? (string) $this->settings->get( 'biz_type_preset', 'agency' ) : 'agency';
			$preset = array_key_exists( $preset, BusinessDictionaries::PRESETS ) ? $preset : 'agency';

			return 'business:' . $preset;
		}

		$preset = null !== $this->settings ? (string) $this->settings->get( 'med_specialty_preset', 'general' ) : 'general';
		$preset = array_key_exists( $preset, MedicalDictionaries::PRESETS ) ? $preset : 'general';

		return 'medical:' . $preset;
	}

	/** Category slug => human label for the active vertical (for the UI). */
	public function category_labels(): array {
		return 'business' === $this->vertical() ? BusinessDictionaries::CATEGORY_LABEL : [];
	}

	/**
	 * The active dictionary for the current vertical + preset, then the
	 * relevant filter. Longer terms first so "فتق ناف" matches before "فتق".
	 *
	 * @return array<string, string[]>
	 */
	public function dictionary(): array {
		// active_preset() is namespaced ("medical:foo" / "business:bar").
		[ $vertical, $preset ] = array_pad( explode( ':', $this->active_preset(), 2 ), 2, '' );

		if ( 'business' === $vertical ) {
			$base = BusinessDictionaries::for_preset( $preset );
		} else {
			$base = MedicalDictionaries::for_preset( $preset );
			/**
			 * Filters the medical entity dictionary.
			 *
			 * @param array<string, string[]> $dictionary category => terms.
			 * @param string                  $preset     Active specialty preset slug.
			 */
			$base = (array) apply_filters( 'sda_medical_dictionary', $base, $preset );
		}

		$dictionary = $base;

		foreach ( $dictionary as &$terms ) {
			$terms = array_values( array_unique( array_map( 'strval', (array) $terms ) ) );
			usort( $terms, static fn( string $a, string $b ) => mb_strlen( $b ) <=> mb_strlen( $a ) );
		}
		unset( $terms );

		return $dictionary;
	}

	/**
	 * Detect entities in raw text (already stripped of tags is fine too).
	 *
	 * @return array<int, array{term: string, category: string, count: int}>
	 */
	public function detect( string $text ): array {
		$haystack = ' ' . mb_strtolower( wp_strip_all_tags( $text ) ) . ' ';
		$found    = [];

		foreach ( $this->dictionary() as $category => $terms ) {
			foreach ( $terms as $term ) {
				$needle = mb_strtolower( $term );
				$count  = mb_substr_count( $haystack, $needle );
				if ( $count > 0 ) {
					$found[] = [ 'term' => $term, 'category' => $category, 'count' => $count ];
				}
			}
		}

		// Most-mentioned first.
		usort( $found, static fn( array $a, array $b ) => $b['count'] <=> $a['count'] );

		return $found;
	}

	/**
	 * Detect entities in a published post by id.
	 *
	 * @return array<int, array{term: string, category: string, count: int}>
	 */
	public function detect_in_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( null === $post ) {
			return [];
		}

		return $this->detect( (string) get_the_title( $post ) . ' ' . (string) $post->post_content );
	}

	/** Flat list of unique terms across the dictionary (for coverage checks). */
	public function all_terms(): array {
		$all = [];
		foreach ( $this->dictionary() as $category => $terms ) {
			foreach ( $terms as $term ) {
				$all[ $term ] = $category;
			}
		}

		return $all;
	}
}
