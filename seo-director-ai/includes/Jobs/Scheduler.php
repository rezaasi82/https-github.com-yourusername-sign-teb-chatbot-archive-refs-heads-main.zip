<?php
/**
 * Job scheduling. Uses Action Scheduler when present (e.g. WooCommerce installed),
 * falls back to WP-Cron single events. Handlers are idempotent and chunked.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs;

defined( 'ABSPATH' ) || exit;

use SEODirector\Alerts\AlertEngine;
use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
use SEODirector\Jobs\Handlers\SyncGa4Job;
use SEODirector\Jobs\Handlers\SyncGscJob;
use SEODirector\Jobs\Handlers\WeeklyMaintenanceJob;

final class Scheduler {

	public const HOOK_DAILY_SYNC      = 'sda_daily_sync';
	public const HOOK_HOURLY_ALERTS   = 'sda_hourly_alerts';
	public const HOOK_WEEKLY_PIPELINE = 'sda_weekly_pipeline';
	public const HOOK_RUN_CHUNK       = 'sda_run_chunk';

	public function __construct(
		private readonly SyncGscJob $sync_gsc,
		private readonly SyncGa4Job $sync_ga4,
		private readonly RunAnalysisJob $run_analysis,
		private readonly RunPsiAuditJob $run_psi,
		private readonly WeeklyMaintenanceJob $weekly_maintenance,
		private readonly AlertEngine $alert_engine,
	) {}

	public function register_hooks(): void {
		add_action( self::HOOK_DAILY_SYNC, array( $this, 'start_daily_pipeline' ) );
		add_action( self::HOOK_HOURLY_ALERTS, array( $this->alert_engine, 'evaluate_all' ) );
		add_action( self::HOOK_WEEKLY_PIPELINE, array( $this, 'start_weekly_pipeline' ) );
		add_action( self::HOOK_RUN_CHUNK, array( $this, 'run_chunk' ), 10, 1 );
	}

	/**
	 * Nightly entry point: GSC + GA4 syncs; analysis chains after the GSC sync.
	 */
	public function start_daily_pipeline(): void {
		$this->enqueue( SyncGscJob::NAME );
		$this->enqueue( SyncGa4Job::NAME );
	}

	/**
	 * Saturday pipeline: rollups + retention, then the CWV audit round.
	 */
	public function start_weekly_pipeline(): void {
		$this->enqueue( WeeklyMaintenanceJob::NAME );
		$this->enqueue( RunPsiAuditJob::NAME, 60 );
	}

	/**
	 * Enqueue a named job chunk as soon as possible.
	 */
	public function enqueue( string $job, int $delay_seconds = 0 ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay_seconds, self::HOOK_RUN_CHUNK, array( $job ), 'seo-director-ai' );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK_RUN_CHUNK, array( $job ) ) ) {
			wp_schedule_single_event( time() + max( 1, $delay_seconds ), self::HOOK_RUN_CHUNK, array( $job ) );
		}
	}

	/**
	 * Run one bounded chunk of a job; re-enqueue while the handler reports more work.
	 */
	public function run_chunk( string $job ): void {
		$result = match ( $job ) {
			SyncGscJob::NAME           => $this->sync_gsc->run_chunk(),
			SyncGa4Job::NAME           => $this->sync_ga4->run_chunk(),
			RunAnalysisJob::NAME       => $this->run_analysis->run_chunk(),
			RunPsiAuditJob::NAME       => $this->run_psi->run_chunk(),
			WeeklyMaintenanceJob::NAME => $this->weekly_maintenance->run_chunk(),
			default                    => JobResult::done(),
		};

		if ( $result->has_more ) {
			$this->enqueue( $job, 5 );
			return;
		}

		// Chain: analysis follows a completed GSC sync.
		if ( SyncGscJob::NAME === $job && $result->succeeded ) {
			/**
			 * Fires after a data sync completes.
			 *
			 * @param string $job Job name.
			 */
			do_action( 'sda_sync_completed', $job );
			$this->enqueue( RunAnalysisJob::NAME, 10 );
		}
	}
}
