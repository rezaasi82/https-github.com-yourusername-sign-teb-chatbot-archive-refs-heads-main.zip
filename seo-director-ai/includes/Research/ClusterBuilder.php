<?php
/**
 * Topic cluster builder (PRO). Runs keyword research for the seed first, then
 * asks the AI to organize the real demand into a pillar page + cluster
 * articles with an internal-linking plan. Existing posts on the topic are
 * passed along so clusters map to "update this post" instead of duplicates.
 *
 * @package SEODirector
 */

namespace SEODirector\Research;

use SEODirector\Ai\InsightService;

defined( 'ABSPATH' ) || exit;

final class ClusterBuilder {

	public function __construct(
		private InsightService $insights,
		private KeywordResearcher $researcher,
	) {}

	public function is_available(): bool {
		return $this->insights->is_available();
	}

	/**
	 * @return array{pillar: array<string, mixed>, clusters: array<int, array<string, mixed>>, linking_notes: string, keywords_used: int}|\WP_Error
	 */
	public function build( string $seed, string $lang = 'fa' ): array|\WP_Error {
		$seed = trim( $seed );
		if ( '' === $seed ) {
			return new \WP_Error( 'sda_input', __( 'A seed topic is required.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		$research = $this->researcher->research( $seed, $lang );
		$keywords = array_map(
			static fn( array $k ) => [
				'keyword'     => $k['keyword'],
				'intent'      => $k['intent'],
				'impressions' => $k['impressions'],
			],
			array_slice( $research['keywords'], 0, 60 )
		);

		$existing = $this->existing_posts( $seed );

		$result = $this->insights->generate(
			'topic_cluster',
			[
				'seed_topic'     => $seed,
				'keywords'       => $keywords,
				'existing_posts' => array_map( static fn( array $p ) => $p['title'], $existing ),
			],
			[ 'entity_type' => 'site', 'entity_label' => $seed ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload = (array) $result['payload'];

		return [
			'pillar'        => (array) ( $payload['pillar'] ?? [] ),
			'clusters'      => (array) ( $payload['clusters'] ?? [] ),
			'linking_notes' => (string) ( $payload['linking_notes'] ?? '' ),
			'keywords_used' => count( $keywords ),
		];
	}

	/**
	 * @return array<int, array{id: int, title: string}>
	 */
	private function existing_posts( string $seed ): array {
		$query = new \WP_Query(
			[
				's'              => $seed,
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		return array_map(
			static fn( int $id ) => [ 'id' => $id, 'title' => (string) get_the_title( $id ) ],
			$query->posts
		);
	}
}
