<?php
/**
 * Pushes a roadmap task into Trello as a card on a configured list.
 * Auth is the key + token pair passed as query parameters.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\TaskSync;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class TrelloConnector implements TaskSyncInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'trello';
	}

	public function is_configured(): bool {
		return '' !== (string) $this->settings->get( 'trello_key', '' )
			&& '' !== (string) $this->settings->get( 'trello_token', '' )
			&& '' !== (string) $this->settings->get( 'trello_list_id', '' );
	}

	public function push_task( array $task ): ?string {
		if ( ! $this->is_configured() ) {
			return null;
		}

		$url = add_query_arg(
			[
				'key'    => (string) $this->settings->get( 'trello_key', '' ),
				'token'  => (string) $this->settings->get( 'trello_token', '' ),
				'idList' => (string) $this->settings->get( 'trello_list_id', '' ),
				'name'   => mb_substr( (string) ( $task['title'] ?? 'SEO task' ), 0, 240 ),
				'desc'   => (string) ( $task['description'] ?? '' ),
			],
			'https://api.trello.com/1/cards'
		);

		$result = $this->http->post( $url, [ 'timeout' => 15 ] );
		if ( ! $result->ok() ) {
			return null;
		}

		$json = $result->json();

		return $json['shortUrl'] ?? ( $json['url'] ?? ( isset( $json['id'] ) ? (string) $json['id'] : null ) );
	}
}
