<?php
/**
 * GA4 sync: month-by-month resumable backfill + incremental, paginating
 * runReport within each month.
 *
 * Cursor: { property_id, external_id, start, end, month_start, offset }
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Data\Repository\Ga4Repository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Jobs\AbstractChunkedJob;

defined( 'ABSPATH' ) || exit;

final class SyncGa4Job extends AbstractChunkedJob {

	public const NAME = 'sync_ga4';

	private const PAGE_SIZE    = 10000;
	private const RESTATE_DAYS = 3;

	public function __construct(
		JobStateRepository $state,
		private Analytics4Client $client,
		private Ga4Repository $repo,
		private PropertiesRepository $properties,
		private UrlCanonicalizer $canonicalizer,
	) {
		parent::__construct( $state );
	}

	public function name(): string {
		return self::NAME;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function initial_cursor( bool $backfill ): ?array {
		$property = $this->properties->active( 'ga4' );
		if ( null === $property ) {
			return null;
		}

		$end = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		if ( $backfill ) {
			$start = gmdate( 'Y-m-d', strtotime( '-14 months' ) );
		} else {
			$latest = $this->repo->latest_date( $property['id'] );
			$start  = $latest
				? gmdate( 'Y-m-d', strtotime( $latest . ' -' . self::RESTATE_DAYS . ' days' ) )
				: gmdate( 'Y-m-d', strtotime( '-14 months' ) );
		}

		if ( $start > $end ) {
			return null;
		}

		return [
			'property_id' => $property['id'],
			'external_id' => $property['external_id'],
			'start'       => $start,
			'end'         => $end,
			'month_start' => $start,
			'offset'      => 0,
		];
	}

	protected function process_chunk( array $cursor ): ?array {
		if ( empty( $cursor ) ) {
			$cursor = $this->initial_cursor( true );
			if ( null === $cursor ) {
				return null;
			}
		}

		$month_start = (string) $cursor['month_start'];
		$month_end   = min(
			(string) $cursor['end'],
			gmdate( 'Y-m-d', strtotime( gmdate( 'Y-m-01', strtotime( $month_start ) ) . ' +1 month -1 day' ) )
		);

		$rows = $this->client->daily_report(
			(string) $cursor['external_id'],
			$month_start,
			$month_end,
			(int) $cursor['offset'],
			self::PAGE_SIZE
		);

		if ( is_wp_error( $rows ) ) {
			throw new \RuntimeException( $rows->get_error_message() );
		}

		$this->repo->upsert_daily(
			(int) $cursor['property_id'],
			array_map(
				function ( array $r ) {
					$canonical = $this->canonicalizer->canonicalize( $r['landing'] );

					return [
						'date'             => $r['date'],
						'channel'          => mb_substr( $r['channel'], 0, 64 ),
						'landing_hash_hex' => $this->canonicalizer->hash_hex( $canonical ),
						'landing_path'     => $canonical,
						'sessions'         => $r['sessions'],
						'total_users'      => $r['total_users'],
						'engaged_sessions' => $r['engaged_sessions'],
						'engagement_rate'  => $r['engagement_rate'],
						'conversions'      => $r['conversions'],
						'event_count'      => $r['event_count'],
					];
				},
				$rows
			)
		);

		// More pages in this month?
		if ( count( $rows ) === self::PAGE_SIZE ) {
			$cursor['offset'] = (int) $cursor['offset'] + self::PAGE_SIZE;
			return $cursor;
		}

		$this->repo->rebuild_daily_totals( (int) $cursor['property_id'], $month_start, $month_end );

		// Advance to the next month.
		$next_month = gmdate( 'Y-m-01', strtotime( $month_start . ' +1 month' ) );
		if ( $next_month > (string) $cursor['end'] ) {
			/** This action is documented in includes/Jobs/Handlers/SyncGscJob.php */
			do_action( 'sda_sync_completed', (int) $cursor['property_id'], (string) $cursor['start'], (string) $cursor['end'] );
			return null;
		}

		$cursor['month_start'] = $next_month;
		$cursor['offset']      = 0;

		return $cursor;
	}
}
