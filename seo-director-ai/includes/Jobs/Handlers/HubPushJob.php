<?php
/**
 * Client-side counterpart to the hub: once a day, build a snapshot, sign it
 * with the pairing key, and push it to the configured agency hub. A no-op
 * unless both a hub URL and a pairing key are set, so non-agency installs
 * never make an outbound call.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Agency\SiteConnector;
use SEODirector\Agency\SnapshotBuilder;
use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class HubPushJob {

	public function __construct(
		private SnapshotBuilder $snapshots,
		private SiteConnector $connector,
		private RetryingHttpClient $http,
		private Settings $settings,
	) {}

	/**
	 * @return bool True when a snapshot was pushed and accepted.
	 */
	public function run(): bool {
		$hub_url  = (string) $this->settings->get( 'agency_hub_url', '' );
		$pair_key = (string) $this->settings->get( 'agency_pair_key', '' );

		if ( '' === $hub_url || '' === $pair_key ) {
			return false;
		}

		// Sign the exact bytes that go on the wire — encode once, here.
		$body      = (string) wp_json_encode( $this->snapshots->build() );
		$timestamp = time();
		$signature = $this->connector->sign( $body, $pair_key, $timestamp );

		$result = $this->http->post(
			$hub_url,
			[
				'body'    => $body,
				'headers' => [
					'Content-Type'                 => 'application/json; charset=utf-8',
					SiteConnector::HEADER_SITE      => home_url(),
					SiteConnector::HEADER_TIMESTAMP => (string) $timestamp,
					SiteConnector::HEADER_SIGNATURE => $signature,
				],
				'timeout' => 15,
			]
		);

		return $result->ok();
	}
}
