<?php
/**
 * SignTeb Medical Core — Admin Menu
 *
 * منوی اصلی پلاگین در پیشخوان وردپرس
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Admin;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'admin_menu', $this, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
	}

	public function register_menu(): void {
		// Main menu
		add_menu_page(
			__( 'SignTeb MedCore', STMC_TEXT ),
			__( 'SignTeb', STMC_TEXT ),
			'manage_options',
			'signteb-medical-core',
			[ $this, 'render_dashboard' ],
			$this->get_menu_icon(),
			2
		);

		// Sub-menus
		$submenus = [
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'داشبورد', STMC_TEXT ),
				'menu_title' => __( 'داشبورد', STMC_TEXT ),
				'capability' => 'manage_options',
				'slug'       => 'signteb-medical-core',
				'callback'   => [ $this, 'render_dashboard' ],
			],
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'نوبت‌ها', STMC_TEXT ),
				'menu_title' => __( 'نوبت‌ها', STMC_TEXT ),
				'capability' => 'edit_posts',
				'slug'       => 'stmc-appointments',
				'callback'   => [ Appointments::class, 'render_page' ],
			],
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'تقویم پزشکان', STMC_TEXT ),
				'menu_title' => __( '🗓️ تقویم', STMC_TEXT ),
				'capability' => 'edit_posts',
				'slug'       => 'stmc-availability',
				'callback'   => [ Availability::class, 'render_page' ],
			],
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'نظرات بیماران', STMC_TEXT ),
				'menu_title' => $this->reviews_menu_title(),
				'capability' => 'edit_posts',
				'slug'       => 'stmc-reviews',
				'callback'   => [ Reviews::class, 'render_page' ],
			],
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'داشبورد SEO', STMC_TEXT ),
				'menu_title' => __( 'SEO', STMC_TEXT ),
				'capability' => 'manage_options',
				'slug'       => 'stmc-seo',
				'callback'   => [ SeoDashboard::class, 'render_page' ],
			],
			[
				'parent'     => 'signteb-medical-core',
				'page_title' => __( 'تنظیمات', STMC_TEXT ),
				'menu_title' => __( 'تنظیمات', STMC_TEXT ),
				'capability' => 'manage_options',
				'slug'       => 'stmc-settings',
				'callback'   => [ Settings::class, 'render_page' ],
			],
		];

		foreach ( $submenus as $item ) {
			add_submenu_page(
				$item['parent'],
				$item['page_title'],
				$item['menu_title'],
				$item['capability'],
				$item['slug'],
				$item['callback']
			);
		}
	}

	public function render_dashboard(): void {
		$appointments_count    = $this->count_appointments( 'pending' );
		$doctors_count         = wp_count_posts( 'doctor' )->publish;
		$services_count        = wp_count_posts( 'medical-service' )->publish;
		$reviews_pending_count = ( new \STMC\Reviews\Repository() )->count_pending();
		?>
		<div class="wrap stmc-admin-dashboard">
			<h1 class="stmc-admin-title">⚕️ <?php esc_html_e( 'SignTeb MedCore — داشبورد', STMC_TEXT ); ?></h1>

			<div class="stmc-admin-stats">
				<div class="stmc-admin-stat-card">
					<div class="stmc-admin-stat-card__icon">📅</div>
					<div class="stmc-admin-stat-card__value"><?php echo esc_html( number_format_i18n( $appointments_count ) ); ?></div>
					<div class="stmc-admin-stat-card__label"><?php esc_html_e( 'نوبت در انتظار', STMC_TEXT ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-appointments' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'مشاهده', STMC_TEXT ); ?>
					</a>
				</div>

				<div class="stmc-admin-stat-card">
					<div class="stmc-admin-stat-card__icon">👨‍⚕️</div>
					<div class="stmc-admin-stat-card__value"><?php echo esc_html( number_format_i18n( $doctors_count ) ); ?></div>
					<div class="stmc-admin-stat-card__label"><?php esc_html_e( 'پروفایل پزشک', STMC_TEXT ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=doctor' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'مدیریت', STMC_TEXT ); ?>
					</a>
				</div>

				<div class="stmc-admin-stat-card">
					<div class="stmc-admin-stat-card__icon">💊</div>
					<div class="stmc-admin-stat-card__value"><?php echo esc_html( number_format_i18n( $services_count ) ); ?></div>
					<div class="stmc-admin-stat-card__label"><?php esc_html_e( 'خدمت پزشکی', STMC_TEXT ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=medical-service' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'مدیریت', STMC_TEXT ); ?>
					</a>
				</div>

				<div class="stmc-admin-stat-card">
					<div class="stmc-admin-stat-card__icon">⭐</div>
					<div class="stmc-admin-stat-card__value"><?php echo esc_html( number_format_i18n( $reviews_pending_count ) ); ?></div>
					<div class="stmc-admin-stat-card__label"><?php esc_html_e( 'نظر در انتظار تأیید', STMC_TEXT ); ?></div>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stmc-reviews&status=pending' ) ); ?>" class="button button-small">
						<?php esc_html_e( 'بررسی', STMC_TEXT ); ?>
					</a>
				</div>
			</div>

			<div class="stmc-admin-version">
				<?php printf( esc_html__( 'SignTeb Medical Core v%s', STMC_TEXT ), esc_html( STMC_VERSION ) ); ?>
				&nbsp;|&nbsp;
				<a href="https://signteb.com/docs" target="_blank" rel="noopener"><?php esc_html_e( 'مستندات', STMC_TEXT ); ?></a>
			</div>
		</div>
		<?php
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'signteb' ) && ! str_contains( $hook, 'stmc' ) ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->admin_styles() );
	}

	private function admin_styles(): string {
		return '
		.stmc-admin-dashboard { max-width:1200px; }
		.stmc-admin-title { font-size:24px; margin-bottom:24px; }
		.stmc-admin-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
		.stmc-admin-stat-card { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.06); }
		.stmc-admin-stat-card__icon { font-size:32px; margin-bottom:8px; }
		.stmc-admin-stat-card__value { font-size:32px; font-weight:800; color:#2271b1; margin-bottom:4px; }
		.stmc-admin-stat-card__label { font-size:13px; color:#666; margin-bottom:12px; }
		.stmc-admin-version { color:#999; font-size:12px; }
		';
	}

	private function count_appointments( string $status ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}stmc_appointments WHERE status=%s",
			$status
		) );
	}

	private function reviews_menu_title(): string {
		$pending = ( new \STMC\Reviews\Repository() )->count_pending();
		$label   = __( 'نظرات', STMC_TEXT );

		if ( $pending > 0 ) {
			return sprintf( '%s <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>', $label, $pending, $pending );
		}

		return $label;
	}

	private function get_menu_icon(): string {
		// Base64 encoded medical cross SVG
		return 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad">'
			. '<path d="M8 2h4v5h5v4h-5v5H8v-5H3V7h5z"/>'
			. '</svg>'
		);
	}
}
