<?php
/**
 * Keyword research engine. Honest about its data sources for the Persian
 * market, where paid tools have weak volume data:
 *
 *  - Google Autocomplete (free): seed + question/commercial prefix expansion
 *  - SerpApi, when a key is configured: People-Also-Ask + related searches
 *  - The site's own GSC data: impressions as a volume proxy and the current
 *    position, for keywords the site already surfaces for
 *  - Deterministic intent classification (fa + en heuristics)
 *
 * No fabricated "volume" or "difficulty" numbers — impressions and position
 * are real; everything else is labeled by its source.
 *
 * @package SEODirector
 */

namespace SEODirector\Research;

use SEODirector\Core\Schema;
use SEODirector\Data\Repository\PropertiesRepository;

defined( 'ABSPATH' ) || exit;

final class KeywordResearcher {

	/** Expansion prefixes per language: question + commercial modifiers. */
	private const PREFIXES = [
		'fa' => [ 'چگونه', 'چرا', 'آیا', 'هزینه', 'قیمت', 'بهترین', 'عوارض', 'درمان' ],
		'en' => [ 'how', 'why', 'what', 'best', 'cost', 'price' ],
	];

	private const INTENT_MARKERS = [
		'transactional' => [ 'خرید', 'قیمت', 'هزینه', 'رزرو', 'نوبت', 'تخفیف', 'buy', 'price', 'cost', 'order', 'booking' ],
		'commercial'    => [ 'بهترین', 'مقایسه', 'برترین', 'کدام', 'best', 'top', 'vs', 'compare', 'review' ],
		'local'         => [ 'تهران', 'مشهد', 'اصفهان', 'شیراز', 'کرج', 'دبی', 'نزدیک من', 'near me', 'آدرس' ],
		'informational' => [ 'چیست', 'چگونه', 'چرا', 'آیا', 'علائم', 'عوارض', 'درمان', 'آموزش', 'روش', 'how', 'what', 'why', 'symptoms', 'treatment', 'guide' ],
	];

	public function __construct(
		private AutocompleteClient $autocomplete,
		private SerpClient $serp,
		private PropertiesRepository $properties,
	) {}

	/**
	 * @return array{seed: string, keywords: array<int, array{keyword: string, sources: string[], intent: string, impressions: int|null, position: float|null}>, serp_used: bool}
	 */
	public function research( string $seed, string $lang = 'fa' ): array {
		$seed = trim( $seed );
		$lang = 'en' === $lang ? 'en' : 'fa';

		// 1) Autocomplete: plain seed + prefixed variants.
		$found = [];
		foreach ( $this->autocomplete->suggest( $seed, $lang ) as $kw ) {
			$found[ $this->norm( $kw ) ]['sources']['autocomplete'] = true;
			$found[ $this->norm( $kw ) ]['keyword']                 = $kw;
		}
		foreach ( self::PREFIXES[ $lang ] as $prefix ) {
			foreach ( $this->autocomplete->suggest( $prefix . ' ' . $seed, $lang ) as $kw ) {
				$found[ $this->norm( $kw ) ]['sources']['autocomplete'] = true;
				$found[ $this->norm( $kw ) ]['keyword']                 = $kw;
			}
		}

		// 2) SerpApi (optional): PAA questions + related searches.
		$serp_used = false;
		$serp      = $this->serp->search( $seed, '', $lang );
		if ( null !== $serp ) {
			$serp_used = true;
			foreach ( $serp['questions'] as $kw ) {
				$found[ $this->norm( $kw ) ]['sources']['people_also_ask'] = true;
				$found[ $this->norm( $kw ) ]['keyword']                    = $kw;
			}
			foreach ( $serp['related'] as $kw ) {
				$found[ $this->norm( $kw ) ]['sources']['related_searches'] = true;
				$found[ $this->norm( $kw ) ]['keyword']                     = $kw;
			}
		}

		// 3) The site's own GSC queries containing a seed token.
		foreach ( $this->gsc_matches( $seed ) as $row ) {
			$key                                = $this->norm( $row['query'] );
			$found[ $key ]['sources']['gsc']    = true;
			$found[ $key ]['keyword']         ??= $row['query'];
			$found[ $key ]['impressions']       = $row['impressions'];
			$found[ $key ]['position']          = $row['position'];
		}

		// Assemble, classify intent, sort: GSC-backed (real demand) first by
		// impressions, then the rest alphabetically.
		$keywords = [];
		foreach ( $found as $item ) {
			$keyword    = (string) $item['keyword'];
			$keywords[] = [
				'keyword'     => $keyword,
				'sources'     => array_keys( $item['sources'] ),
				'intent'      => $this->intent( $keyword ),
				'impressions' => isset( $item['impressions'] ) ? (int) $item['impressions'] : null,
				'position'    => isset( $item['position'] ) ? (float) $item['position'] : null,
			];
		}

		usort(
			$keywords,
			static function ( array $a, array $b ): int {
				$ai = $a['impressions'] ?? -1;
				$bi = $b['impressions'] ?? -1;
				if ( $ai !== $bi ) {
					return $bi <=> $ai;
				}
				return strcmp( $a['keyword'], $b['keyword'] );
			}
		);

		return [
			'seed'      => $seed,
			'keywords'  => array_slice( $keywords, 0, 100 ),
			'serp_used' => $serp_used,
		];
	}

	private function intent( string $keyword ): string {
		$kw = mb_strtolower( $keyword );

		// Order matters: transactional beats commercial beats local beats info.
		foreach ( self::INTENT_MARKERS as $intent => $markers ) {
			foreach ( $markers as $marker ) {
				if ( str_contains( $kw, $marker ) ) {
					return $intent;
				}
			}
		}

		return 'informational';
	}

	/**
	 * @return array<int, array{query: string, impressions: int, position: float}>
	 */
	private function gsc_matches( string $seed ): array {
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

		$tokens = array_values(
			array_filter( preg_split( '/\s+/u', $seed ) ?: [], static fn( string $t ) => mb_strlen( $t ) >= 3 )
		);
		if ( [] === $tokens ) {
			$tokens = [ $seed ];
		}

		$like_sql = implode( ' OR ', array_fill( 0, count( $tokens ), 'query LIKE %s' ) );
		$params   = [ $property['id'], $week ];
		foreach ( $tokens as $token ) {
			$params[] = '%' . $wpdb->esc_like( $token ) . '%';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT query, impressions, position FROM {$table}
				WHERE property_id = %d AND week_start = %s AND ({$like_sql})
				ORDER BY impressions DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

	private function norm( string $keyword ): string {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $keyword ) ?? $keyword ) );
	}
}
