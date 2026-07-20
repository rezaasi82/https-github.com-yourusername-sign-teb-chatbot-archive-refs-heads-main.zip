<?php
/**
 * Medical structured data (Medical Pack). Builds the schema types YMYL
 * medical sites need but the generic SchemaGenerator does not:
 *
 *  - Physician + MedicalClinic (site-level, from settings) — printed on the
 *    front page so Google can attach the practice to the brand.
 *  - MedicalWebPage (per post) — marks the page as medical and lists the
 *    conditions/procedures it covers, detected by the entity engine.
 *
 * Per-post medical schema is stored in its own meta key and merged by
 * SchemaInjector, so it composes with (never overwrites) the generic
 * Article/FAQ schema.
 *
 * @package SEODirector
 */

namespace SEODirector\Medical;

use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class MedicalSchemaBuilder {

	public const META_KEY = '_sda_medical_schema';

	/** Entity category → schema.org type for the "about" list. */
	private const ABOUT_TYPE = [
		'disease'   => 'MedicalCondition',
		'symptom'   => 'MedicalSymptom',
		'treatment' => 'MedicalProcedure',
		'drug'      => 'Drug',
		'body_part' => 'AnatomicalStructure',
	];

	public function __construct(
		private Settings $settings,
		private MedicalEntityEngine $entities,
	) {}

	/**
	 * Site-level Physician + MedicalClinic nodes from settings. Returns an
	 * empty array when nothing is configured, so the injector prints nothing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function site_nodes(): array {
		$physician_name = trim( (string) $this->settings->get( 'med_physician_name', '' ) );
		$clinic_name    = trim( (string) $this->settings->get( 'med_clinic_name', '' ) );

		$nodes = [];

		if ( '' !== $physician_name ) {
			$physician = [
				'@type'      => 'Physician',
				'name'       => $physician_name,
				'url'        => (string) home_url( '/' ),
				'medicalSpecialty' => (string) $this->settings->get( 'med_physician_specialty', '' ),
			];
			$license = trim( (string) $this->settings->get( 'med_physician_license', '' ) );
			if ( '' !== $license ) {
				$physician['identifier'] = [
					'@type' => 'PropertyValue',
					'name'  => 'Medical license',
					'value' => $license,
				];
			}
			$nodes[] = array_filter( $physician );
		}

		if ( '' !== $clinic_name ) {
			$clinic = [
				'@type'     => 'MedicalClinic',
				'name'      => $clinic_name,
				'url'       => (string) home_url( '/' ),
				'telephone' => (string) $this->settings->get( 'med_clinic_phone', '' ),
			];
			$address = trim( (string) $this->settings->get( 'med_clinic_address', '' ) );
			if ( '' !== $address ) {
				$clinic['address'] = [ '@type' => 'PostalAddress', 'streetAddress' => $address ];
			}
			$nodes[] = array_filter( $clinic );
		}

		return $nodes;
	}

	/**
	 * MedicalWebPage node for a post, with detected conditions as "about".
	 *
	 * @return array{graph: array<int, array<string, mixed>>, entities: array<int, array{term: string, category: string, count: int}>}|\WP_Error
	 */
	public function build_post( int $post_id ): array|\WP_Error {
		$post = get_post( $post_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'sda_not_found', __( 'Post not found or not published.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$found = $this->entities->detect_in_post( $post_id );
		$about = [];
		$seen  = [];
		foreach ( $found as $entity ) {
			$type = self::ABOUT_TYPE[ $entity['category'] ] ?? null;
			if ( null === $type || isset( $seen[ $entity['term'] ] ) ) {
				continue;
			}
			$seen[ $entity['term'] ] = true;
			$about[]                 = [ '@type' => $type, 'name' => $entity['term'] ];
			if ( count( $about ) >= 15 ) {
				break;
			}
		}

		$node = [
			'@type'        => 'MedicalWebPage',
			'name'         => (string) get_the_title( $post ),
			'url'          => (string) get_permalink( $post ),
			'dateModified' => get_the_modified_date( 'c', $post ),
			'inLanguage'   => get_bloginfo( 'language' ),
		];
		if ( [] !== $about ) {
			$node['about'] = $about;
		}
		$reviewer = trim( (string) $this->settings->get( 'med_physician_name', '' ) );
		if ( '' !== $reviewer ) {
			$node['reviewedBy'] = [ '@type' => 'Physician', 'name' => $reviewer ];
		}

		return [ 'graph' => [ $node ], 'entities' => $found ];
	}

	/**
	 * Build and persist per-post medical schema.
	 *
	 * @return array{graph: array<int, array<string, mixed>>, entities: array<int, array<string, mixed>>}|\WP_Error
	 */
	public function save( int $post_id ): array|\WP_Error {
		$built = $this->build_post( $post_id );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $built['graph'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

		return $built;
	}

	public function remove( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}
}
