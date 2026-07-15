<?php
/**
 * Contract for report renderers. Add a format (XLSX, Google Sheets…) by
 * implementing this interface and registering it — no core change needed.
 *
 * @package SEODirector
 */

namespace SEODirector\Reports\Renderers;

defined( 'ABSPATH' ) || exit;

interface ReportRendererInterface {

	/**
	 * Format slug: csv | html | pdf | xlsx.
	 */
	public function format(): string;

	/**
	 * File extension for the rendered artifact.
	 */
	public function extension(): string;

	/**
	 * Render the report data to a string artifact.
	 *
	 * @param array<string, mixed> $report
	 */
	public function render( array $report ): string;
}
