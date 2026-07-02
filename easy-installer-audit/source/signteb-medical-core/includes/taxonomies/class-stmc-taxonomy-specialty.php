<?php
/**
 * SignTeb Medical Core — Specialty Taxonomy
 * URL: /specialty/{term}/
 */
declare( strict_types=1 );
namespace STMC\Taxonomies;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Specialty {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 6 );
	}
	public function register(): void {
		register_taxonomy( 'specialty', [ 'doctor', 'medical-service', 'treatment', 'disease', 'clinic', 'medical-faq', 'case-study', 'medical-video' ], [
			'labels' => [
				'name'              => _x( 'تخصص‌ها', 'taxonomy general name', STMC_TEXT ),
				'singular_name'     => _x( 'تخصص', 'taxonomy singular name', STMC_TEXT ),
				'menu_name'         => __( 'تخصص‌ها', STMC_TEXT ),
				'all_items'         => __( 'همه تخصص‌ها', STMC_TEXT ),
				'parent_item'       => __( 'تخصص والد', STMC_TEXT ),
				'parent_item_colon' => __( 'تخصص والد:', STMC_TEXT ),
				'new_item_name'     => __( 'نام تخصص جدید', STMC_TEXT ),
				'add_new_item'      => __( 'افزودن تخصص جدید', STMC_TEXT ),
				'edit_item'         => __( 'ویرایش تخصص', STMC_TEXT ),
				'update_item'       => __( 'بروزرسانی تخصص', STMC_TEXT ),
				'search_items'      => __( 'جستجو در تخصص‌ها', STMC_TEXT ),
				'not_found'         => __( 'تخصصی یافت نشد', STMC_TEXT ),
			],
			'hierarchical'      => true,  // مثل category — دارای سلسله مراتب
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'specialty', 'with_front' => false, 'hierarchical' => true ],
			'query_var'         => true,
		] );
	}
}
