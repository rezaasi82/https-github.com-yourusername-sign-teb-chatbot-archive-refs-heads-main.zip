<?php
/**
 * Builds the site's internal link graph from published WordPress content and
 * finds orphan pages — URLs that earn Search Console impressions but receive
 * no internal links. Feeds internal-link opportunities and the health score's
 * internal-linking component. Bounded crawl (batched), cached in a transient.
 *
 * @package SEODirector
 */

namespace SEODirector\Analysis\InternalLinks;

use SEODirector\Core\Schema;
use SEODirector\Data\UrlCanonicalizer;

defined( 'ABSPATH' ) || exit;

final class LinkGraphBuilder {

	private const CACHE_KEY   = 'sda_link_graph';
	private const CACHE_TTL   = DAY_IN_SECONDS;
	private const MAX_POSTS   = 2000;

	public function __construct( private UrlCanonicalizer $canonicalizer ) {}

	/**
	 * Canonical paths that are linked from at least one other post.
	 *
	 * @return array<string, true> Set of linked canonical paths.
	 */
	public function linked_targets(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$linked = $this->crawl();
		set_transient( self::CACHE_KEY, $linked, self::CACHE_TTL );

		return $linked;
	}

	/**
	 * Ratio of impression-earning pages that have at least one internal link
	 * (1.0 = every page is linked). Used by the health score.
	 */
	public function internal_link_ratio( int $property_id ): ?float {
		$pages = $this->impression_pages( $property_id );
		if ( [] === $pages ) {
			return null;
		}

		$linked = $this->linked_targets();
		$hits   = 0;
		foreach ( $pages as $path ) {
			if ( isset( $linked[ $path ] ) ) {
				++$hits;
			}
		}

		return round( $hits / count( $pages ), 3 );
	}

	/**
	 * Impression-earning pages with no internal links (orphans).
	 *
	 * @return string[] Canonical paths.
	 */
	public function orphans( int $property_id ): array {
		$linked  = $this->linked_targets();
		$orphans = [];
		foreach ( $this->impression_pages( $property_id ) as $path ) {
			if ( ! isset( $linked[ $path ] ) && '/' !== $path ) {
				$orphans[] = $path;
			}
		}

		return $orphans;
	}

	/**
	 * @return array<string, true>
	 */
	private function crawl(): array {
		$home   = home_url();
		$linked = [];

		$query = new \WP_Query(
			[
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_POSTS,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);

		foreach ( $query->posts as $post_id ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			if ( ! str_contains( $content, 'href' ) ) {
				continue;
			}

			if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $matches ) ) {
				foreach ( $matches[1] as $href ) {
					if ( ! str_starts_with( $href, '/' ) && ! str_starts_with( $href, $home ) ) {
						continue; // External link.
					}
					$linked[ $this->canonicalizer->canonicalize( $href ) ] = true;
				}
			}
		}

		return $linked;
	}

	/**
	 * Recently impression-earning page paths from GSC.
	 *
	 * @return string[]
	 */
	private function impression_pages( int $property_id ): array {
		global $wpdb;

		$table = Schema::table( 'gsc_page_daily' );

		return array_map(
			'strval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT page_path FROM {$table} WHERE property_id = %d AND date >= %s AND impressions > 10 GROUP BY page_hash, page_path LIMIT 2000", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$property_id,
					gmdate( 'Y-m-d', strtotime( '-28 days' ) )
				)
			)
		);
	}
}
