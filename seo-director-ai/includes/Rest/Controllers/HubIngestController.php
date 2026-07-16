<?php
/**
 * POST /sda/v1/hub/ingest — the endpoint client sites push snapshots to.
 *
 * Authentication is HMAC, not a WP session: the caller signs the raw body and
 * a timestamp with the shared pairing key, and the hub verifies against the
 * key it stored (encrypted) when the client was paired. There is no cookie or
 * nonce here, so the permission callback is open and every request must pass
 * signature verification inside the handler.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Agency\SiteConnector;
use SEODirector\Data\Repository\AgencySitesRepository;
use SEODirector\License\FeatureGate;
use SEODirector\Support\RateLimiter;

defined( 'ABSPATH' ) || exit;

final class HubIngestController extends AbstractController {

	public function __construct(
		private AgencySitesRepository $sites,
		private SiteConnector $connector,
		private FeatureGate $gate,
		private RateLimiter $limiter,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/hub/ingest',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'ingest' ],
				'permission_callback' => '__return_true', // HMAC-verified in the handler.
			]
		);
	}

	public function ingest( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! $this->gate->allows( 'agency_hub' ) ) {
			return new \WP_Error( 'sda_agency', __( 'Hub ingest is not enabled on this site.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		$site      = esc_url_raw( (string) $request->get_header( 'x_sda_site' ) );
		$timestamp = (int) $request->get_header( 'x_sda_timestamp' );
		$signature = (string) $request->get_header( 'x_sda_signature' );
		$body      = $request->get_body();

		if ( '' === $site || '' === $signature ) {
			return new \WP_Error( 'sda_hub_headers', __( 'Missing signature headers.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		// Throttle by source site so a leaked key can't be used to flood the hub.
		if ( ! $this->limiter->allow( 'hub_ingest_' . md5( $site ), 60, HOUR_IN_SECONDS ) ) {
			return new \WP_Error( 'sda_rate', __( 'Too many snapshots. Try again later.', 'seo-director-ai' ), [ 'status' => 429 ] );
		}

		$secret = $this->sites->find_secret_by_url( $site );
		if ( null === $secret ) {
			return new \WP_Error( 'sda_hub_unknown', __( 'Unknown or unpaired site.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		if ( ! $this->connector->verify( $body, $secret['pair_key'], $timestamp, $signature, time() ) ) {
			return new \WP_Error( 'sda_hub_sig', __( 'Signature verification failed.', 'seo-director-ai' ), [ 'status' => 401 ] );
		}

		$snapshot = json_decode( $body, true );
		if ( ! is_array( $snapshot ) ) {
			return new \WP_Error( 'sda_hub_body', __( 'Malformed snapshot payload.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		$this->sites->record_snapshot( $secret['id'], $this->sanitize_snapshot( $snapshot ) );

		return rest_ensure_response( [ 'received' => true ] );
	}

	/**
	 * Whitelist the snapshot to known scalar fields so a compromised client
	 * cannot inject arbitrary data into the hub's stored JSON.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, mixed>
	 */
	private function sanitize_snapshot( array $raw ): array {
		$health = null;
		if ( isset( $raw['health'] ) && is_array( $raw['health'] ) ) {
			$health = [
				'score' => (int) ( $raw['health']['score'] ?? 0 ),
				'band'  => sanitize_key( (string) ( $raw['health']['band'] ?? '' ) ),
				'delta' => isset( $raw['health']['delta'] ) ? (int) $raw['health']['delta'] : null,
			];
		}

		$traffic = null;
		if ( isset( $raw['traffic'] ) && is_array( $raw['traffic'] ) ) {
			$traffic = [
				'clicks'     => (int) ( $raw['traffic']['clicks'] ?? 0 ),
				'change_pct' => isset( $raw['traffic']['change_pct'] ) ? (float) $raw['traffic']['change_pct'] : null,
			];
		}

		return [
			'site_name'      => sanitize_text_field( (string) ( $raw['site_name'] ?? '' ) ),
			'site_url'       => esc_url_raw( (string) ( $raw['site_url'] ?? '' ) ),
			'generated_at'   => sanitize_text_field( (string) ( $raw['generated_at'] ?? '' ) ),
			'plugin_version' => sanitize_text_field( (string) ( $raw['plugin_version'] ?? '' ) ),
			'health'         => $health,
			'alerts'         => [
				'critical' => (int) ( $raw['alerts']['critical'] ?? 0 ),
				'high'     => (int) ( $raw['alerts']['high'] ?? 0 ),
				'total'    => (int) ( $raw['alerts']['total'] ?? 0 ),
			],
			'opportunities'  => (int) ( $raw['opportunities'] ?? 0 ),
			'traffic'        => $traffic,
		];
	}
}
