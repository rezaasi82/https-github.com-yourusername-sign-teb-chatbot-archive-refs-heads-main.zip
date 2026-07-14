<?php
/**
 * Base class for idempotent, resumable, chunked background jobs.
 *
 * A job runs one bounded chunk per invocation, persists its cursor, and
 * re-enqueues itself until process_chunk() reports completion. Failures are
 * recorded and retried with capped attempts; a permanently failing job parks
 * itself in "failed" state where the Sync Health screen can surface it.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs;

use SEODirector\Data\Repository\JobStateRepository;

defined( 'ABSPATH' ) || exit;

abstract class AbstractChunkedJob {

	protected const MAX_FAILURES = 5;

	public function __construct( protected JobStateRepository $state ) {}

	/**
	 * Unique job name, e.g. "sync_gsc".
	 */
	abstract public function name(): string;

	/**
	 * Process one bounded chunk (< ~20s of work).
	 *
	 * @param array<mixed> $cursor Cursor from the previous chunk ([] on first run).
	 * @return array<mixed>|null The next cursor, or null when the job is complete.
	 * @throws \Throwable On chunk failure (recorded + retried).
	 */
	abstract protected function process_chunk( array $cursor ): ?array;

	/**
	 * Entry point invoked by the Scheduler hook.
	 */
	public function run(): void {
		$existing = $this->state->get( $this->name() );
		$cursor   = $existing['cursor'] ?? [];

		if ( ( $existing['fail_count'] ?? 0 ) >= static::MAX_FAILURES ) {
			// Parked: requires manual retry from the Sync Health screen.
			return;
		}

		$this->state->put( $this->name(), $cursor, 'running' );

		try {
			$next = $this->process_chunk( $cursor );
		} catch ( \Throwable $e ) {
			$this->state->put( $this->name(), $cursor, 'failed', $e->getMessage() );
			Scheduler::enqueue_retry( $this->name(), $existing['fail_count'] ?? 0 );
			return;
		}

		if ( null === $next ) {
			$this->state->put( $this->name(), null, 'done' );
			return;
		}

		$this->state->put( $this->name(), $next, 'idle' );
		Scheduler::enqueue_next_chunk( $this->name() );
	}
}
