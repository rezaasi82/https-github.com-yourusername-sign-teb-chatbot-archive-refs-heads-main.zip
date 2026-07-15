<?php
/**
 * Builds a report, renders it to the requested formats, writes the artifacts
 * to a protected uploads directory, and records the report row. Format
 * availability is gated: CSV/HTML in every edition, all formats in PRO.
 *
 * @package SEODirector
 */

namespace SEODirector\Reports;

use SEODirector\Data\Repository\ReportsRepository;
use SEODirector\License\FeatureGate;
use SEODirector\Reports\Renderers\ReportRendererInterface;

defined( 'ABSPATH' ) || exit;

final class ReportGenerator {

	private const DIR = 'sda-reports';

	/**
	 * @param array<string, ReportRendererInterface> $renderers Keyed by format.
	 */
	public function __construct(
		private ReportBuilder $builder,
		private array $renderers,
		private ReportsRepository $reports,
		private FeatureGate $gate,
	) {}

	/**
	 * @param 'weekly'|'monthly'|'quarterly' $type
	 * @param string[]                       $formats Requested formats.
	 * @return array{id: int, files: array<string, string>}
	 */
	public function generate( string $type, array $formats, string $status = 'sent' ): array {
		$report = $this->builder->build( $type );
		$allow  = $this->gate->allows( 'reports_all_formats' ) ? array_keys( $this->renderers ) : [ 'html', 'pdf', 'csv' ];

		$dir  = $this->ensure_dir();
		$slug = $type . '-' . gmdate( 'Ymd-His' );
		$files = [];

		foreach ( $formats as $format ) {
			if ( ! isset( $this->renderers[ $format ] ) || ! in_array( $format, $allow, true ) ) {
				continue;
			}
			$renderer = $this->renderers[ $format ];
			$path     = trailingslashit( $dir ) . $slug . '.' . $renderer->extension();
			file_put_contents( $path, $renderer->render( $report ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$files[ $renderer->format() ] = $path;
		}

		$id = $this->reports->insert( $type, $report['period']['from'], $report['period']['to'], $files, $status );

		/**
		 * Fires after a report is generated.
		 *
		 * @param int                  $id     Report row id.
		 * @param array<string, mixed> $report Report data.
		 */
		do_action( 'sda_report_generated', $id, $report );

		return [ 'id' => $id, 'files' => $files ];
	}

	private function ensure_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::DIR;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Deny direct web access to generated reports.
			file_put_contents( $dir . '/.htaccess', "Require all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}
}
