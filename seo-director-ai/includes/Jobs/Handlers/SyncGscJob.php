<?php
/**
 * Chunked, resumable GSC sync.
 * Cursor: {property_id, property_uri, date} — one day of query+page rows per chunk,
 * plus a totals refresh over the restatement window on the first chunk.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\ConnectionsRepository;
use SEODirector\Data\Repository\GscDailyTotalsRepository;
use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\GscQueryDailyRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\SearchConsoleClient;
use SEODirector\Jobs\JobResult;

final class SyncGscJob {

	public const NAME = 'sync_gsc';

	/** GSC data is restated for ~3 days; always re-pull a safety window. */
	private const RESTATEMENT_DAYS = 4;

	/** How many days back to start on a fresh install (incremental default). */
	private const INITIAL_BACKFILL_DAYS = 90;

	public function __construct(
		private readonly SearchConsoleClient $gsc,
		private readonly ConnectionsRepository $connections,
		private readonly JobStateRepository $job_state,
		private readonly GscDailyTotalsRepository $totals,
		private readonly GscQueryDailyRepository $queries,
		private readonly GscPageDailyRepository $pages,
		private readonly UrlCanonicalizer $canonicalizer,
	) {}

	public function run_chunk(): JobResult {
		$connection = $this->connections->get_by_service( 'gsc' );
		if ( ! $connection || 'connected' !== $connection->status ) {
			$this->job_state->save( self::NAME, 'idle', array(), 'GSC not connected.' );
			return JobResult::done();
		}

		$state  = $this->job_state->get( self::NAME );
		$cursor = $state['cursor'];

		// Fresh run: build the work plan.
		if ( empty( $cursor['property_uri'] ) ) {
			$cursor = $this->initialize_cursor();
			if ( null === $cursor ) {
				$this->job_state->save( self::NAME, 'failed', array(), 'No GSC property available.' );
				return JobResult::failed();
			}
		}

		$this->job_state->save( self::NAME, 'running', $cursor );

		$property_id  = (int) $cursor['property_id'];
		$property_uri = (string) $cursor['property_uri'];
		$date         = (string) $cursor['date'];
		$end_date     = (string) $cursor['end_date'];

		// First chunk also refreshes exact daily totals across the whole window.
		if ( ! empty( $cursor['needs_totals'] ) ) {
			$rows = $this->gsc->daily_totals( $property_uri, $date, $end_date );
			if ( is_wp_error( $rows ) ) {
				$this->job_state->save( self::NAME, 'failed', $cursor, $rows->get_error_message() );
				return JobResult::failed();
			}
			$this->totals->upsert_rows( $property_id, array_map( array( $this, 'map_totals_row' ), $rows ) );
			$cursor['needs_totals'] = false;
			$this->job_state->save( self::NAME, 'running', $cursor );
		}

		// One day of dimension detail per chunk keeps runtime bounded.
		$query_rows = $this->gsc->top_rows( $property_uri, $date, 'query', 5000 );
		$page_rows  = $this->gsc->top_rows( $property_uri, $date, 'page', 2000 );

		if ( is_wp_error( $query_rows ) || is_wp_error( $page_rows ) ) {
			$error = is_wp_error( $query_rows ) ? $query_rows : $page_rows;
			$this->job_state->save( self::NAME, 'failed', $cursor, $error->get_error_message() );
			return JobResult::failed();
		}

		$this->queries->upsert_rows(
			$property_id,
			$date,
			array_map(
				static fn( array $r ) => array(
					'query'       => (string) ( $r['keys'][0] ?? '' ),
					'clicks'      => (int) ( $r['clicks'] ?? 0 ),
					'impressions' => (int) ( $r['impressions'] ?? 0 ),
					'ctr'         => (float) ( $r['ctr'] ?? 0 ),
					'position'    => (float) ( $r['position'] ?? 0 ),
				),
				$query_rows
			)
		);
		$this->pages->upsert_rows(
			$property_id,
			$date,
			array_map(
				fn( array $r ) => array(
					'page_path'   => $this->canonicalizer->canonicalize( (string) ( $r['keys'][0] ?? '' ) ),
					'clicks'      => (int) ( $r['clicks'] ?? 0 ),
					'impressions' => (int) ( $r['impressions'] ?? 0 ),
					'ctr'         => (float) ( $r['ctr'] ?? 0 ),
					'position'    => (float) ( $r['position'] ?? 0 ),
				),
				$page_rows
			)
		);

		// Advance the cursor one day.
		$next = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		if ( $next > $end_date ) {
			$this->job_state->save( self::NAME, 'done', array() );
			return JobResult::done();
		}
		$cursor['date'] = $next;
		$this->job_state->save( self::NAME, 'running', $cursor );
		return JobResult::more();
	}

	/** @return array<string, mixed>|null */
	private function initialize_cursor(): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'sda_properties';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, external_id FROM {$table} WHERE site_id = %d AND service = 'gsc' AND is_active = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				get_current_blog_id()
			)
		);
		if ( ! $row ) {
			return null;
		}

		$property_id = (int) $row->id;
		// GSC data lags ~2 days behind.
		$end_date = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$latest   = $this->totals->latest_date( $property_id );
		$start    = $latest
			? gmdate( 'Y-m-d', strtotime( $latest . ' -' . self::RESTATEMENT_DAYS . ' days' ) )
			: gmdate( 'Y-m-d', strtotime( '-' . self::INITIAL_BACKFILL_DAYS . ' days' ) );

		if ( $start > $end_date ) {
			$start = $end_date;
		}

		return array(
			'property_id'  => $property_id,
			'property_uri' => (string) $row->external_id,
			'date'         => $start,
			'end_date'     => $end_date,
			'needs_totals' => true,
		);
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function map_totals_row( array $row ): array {
		return array(
			'date'        => (string) ( $row['keys'][0] ?? gmdate( 'Y-m-d' ) ),
			'clicks'      => (int) ( $row['clicks'] ?? 0 ),
			'impressions' => (int) ( $row['impressions'] ?? 0 ),
			'ctr'         => (float) ( $row['ctr'] ?? 0 ),
			'position'    => (float) ( $row['position'] ?? 0 ),
		);
	}
}
