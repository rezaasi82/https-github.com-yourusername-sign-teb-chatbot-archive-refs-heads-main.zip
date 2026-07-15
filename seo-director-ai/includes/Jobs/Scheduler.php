<?php
/**
 * Job scheduling. Uses Action Scheduler when present (e.g. WooCommerce installed),
 * falls back to WP-Cron single events. Handlers are idempotent and chunked.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs;

defined( 'ABSPATH' ) || exit;

use SEODirector\Jobs\Handlers\RunAnalysisJob;
use SEODirector\Jobs\Handlers\SyncGscJob;

final class Scheduler {

	public const HOOK_DAILY_SYNC = 'sda_daily_sync';
	public const HOOK_RUN_CHUNK  = 'sda_run_chunk';

	public function __construct(
		private readonly SyncGscJob $sync_gsc,
		private readonly RunAnalysisJob $run_analysis,
	) {}

	public function register_hooks(): void {
		add_action( self::HOOK_DAILY_SYNC, array( $this, 'start_daily_pipeline' ) );
		add_action( self::HOOK_RUN_CHUNK, array( $this, 'run_chunk' ), 10, 1 );
	}

	/**
	 * Nightly entry point: kick the GSC sync; analysis chains automatically after it.
	 */
	public function start_daily_pipeline(): void {
		$this->enqueue( SyncGscJob::NAME );
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
			SyncGscJob::NAME     => $this->sync_gsc->run_chunk(),
			RunAnalysisJob::NAME => $this->run_analysis->run_chunk(),
			default              => JobResult::done(),
		};

		if ( $result->has_more ) {
			$this->enqueue( $job, 5 );
			return;
		}

		// Chain: analysis follows a completed sync.
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
