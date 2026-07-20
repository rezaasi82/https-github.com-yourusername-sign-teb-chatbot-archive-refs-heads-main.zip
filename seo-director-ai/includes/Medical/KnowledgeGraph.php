<?php
/**
 * Medical knowledge graph (Medical Pack). Aggregates the entity engine across
 * all published content to answer: which medical concepts does this site
 * cover, how deeply, and which important concepts are missing entirely. This
 * is the site-wide E-E-A-T / content-gap view for a medical practice.
 *
 * Cached for 6h — a full scan reads every post body, cheap but not free.
 *
 * @package SEODirector
 */

namespace SEODirector\Medical;

defined( 'ABSPATH' ) || exit;

final class KnowledgeGraph {

	private const CACHE_KEY = 'sda_medical_kg';
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
	private const MAX_POSTS = 500;

	public function __construct( private MedicalEntityEngine $entities ) {}

	/**
	 * @return array{generated_at: string, posts_scanned: int, by_category: array<string, array<int, array{term: string, posts: int, mentions: int}>>, missing: array<int, array{term: string, category: string}>, covered_terms: int, total_terms: int}
	 */
	public function build( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$ids = get_posts(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_POSTS,
				'fields'         => 'ids',
			]
		);

		// term => [category, posts, mentions]
		$agg = [];
		foreach ( $ids as $id ) {
			foreach ( $this->entities->detect_in_post( (int) $id ) as $entity ) {
				$term = $entity['term'];
				$agg[ $term ] ??= [ 'category' => $entity['category'], 'posts' => 0, 'mentions' => 0 ];
				$agg[ $term ]['posts']++;
				$agg[ $term ]['mentions'] += $entity['count'];
			}
		}

		// Group covered terms by category, most-covered first.
		$by_category = [];
		foreach ( $agg as $term => $data ) {
			$by_category[ $data['category'] ][] = [
				'term'     => $term,
				'posts'    => $data['posts'],
				'mentions' => $data['mentions'],
			];
		}
		foreach ( $by_category as &$rows ) {
			usort( $rows, static fn( array $a, array $b ) => $b['posts'] <=> $a['posts'] );
		}
		unset( $rows );

		// Dictionary terms nobody covers — the content gaps.
		$missing = [];
		foreach ( $this->entities->all_terms() as $term => $category ) {
			if ( ! isset( $agg[ $term ] ) ) {
				$missing[] = [ 'term' => $term, 'category' => $category ];
			}
		}

		$total = count( $this->entities->all_terms() );

		$result = [
			'generated_at'  => gmdate( 'c' ),
			'posts_scanned' => count( $ids ),
			'by_category'   => $by_category,
			'missing'       => $missing,
			'covered_terms' => count( $agg ),
			'total_terms'   => $total,
		];

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		return $result;
	}
}
