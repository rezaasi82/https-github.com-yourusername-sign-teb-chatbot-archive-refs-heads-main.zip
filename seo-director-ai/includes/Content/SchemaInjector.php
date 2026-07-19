<?php
/**
 * Prints saved JSON-LD on the front end. This is the plugin's only public-
 * side output: one echo of pre-built post meta on singular pages — no
 * queries beyond the meta read, no assets, so the conditional-loading rule
 * ("nothing enqueued on the public site") effectively still holds.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

defined( 'ABSPATH' ) || exit;

final class SchemaInjector {

	public function register(): void {
		add_action( 'wp_head', [ $this, 'print_schema' ], 5 );
	}

	public function print_schema(): void {
		if ( ! is_singular() ) {
			return;
		}

		$raw = get_post_meta( (int) get_queried_object_id(), SchemaGenerator::META_KEY, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$graph = json_decode( $raw, true );
		if ( ! is_array( $graph ) || [] === $graph ) {
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
}
