<?php
/**
 * SignTeb MedCore — Theme Setup
 *
 * add_theme_support، register_nav_menus، add_image_size
 * فقط مقداردهی اولیه — بدون render و بدون business logic
 *
 * @package SignTeb_MedCore
 */

defined( 'ABSPATH' ) || exit;

class MedCore_Setup {

	public function __construct() {
		add_action( 'after_setup_theme',  [ $this, 'theme_supports' ] );
		add_action( 'after_setup_theme',  [ $this, 'register_menus' ] );
		add_action( 'after_setup_theme',  [ $this, 'register_image_sizes' ] );
		add_action( 'widgets_init',       [ $this, 'register_sidebars' ] );
		add_filter( 'body_class',         [ $this, 'body_classes' ] );
		add_filter( 'excerpt_length',     [ $this, 'excerpt_length' ] );
		add_filter( 'excerpt_more',       [ $this, 'excerpt_more' ] );
		add_filter( 'wp_resource_hints',  [ $this, 'preconnect_fonts' ], 10, 2 );
	}

	// ─── Theme Supports ───────────────────────────────────────────────────────

	public function theme_supports(): void {
		// Content
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'post-formats', [ 'video', 'gallery', 'quote' ] );
		add_theme_support( 'html5', [
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		] );

		// Editor / Gutenberg
		add_theme_support( 'block-templates' );
		add_theme_support( 'block-template-parts' );
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/editor.css' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'custom-spacing' );
		add_theme_support( 'custom-line-height' );
		add_theme_support( 'appearance-tools' );

		// Disable core color / font UI — all managed via theme.json
		add_theme_support( 'disable-custom-colors' );
		add_theme_support( 'disable-custom-gradients' );
		add_theme_support( 'disable-custom-font-sizes' );

		// Logo & Header
		add_theme_support( 'custom-logo', [
			'width'       => 320,
			'height'      => 80,
			'flex-width'  => true,
			'flex-height' => true,
			'header-text' => [ 'site-title', 'site-description' ],
		] );
		add_theme_support( 'custom-header', [
			'default-image' => '',
			'width'         => 1920,
			'height'        => 800,
			'flex-height'   => true,
			'flex-width'    => true,
		] );
		add_theme_support( 'custom-background', [
			'default-color' => 'f8fafc',
		] );

		// Feeds & Links
		add_theme_support( 'automatic-feed-links' );

		// Internationalization
		load_theme_textdomain( MEDCORE_TEXT, MEDCORE_DIR . '/languages' );

