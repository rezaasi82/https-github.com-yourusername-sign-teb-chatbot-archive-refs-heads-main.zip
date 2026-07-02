<?php
/**
 * SignTeb Medical Core — Clinic CPT
 * Schema: MedicalClinic | URL: /clinic/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Clinic {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'clinic', [
			'labels' => [
				'name'          => _x( 'کلینیک‌ها', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'کلینیک', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'کلینیک‌ها', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن کلینیک', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش کلینیک', STMC_TEXT ),
				'all_items'     => __( 'همه کلینیک‌ها', STMC_TEXT ),
				'not_found'     => __( 'کلینیکی یافت نشد.', STMC_TEXT ),
				'featured_image'     => __( 'تصویر کلینیک', STMC_TEXT ),
				'set_featured_image' => __( 'تنظیم تصویر کلینیک', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'clinic', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => 'clinics',
			'menu_icon'       => 'dashicons-building',
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
			'taxonomies'      => [ 'specialty', 'location' ],
			'show_in_rest'    => true,
		] );
	}
}
