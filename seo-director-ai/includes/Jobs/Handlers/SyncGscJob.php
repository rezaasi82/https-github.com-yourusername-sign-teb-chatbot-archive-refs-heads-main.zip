<?php
/**
 * Search Console sync: 16-month backfill + daily incremental, as one
 * resumable chunked job.
 *
 * Cursor shape:
 *   phase "totals" — bulk site-level daily totals for the whole range (few requests)
 *   phase "daily"  — per-day top-N query rows, page rows, and country/device
 *                    dimensions, advancing one day at a time
 *
 * Incremental runs are the same job started with a cursor whose date range
 * begins a few days back (GSC restates late data), so all writes are upserts.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Rollup\RollupBuilder;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Jobs\AbstractChunkedJob;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SyncGscJob extends AbstractChunkedJob {

	public const NAME = 'sync_gsc';

	/** GSC publishes data with ~2 days of lag. */
	private const DATA_LAG_DAYS = 2;

	/** Re-sync window for late restatements on incremental runs. */
	public const RESTATE_DAYS = 3;

	/** Days processed per chunk in the "daily" phase (4 requests/day). */
	private const DAYS_PER_CHUNK = 5;

	public function __construct(
		JobStateRepository $state,
		private SearchConsoleClient $client,
		private GscRepository $repo,
		private PropertiesRepository $properties,
		private RollupBuilder $rollups,
		private UrlCanonicalizer $canonicalizer,
		private Settings $settings,
	) {
		parent::__construct( $state );
	}

	public function name(): string {
		return self::NAME;
	}

	/**
	 * Build the initial cursor. $backfill=true starts 16 months back;
	 * otherwise from the last synced date minus the restatement window.
	 *
	 * @return array<string, mixed>|null Null when there is nothing to sync.
	 */
	public function initial_cursor( bool $backfill ): ?array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return null;
		}

		$end = gmdate( 'Y-m-d', strtotime( '-' . self::DATA_LAG_DAYS . ' days' ) );

		if ( $backfill ) {
			$start = gmdate( 'Y-m-d', strtotime( '-16 months' ) );
		} else {
			$latest = $this->repo->latest_date( $property['id'] );
			$start  = $latest
				? gmdate( 'Y-m-d', strtotime( $latest . ' -' . self::RESTATE_DAYS . ' days' ) )
				: gmdate( 'Y-m-d', strtotime( '-16 months' ) );
		}

		if ( $start > $end ) {
			return null;
		}

		return [
			'phase'       => 'totals',
			'property_id' => $property['id'],
			'site_url'    => $property['external_id'],
			'start'       => $start,
			'end'         => $end,
			'date'        => $start,
		];
	}

	protected function process_chunk( array $cursor ): ?array {
		if ( empty( $cursor ) ) {
			$cursor = $this->initial_cursor( true );
			if ( null === $cursor ) {
				return null;
			}
		}

		return 'totals' === $cursor['phase']
			? $this->run_totals_phase( $cursor )
			: $this->run_daily_phase( $cursor );
	}

	/**
	 * One request per ~500 days: dimensions=[date] over the whole range.
	 *
	 * @param array<string, mixed> $cursor
	 * @return array<string, mixed>
	 */
	private function run_totals_phase( array $cursor ): array {
		$rows = $this->client->query( $cursor['site_url'], $cursor['start'], $cursor['end'], [ 'date' ], 25000 );

		if ( is_wp_error( $rows ) ) {
			throw new \RuntimeException( $rows->get_error_message() );
		}

		$this->repo->upsert_daily_totals(
			(int) $cursor['property_id'],
			array_map(
				static fn( array $r ) => [
					'date'        => $r['keys'][0],
					'clicks'      => (int) $r['clicks'],
					'impressions' => (int) $r['impressions'],
					'ctr'         => $r['ctr'],
					'position'    => $r['position'],
				],
				$rows
			)
		);

		$cursor['phase'] = 'daily';

		return $cursor;
	}

	/**
	 * Per-day detail: top-N queries, top-N pages, country + device splits.
	 *
	 * @param array<string, mixed> $cursor
	 * @return array<string, mixed>|null
	 */
	private function run_daily_phase( array $cursor ): ?array {
		$property_id = (int) $cursor['property_id'];
		$site_url    = (string) $cursor['site_url'];
		$query_cap   = (int) $this->settings->get( 'gsc_query_rows_cap', 5000 );
		$page_cap    = (int) $this->settings->get( 'gsc_page_rows_cap', 2000 );

		for ( $i = 0; $i < self::DAYS_PER_CHUNK; $i++ ) {
			$date = (string) $cursor['date'];
			if ( $date > $cursor['end'] ) {
				return $this->finish( $cursor );
			}

			$this->sync_day( $property_id, $site_url, $date, $query_cap, $page_cap );

			$cursor['date'] = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		}

		return $cursor['date'] > $cursor['end'] ? $this->finish( $cursor ) : $cursor;
	}

	private function sync_day( int $property_id, string $site_url, string $date, int $query_cap, int $page_cap ): void {
		// Queries.
		$rows = $this->client->query( $site_url, $date, $date, [ 'query' ], $query_cap );
		if ( is_wp_error( $rows ) ) {
			throw new \RuntimeException( $rows->get_error_message() );
		}
		$this->repo->upsert_entity_daily(
			'query',
			$property_id,
			array_map(
				fn( array $r ) => [
					'date'        => $date,
					'hash_hex'    => md5( $r['keys'][0] ),
					'label'       => mb_substr( $r['keys'][0], 0, 750 ),
					'clicks'      => (int) $r['clicks'],
					'impressions' => (int) $r['impressions'],
					'ctr'         => $r['ctr'],
					'position'    => $r['position'],
				],
				$rows
			)
		);

		// Pages (canonicalized).
		$rows = $this->client->query( $site_url, $date, $date, [ 'page' ], $page_cap );
		if ( is_wp_error( $rows ) ) {
			throw new \RuntimeException( $rows->get_error_message() );
		}
		$pages = [];
		foreach ( $rows as $r ) {
			$canonical = $this->canonicalizer->canonicalize( $r['keys'][0] );
			$hash      = $this->canonicalizer->hash_hex( $canonical );
			// Canonicalization can merge URLs (e.g. utm variants) — aggregate.
			if ( isset( $pages[ $hash ] ) ) {
				$prev                          = $pages[ $hash ];
				$total_impressions             = $prev['impressions'] + $r['impressions'];
				$pages[ $hash ]['position']    = $total_impressions > 0
					? ( $prev['position'] * $prev['impressions'] + $r['position'] * $r['impressions'] ) / $total_impressions
					: 0;
				$pages[ $hash ]['clicks']      += (int) $r['clicks'];
				$pages[ $hash ]['impressions'] = (int) $total_impressions;
				$pages[ $hash ]['ctr']         = $total_impressions > 0 ? $pages[ $hash ]['clicks'] / $total_impressions : 0;
				continue;
			}
			$pages[ $hash ] = [
				'date'        => $date,
				'hash_hex'    => $hash,
				'label'       => $canonical,
				'clicks'      => (int) $r['clicks'],
				'impressions' => (int) $r['impressions'],
				'ctr'         => $r['ctr'],
				'position'    => $r['position'],
			];
		}
		$this->repo->upsert_entity_daily( 'page', $property_id, array_values( $pages ) );

		// Country + device.
		foreach ( [ 'country', 'device' ] as $dimension ) {
			$rows = $this->client->query( $site_url, $date, $date, [ $dimension ], 300 );
			if ( is_wp_error( $rows ) ) {
				throw new \RuntimeException( $rows->get_error_message() );
			}
			$this->repo->upsert_dimension_daily(
				$property_id,
				array_map(
					static fn( array $r ) => [
						'date'        => $date,
						'dim_type'    => $dimension,
						'dim_value'   => mb_substr( $r['keys'][0], 0, 64 ),
						'clicks'      => (int) $r['clicks'],
						'impressions' => (int) $r['impressions'],
						'ctr'         => $r['ctr'],
						'position'    => $r['position'],
					],
					$rows
				)
			);
		}
	}

	/**
	 * Completion: rebuild rollups for the synced range, fire the hook.
	 *
	 * @param array<string, mixed> $cursor
	 */
	private function finish( array $cursor ): ?array {
		$this->rollups->rebuild_range( (int) $cursor['property_id'], (string) $cursor['start'], (string) $cursor['end'] );

		/**
		 * Fires when a GSC sync run completes.
		 *
		 * @param int    $property_id Synced property.
		 * @param string $start       Range start (Y-m-d).
		 * @param string $end         Range end (Y-m-d).
		 */
		do_action( 'sda_sync_completed', (int) $cursor['property_id'], (string) $cursor['start'], (string) $cursor['end'] );

		return null;
	}
}
