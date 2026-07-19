<?php
/**
 * License lifecycle: activate, deactivate, daily verify. Talks to the license
 * server with HMAC-verified responses and fails soft — a server outage never
 * locks a paying customer out mid-term (cached grace behavior).
 *
 * @package SEODirector
 */

namespace SEODirector\License;

defined( 'ABSPATH' ) || exit;

use SEODirector\Core\Options;
use SEODirector\Data\Repository\LicenseRepository;
use SEODirector\Integrations\Http\RetryingHttpClient;
use WP_Error;

final class LicenseManager {

	private const DEFAULT_SERVER = 'https://api.seodirector.app';
	private const GRACE_DAYS     = 14;

	public function __construct(
		private readonly LicenseRepository $repository,
		private readonly RetryingHttpClient $http,
		private readonly Options $options,
	) {}

	/**
	 * Activate a key against the server and persist the resulting state.
	 */
	public function activate( string $license_key ): LicenseState|WP_Error {
		$response = $this->call_server( 'activate', $license_key );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$edition = (string) ( $response['edition'] ?? '' );
		if ( ! Edition::is_valid( $edition ) ) {
			return new WP_Error( 'sda_license_invalid', __( 'The license server returned an unknown edition.', 'seo-director-ai' ) );
		}

		$expires = isset( $response['expires_at'] ) ? (string) $response['expires_at'] : null;
		$this->repository->save( $license_key, $edition, LicenseState::ACTIVE, $expires, $response );
		$state = new LicenseState( LicenseState::ACTIVE, $edition, $expires );
		$this->fire_change( $state );

		return $state;
	}

	/**
	 * Deactivate locally (and best-effort on the server); data is retained.
	 */
	public function deactivate(): LicenseState {
		$key = $this->repository->get_key();
		if ( $key ) {
			$this->call_server( 'deactivate', $key ); // Best effort; ignore failure.
		}
		$this->repository->update_status( LicenseState::DEACTIVATED );
		$state = LicenseState::free();
		$this->fire_change( $state );
		return $state;
	}

	/**
	 * Daily cron check. Re-verifies with the server; on outage, holds the last
	 * good state until the grace window closes.
	 */
	public function verify(): LicenseState {
		$row = $this->repository->get();
		if ( ! $row ) {
			return LicenseState::free();
		}
		$key = $this->repository->get_key();
		if ( ! $key ) {
			return $this->current_state();
		}

		$response = $this->call_server( 'status', $key );

		if ( is_wp_error( $response ) ) {
			// Server unreachable — apply grace based on the stored expiry.
			return $this->apply_grace( $row );
		}

		$valid   = (bool) ( $response['valid'] ?? false );
		$edition = (string) ( $response['edition'] ?? $row->edition );
		$expires = isset( $response['expires_at'] ) ? (string) $response['expires_at'] : $row->expires_at;

		if ( ! $valid ) {
			$this->repository->save( $key, $edition, LicenseState::INVALID, $expires, $response );
			$state = new LicenseState( LicenseState::INVALID, $edition, $expires );
			$this->fire_change( $state );
			return $state;
		}

		$this->repository->save( $key, $edition, LicenseState::ACTIVE, $expires, $response );
		$state = new LicenseState( LicenseState::ACTIVE, $edition, $expires );
		$this->fire_change( $state );
		return $state;
	}

	/**
	 * Read current state from storage without hitting the network (used everywhere).
	 */
	public function current_state(): LicenseState {
		$row = $this->repository->get();
		if ( ! $row ) {
			return LicenseState::free();
		}
		// A stored ACTIVE row past its expiry is re-evaluated for grace on read.
		if ( LicenseState::ACTIVE === $row->status && $this->is_expired( $row->expires_at ) ) {
			return $this->apply_grace( $row, false );
		}
		return new LicenseState(
			(string) $row->status,
			(string) $row->edition,
			$row->expires_at,
			$this->grace_end( $row->expires_at )
		);
	}

	/**
	 * Compute grace vs. expired from the stored expiry; optionally persist the transition.
	 */
	private function apply_grace( object $row, bool $persist = true ): LicenseState {
		$grace_end = $this->grace_end( $row->expires_at );
		$now       = time();

		if ( $grace_end && $now <= strtotime( $grace_end ) ) {
			if ( $persist && LicenseState::GRACE !== $row->status ) {
				$this->repository->update_status( LicenseState::GRACE );
			}
			$state = new LicenseState( LicenseState::GRACE, (string) $row->edition, $row->expires_at, $grace_end );
		} else {
			if ( $persist && LicenseState::EXPIRED !== $row->status ) {
				$this->repository->update_status( LicenseState::EXPIRED );
			}
			$state = new LicenseState( LicenseState::EXPIRED, (string) $row->edition, $row->expires_at, $grace_end );
		}

		$this->fire_change( $state );
		return $state;
	}

	/**
	 * POST to the license server; verify the HMAC signature on the response.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function call_server( string $action, string $license_key ): array|WP_Error {
		$base = untrailingslashit( (string) $this->options->get( 'license_server', self::DEFAULT_SERVER ) );
		$url  = $base . '/v1/license/' . $action;

		$response = $this->http->post_json(
			$url,
			array(
				'body'    => array(
					'license_key' => $license_key,
					'domain_hash' => LicenseRepository::domain_hash(),
					'plugin'      => 'seo-director-ai',
					'version'     => SDA_VERSION,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== $response['code'] ) {
			$message = (string) ( $response['body']['message'] ?? __( 'License server rejected the request.', 'seo-director-ai' ) );
			return new WP_Error( 'sda_license_server_' . $response['code'], $message );
		}
		if ( ! $this->verify_signature( $response['raw'], $license_key ) ) {
			return new WP_Error( 'sda_license_signature', __( 'License response failed signature verification.', 'seo-director-ai' ) );
		}

		return (array) $response['body'];
	}

	/**
	 * HMAC-SHA256 of the raw body keyed by the license key, compared to the
	 * X-SDA-Signature header echoed inside the JSON body (server contract).
	 */
	private function verify_signature( string $raw_body, string $license_key ): bool {
		$decoded = json_decode( $raw_body, true );
		if ( ! is_array( $decoded ) || empty( $decoded['signature'] ) ) {
			return false;
		}
		$provided = (string) $decoded['signature'];
		unset( $decoded['signature'] );
		ksort( $decoded );
		$expected = hash_hmac( 'sha256', (string) wp_json_encode( $decoded ), $license_key );
		return hash_equals( $expected, $provided );
	}

	private function grace_end( ?string $expires_at ): ?string {
		if ( null === $expires_at ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', strtotime( $expires_at . ' +' . self::GRACE_DAYS . ' days' ) );
	}

	private function is_expired( ?string $expires_at ): bool {
		return null !== $expires_at && strtotime( $expires_at ) < time();
	}

	private function fire_change( LicenseState $state ): void {
		/**
		 * Fires whenever the license state is recomputed.
		 *
		 * @param LicenseState $state Current state.
		 */
		do_action( 'sda_license_status_changed', $state );
	}
}
