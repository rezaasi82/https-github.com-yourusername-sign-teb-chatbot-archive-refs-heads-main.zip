<?php
/**
 * SignTeb Setup Wizard — Controller
 *
 * مدیریت:
 * - ثبت صفحه admin
 * - AJAX handlers
 * - ذخیره هر step
 * - پیشرفت Wizard
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard;

defined( 'ABSPATH' ) || exit;

final class Controller {

	/** ۶ مرحله wizard */
	private const STEPS = [
		'welcome'  => [ 'label' => 'خوش آمدید',   'icon' => '👋' ],
		'brand'    => [ 'label' => 'برند و هویت',  'icon' => '🎨' ],
		'clinic'   => [ 'label' => 'اطلاعات کلینیک','icon' => '🏥' ],
		'contact'  => [ 'label' => 'تماس و شبکه‌ها','icon' => '📞' ],
		'demo'     => [ 'label' => 'انتخاب دمو',   'icon' => '🖼️' ],
		'finish'   => [ 'label' => 'پایان',         'icon' => '🎉' ],
	];

	public function boot(): void {
		add_action( 'admin_menu',                   [ $this, 'register_page' ] );
		add_action( 'admin_enqueue_scripts',        [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_stwiz_save_step',      [ $this, 'ajax_save_step' ] );
		add_action( 'wp_ajax_stwiz_import_demo',    [ $this, 'ajax_import_demo' ] );
		add_action( 'wp_ajax_stwiz_check_plugins',  [ $this, 'ajax_check_plugins' ] );
		add_action( 'wp_ajax_stwiz_reset',          [ $this, 'ajax_reset' ] );
	}

	// ─── Admin Page ───────────────────────────────────────────────────────────

	public function register_page(): void {
		add_menu_page(
			__( 'راه‌اندازی SignTeb', STWIZ_TEXT ),
			__( '🪄 Setup Wizard', STWIZ_TEXT ),
			'manage_options',
			'signteb-wizard',
			[ $this, 'render_wizard' ],
			'',
			1
		);
	}

	// ─── Render ───────────────────────────────────────────────────────────────

	public function render_wizard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیر مجاز', STWIZ_TEXT ) );
		}

		$current_step = sanitize_key( $_GET['step'] ?? 'welcome' );
		if ( ! array_key_exists( $current_step, self::STEPS ) ) {
			$current_step = 'welcome';
		}

		$steps        = self::STEPS;
		$step_keys    = array_keys( $steps );
		$step_index   = array_search( $current_step, $step_keys, true );
		$progress_pct = (int) ( ( ( $step_index ) / ( count( $steps ) - 1 ) ) * 100 );

		// Load step file
		$step_file = STWIZ_DIR . 'steps/step-' . $current_step . '.php';

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?> dir="rtl">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'راه‌اندازی SignTeb MedCore', STWIZ_TEXT ); ?></title>
			<?php wp_print_styles( 'stwiz-wizard' ); ?>
		</head>
		<body class="stwiz-body">

		<div class="stwiz-wrap">

			<!-- ── Sidebar ───────────────────────────────────────── -->
			<aside class="stwiz-sidebar">
				<div class="stwiz-sidebar__brand">
					<div class="stwiz-sidebar__logo">⚕️</div>
					<div>
						<div class="stwiz-sidebar__title">SignTeb MedCore</div>
						<div class="stwiz-sidebar__sub">Setup Wizard v<?php echo STWIZ_VERSION; ?></div>
					</div>
				</div>

				<nav class="stwiz-steps-nav" aria-label="<?php esc_attr_e( 'مراحل راه‌اندازی', STWIZ_TEXT ); ?>">
					<?php foreach ( $steps as $key => $step ) :
						$s_index = array_search( $key, $step_keys, true );
						$is_done = $s_index < $step_index;
						$is_cur  = $key === $current_step;
						$is_lock = $s_index > $step_index;
						$cls     = $is_done ? 'done' : ( $is_cur ? 'active' : 'locked' );
					?>
					<div class="stwiz-step-item stwiz-step-item--<?php echo $cls; ?>">
						<div class="stwiz-step-item__icon" aria-hidden="true">
							<?php if ( $is_done ) echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
							      else echo $step['icon']; ?>
						</div>
						<div class="stwiz-step-item__label"><?php echo esc_html( $step['label'] ); ?></div>
						<div class="stwiz-step-item__num"><?php echo $s_index + 1; ?></div>
					</div>
					<?php endforeach; ?>
				</nav>

				<!-- Progress -->
				<div class="stwiz-progress">
					<div class="stwiz-progress__bar">
						<div class="stwiz-progress__fill" style="width:<?php echo $progress_pct; ?>%"></div>
					</div>
					<div class="stwiz-progress__label"><?php echo $progress_pct; ?>% <?php esc_html_e( 'تکمیل شده', STWIZ_TEXT ); ?></div>
				</div>

				<a href="<?php echo esc_url( admin_url() ); ?>" class="stwiz-sidebar__skip">
					<?php esc_html_e( 'بعداً راه‌اندازی می‌کنم ←', STWIZ_TEXT ); ?>
				</a>
			</aside>

			<!-- ── Main Content ──────────────────────────────────── -->
			<main class="stwiz-main" role="main">

				<!-- Header strip -->
				<header class="stwiz-main__header">
					<div class="stwiz-breadcrumb">
						<?php echo esc_html( $steps[ $current_step ]['icon'] ); ?>
						<span><?php echo esc_html( $steps[ $current_step ]['label'] ); ?></span>
					</div>
					<div class="stwiz-step-counter">
						<?php printf(
							esc_html__( 'مرحله %1$d از %2$d', STWIZ_TEXT ),
							$step_index + 1,
							count( $steps )
						); ?>
					</div>
				</header>

				<!-- Step content -->
				<div class="stwiz-step-content" id="stwiz-content">
					<?php
					if ( file_exists( $step_file ) ) {
						include $step_file;
					} else {
						echo '<p class="stwiz-missing">' . esc_html__( 'فایل این مرحله یافت نشد.', STWIZ_TEXT ) . '</p>';
					}
					?>
				</div>

				<!-- Footer nav -->
				<footer class="stwiz-main__footer">
					<?php if ( $step_index > 0 ) :
						$prev_key = $step_keys[ $step_index - 1 ];
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=signteb-wizard&step=' . $prev_key ) ); ?>" class="stwiz-btn stwiz-btn--ghost">
						← <?php esc_html_e( 'مرحله قبل', STWIZ_TEXT ); ?>
					</a>
					<?php else : ?>
					<span></span>
					<?php endif; ?>

					<?php if ( $step_index < count( $steps ) - 1 ) :
						$next_key = $step_keys[ $step_index + 1 ];
					?>
					<button type="button" class="stwiz-btn stwiz-btn--primary stwiz-next-btn"
						data-step="<?php echo esc_attr( $current_step ); ?>"
						data-next="<?php echo esc_url( admin_url( 'admin.php?page=signteb-wizard&step=' . $next_key ) ); ?>"
					>
						<?php esc_html_e( 'مرحله بعد', STWIZ_TEXT ); ?> →
					</button>
					<?php else : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>" class="stwiz-btn stwiz-btn--gold">
						🚀 <?php esc_html_e( 'ورود به پیشخوان', STWIZ_TEXT ); ?>
					</a>
					<?php endif; ?>
				</footer>

			</main>
		</div>

		<?php wp_print_scripts( 'stwiz-wizard' ); ?>
		<script>
		var stWizData = {
			ajaxUrl: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			nonce:   '<?php echo wp_create_nonce( 'stwiz_nonce' ); ?>',
			step:    '<?php echo esc_js( $current_step ); ?>'
		};
		</script>

		</body>
		</html>
		<?php
	}

	// ─── Assets ───────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'signteb-wizard' ) ) {
			return;
		}

		wp_enqueue_style(
			'stwiz-wizard',
			STWIZ_URI . 'assets/css/wizard.css',
			[],
			STWIZ_VERSION
		);

		wp_enqueue_script(
			'stwiz-wizard',
			STWIZ_URI . 'assets/js/wizard.js',
			[],
			STWIZ_VERSION,
			true
		);
	}

	// ─── AJAX: Save Step Data ─────────────────────────────────────────────────

	public function ajax_save_step(): void {
		check_ajax_referer( 'stwiz_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$step = sanitize_key( $_POST['step'] ?? '' );
		$data = $_POST['data'] ?? [];

		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => 'Invalid data' ] );
		}

		switch ( $step ) {

			case 'brand':
				$this->save_brand_step( $data );
				break;

			case 'clinic':
				$this->save_clinic_step( $data );
				break;

			case 'contact':
				$this->save_contact_step( $data );
				break;

			default:
				// Store raw for other steps
				update_option( 'stwiz_step_' . $step, array_map( 'sanitize_text_field', $data ) );
		}

		// Mark step as complete
		$completed = (array) get_option( 'stwiz_completed_steps', [] );
		$completed[] = $step;
		update_option( 'stwiz_completed_steps', array_unique( $completed ) );

		wp_send_json_success( [ 'message' => __( 'ذخیره شد.', STWIZ_TEXT ) ] );
	}

	// ─── AJAX: Import Demo ────────────────────────────────────────────────────

	public function ajax_import_demo(): void {
		check_ajax_referer( 'stwiz_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$demo_type = sanitize_key( $_POST['demo'] ?? 'solo-doctor' );

		// Demo importer
		require_once STWIZ_DIR . 'includes/class-wizard-demo-importer.php';
		$importer = new DemoImporter();
		$result   = $importer->import( $demo_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message'  => __( 'دمو با موفقیت نصب شد.', STWIZ_TEXT ),
			'redirect' => home_url( '/' ),
		] );
	}

	// ─── AJAX: Check Plugins ──────────────────────────────────────────────────

	public function ajax_check_plugins(): void {
		check_ajax_referer( 'stwiz_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$required = [
			'signteb-medical-core/signteb-medical-core.php' => [
				'name'   => 'SignTeb Medical Core',
				'active' => is_plugin_active( 'signteb-medical-core/signteb-medical-core.php' ),
			],
			'signteb-blocks/signteb-blocks.php' => [
				'name'   => 'SignTeb Medical Blocks',
				'active' => is_plugin_active( 'signteb-blocks/signteb-blocks.php' ),
			],
		];

		$optional = [
			'yoast-seo/wp-seo.php' => [
				'name'   => 'Yoast SEO',
				'active' => is_plugin_active( 'yoast-seo/wp-seo.php' ),
			],
			'contact-form-7/wp-contact-form-7.php' => [
				'name'   => 'Contact Form 7',
				'active' => is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
			],
			'polylang/polylang.php' => [
				'name'   => 'Polylang',
				'active' => is_plugin_active( 'polylang/polylang.php' ),
			],
		];

		wp_send_json_success( compact( 'required', 'optional' ) );
	}

	// ─── AJAX: Reset ──────────────────────────────────────────────────────────

	public function ajax_reset(): void {
		check_ajax_referer( 'stwiz_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		delete_option( 'stwiz_completed_steps' );
		foreach ( array_keys( self::STEPS ) as $step ) {
			delete_option( 'stwiz_step_' . $step );
		}

		wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=signteb-wizard&step=welcome' ) ] );
	}

	// ─── Save Helpers ─────────────────────────────────────────────────────────

	private function save_brand_step( array $data ): void {
		$site_name = sanitize_text_field( $data['site_name'] ?? '' );
		$tagline   = sanitize_text_field( $data['tagline']   ?? '' );

		if ( $site_name ) {
			update_option( 'blogname',        $site_name );
			update_option( 'stmc_clinic_name', $site_name );
		}
		if ( $tagline ) {
			update_option( 'blogdescription', $tagline );
		}

		// Colors
		$primary = sanitize_hex_color( $data['primary_color'] ?? '' );
		if ( $primary ) {
			update_option( 'stmc_brand_primary', $primary );
		}

		update_option( 'stwiz_step_brand', [
			'site_name'     => $site_name,
			'tagline'       => $tagline,
			'primary_color' => $primary,
		] );
	}

	private function save_clinic_step( array $data ): void {
		$fields = [
			'stmc_clinic_name'    => 'clinic_name',
			'stmc_clinic_phone'   => 'phone',
			'stmc_clinic_email'   => 'email',
			'stmc_clinic_address' => 'address',
			'stmc_country_code'   => 'country',
			'stmc_geo_placename'  => 'city',
		];

		foreach ( $fields as $option_key => $data_key ) {
			$val = sanitize_text_field( $data[ $data_key ] ?? '' );
			if ( $val ) {
				update_option( $option_key, $val );
			}
		}
	}

	private function save_contact_step( array $data ): void {
		$fields = [
			'stmc_clinic_whatsapp'   => 'whatsapp',
			'stmc_clinic_phone'      => 'phone',
			'stmc_social_instagram'  => 'instagram',
			'stmc_social_linkedin'   => 'linkedin',
			'stmc_social_youtube'    => 'youtube',
			'stmc_appointment_email' => 'appointment_email',
		];

		foreach ( $fields as $option_key => $data_key ) {
			$val = sanitize_text_field( $data[ $data_key ] ?? '' );
			if ( $val ) {
				update_option( $option_key, $val );
			}
		}
	}
}
