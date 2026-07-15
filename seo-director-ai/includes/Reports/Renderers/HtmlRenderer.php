<?php
/**
 * Branded HTML report renderer — a standalone, print-to-PDF-ready document
 * (no external assets, all styles inlined). Serves as the PDF format without
 * bundling a heavy PDF library; the browser's print dialog produces the PDF.
 * White-label branding overrides are applied when present (Agency).
 *
 * @package SEODirector
 */

namespace SEODirector\Reports\Renderers;

defined( 'ABSPATH' ) || exit;

final class HtmlRenderer implements ReportRendererInterface {

	public function format(): string {
		return 'html';
	}

	public function extension(): string {
		return 'html';
	}

	public function render( array $report ): string {
		/**
		 * Filters report branding (Agency white-label).
		 *
		 * @param array{name: string, color: string} $branding
		 */
		$branding = apply_filters(
			'sda_report_branding',
			[ 'name' => 'SEO Director AI', 'color' => '#2563eb' ]
		);

		$health = $report['health']['score'] ?? null;
		$e      = static fn( $v ) => esc_html( (string) $v );

		ob_start();
		?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $e( $report['site'] ); ?> — <?php echo $e( ucfirst( (string) $report['type'] ) ); ?> SEO Report</title>
<style>
body{font-family:-apple-system,Segoe UI,Roboto,Vazirmatn,sans-serif;color:#1c2430;margin:0;padding:32px;background:#fff}
h1{font-size:22px;margin:0 0 4px}h2{font-size:15px;margin:24px 0 8px;color:<?php echo esc_attr( $branding['color'] ); ?>}
.meta{color:#5b6672;font-size:13px}.tiles{display:flex;gap:16px;margin:16px 0}
.tile{border:1px solid #dfe3e8;border-radius:10px;padding:12px 16px;flex:1}
.tile .n{font-size:26px;font-weight:700}.tile .l{font-size:12px;color:#5b6672}
table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:12px}
th,td{text-align:start;padding:6px 8px;border-bottom:1px solid #eef0f3}
th{font-size:11px;text-transform:uppercase;color:#5b6672}
.summary{background:#f6f7f9;border-radius:10px;padding:12px 16px;font-size:14px}
footer{margin-top:32px;color:#98a2b3;font-size:11px}
</style>
</head>
<body>
<h1><?php echo $e( $branding['name'] ); ?></h1>
<div class="meta"><?php echo $e( $report['site'] ); ?> · <?php echo $e( ucfirst( (string) $report['type'] ) ); ?> report · <?php echo $e( $report['period']['from'] ); ?> → <?php echo $e( $report['period']['to'] ); ?></div>

<div class="tiles">
	<div class="tile"><div class="n"><?php echo null !== $health ? $e( $health ) : '—'; ?></div><div class="l">Health score</div></div>
	<div class="tile"><div class="n"><?php echo $e( number_format_i18n( (int) $report['totals']['clicks'] ) ); ?></div><div class="l">Clicks</div></div>
	<div class="tile"><div class="n"><?php echo $e( number_format_i18n( (int) $report['totals']['impressions'] ) ); ?></div><div class="l">Impressions</div></div>
</div>

		<?php if ( ! empty( $report['summary'] ) ) : ?>
<div class="summary">✦ <?php echo $e( $report['summary'] ); ?></div>
		<?php endif; ?>

		<?php if ( ! empty( $report['winners'] ) ) : ?>
<h2>Top Winners</h2>
<table><tr><th>Keyword</th><th>Clicks</th><th>Δ</th><th>Position</th></tr>
		<?php foreach ( $report['winners'] as $w ) : ?>
<tr><td><?php echo $e( $w['label'] ); ?></td><td><?php echo $e( $w['clicks'] ); ?></td><td>+<?php echo $e( $w['clicks_delta'] ); ?></td><td><?php echo $e( $w['position'] ); ?></td></tr>
		<?php endforeach; ?>
</table>
		<?php endif; ?>

		<?php if ( ! empty( $report['losers'] ) ) : ?>
<h2>Top Losers</h2>
<table><tr><th>Keyword</th><th>Clicks</th><th>Δ</th><th>Priority</th></tr>
		<?php foreach ( $report['losers'] as $l ) : ?>
<tr><td><?php echo $e( $l['label'] ); ?></td><td><?php echo $e( $l['clicks'] ); ?></td><td><?php echo $e( $l['clicks_delta'] ); ?></td><td><?php echo $e( $l['priority'] ); ?></td></tr>
		<?php endforeach; ?>
</table>
		<?php endif; ?>

		<?php if ( ! empty( $report['opportunities'] ) ) : ?>
<h2>Top Opportunities</h2>
<table><tr><th>Opportunity</th><th>Est. gain</th><th>Difficulty</th></tr>
		<?php foreach ( $report['opportunities'] as $o ) : ?>
<tr><td><?php echo $e( $o['label'] ); ?></td><td>+<?php echo $e( number_format_i18n( (int) $o['est_traffic_gain'] ) ); ?>/mo</td><td><?php echo $e( $o['difficulty'] ); ?>/10</td></tr>
		<?php endforeach; ?>
</table>
		<?php endif; ?>

<footer>Generated <?php echo $e( $report['generated_at'] ); ?> · <?php echo $e( $branding['name'] ); ?></footer>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}
}
