<?php
/**
 * Pushes a roadmap task into Jira as an issue (Jira Cloud REST v3).
 * Auth is HTTP Basic with the account email + an API token.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\TaskSync;

use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class JiraConnector implements TaskSyncInterface {

	public function __construct(
		private Settings $settings,
		private RetryingHttpClient $http,
	) {}

	public function slug(): string {
		return 'jira';
	}

	public function is_configured(): bool {
		return '' !== (string) $this->settings->get( 'jira_base_url', '' )
			&& '' !== (string) $this->settings->get( 'jira_email', '' )
			&& '' !== (string) $this->settings->get( 'jira_token', '' )
			&& '' !== (string) $this->settings->get( 'jira_project_key', '' );
	}

	public function push_task( array $task ): ?string {
		if ( ! $this->is_configured() ) {
			return null;
		}

		$base  = untrailingslashit( (string) $this->settings->get( 'jira_base_url', '' ) );
		$email = (string) $this->settings->get( 'jira_email', '' );
		$token = (string) $this->settings->get( 'jira_token', '' );
		$key   = (string) $this->settings->get( 'jira_project_key', '' );

		$body = [
			'fields' => [
				'project'     => [ 'key' => $key ],
				'summary'     => $this->summary( $task ),
				'issuetype'   => [ 'name' => 'Task' ],
				'description' => $this->adf( (string) ( $task['description'] ?? '' ) ),
			],
		];

		$result = $this->http->post(
			$base . '/rest/api/3/issue',
			[
				'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $email . ':' . $token ) ],
				'body'    => $body,
				'timeout' => 15,
			]
		);

		if ( ! $result->ok() ) {
			return null;
		}

		$json = $result->json();

		return isset( $json['key'] ) ? $base . '/browse/' . (string) $json['key'] : ( $json['self'] ?? null );
	}

	private function summary( array $task ): string {
		return '[SEO] ' . mb_substr( (string) ( $task['title'] ?? 'SEO task' ), 0, 240 );
	}

	/**
	 * Wrap plain text in Jira's Atlassian Document Format (required by v3).
	 *
	 * @return array<string, mixed>
	 */
	private function adf( string $text ): array {
		return [
			'type'    => 'doc',
			'version' => 1,
			'content' => [
				[
					'type'    => 'paragraph',
					'content' => [ [ 'type' => 'text', 'text' => '' === $text ? ' ' : $text ] ],
				],
			],
		];
	}
}
