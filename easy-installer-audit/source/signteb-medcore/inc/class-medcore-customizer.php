<?php
/**
 * SignTeb MedCore — Customizer
 * تنظیمات قابل تغییر از طریق Customize > پیش‌نمایش زنده
 */
defined( 'ABSPATH' ) || exit;

class MedCore_Customizer {

	public function __construct() {
		add_action( 'customize_register', [ $this, 'register' ] );
		add_action( 'customize_preview_init', [ $this, 'preview_js' ] );
	}

	public function register( \WP_Customize_Manager $wp_customize ): void {

		// ── Panel ──────────────────────────────────────────────────────────────
		$wp_customize->add_panel( 'stmc_panel', [
			'title'    => __( 'SignTeb MedCore', 'signteb-medcore' ),
			'priority' => 30,
		] );

		// ── Section: Contact ──────────────────────────────────────────────────
		$wp_customize->add_section( 'stmc_contact', [
			'title'    => __( 'اطلاعات تماس', 'signteb-medcore' ),
			'panel'    => 'stmc_panel',
			'priority' => 10,
		] );

		$contact_settings = [
			'stmc_whatsapp' => [
				'label'       => __( 'شماره WhatsApp', 'signteb-medcore' ),
				'description' => __( 'با کد کشور — مثال: 989191182649', 'signteb-medcore' ),
				'type'        => 'text',
			],
			'stmc_phone' => [
				'label' => __( 'تلفن', 'signteb-medcore' ),
				'type'  => 'text',
			],
		];

		foreach ( $contact_settings as $key => $args ) {
			$wp_customize->add_setting( $key, [
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			] );
			$wp_customize->add_control( $key, array_merge( [ 'section' => 'stmc_contact' ], $args ) );
		}

		// ── Section: Brand ────────────────────────────────────────────────────
		$wp_customize->add_section( 'stmc_brand', [
			'title'    => __( 'برند و رنگ‌ها', 'signteb-medcore' ),
			'panel'    => 'stmc_panel',
			'priority' => 20,
		] );

		$wp_customize->add_setting( 'stmc_primary_color', [
			'default'           => '#1a56db',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'postMessage',
		] );

		$wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'stmc_primary_color', [
			'label'   => __( 'رنگ اصلی برند', 'signteb-medcore' ),
			'section' => 'stmc_brand',
		] ) );

		$wp_customize->add_setting( 'stmc_gold_color', [
			'default'           => '#C9A84C',
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'postMessage',
		] );

		$wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, 'stmc_gold_color', [
			'label'   => __( 'رنگ طلایی VIP', 'signteb-medcore' ),
			'section' => 'stmc_brand',
		] ) );
	}

	public function preview_js(): void {
		if ( ! file_exists( MEDCORE_DIR . '/assets/js/customizer-preview.js' ) ) {
			return;
		}
		wp_enqueue_script(
			'stmc-customizer-preview',
			MEDCORE_ASSETS . 'js/customizer-preview.js',
			[ 'customize-preview' ],
			MEDCORE_VERSION,
			true
		);
	}
}

new MedCore_Customizer();
