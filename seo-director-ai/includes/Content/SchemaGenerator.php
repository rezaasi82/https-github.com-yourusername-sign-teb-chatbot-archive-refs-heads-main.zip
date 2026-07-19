<?php
/**
 * Structured-data generator. Builds JSON-LD for a post deterministically
 * (Article, BreadcrumbList, and FAQPage extracted from question-style
 * headings in the content), stores the selection in post meta, and lets the
 * injector print it on the front end. No AI involved — schema must be exact.
 *
 * @package SEODirector
 */

namespace SEODirector\Content;

defined( 'ABSPATH' ) || exit;

final class SchemaGenerator {

	public const META_KEY = '_sda_schema';

	public const TYPES = [ 'article', 'breadcrumb', 'faq' ];

	/**
	 * Build (without saving) the JSON-LD graph for a post.
	 *
	 * @param string[] $types Subset of self::TYPES.
	 * @return array{graph: array<int, array<string, mixed>>, faq_found: int}|\WP_Error
	 */
	public function build( int $post_id, array $types ): array|\WP_Error {
		$post = get_post( $post_id );
		if ( null === $post || 'publish' !== $post->post_status ) {
			return new \WP_Error( 'sda_not_found', __( 'Post not found or not published.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$graph = [];
		$faqs  = $this->extract_faq( (string) $post->post_content );

		foreach ( array_intersect( $types, self::TYPES ) as $type ) {
			$node = match ( $type ) {
				'article'    => $this->article( $post ),
				'breadcrumb' => $this->breadcrumb( $post ),
				'faq'        => [] === $faqs ? null : $this->faq_page( $faqs ),
				default      => null,
			};
			if ( null !== $node ) {
				$graph[] = $node;
			}
		}

		return [ 'graph' => $graph, 'faq_found' => count( $faqs ) ];
	}

	/**
	 * Build and persist so the injector outputs it on the front end.
	 *
	 * @param string[] $types
	 * @return array{graph: array<int, array<string, mixed>>, faq_found: int}|\WP_Error
	 */
	public function save( int $post_id, array $types ): array|\WP_Error {
		$built = $this->build( $post_id, $types );
		if ( is_wp_error( $built ) ) {
			return $built;
		}

		if ( [] === $built['graph'] ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, wp_json_encode( $built['graph'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		}

		return $built;
	}

	public function remove( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}

	/** @return array<string, mixed> */
	private function article( \WP_Post $post ): array {
		$author = get_userdata( (int) $post->post_author );
		$image  = get_the_post_thumbnail_url( $post, 'full' );

		$node = [
			'@type'         => 'Article',
			'headline'      => (string) get_the_title( $post ),
			'url'           => (string) get_permalink( $post ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'inLanguage'    => get_bloginfo( 'language' ),
			'publisher'     => [
				'@type' => 'Organization',
				'name'  => (string) get_bloginfo( 'name' ),
				'url'   => (string) home_url( '/' ),
			],
		];

		if ( $author ) {
			$node['author'] = [ '@type' => 'Person', 'name' => (string) $author->display_name ];
		}
		if ( $image ) {
			$node['image'] = (string) $image;
		}

		return $node;
	}

	/** @return array<string, mixed> */
	private function breadcrumb( \WP_Post $post ): array {
		$items = [
			[
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => (string) get_bloginfo( 'name' ),
				'item'     => (string) home_url( '/' ),
			],
		];

		$position   = 2;
		$categories = get_the_category( $post->ID );
		if ( [] !== $categories ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => (string) $categories[0]->name,
				'item'     => (string) get_category_link( $categories[0] ),
			];
		}

		$items[] = [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => (string) get_the_title( $post ),
			'item'     => (string) get_permalink( $post ),
		];

		return [ '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
	}

	/**
	 * @param array<int, array{question: string, answer: string}> $faqs
	 * @return array<string, mixed>
	 */
	private function faq_page( array $faqs ): array {
		return [
			'@type'      => 'FAQPage',
			'mainEntity' => array_map(
				static fn( array $faq ) => [
					'@type'          => 'Question',
					'name'           => $faq['question'],
					'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $faq['answer'] ],
				],
				$faqs
			),
		];
	}

	/**
	 * Question-style headings (ending with ? or ؟) become FAQ entries; the
	 * answer is the text between that heading and the next one. Covers both
	 * classic-editor HTML and Gutenberg output, since both render h2–h4 tags.
	 *
	 * @return array<int, array{question: string, answer: string}>
	 */
	private function extract_faq( string $content_html ): array {
		if ( ! preg_match_all( '/<h([2-4])[^>]*>(.*?)<\/h\1>/isu', $content_html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return [];
		}

		$faqs  = [];
		$count = count( $matches[0] );

		for ( $i = 0; $i < $count; $i++ ) {
			$question = trim( wp_strip_all_tags( $matches[2][ $i ][0] ) );
			if ( ! preg_match( '/[?؟]\s*$/u', $question ) ) {
				continue;
			}

			$start = $matches[0][ $i ][1] + strlen( $matches[0][ $i ][0] );
			$end   = $i + 1 < $count ? $matches[0][ $i + 1 ][1] : strlen( $content_html );
			$slice = substr( $content_html, $start, $end - $start );

			$answer = trim( wp_strip_all_tags( $slice ) );
			if ( mb_strlen( $answer ) < 20 ) {
				continue;
			}

			$faqs[] = [
				'question' => $question,
				'answer'   => mb_substr( $answer, 0, 1200 ),
			];
		}

		return array_slice( $faqs, 0, 20 );
	}
}
