<?php
/**
 * Deactivation routine: unschedule everything, keep all data.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

use SEODirector\Jobs\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Deactivator {

	public static function deactivate(): void {
		Scheduler::unschedule_all();
	}
}
