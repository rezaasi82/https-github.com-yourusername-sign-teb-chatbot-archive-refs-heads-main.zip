<?php
/**
 * SERP context via SerpApi (Enterprise). Returns the SERP features present for
 * a query plus the leading organic domains, used to enrich root-cause
 * analysis. Returns null unless the serp_enrichment feature is unlocked and an
 * API key is configured, so it is safe to inject unconditionally.
 *
 * @package SEODirector
 */

namespace SEODirector\Integrations\Serp;

use SEODirector\Analysis\RootCause\SerpProviderInterface;
use SEODirector\Integrations\Http\RetryingHttpClient;
use SEODirector\License\FeatureGate;
use SEODirector\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class SerpApiProvider implements SerpProviderInterface {

	private const ENDPOINT = 'https://serpapi.com/search.json';

	public function __construct(
		private Settings $settings,
		private FeatureGate $gate,
		private RetryingHttpClient $http,
	) {}

	public function serp_context( string $query ): ?array {
		if ( ! $this->gate->allows( 'serp_enrichment' ) ) {
			return null;
		}
		if ( 'serpapi' !== (string) $this->settings->get( 'serp_provider', '' ) ) {
			return null;
		}

		$key = (string) $this->settings->get( 'serp_api_key', '' );
		if ( '' === $key || '' === trim( $query ) ) {
			return null;
		}

		$url = add_query_arg(
			[
				'engine' => 'google',
				'q'      => $query,
				'api_key' => $key,
				'num'    => 10,
			],
			self::ENDPOINT
		);

		$result = $this->http->get( $url, [ 'timeout' => 15 ] );
		if ( ! $result->ok() ) {
			return null;
		}

		$json = $result->json();
		if ( null === $json ) {
			return null;
		}

		return [
			'features'    => $this->features( $json ),
			'top_domains' => $this->top_domains( $json ),
		];
	}

	/**
	 * @param array<string, mixed> $json
	 * @return string[]
	 */
	private function features( array $json ): array {
		$features = [];

		// SerpApi surfaces distinct blocks as top-level keys.
		$map = [
			'ai_overview'          => 'AI overview',
			'answer_box'           => 'Featured snippet',
			'knowledge_graph'      => 'Knowledge panel',
			'related_questions'    => 'People also ask',
			'inline_videos'        => 'Video carousel',
			'inline_images'        => 'Image pack',
			'local_results'        => 'Local pack',
			'shopping_results'     => 'Shopping',
			'ads'                  => 'Ads',
		];

		foreach ( $map as $key => $label ) {
			if ( ! empty( $json[ $key ] ) ) {
				$features[] = $label;
			}
		}

		return $features;
	}

	/**
	 * @param array<string, mixed> $json
	 * @return string[]
	 */
	private function top_domains( array $json ): array {
		$domains = [];

		foreach ( (array) ( $json['organic_results'] ?? [] ) as $organic ) {
			$link = (string) ( $organic['link'] ?? '' );
			$host = '' !== $link ? wp_parse_url( $link, PHP_URL_HOST ) : null;
			if ( is_string( $host ) && '' !== $host ) {
				$domains[] = $host;
			}
			if ( count( $domains ) >= 5 ) {
				break;
			}
		}

		return array_values( array_unique( $domains ) );
	}
}
