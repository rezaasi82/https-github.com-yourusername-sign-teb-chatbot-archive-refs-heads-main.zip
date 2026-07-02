<?php
/**
 * SignTeb MedCore — Asset Enqueue
 *
 * بارگذاری بهینه CSS و JS
 * - Critical CSS inline در header
 * - Non-critical CSS deferred
 * - JS با defer (بدون render blocking)
 * - Font preload
 *
 * @package SignTeb_MedCore
 */

defined( 'ABSPATH' ) || exit;

class MedCore_Enqueue {

	/** آیا صفحه جاری یک CPT پزشکی است؟ */
	private bool $is_medical_cpt = false;

	public function __construct() {
		add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_frontend' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
		add_action( 'enqueue_block_assets',  [ $this, 'enqueue_block_assets' ] );
		add_action( 'wp_head',               [ $this, 'preload_fonts' ], 2 );
		add_action( 'wp_head',               [ $this, 'inline_critical_css' ], 3 );
		add_filter( 'script_loader_tag',     [ $this, 'add_defer_attribute' ], 10, 2 );
		add_filter( 'style_loader_tag',      [ $this, 'add_media_print_trick' ], 10, 4 );
	}

	// ─── Frontend Assets ──────────────────────────────────────────────────────

	public function enqueue_frontend(): void {
		$ver = MEDCORE_VERSION;
		$uri = MEDCORE_ASSETS;

		// Detect medical CPT context
		$this->is_medical_cpt = is_singular( [ 'doctor', 'medical-service', 'treatment', 'disease', 'clinic' ] )
			|| is_post_type_archive( [ 'doctor', 'medical-service' ] )
			|| is_tax( [ 'specialty', 'location', 'condition', 'treatment-type' ] );

		// ── Main stylesheet ──
		wp_enqueue_style(
			'stmc-main',
			$uri . 'css/main.css',
			[],
			$ver
		);

		// ── RTL stylesheet ──
		if ( is_rtl() ) {
			wp_enqueue_style(
				'stmc-rtl',
				MEDCORE_URI . '/rtl.css',
				[ 'stmc-main' ],
				$ver
			);
		}

		// ── Components (deferred) ──
		wp_enqueue_style(
			'stmc-components',
			$uri . 'css/components.css',
			[ 'stmc-main' ],
			$ver
		);

		// ── Navigation JS ──
		wp_enqueue_script(
			'stmc-navigation',
			$uri . 'js/navigation.js',
			[],
			$ver,
			true // footer
		);

		// ── Main JS (deferred via filter) ──
		wp_enqueue_script(
			'stmc-main',
			$uri . 'js/main.js',
			[ 'stmc-navigation' ],
			$ver,
			true
		);

		// ── Animations JS (only if not reduced motion) ──
		wp_enqueue_script(
			'stmc-animations',
			$uri . 'js/animations.js',
			[ 'stmc-main' ],
			$ver,
			true
		);

		// ── Before/After Slider (only on doctor profiles) ──
		if ( is_singular( 'doctor' ) || is_singular( 'case-study' ) ) {
			wp_enqueue_script(
				'stmc-before-after',
				$uri . 'js/before-after.js',
				[],
				$ver,
				true
			);
		}

		// ── Localize script data ──
		wp_localize_script( 'stmc-main', 'stmcData', $this->get_js_data() );

		// ── Appointment form assets (when shortcode/block is present) ──
		// file_exists() guards prevent enqueuing phantom (404) assets on sites
		// where the appointment-form companion files have not been shipped/added yet.
		if ( $this->has_appointment_form() ) {
			if ( file_exists( MEDCORE_DIR . '/assets/css/appointment-form.css' ) ) {
				wp_enqueue_style(
					'stmc-appointment',
					$uri . 'css/appointment-form.css',
					[ 'stmc-main' ],
					$ver
				);
			}

			if ( file_exists( MEDCORE_DIR . '/assets/js/appointment-form.js' ) ) {
				wp_enqueue_script(
					'stmc-appointment',
					$uri . 'js/appointment-form.js',
					[],
					$ver,
					true
				);
				wp_localize_script( 'stmc-appointment', 'stmcAppointment', [
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'stmc_appointment_nonce' ),
					'i18n'    => [
						'sending'  => __( 'در حال ارسال...', 'signteb-medcore' ),
						'success'  => __( 'درخواست شما ثبت شد. به زودی با شما تماس می‌گیریم.', 'signteb-medcore' ),
						'error'    => __( 'خطا در ارسال. لطفاً مجدداً تلاش کنید.', 'signteb-medcore' ),
						'required' => __( 'لطفاً این فیلد را تکمیل کنید.', 'signteb-medcore' ),
					],
				] );
			}
		}

