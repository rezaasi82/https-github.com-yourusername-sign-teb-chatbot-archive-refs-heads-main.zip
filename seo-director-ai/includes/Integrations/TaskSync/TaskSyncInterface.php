<?php
/**
 * Contract for pushing a roadmap task into an external project-management
 * tool (Jira, Trello, …). Adding a new destination is one class implementing
 * this interface — the dispatcher and REST layer never change.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\TaskSync;

defined( 'ABSPATH' ) || exit;

interface TaskSyncInterface {

	/** Stable provider slug (e.g. "jira", "trello"). */
	public function slug(): string;

	/** Whether the provider has the credentials it needs to push. */
	public function is_configured(): bool;

	/**
	 * Create the task in the external tool.
	 *
	 * @param array<string, mixed> $task Roadmap task row (title, description, priority…).
	 * @return string|null External id/URL on success, null on failure.
	 */
	public function push_task( array $task ): ?string;
}
