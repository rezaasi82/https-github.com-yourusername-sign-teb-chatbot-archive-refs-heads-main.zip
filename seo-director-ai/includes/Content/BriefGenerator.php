<?php
/**
 * AI SEO brief generator (PRO). Given a target keyword, gathers demand
 * evidence deterministically — related queries the site already earns
 * impressions for (GSC) and existing posts on the topic — then asks the AI
 * for a complete writing brief: goal, keyword set, H2/H3 outline, FAQs, and
 * the entities a comprehensive article must cover.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Ai\InsightService;
use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class BriefGenerator {

	public function __construct(
		private InsightService $insights,
		private PropertiesRepository $properties,
	) {}

	public function is_available(): bool {
		return $this->insights->is_available();
	}

	/**
	 * @return array{goal: string, primary_keyword: string, secondary_keywords: string[], outline: array<int, array<string, mixed>>, faq: array<int, array<string, mixed>>, entities: string[], related_queries: array<int, array<string, mixed>>, existing_posts: array<int, array<string, mixed>>}|\WP_Error
	 */
	public function brief( string $keyword ): array|\WP_Error {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return new \WP_Error( 'sda_input', __( 'A target keyword is required.', 'seo-director-ai' ), [ 'status' => 400 ] );
		}

		$related  = $this->related_queries( $keyword );
		$existing = $this->existing_posts( $keyword );

		$result = $this->insights->generate(
			'content_brief',
			[
				'target_keyword'  => $keyword,
				'related_queries' => $related,
				'existing_posts'  => array_map( static fn( array $p ) => $p['title'], $existing ),
			],
			[ 'entity_type' => 'site', 'entity_label' => $keyword ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload = (array) $result['payload'];

		return [
			'goal'               => (string) ( $payload['goal'] ?? '' ),
			'primary_keyword'    => (string) ( $payload['primary_keyword'] ?? $keyword ),
			'secondary_keywords' => array_map( 'strval', (array) ( $payload['secondary_keywords'] ?? [] ) ),
			'outline'            => (array) ( $payload['outline'] ?? [] ),
			'faq'                => (array) ( $payload['faq'] ?? [] ),
			'entities'           => array_map( 'strval', (array) ( $payload['entities'] ?? [] ) ),
			'related_queries'    => $related,
			'existing_posts'     => $existing,
		];
	}

	/**
	 * GSC queries sharing a token with the keyword — real demand the site
	 * already sees. Empty when GSC is not connected; the brief still works.
	 *
	 * @return array<int, array{query: string, impressions: int, position: float}>
	 */
	private function related_queries( string $keyword ): array {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return [];
		}

		global $wpdb;
		$table = Schema::table( 'gsc_query_weekly' );
		$week  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(week_start) FROM {$table} WHERE property_id = %d", $property['id'] ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( ! $week ) {
			return [];
		}

		// Match any keyword token of 3+ chars to catch morphology variants.
		$tokens = array_values(
			array_filter(
				preg_split( '/\s+/u', $keyword ) ?: [],
				static fn( string $t ) => mb_strlen( $t ) >= 3
			)
		);
		if ( [] === $tokens ) {
			$tokens = [ $keyword ];
		}

		$like_sql = implode(
			' OR ',
			array_fill( 0, count( $tokens ), 'query LIKE %s' )
		);
		$params   = [ $property['id'], $week ];
		foreach ( $tokens as $token ) {
			$params[] = '%' . $wpdb->esc_like( $token ) . '%';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT query, impressions, position FROM {$table}
				WHERE property_id = %d AND week_start = %s AND ({$like_sql})
				ORDER BY impressions DESC LIMIT 25", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			ARRAY_A
		);

		return array_map(
			static fn( array $r ) => [
				'query'       => (string) $r['query'],
				'impressions' => (int) $r['impressions'],
				'position'    => round( (float) $r['position'], 1 ),
			],
			$rows ?: []
		);
	}

	/**
	 * Published posts already touching the topic — the writer should link to
	 * them (and avoid duplicating them).
	 *
	 * @return array<int, array{id: int, title: string, url: string}>
	 */
	private function existing_posts( string $keyword ): array {
		$query = new \WP_Query(
			[
				's'              => $keyword,
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		return array_map(
			static fn( int $id ) => [
				'id'    => $id,
				'title' => (string) get_the_title( $id ),
				'url'   => (string) get_permalink( $id ),
			],
			$query->posts
		);
	}
}