		// ── Remove jQuery from frontend (unless a plugin needs it) ──
		if ( apply_filters( 'stmc_remove_jquery', true ) ) {
			wp_dequeue_script( 'jquery' );
		}
	}

	// ─── Admin Assets ─────────────────────────────────────────────────────────

	public function enqueue_admin( string $hook ): void {
		// Only load on our CPT edit screens
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$our_cpts = [ 'doctor', 'medical-service', 'treatment', 'disease', 'clinic', 'medical-faq', 'case-study', 'medical-video' ];

		if ( 'post' === $screen->base && in_array( $screen->post_type, $our_cpts, true )
			&& file_exists( MEDCORE_DIR . '/assets/css/admin-meta.css' ) ) {
			wp_enqueue_style(
				'stmc-admin-meta',
				MEDCORE_ASSETS . 'css/admin-meta.css',
				[],
				MEDCORE_VERSION
			);
		}
	}

	// ─── Block Editor Assets ──────────────────────────────────────────────────

	public function enqueue_block_assets(): void {
		if ( is_admin() ) {
			wp_enqueue_style(
				'stmc-editor',
				MEDCORE_ASSETS . 'css/editor.css',
				[ 'wp-edit-blocks' ],
				MEDCORE_VERSION
			);
		}
	}

	// ─── Font Preload ─────────────────────────────────────────────────────────

	public function preload_fonts(): void {
		$fonts = [
			'vazirmatn/Vazirmatn-Regular.woff2',
			'vazirmatn/Vazirmatn-Bold.woff2',
		];

		// Add Inter for LTR
		if ( ! is_rtl() ) {
			$fonts[] = 'inter/Inter-Regular.woff2';
			$fonts[] = 'inter/Inter-SemiBold.woff2';
		}

		foreach ( $fonts as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
				esc_url( MEDCORE_ASSETS . 'fonts/' . $font )
			);
		}
	}

	// ─── Critical CSS (Inline) ────────────────────────────────────────────────

	public function inline_critical_css(): void {
		// Only inline minimal above-the-fold CSS
		// Full critical CSS should be extracted via build tool
		echo '<style id="stmc-critical">';
		echo ':root{--stmc-header-height:72px;}';
		echo '.site-header{height:var(--stmc-header-height);position:sticky;top:0;z-index:200;}';
		echo '.is-loading{opacity:0;transition:opacity .3s ease;}';
		echo '.is-loaded{opacity:1;}';
		echo '</style>' . "\n";
	}

	// ─── Add defer to our JS ──────────────────────────────────────────────────

	public function add_defer_attribute( string $tag, string $handle ): string {
		$defer_handles = [ 'stmc-main', 'stmc-animations', 'stmc-before-after' ];

		if ( in_array( $handle, $defer_handles, true ) ) {
			return str_replace( ' src=', ' defer src=', $tag );
		}

		return $tag;
	}

	// ─── Non-critical CSS via print trick ────────────────────────────────────

	public function add_media_print_trick( string $tag, string $handle, string $href, string $media ): string {
		$non_critical = [ 'stmc-components' ];

		if ( in_array( $handle, $non_critical, true ) && 'all' === $media ) {
			return sprintf(
				'<link rel="stylesheet" id="%s-css" href="%s" media="print" onload="this.media=\'all\'">' . "\n" .
				'<noscript><link rel="stylesheet" href="%s"></noscript>' . "\n",
				esc_attr( $handle ),
				esc_url( $href ),
				esc_url( $href )
			);
		}

		return $tag;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function get_js_data(): array {
		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'siteUrl'   => home_url(),
			'isRtl'     => is_rtl(),
			'isMobile'  => wp_is_mobile(),
			'themeUri'  => MEDCORE_URI,
			'nonce'     => wp_create_nonce( 'stmc_frontend_nonce' ),
			'whatsapp'  => get_theme_mod( 'stmc_whatsapp', '' ),
			'i18n'      => [
				'openMenu'  => __( 'باز کردن منو', 'signteb-medcore' ),
				'closeMenu' => __( 'بستن منو', 'signteb-medcore' ),
				'loading'   => __( 'در حال بارگذاری...', 'signteb-medcore' ),
			],
		];
	}

	private function has_appointment_form(): bool {
		global $post;
		if ( ! $post ) {
			return false;
		}
		// Check for block or shortcode
		return has_block( 'signteb/appointment-cta', $post )
			|| has_shortcode( $post->post_content, 'stmc_appointment' )
			|| is_singular( 'doctor' ); // Doctor profiles always have appointment form
	}
}

// Instantiate
new MedCore_Enqueue();
