<?php
/**
 * Saturday pipeline: rebuild rollups, prune old rows, queue the CWV audit round.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Retention\RetentionPolicy;
use SEODirector\Data\Rollup\RollupBuilder;
use SEODirector\Jobs\JobResult;

final class WeeklyMaintenanceJob {

	public const NAME = 'weekly_maintenance';

	public function __construct(
		private readonly PropertiesRepository $properties,
		private readonly RollupBuilder $rollups,
		private readonly RetentionPolicy $retention,
	) {}

	public function run_chunk(): JobResult {
		$property = $this->properties->active_property( 'gsc' );
		if ( $property ) {
			$this->rollups->build( (int) $property->id );
		}

		// Keep pruning in bounded passes until the backlog clears.
		if ( $this->retention->prune() >= 5000 ) {
			return JobResult::more();
		}

		return JobResult::done();
	}
}
