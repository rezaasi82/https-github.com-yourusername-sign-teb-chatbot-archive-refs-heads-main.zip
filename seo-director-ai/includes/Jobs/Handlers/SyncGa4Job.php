<?php
/**
 * Chunked GA4 sync — one 7-day window per chunk, restatement-safe.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\Ga4DailyRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\Analytics4Client;
use SEODirector\Jobs\JobResult;

final class SyncGa4Job {

	public const NAME = 'sync_ga4';

	private const RESTATEMENT_DAYS      = 3;
	private const INITIAL_BACKFILL_DAYS = 90;
	private const CHUNK_DAYS            = 7;

	public function __construct(
		private readonly Analytics4Client $ga4,
		private readonly PropertiesRepository $properties,
		private readonly JobStateRepository $job_state,
		private readonly Ga4DailyRepository $daily,
		private readonly UrlCanonicalizer $canonicalizer,
	) {}

	public function run_chunk(): JobResult {
		$property = $this->properties->active_property( 'ga4' );
		if ( ! $property ) {
			$this->job_state->save( self::NAME, 'idle', array(), 'No active GA4 property.' );
			return JobResult::done();
		}

		$state  = $this->job_state->get( self::NAME );
		$cursor = $state['cursor'];

		if ( empty( $cursor['date'] ) ) {
			$end    = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
			$latest = $this->daily->latest_date( (int) $property->id );
			$start  = $latest
				? gmdate( 'Y-m-d', strtotime( $latest . ' -' . self::RESTATEMENT_DAYS . ' days' ) )
				: gmdate( 'Y-m-d', strtotime( '-' . self::INITIAL_BACKFILL_DAYS . ' days' ) );
			if ( $start > $end ) {
				$start = $end;
			}
			$cursor = array(
				'date'     => $start,
				'end_date' => $end,
			);
		}

		$this->job_state->save( self::NAME, 'running', $cursor );

		$window_end = min(
			(string) $cursor['end_date'],
			gmdate( 'Y-m-d', strtotime( $cursor['date'] . ' +' . ( self::CHUNK_DAYS - 1 ) . ' days' ) )
		);

		$rows = $this->ga4->daily_report( (string) $property->external_id, (string) $cursor['date'], $window_end );
		if ( is_wp_error( $rows ) ) {
			$this->job_state->save( self::NAME, 'failed', $cursor, $rows->get_error_message() );
			return JobResult::failed();
		}

		foreach ( $rows as &$row ) {
			$row['landing_path'] = $this->canonicalizer->canonicalize( (string) $row['landing_path'] );
		}
		unset( $row );
		$this->daily->upsert_rows( (int) $property->id, $rows );

		$next = gmdate( 'Y-m-d', strtotime( $window_end . ' +1 day' ) );
		if ( $next > (string) $cursor['end_date'] ) {
			$this->job_state->save( self::NAME, 'done', array() );
			return JobResult::done();
		}
		$cursor['date'] = $next;
		$this->job_state->save( self::NAME, 'running', $cursor );
		return JobResult::more();
	}
}
