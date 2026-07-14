<?php
/**
 * Job scheduling backbone. Prefers Action Scheduler when present (bundled in
 * the release build; also provided by WooCommerce and many hosts), falling
 * back to WP-Cron with the same hook names so job code never cares which
 * backend fires it.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs;

use SEODirector\Data\Repository\JobStateRepository;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	public const HOOK_PREFIX = 'sda_job_';
	public const GROUP       = 'seo-director-ai';

	/** Recurring schedules: hook suffix => interval seconds. */
	private const RECURRING = [
		'daily_sync'      => DAY_IN_SECONDS,
		'hourly_alerts'   => HOUR_IN_SECONDS,
		'weekly_pipeline' => WEEK_IN_SECONDS,
		'license_check'   => DAY_IN_SECONDS,
	];

	/** @var array<string, AbstractChunkedJob> */
	private array $jobs = [];

	public function __construct( private JobStateRepository $state ) {}

	/**
	 * Register hook listeners and ensure recurring schedules exist.
	 * Runnable job handlers register themselves via sda_register_jobs.
	 */
	public function register_hooks(): void {
		/**
		 * Filters the chunked job handlers keyed by job name.
		 *
		 * @param array<string, AbstractChunkedJob> $jobs Registered jobs.
		 * @param JobStateRepository                $state Shared job state repository.
		 */
		$this->jobs = apply_filters( 'sda_register_jobs', [], $this->state );

		foreach ( $this->jobs as $name => $job ) {
			add_action( self::HOOK_PREFIX . $name, [ $job, 'run' ] );
		}

		add_action( 'init', [ $this, 'ensure_recurring' ] );
	}

	public function ensure_recurring(): void {
		foreach ( self::RECURRING as $suffix => $interval ) {
			$hook = self::HOOK_PREFIX . $suffix;

			if ( self::has_action_scheduler() ) {
				if ( false === as_next_scheduled_action( $hook, [], self::GROUP ) ) {
					as_schedule_recurring_action( time() + $interval, $interval, $hook, [], self::GROUP );
				}
			} elseif ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + $interval, self::interval_name( $interval ), $hook );
			}
		}
	}

	/**
	 * Queue the next chunk of a chunked job as soon as possible.
	 */
	public static function enqueue_next_chunk( string $job ): void {
		$hook = self::HOOK_PREFIX . $job;

		if ( self::has_action_scheduler() ) {
			as_enqueue_async_action( $hook, [], self::GROUP );
			return;
		}

		wp_schedule_single_event( time() + 5, $hook );
	}

	/**
	 * Queue a retry with exponential backoff based on prior failures.
	 */
	public static function enqueue_retry( string $job, int $prior_failures ): void {
		$delay = min( HOUR_IN_SECONDS, ( 2 ** $prior_failures ) * MINUTE_IN_SECONDS );
		$hook  = self::HOOK_PREFIX . $job;

		if ( self::has_action_scheduler() ) {
			as_schedule_single_action( time() + $delay, $hook, [], self::GROUP );
			return;
		}

		wp_schedule_single_event( time() + $delay, $hook );
	}

	public static function unschedule_all(): void {
		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( '', [], self::GROUP );
		}

		foreach ( array_keys( self::RECURRING ) as $suffix ) {
			$hook = self::HOOK_PREFIX . $suffix;
			$time = wp_next_scheduled( $hook );
			while ( false !== $time ) {
				wp_unschedule_event( $time, $hook );
				$time = wp_next_scheduled( $hook );
			}
		}
	}

	public static function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_next_scheduled_action' );
	}

	private static function interval_name( int $seconds ): string {
		return match ( true ) {
			$seconds <= HOUR_IN_SECONDS => 'hourly',
			$seconds <= DAY_IN_SECONDS  => 'daily',
			default                     => 'weekly',
		};
	}
}
