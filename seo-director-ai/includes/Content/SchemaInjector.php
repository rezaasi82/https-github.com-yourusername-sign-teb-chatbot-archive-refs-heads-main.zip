<?php
/**
 * Prints saved JSON-LD on the front end. This is the plugin's only public-
 * side output: pre-built post meta echoed on singular pages, plus optional
 * site-level medical nodes on the front page — no assets, no heavy queries,
 * so the conditional-loading rule ("nothing enqueued on the public site")
 * effectively still holds.
 *
 * Three sources are merged into one @graph: the generic Article/FAQ/Breadcrumb
 * schema (SchemaGenerator), the per-post medical schema (MedicalSchemaBuilder),
 * and — on the front page only — the site-level Physician/MedicalClinic nodes.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Medical\MedicalSchemaBuilder;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SchemaInjector {

	public function __construct(
		private Settings $settings,
		private MedicalSchemaBuilder $medical,
	) {}

	public function register(): void {
		add_action( 'wp_head', [ $this, 'print_schema' ], 5 );
	}

	public function print_schema(): void {
		$graph = [];

		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			$graph   = array_merge(
				$graph,
				$this->decode( get_post_meta( $post_id, SchemaGenerator::META_KEY, true ) )
			);

			if ( $this->medical_mode() ) {
				$graph = array_merge(
					$graph,
					$this->decode( get_post_meta( $post_id, MedicalSchemaBuilder::META_KEY, true ) )
				);
			}
		}

		// Site-level medical identity on the front page.
		if ( $this->medical_mode() && ( is_front_page() || is_home() ) ) {
			$graph = array_merge( $graph, $this->medical->site_nodes() );
		}

		if ( [] === $graph ) {
			return;
		}

		$document = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		];

		// wp_json_encode escapes </script> sequences, so this is safe to echo.
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * @param mixed $raw Stored JSON string.
	 * @return array<int, array<string, mixed>>
	 */
	private function decode( mixed $raw ): array {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}
		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	private function medical_mode(): bool {
		// Any active vertical (medical or business) prints its schema.
		return 'none' !== $this->settings->vertical();
	}
}
