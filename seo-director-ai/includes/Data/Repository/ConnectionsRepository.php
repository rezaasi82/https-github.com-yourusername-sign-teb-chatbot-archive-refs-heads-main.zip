<?php
/**
 * Repository for {p}sda_connections.
 *
 * @package SEODirector
 */

namespace SEODirector\Data\Repository;

defined( 'ABSPATH' ) || exit;

use SEODirector\Integrations\Google\TokenVault;

final class ConnectionsRepository extends BaseRepository {

	protected const TABLE = 'connections';

	private const SERVICES = array( 'gsc', 'ga4', 'psi', 'openai', 'claude', 'gemini', 'gdrive' );

	public function __construct( private readonly TokenVault $vault ) {}

	public function get_by_service( string $service ): ?object {
		$db = $this->db();
		return $db->get_row(
			$db->prepare(
				"SELECT * FROM {$this->table()} WHERE site_id = %d AND service = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service
			)
		) ?: null;
	}

	/**
	 * Status list for the connection health screen — credentials never leave the row.
	 *
	 * @return array<int, array{service: string, status: string, account_label: string, last_used_at: ?string}>
	 */
	public function list_statuses(): array {
		$db   = $this->db();
		$rows = $db->get_results(
			$db->prepare(
				"SELECT service, status, account_label, last_used_at FROM {$this->table()} WHERE site_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id()
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ) => array(
				'service'       => (string) $r['service'],
				'status'        => (string) $r['status'],
				'account_label' => (string) $r['account_label'],
				'last_used_at'  => $r['last_used_at'],
			),
			$rows ?: array()
		);
	}

	public function upsert( string $service, string $sealed_credentials, string $scopes = '', string $account_label = '' ): void {
		if ( ! in_array( $service, self::SERVICES, true ) ) {
			return;
		}
		$db  = $this->db();
		$now = current_time( 'mysql', true );
		$db->query(
			$db->prepare(
				"INSERT INTO {$this->table()} (site_id, service, status, account_label, credentials, scopes, created_at, updated_at)
				 VALUES (%d, %s, 'connected', %s, %s, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE status = 'connected', account_label = VALUES(account_label),
					credentials = VALUES(credentials), scopes = VALUES(scopes), updated_at = VALUES(updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->site_id(),
				$service,
				$account_label,
				$sealed_credentials,
				$scopes,
				$now,
				$now
			)
		);
	}

	/**
	 * Store a plain API key (AI providers, PSI) — sealed before it touches the row.
	 */
	public function store_api_key( string $service, string $api_key, string $label = '' ): void {
		$this->upsert( $service, $this->vault->seal( array( 'api_key' => $api_key ) ), '', $label ?: TokenVault::mask( $api_key ) );
	}

	/**
	 * Read back a stored API key, or null when missing/unreadable.
	 */
	public function get_api_key( string $service ): ?string {
		$row = $this->get_by_service( $service );
		if ( ! $row ) {
			return null;
		}
		$payload = $this->vault->open( (string) $row->credentials );
		return isset( $payload['api_key'] ) ? (string) $payload['api_key'] : null;
	}

	public function update_credentials( string $service, string $sealed_credentials ): void {
		$db = $this->db();
		$db->update(
			$this->table(),
			array(
				'credentials'  => $sealed_credentials,
				'last_used_at' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			),
			array(
				'site_id' => $this->site_id(),
				'service' => $service,
			)
		);
	}

	public function mark_status( string $service, string $status ): void {
		$db = $this->db();
		$db->update(
			$this->table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'site_id' => $this->site_id(),
				'service' => $service,
			)
		);
	}

	public function disconnect( string $service ): void {
		$db = $this->db();
		$db->delete(
			$this->table(),
			array(
				'site_id' => $this->site_id(),
				'service' => $service,
			)
		);
	}
}