		// Content width (fallback for older embeds)
		$GLOBALS['content_width'] = 760;
	}

	// ─── Navigation Menus ─────────────────────────────────────────────────────

	public function register_menus(): void {
		register_nav_menus( [
			'primary'   => __( 'منوی اصلی', 'signteb-medcore' ),
			'secondary' => __( 'منوی ثانویه', 'signteb-medcore' ),
			'footer'    => __( 'منوی فوتر', 'signteb-medcore' ),
			'mobile'    => __( 'منوی موبایل', 'signteb-medcore' ),
			'social'    => __( 'منوی شبکه‌های اجتماعی', 'signteb-medcore' ),
			'legal'     => __( 'منوی حقوقی (Privacy / Terms)', 'signteb-medcore' ),
		] );
	}

	// ─── Image Sizes ──────────────────────────────────────────────────────────

	public function register_image_sizes(): void {
		// Doctor profile images
		add_image_size( 'stmc-doctor-hero',   800, 900, true );   // پروفایل Hero
		add_image_size( 'stmc-doctor-card',   400, 450, true );   // کارت پزشک
		add_image_size( 'stmc-doctor-thumb',  200, 200, true );   // تامبنیل کوچک

		// Service / Treatment images
		add_image_size( 'stmc-service-cover', 800, 500, true );   // کاور خدمت
		add_image_size( 'stmc-service-thumb', 400, 250, true );   // تامبنیل خدمت

		// Before / After
		add_image_size( 'stmc-before-after',  760, 500, true );   // تصویر قبل/بعد

		// Blog & Articles
		add_image_size( 'stmc-post-cover',    1200, 630, true );  // OG image size
		add_image_size( 'stmc-post-card',     600, 380, true );   // کارت مقاله

		// Clinic
		add_image_size( 'stmc-clinic-cover',  1200, 500, true );  // کاور کلینیک
		add_image_size( 'stmc-clinic-thumb',  400, 280, true );   // تامبنیل کلینیک

		// Testimonial avatar
		add_image_size( 'stmc-avatar',        120, 120, true );   // آواتار بیمار

		// Case Study
		add_image_size( 'stmc-case-cover',    800, 500, true );   // کاور پرونده
	}

	// ─── Sidebars / Widget Areas ──────────────────────────────────────────────

	public function register_sidebars(): void {
		$defaults = [
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="widget__title">',
			'after_title'   => '</h3>',
		];

		register_sidebar( array_merge( $defaults, [
			'id'          => 'sidebar-blog',
			'name'        => __( 'سایدبار بلاگ', 'signteb-medcore' ),
			'description' => __( 'ناحیه ابزارک بلاگ', 'signteb-medcore' ),
		] ) );

		register_sidebar( array_merge( $defaults, [
			'id'          => 'sidebar-doctor',
			'name'        => __( 'سایدبار پروفایل پزشک', 'signteb-medcore' ),
			'description' => __( 'ناحیه ابزارک صفحه پروفایل پزشک', 'signteb-medcore' ),
		] ) );

		register_sidebar( array_merge( $defaults, [
			'id'          => 'footer-1',
			'name'        => __( 'فوتر — ستون ۱', 'signteb-medcore' ),
			'description' => __( 'ناحیه ابزارک فوتر — ستون اول', 'signteb-medcore' ),
		] ) );

		register_sidebar( array_merge( $defaults, [
			'id'          => 'footer-2',
			'name'        => __( 'فوتر — ستون ۲', 'signteb-medcore' ),
			'description' => __( 'ناحیه ابزارک فوتر — ستون دوم', 'signteb-medcore' ),
		] ) );

		register_sidebar( array_merge( $defaults, [
			'id'          => 'footer-3',
			'name'        => __( 'فوتر — ستون ۳', 'signteb-medcore' ),
			'description' => __( 'ناحیه ابزارک فوتر — ستون سوم', 'signteb-medcore' ),
		] ) );
	}

	// ─── Body Classes ─────────────────────────────────────────────────────────

	public function body_classes( array $classes ): array {
		// RTL
		if ( is_rtl() ) {
			$classes[] = 'is-rtl';
		}

		// Singular / archive
		if ( is_singular() ) {
			$classes[] = 'is-singular';
		}

		// Custom post types
		if ( is_singular( 'doctor' ) ) {
			$classes[] = 'is-doctor-profile';
		}
		if ( is_singular( 'medical-service' ) ) {
			$classes[] = 'is-service-page';
		}
		if ( is_post_type_archive( 'doctor' ) || is_tax( 'specialty' ) || is_tax( 'location' ) ) {
			$classes[] = 'is-doctor-archive';
		}

		// No sidebar pages
		if ( is_page_template( 'page-no-sidebar.html' ) || is_page_template( 'page-landing.html' ) ) {
			$classes[] = 'has-no-sidebar';
		}

		return array_unique( $classes );
	}

	// ─── Excerpt ──────────────────────────────────────────────────────────────

	public function excerpt_length(): int {
		return 30; // کلمه — مناسب برای کارت‌های پزشکی
	}

	public function excerpt_more( string $more ): string {
		return sprintf(
			'&hellip; <a class="stmc-read-more" href="%s">%s</a>',
			esc_url( get_permalink() ),
			esc_html__( 'ادامه مطلب', 'signteb-medcore' )
		);
	}

	// ─── Preconnect for fonts ─────────────────────────────────────────────────

	public function preconnect_fonts( array $hints, string $relation_type ): array {
		// Only preconnect if using CDN fonts (self-hosted doesn't need preconnect)
		// Kept as placeholder for CDN fallback strategy
		return $hints;
	}
}

// Instantiate
new MedCore_Setup();
