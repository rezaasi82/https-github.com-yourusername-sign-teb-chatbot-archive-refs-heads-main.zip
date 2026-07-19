<?php
/**
 * Result of a single job chunk.
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs;

defined( 'ABSPATH' ) || exit;

final class JobResult {

	private function __construct(
		public readonly bool $has_more,
		public readonly bool $succeeded,
	) {}

	public static function more(): self {
		return new self( true, true );
	}

	public static function done(): self {
		return new self( false, true );
	}

	public static function failed(): self {
		return new self( false, false );
	}
}
