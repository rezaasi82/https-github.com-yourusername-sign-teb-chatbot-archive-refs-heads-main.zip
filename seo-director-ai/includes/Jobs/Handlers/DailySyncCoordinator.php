<?php
/**
 * Fired by the recurring "daily_sync" schedule: seeds incremental cursors
 * for GSC/GA4 (when no run is already in flight) and kicks the chunk chain.
 * The weekly schedule additionally queues PSI audits and retention pruning.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Retention\RetentionPolicy;
use SEODirector\Jobs\Scheduler;

defined( 'ABSPATH' ) || exit;

final class DailySyncCoordinator {

	public function __construct(
		private JobStateRepository $state,
		private SyncGscJob $gsc_job,
		private SyncGa4Job $ga4_job,
		private RetentionPolicy $retention,
	) {}

	public function run_daily(): void {
		$this->kick( SyncGscJob::NAME, fn() => $this->gsc_job->initial_cursor( false ) );
		$this->kick( SyncGa4Job::NAME, fn() => $this->ga4_job->initial_cursor( false ) );
	}

	public function run_weekly(): void {
		$this->kick( RunPsiAuditJob::NAME, static fn() => [] );
		$this->retention->prune();
	}

	/**
	 * Start a full backfill for a service right after a property is selected.
	 */
	public function start_backfill( string $service ): void {
		if ( 'gsc' === $service ) {
			$this->seed_and_kick( SyncGscJob::NAME, $this->gsc_job->initial_cursor( true ) );
		}
		if ( 'ga4' === $service ) {
			$this->seed_and_kick( SyncGa4Job::NAME, $this->ga4_job->initial_cursor( true ) );
		}
	}

	/**
	 * @param callable(): (array<mixed>|null) $cursor_factory
	 */
	private function kick( string $job, callable $cursor_factory ): void {
		$existing = $this->state->get( $job );
		if ( null !== $existing && 'running' === $existing['status'] ) {
			return; // A chain is already in flight.
		}

		$this->seed_and_kick( $job, $cursor_factory() );
	}

	/**
	 * @param array<mixed>|null $cursor
	 */
	private function seed_and_kick( string $job, ?array $cursor ): void {
		if ( null === $cursor ) {
			return; // Nothing to sync (no property selected / already current).
		}

		$this->state->put( $job, $cursor, 'idle' );
		Scheduler::enqueue_next_chunk( $job );
	}
}
