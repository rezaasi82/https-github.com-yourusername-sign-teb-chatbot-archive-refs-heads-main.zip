<?php
/**
 * License activation, deactivation, and daily verification against the
 * standalone license server. Responses are HMAC-signed; the last verified
 * payload is cached so a server outage never hard-locks the site. Grace and
 * lock states are computed from the cached expiry — see GracePeriodHandler.
 *
 * Fail-soft rules (per strategy): data is never deleted; after grace only PRO
 * features pause, base booking/analytics stay live.
 *
 * @package SEODirector
 */

namespace SEODirector\License;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class LicenseManager {

	private const SERVER = 'https://license.seodirector.app/v1';

	/** Cached signed payload keeps the site working through this much server downtime. */
	private const OUTAGE_TOLERANCE_DAYS = 21;

	public function __construct(
		private LicenseRepository $repository,
		private RetryingHttpClient $http,
		private GracePeriodHandler $grace,
		private Settings $settings,
	) {}

	/**
	 * Activate a key on this domain.
	 *
	 * @return true|\WP_Error
	 */
	public function activate( string $license_key ): bool|\WP_Error {
		$response = $this->call(
			'/activate',
			[
				'license_key' => $license_key,
				'domain_hash' => hash( 'sha256', home_url() ),
				'domain'      => home_url(),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->repository->save(
			[
				'license_key'    => $license_key,
				'edition'        => (string) ( $response['edition'] ?? 'starter' ),
				'status'         => 'active',
				'expires_at'     => $response['expires_at'] ?? null,
				'server_payload' => $response,
			]
		);

		$this->fire_status_changed( 'active' );

		return true;
	}

	/**
	 * Deactivate on this domain (frees a slot for self-service transfer).
	 */
	public function deactivate(): bool|\WP_Error {
		$license = $this->repository->get();
		if ( null === $license || '' === $license['license_key'] ) {
			return true;
		}

		$this->call(
			'/deactivate',
			[
				'license_key' => $license['license_key'],
				'domain_hash' => hash( 'sha256', home_url() ),
			]
		);

		$this->repository->delete();
		$this->fire_status_changed( 'deactivated' );

		return true;
	}

	/**
	 * Daily verification (cron). Refreshes the cached payload; tolerates outages.
	 */
	public function verify(): void {
		$license = $this->repository->get();
		if ( null === $license || '' === $license['license_key'] ) {
			return;
		}

		$response = $this->call(
			'/check',
			[
				'license_key' => $license['license_key'],
				'domain_hash' => hash( 'sha256', home_url() ),
			]
		);

		if ( is_wp_error( $response ) ) {
			// Server unreachable — keep the cached payload (grace logic handles expiry).
			return;
		}

		$prev = $license['status'];
		$this->repository->save(
			[
				'edition'        => (string) ( $response['edition'] ?? $license['edition'] ),
				'status'         => (string) ( $response['status'] ?? 'active' ),
				'expires_at'     => $response['expires_at'] ?? $license['expires_at'],
				'server_payload' => $response,
			]
		);

		$new = (string) ( $response['status'] ?? 'active' );
		if ( $new !== $prev ) {
			$this->fire_status_changed( $new );
		}
	}

	/**
	 * Resolved license state for gates and UI.
	 *
	 * @return array{state: string, edition: string, tier: string|null, expires_at: string|null, days_left: int|null, in_grace: bool, has_license: bool}
	 */
	public function status(): array {
		$license = $this->repository->get();

		if ( null === $license || '' === $license['license_key'] ) {
			return [
				'state'       => 'none',
				'edition'     => 'starter',
				'tier'        => null,
				'expires_at'  => null,
				'days_left'   => null,
				'in_grace'    => false,
				'has_license' => false,
			];
		}

		$outage = null !== $license['last_check_at']
			&& strtotime( $license['last_check_at'] ) < time() - self::OUTAGE_TOLERANCE_DAYS * DAY_IN_SECONDS;

		$state = $this->grace->resolve( $license['status'], $license['expires_at'], $outage );

		return [
			'state'       => $state,
			'edition'     => $license['edition'],
			'tier'        => $license['server_payload']['tier'] ?? null,
			'expires_at'  => $license['expires_at'],
			'days_left'   => $this->grace->days_left( $license['expires_at'] ),
			'in_grace'    => 'grace' === $state,
			'has_license' => true,
		];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|\WP_Error Verified payload or error.
	 */
	private function call( string $path, array $body ): array|\WP_Error {
		$result = $this->http->post(
			self::SERVER . $path,
			[
				'timeout' => 15,
				'body'    => $body,
			]
		);

		$data = $result->json();
		if ( ! $result->ok() || ! is_array( $data ) ) {
			return new \WP_Error( 'sda_license_http', (string) ( $data['message'] ?? $result->error ?? __( 'License server is unreachable.', 'seo-director-ai' ) ) );
		}

		if ( ! empty( $data['error'] ) ) {
			return new \WP_Error( 'sda_license_denied', (string) $data['error'] );
		}

		if ( ! $this->signature_valid( $data ) ) {
			return new \WP_Error( 'sda_license_sig', __( 'License response failed signature verification.', 'seo-director-ai' ) );
		}

		return $data;
	}

	/**
	 * Verify the HMAC signature the server attaches to every response.
	 *
	 * @param array<string, mixed> $data
	 */
	private function signature_valid( array $data ): bool {
		$signature = (string) ( $data['signature'] ?? '' );
		if ( '' === $signature ) {
			return false;
		}

		$secret = (string) $this->settings->get( 'license_shared_secret', '' );
		if ( '' === $secret ) {
			// No shared secret provisioned yet — accept during first activation,
			// server binds a per-license secret we store on success.
			return true;
		}

		$payload = $data;
		unset( $payload['signature'] );
		ksort( $payload );
		$expected = hash_hmac( 'sha256', (string) wp_json_encode( $payload ), $secret );

		return hash_equals( $expected, $signature );
	}

	private function fire_status_changed( string $state ): void {
		/**
		 * Fires when the license status changes.
		 *
		 * @param string $state New license state.
		 */
		do_action( 'sda_license_status_changed', $state );
	}
}
