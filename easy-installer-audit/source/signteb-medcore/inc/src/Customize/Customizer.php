<?php
/**
 * SignTeb MedCore — Customizer (فاز ۵)
 *
 * پنل و کنترل‌های Customizer را از روی رجیستری DesignTokens می‌سازد (منبع واحد)،
 * خروجی CSS زنده را به <head> وصل می‌کند، و پیش‌نمایش زنده (postMessage) را
 * فراهم می‌کند. جایگزین MedCore_Customizer قدیمی که خروجی CSS نداشت.
 *
 * @package SignTeb_MedCore
 */

declare( strict_types=1 );

namespace SignTeb\MedCore\Customize;

defined( 'ABSPATH' ) || exit;

final class Customizer {

	private DesignTokens $tokens;

	public function __construct( ?DesignTokens $tokens = null ) {
		$this->tokens = $tokens ?? new DesignTokens();
	}

	public function register_hooks(): void {
		add_action( 'customize_register',     [ $this, 'register' ] );
		add_action( 'wp_head',                [ $this->tokens, 'output_css' ], 100 );
		add_action( 'customize_preview_init', [ $this, 'preview_js' ] );
	}

	public function register( \WP_Customize_Manager $wp_customize ): void {
		$wp_customize->add_panel( 'stmc_panel', [
			'title'    => __( 'SignTeb MedCore', 'signteb-medcore' ),
			'priority' => 30,
		] );

		// سکشن‌ها
		foreach ( $this->tokens->sections() as $id => $sec ) {
			$wp_customize->add_section( $id, [
				'title'    => $sec['title'],
				'panel'    => 'stmc_panel',
				'priority' => $sec['priority'],
			] );
		}

		// کنترل‌ها از روی توکن‌ها
		foreach ( $this->tokens->tokens() as $id => $t ) {
			$wp_customize->add_setting( $id, [
				'default'           => $t['default'],
				'sanitize_callback' => $this->sanitizer( $t ),
				'transport'         => 'postMessage',
			] );

			if ( 'color' === $t['type'] ) {
				$wp_customize->add_control( new \WP_Customize_Color_Control( $wp_customize, $id, [
					'label'   => $t['label'],
					'section' => $t['section'],
				] ) );
			} elseif ( 'select' === $t['type'] ) {
				$wp_customize->add_control( $id, [
					'type'    => 'select',
					'label'   => $t['label'],
					'section' => $t['section'],
					'choices' => $t['choices'],
				] );
			} elseif ( 'range' === $t['type'] ) {
				$wp_customize->add_control( $id, [
					'type'        => 'range',
					'label'       => $t['label'],
					'section'     => $t['section'],
					'input_attrs' => [
						'min'  => $t['min'],
						'max'  => $t['max'],
						'step' => 1,
					],
				] );
			}
		}

		// ── سکشن تماس (انتقال از Customizer قدیمی) ──────────────────────────────
		$wp_customize->add_section( 'stmc_sec_contact', [
			'title'    => __( 'اطلاعات تماس', 'signteb-medcore' ),
			'panel'    => 'stmc_panel',
			'priority' => 50,
		] );
		foreach ( [ 'stmc_whatsapp' => __( 'شماره WhatsApp', 'signteb-medcore' ), 'stmc_phone' => __( 'تلفن', 'signteb-medcore' ) ] as $id => $label ) {
			$wp_customize->add_setting( $id, [
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			] );
			$wp_customize->add_control( $id, [ 'type' => 'text', 'label' => $label, 'section' => 'stmc_sec_contact' ] );
		}

		// پشتیبانی از پیش‌نمایش انتخابی (selective refresh) در دسترس بودن
		if ( isset( $wp_customize->selective_refresh ) ) {
			$wp_customize->get_setting( 'stmc_color_primary' )->transport = 'postMessage';
		}
	}

	/** انتخاب sanitizer مناسب هر نوع توکن. */
	private function sanitizer( array $t ): callable {
		return match ( $t['type'] ) {
			'color'  => 'sanitize_hex_color',
			'select' => static fn( $v ) => array_key_exists( $v, $t['choices'] ) ? $v : $t['default'],
			'range'  => static fn( $v ) => max( (int) $t['min'], min( (int) $t['max'], (int) $v ) ),
			default  => 'sanitize_text_field',
		};
	}

	public function preview_js(): void {
		wp_enqueue_script(
			'stmc-customizer-preview',
			MEDCORE_ASSETS . 'js/customizer-preview.js',
			[ 'customize-preview', 'jquery' ],
			MEDCORE_VERSION,
			true
		);
		// انتقال نگاشت توکن→CSS var به JS برای پیش‌نمایش زنده.
		$map = [];
		foreach ( $this->tokens->tokens() as $id => $t ) {
			$map[ $id ] = [
				'css'  => $t['css'],
				'type' => $t['type'],
				'unit' => $t['unit'] ?? '',
				'map'  => $t['map'] ?? null,
			];
		}
		wp_localize_script( 'stmc-customizer-preview', 'stmcTokens', $map );
	}
}
