<?php
/**
 * GET /sda/v1/reports — list generated reports.
 * POST /sda/v1/reports — generate one now.
 * GET /sda/v1/reports/{id}/download?format=csv — stream an artifact.
 *
 * @package SEODirector
 */

namespace SEODirector\Rest\Controllers;

use SEODirector\Core\Capabilities;
use SEODirector\Data\Repository\ReportsRepository;
use SEODirector\Reports\ReportGenerator;

defined( 'ABSPATH' ) || exit;

final class ReportsController extends AbstractController {

	private const MIME = [
		'csv'  => 'text/csv',
		'html' => 'text/html',
		'pdf'  => 'application/pdf',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	];

	public function __construct(
		private ReportsRepository $reports,
		private ReportGenerator $generator,
	) {}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/reports',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => fn() => rest_ensure_response( [ 'items' => $this->reports->list() ] ),
					'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'generate' ],
					'permission_callback' => $this->require_cap( Capabilities::MANAGE ),
					'args'                => [
						'type'    => [ 'type' => 'string', 'default' => 'weekly', 'enum' => [ 'weekly', 'monthly', 'quarterly' ] ],
						'formats' => [ 'type' => 'array', 'default' => [ 'html', 'csv' ] ],
					],
				],
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/reports/(?P<id>\d+)/download',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'download' ],
				'permission_callback' => $this->require_cap( Capabilities::VIEW_REPORTS ),
				'args'                => [
					'format' => [ 'type' => 'string', 'default' => 'html' ],
				],
			]
		);
	}

	public function generate( \WP_REST_Request $request ): \WP_REST_Response {
		$formats = array_map( 'sanitize_key', (array) $request->get_param( 'formats' ) );
		$this->generator->generate( (string) $request->get_param( 'type' ), $formats, 'sent' );

		return rest_ensure_response( [ 'items' => $this->reports->list() ] );
	}

	public function download( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$files = $this->reports->files( (int) $request->get_param( 'id' ) );
		if ( null === $files ) {
			return new \WP_Error( 'sda_report', __( 'Report not found.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		$format = sanitize_key( (string) $request->get_param( 'format' ) );
		$path   = $files['format_path'][ $format ] ?? null;
		if ( null === $path || ! is_readable( $path ) ) {
			return new \WP_Error( 'sda_report_file', __( 'That format is not available for this report.', 'seo-director-ai' ), [ 'status' => 404 ] );
		}

		header( 'Content-Type: ' . ( self::MIME[ $format ] ?? 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
