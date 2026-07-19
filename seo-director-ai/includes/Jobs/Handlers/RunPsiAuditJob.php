<?php
/**
 * Weekly Core Web Vitals audit queue: home page + top GSC pages,
 * one page × strategy per chunk (PSI calls are slow — keep chunks tiny).
 *
 * @package SEODirector
 */

namespace SEODirector\Jobs\Handlers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\GscPageDailyRepository;
use SEODirector\Data\Repository\JobStateRepository;
use SEODirector\Data\Repository\PropertiesRepository;
use SEODirector\Data\Repository\PsiAuditsRepository;
use SEODirector\Data\UrlCanonicalizer;
use SEODirector\Integrations\Google\PageSpeedClient;
use SEODirector\Jobs\JobResult;

final class RunPsiAuditJob {

	public const NAME = 'run_psi_audit';

	private const MAX_PAGES = 10;

	public function __construct(
		private readonly PageSpeedClient $psi,
		private readonly PropertiesRepository $properties,
		private readonly GscPageDailyRepository $pages,
		private readonly PsiAuditsRepository $audits,
		private readonly JobStateRepository $job_state,
		private readonly UrlCanonicalizer $canonicalizer,
	) {}

	public function run_chunk(): JobResult {
		$state  = $this->job_state->get( self::NAME );
		$cursor = $state['cursor'];

		if ( empty( $cursor['queue'] ) && ! isset( $cursor['index'] ) ) {
			$cursor = array(
				'queue' => $this->build_queue(),
				'index' => 0,
			);
		}

		$queue = (array) $cursor['queue'];
		$index = (int) $cursor['index'];

		if ( $index >= count( $queue ) ) {
			$this->job_state->save( self::NAME, 'done', array() );
			return JobResult::done();
		}

		$this->job_state->save( self::NAME, 'running', $cursor );

		[ $page_path, $strategy ] = $queue[ $index ];
		$result                   = $this->psi->audit( $this->canonicalizer->to_absolute( $page_path ), $strategy );

		if ( is_wp_error( $result ) ) {
			// Quota/breaker errors abort the run; per-page errors skip forward.
			if ( 'sda_quota' === $result->get_error_code() ) {
				$this->job_state->save( self::NAME, 'failed', $cursor, $result->get_error_message() );
				return JobResult::failed();
			}
		} else {
			$this->audits->insert( $page_path, $strategy, $result );
		}

		$cursor['index'] = $index + 1;
		if ( $cursor['index'] >= count( $queue ) ) {
			$this->job_state->save( self::NAME, 'done', array() );
			return JobResult::done();
		}
		$this->job_state->save( self::NAME, 'running', $cursor );
		return JobResult::more();
	}

	/**
	 * Home page + top clicked pages, mobile + desktop each.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private function build_queue(): array {
		$paths    = array( '/' );
		$property = $this->properties->active_property( 'gsc' );
		if ( $property ) {
			$to   = gmdate( 'Y-m-d' );
			$from = gmdate( 'Y-m-d', strtotime( '-28 days' ) );
			foreach ( $this->pages->top_pages( (int) $property->id, $from, $to, self::MAX_PAGES ) as $row ) {
				$paths[] = (string) $row['page_path'];
			}
		}
		$paths = array_slice( array_unique( $paths ), 0, self::MAX_PAGES );

		$queue = array();
		foreach ( $paths as $path ) {
			$queue[] = array( $path, 'mobile' );
			$queue[] = array( $path, 'desktop' );
		}
		return $queue;
	}
}
