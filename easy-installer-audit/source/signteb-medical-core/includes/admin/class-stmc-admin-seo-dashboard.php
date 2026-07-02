<?php
/**
 * SignTeb Medical Core — SEO Dashboard Admin Page
 */
declare( strict_types=1 );
namespace STMC\Admin;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class SeoDashboard {

	public function __construct( private readonly Loader $loader ) {}

	public static function render_page(): void {
		// Collect SEO stats
		$cpts = [ 'doctor', 'medical-service', 'treatment', 'disease', 'clinic' ];
		$stats = [];
		foreach ( $cpts as $cpt ) {
			$count = wp_count_posts( $cpt )->publish;
			$stats[ $cpt ] = $count;
		}

		// Check for pages missing title/excerpt
		$missing_excerpt = get_posts( [
			'post_type'      => $cpts,
			'posts_per_page' => 10,
			'post_status'    => 'publish',
			'meta_query'     => [ [ 'key' => '_stmc_excerpt_missing', 'compare' => 'EXISTS' ] ],
		] );

		?>
		<div class="wrap">
			<h1>🔍 <?php esc_html_e( 'داشبورد SEO', STMC_TEXT ); ?></h1>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px;">
				<?php foreach ( $stats as $cpt => $count ) : ?>
				<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:16px;text-align:center;">
					<div style="font-size:24px;font-weight:800;color:#2271b1;"><?php echo esc_html( number_format_i18n( $count ) ); ?></div>
					<div style="font-size:12px;color:#666;"><?php echo esc_html( get_post_type_object( $cpt )?->labels->name ?? $cpt ); ?></div>
				</div>
				<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'وضعیت Schema', STMC_TEXT ); ?></h2>
			<table class="wp-list-table widefat fixed">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نوع Schema', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'CPT', STMC_TEXT ); ?></th>
						<th><?php esc_html_e( 'وضعیت', STMC_TEXT ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$schemas = [
						[ 'Physician',        'doctor',          '✅' ],
						[ 'MedicalClinic',    'clinic',          '✅' ],
						[ 'MedicalProcedure', 'medical-service', '✅' ],
						[ 'MedicalTherapy',   'treatment',       '✅' ],
						[ 'MedicalCondition', 'disease',         '✅' ],
						[ 'MedicalStudy',     'case-study',      '✅' ],
						[ 'VideoObject',      'medical-video',   '✅' ],
						[ 'FAQPage',          'page / post',     '✅' ],
						[ 'BreadcrumbList',   'همه صفحات',       '✅' ],
						[ 'Organization',     'صفحه اصلی',       '✅' ],
					];
					foreach ( $schemas as [$type, $cpt, $status] ) :
					?>
					<tr>
						<td><code><?php echo esc_html( $type ); ?></code></td>
						<td><?php echo esc_html( $cpt ); ?></td>
						<td><?php echo esc_html( $status ); ?> <?php esc_html_e( 'فعال', STMC_TEXT ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
