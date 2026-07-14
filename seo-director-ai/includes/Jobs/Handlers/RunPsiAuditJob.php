<?php
/**
 * Weekly PageSpeed audits: home page + top-20 traffic pages, both
 * strategies, one URL×strategy per chunk (PSI calls are slow).
 *
 * Cursor: { targets: [{path, hash}], index, strategy_index }
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

use SEODirector\Data\Repository\GscRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiRepository;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\PageSpeedClient;
use SEODirector\Jobs\AbstractChunkedJob;

defined( 'ABSPATH' ) || exit;

final class RunPsiAuditJob extends AbstractChunkedJob {

	public const NAME = 'psi_audit';

	private const STRATEGIES = [ 'mobile', 'desktop' ];
	private const TOP_PAGES  = 20;

	public function __construct(
		JobStateRepository $state,
		private PageSpeedClient $client,
		private PsiRepository $repo,
		private GscRepository $gsc,
		private PropertiesRepository $properties,
		private UrlCanonicalizer $canonicalizer,
	) {
		parent::__construct( $state );
	}

	public function name(): string {
		return self::NAME;
	}

	protected function process_chunk( array $cursor ): ?array {
		if ( empty( $cursor ) ) {
			$cursor = $this->build_targets();
			if ( null === $cursor ) {
				return null;
			}
		}

		$targets = (array) $cursor['targets'];
		$index   = (int) $cursor['index'];
		$s_index = (int) $cursor['strategy_index'];

		if ( $index >= count( $targets ) ) {
			return null;
		}

		$target   = $targets[ $index ];
		$strategy = self::STRATEGIES[ $s_index ];
		$url      = home_url( (string) $target['path'] );

		$audit = $this->client->audit( $url, $strategy );

		// A single failing URL must not park the whole audit run: record and move on.
		if ( ! is_wp_error( $audit ) ) {
			$this->repo->insert( (string) $target['path'], (string) $target['hash'], $strategy, $audit );
		}

		// Advance strategy, then URL.
		if ( $s_index + 1 < count( self::STRATEGIES ) ) {
			$cursor['strategy_index'] = $s_index + 1;
		} else {
			$cursor['strategy_index'] = 0;
			$cursor['index']          = $index + 1;
		}

		return (int) $cursor['index'] >= count( $targets ) ? null : $cursor;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function build_targets(): ?array {
		$paths = [ '/' ];

		$property = $this->properties->active( 'gsc' );
		if ( null !== $property ) {
			$paths = array_merge(
				$paths,
				$this->gsc->top_pages(
					$property['id'],
					gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
					gmdate( 'Y-m-d' ),
					self::TOP_PAGES
				)
			);
		}

		$targets = [];
		foreach ( array_values( array_unique( $paths ) ) as $path ) {
			$targets[] = [
				'path' => $path,
				'hash' => $this->canonicalizer->hash_hex( $path ),
			];
		}

		return [
			'targets'        => $targets,
			'index'          => 0,
			'strategy_index' => 0,
		];
	}
}
