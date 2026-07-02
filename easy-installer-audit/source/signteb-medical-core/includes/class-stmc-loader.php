<?php
/**
 * SignTeb Medical Core — Action/Filter Loader
 *
 * مدیریت متمرکز همه hook‌ها.
 * کلاس‌های دیگر hooks خود را از طریق این Loader ثبت می‌کنند.
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC;

defined( 'ABSPATH' ) || exit;

final class Loader {

	/** @var array<array{hook:string, component:object, callback:string, priority:int, args:int}> */
	private array $actions = [];

	/** @var array<array{hook:string, component:object, callback:string, priority:int, args:int}> */
	private array $filters = [];

	// ─── API ──────────────────────────────────────────────────────────────────

	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int    $priority = 10,
		int    $args     = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int    $priority = 10,
		int    $args     = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	/**
	 * ثبت همه hook‌ها در WordPress
	 */
	public function run(): void {
		foreach ( $this->filters as $filter ) {
			add_filter(
				$filter['hook'],
				[ $filter['component'], $filter['callback'] ],
				$filter['priority'],
				$filter['args']
			);
		}

		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				[ $action['component'], $action['callback'] ],
				$action['priority'],
				$action['args']
			);
		}
	}
}
