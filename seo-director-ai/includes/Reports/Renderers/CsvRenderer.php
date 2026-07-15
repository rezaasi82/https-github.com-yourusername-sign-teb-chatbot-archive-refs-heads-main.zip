<?php
/**
 * CSV report renderer — one flat file with sectioned rows. No external deps.
 *
 * @package SEODirector
 */

namespace SEODirector\Reports\Renderers;

defined( 'ABSPATH' ) || exit;

final class CsvRenderer implements ReportRendererInterface {

	public function format(): string {
		return 'csv';
	}

	public function extension(): string {
		return 'csv';
	}

	public function render( array $report ): string {
		$handle = fopen( 'php://temp', 'r+' );

		fputcsv( $handle, [ 'SEO Director report', $report['site'], $report['type'] ] );
		fputcsv( $handle, [ 'Period', $report['period']['from'], $report['period']['to'] ] );
		fputcsv( $handle, [ 'Health score', $report['health']['score'] ?? 'n/a' ] );
		fputcsv( $handle, [ 'Clicks', $report['totals']['clicks'], 'Impressions', $report['totals']['impressions'] ] );
		fputcsv( $handle, [] );

		fputcsv( $handle, [ 'Winners', 'Clicks', 'Delta', 'Position', 'Reason' ] );
		foreach ( (array) $report['winners'] as $w ) {
			fputcsv( $handle, [ $w['label'], $w['clicks'], $w['clicks_delta'], $w['position'], $w['reason'] ] );
		}
		fputcsv( $handle, [] );

		fputcsv( $handle, [ 'Losers', 'Clicks', 'Delta', 'Position', 'Cause', 'Priority' ] );
		foreach ( (array) $report['losers'] as $l ) {
			fputcsv( $handle, [ $l['label'], $l['clicks'], $l['clicks_delta'], $l['position'], $l['cause'], $l['priority'] ] );
		}
		fputcsv( $handle, [] );

		fputcsv( $handle, [ 'Opportunities', 'Type', 'Detector', 'Est. gain', 'Difficulty', 'Score' ] );
		foreach ( (array) $report['opportunities'] as $o ) {
			fputcsv( $handle, [ $o['label'], $o['entity_type'], $o['detector'], $o['est_traffic_gain'], $o['difficulty'], $o['score'] ] );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return (string) $csv;
	}
}
