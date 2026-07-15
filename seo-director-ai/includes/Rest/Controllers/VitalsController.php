<?php
/**
 * /sda/v1/metrics/vitals — CWV audit history; POST queues a fresh audit round.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

defined( 'ABSPATH' ) || exit;

use SEODirector\Data\Repository\PsiAuditsRepository;
use SEODirector\Jobs\Handlers\RunPsiAuditJob;
use SEODirector\Jobs\Scheduler;
use WP_Error;
use WP_REST_Response;

final class VitalsController extends BaseController {

	public function __construct(
		private readonly PsiAuditsRepository $audits,
		private readonly Scheduler $scheduler,
	) {}

	public function register(): void {
		register_rest_route(
			$this->ns(),
			'/metrics/vitals',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'queue_audit' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function list(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'home'   => $this->audits->latest_for_page( '/', 2 ),
				'recent' => $this->audits->recent( 20 ),
			)
		);
	}

	public function queue_audit(): WP_REST_Response|WP_Error {
		if ( ! $this->rate_limit( 'psi_audit', 2 ) ) {
			return new WP_Error(
				'sda_rate_limited',
				__( 'An audit round is already queued — results appear as pages complete.', 'seo-director-ai' ),
				array( 'status' => 429 )
			);
		}
		$this->scheduler->enqueue( RunPsiAuditJob::NAME );
		return new WP_REST_Response( array( 'queued' => true ), 202 );
	}
}
