<?php
/**
 * Pushes the outstanding roadmap tasks for a scope into the configured
 * external tool. Enterprise-gated. Idempotent: task ids already pushed are
 * remembered in an option so re-running never creates duplicates.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\TaskSync;

use SEODirector\Data\Repository\TaskRepository;
use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class TaskSyncDispatcher {

	private const SYNCED_OPTION = 'sda_synced_task_ids';

	/**
	 * @param TaskSyncInterface[] $connectors Keyed by slug.
	 */
	public function __construct(
		private array $connectors,
		private TaskRepository $tasks,
		private Settings $settings,
		private FeatureGate $gate,
	) {}

	/**
	 * Push every not-yet-synced open task in a scope.
	 *
	 * @return array{pushed:int, skipped:int, provider:string}|\WP_Error
	 */
	public function push_scope( string $scope ): array|\WP_Error {
		if ( ! $this->gate->allows( 'task_sync' ) ) {
			return new \WP_Error( 'sda_task_sync', __( 'Task sync requires an Enterprise license.', 'seo-director-ai' ), [ 'status' => 403 ] );
		}

		$connector = $this->active_connector();
		if ( null === $connector ) {
			return new \WP_Error( 'sda_task_sync_cfg', __( 'No task-sync provider is configured.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$synced  = $this->synced_ids();
		$pushed  = 0;
		$skipped = 0;

		foreach ( $this->tasks->list( $scope ) as $task ) {
			$id = (int) $task['id'];

			if ( isset( $synced[ $id ] ) || 'todo' !== $task['status'] ) {
				++$skipped;
				continue;
			}

			if ( null !== $connector->push_task( $task ) ) {
				$synced[ $id ] = time();
				++$pushed;
			}
		}

		$this->save_synced( $synced );

		return [ 'pushed' => $pushed, 'skipped' => $skipped, 'provider' => $connector->slug() ];
	}

	private function active_connector(): ?TaskSyncInterface {
		$slug      = (string) $this->settings->get( 'task_sync_provider', '' );
		$connector = $this->connectors[ $slug ] ?? null;

		return ( $connector && $connector->is_configured() ) ? $connector : null;
	}

	/**
	 * @return array<int, int> task id => synced-at timestamp.
	 */
	private function synced_ids(): array {
		$stored = get_option( self::SYNCED_OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * @param array<int, int> $synced
	 */
	private function save_synced( array $synced ): void {
		// Bound the option: keep only the most recent 500 entries.
		if ( count( $synced ) > 500 ) {
			arsort( $synced );
			$synced = array_slice( $synced, 0, 500, true );
		}

		update_option( self::SYNCED_OPTION, $synced, false );
	}
}
