<?php
/**
 * AI content strategist (PRO): generates optimized meta titles/descriptions
 * for a page and finds content gaps — queries the site earns impressions for
 * but has no strong dedicated page. Evidence is computed deterministically;
 * the AI writes the copy and topic ideas.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

use SEODirector\Ai\InsightService;
use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class ContentStrategist {

	public function __construct(
		private InsightService $insights,
		private PropertiesRepository $properties,
	) {}

	public function is_available(): bool {
		return $this->insights->is_available();
	}

	/**
	 * Meta title/description for a page, with SERP pixel-width estimates.
	 *
	 * @return array{title: string, description: string, title_px: int, desc_px: int}|\WP_Error
	 */
	public function meta_for_page( string $page_hash_hex ): array|\WP_Error {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return new \WP_Error( 'sda_no_property', __( 'No Search Console property selected.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$page_queries = $this->top_queries_for_page( $property['id'], $page_hash_hex );
		if ( [] === $page_queries ) {
			return new \WP_Error( 'sda_no_data', __( 'No query data for this page yet.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$result = $this->insights->generate(
			'content_meta',
			[ 'page' => $page_queries['page'], 'top_queries' => $page_queries['queries'] ],
			[ 'entity_type' => 'page', 'entity_hash_hex' => $page_hash_hex, 'entity_label' => $page_queries['page'] ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$title = (string) ( $result['payload']['title'] ?? '' );
		$desc  = (string) ( $result['payload']['description'] ?? '' );

		return [
			'title'       => $title,
			'description' => $desc,
			'title_px'    => $this->pixel_width( $title ),
			'desc_px'     => $this->pixel_width( $desc ),
		];
	}

	/**
	 * Content gap analysis: cluster high-impression under-served queries and
	 * ask the AI for topic ideas.
	 *
	 * @return array{topics: array<int, array<string, mixed>>}|\WP_Error
	 */
	public function gap_analysis(): array|\WP_Error {
		$property = $this->properties->active( 'gsc' );
		if ( null === $property ) {
			return new \WP_Error( 'sda_no_property', __( 'No Search Console property selected.', 'seo-director-ai' ), [ 'status' => 409 ] );
		}

		$gaps = $this->gap_queries( $property['id'] );
		if ( [] === $gaps ) {
			return [ 'topics' => [] ];
		}

		$result = $this->insights->generate(
			'content_gap',
			[ 'underserved_queries' => $gaps ],
			[ 'entity_type' => 'site' ]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [ 'topics' => (array) ( $result['payload']['topics'] ?? [] ) ];
	}

	/**
	 * @return array{page: string, queries: array<int, array<string, mixed>>}|array{}
	 */
	private function top_queries_for_page( int $property_id, string $page_hash_hex ): array {
		global $wpdb;

		$pq   = Schema::table( 'gsc_page_query_weekly' );
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT MAX(page_path) AS page, query, SUM(impressions) AS impressions, AVG(position) AS position
				FROM {$pq} WHERE property_id = %d AND page_hash = UNHEX(%s)
				GROUP BY query_hash, query ORDER BY impressions DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$page_hash_hex
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return [];
		}

		return [
			'page'    => (string) $rows[0]['page'],
			'queries' => array_map(
				static fn( array $r ) => [
					'query'       => (string) $r['query'],
					'impressions' => (int) $r['impressions'],
					'position'    => round( (float) $r['position'], 1 ),
				],
				$rows
			),
		];
	}

	/**
	 * High-impression queries whose best position is weak (> 10): the site
	 * earns visibility but has no page that ranks — a content gap.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function gap_queries( int $property_id ): array {
		global $wpdb;

		$table = Schema::table( 'gsc_query_weekly' );
		$week  = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT MAX(week_start) FROM {$table} WHERE property_id = %d", $property_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( ! $week ) {
			return [];
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT query, impressions, position FROM {$table}
				WHERE property_id = %d AND week_start = %s AND position > 10 AND impressions >= 100
				ORDER BY impressions DESC LIMIT 30", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$property_id,
				$week
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
	 * Approximate SERP pixel width (Arial ~medium metrics). Good enough for a
	 * "too long?" indicator without shipping a font metrics table.
	 */
	private function pixel_width( string $text ): int {
		$narrow = [ 'i', 'l', 'I', 'j', 't', 'f', 'r', '.', ',', ':', ';', '\'', '|', ' ' ];
		$wide   = [ 'm', 'w', 'M', 'W', '—' ];
		$px     = 0;

		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		foreach ( $chars as $char ) {
			if ( in_array( $char, $narrow, true ) ) {
				$px += 4;
			} elseif ( in_array( $char, $wide, true ) ) {
				$px += 11;
			} else {
				$px += 8;
			}
		}

		return $px;
	}
}
