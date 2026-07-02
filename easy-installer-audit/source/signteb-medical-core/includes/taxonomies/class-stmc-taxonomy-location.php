<?php
/**
 * SignTeb Medical Core — Location Taxonomy
 * URL: /location/{city}/
 * برای Local SEO و فیلتر جغرافیایی
 */
declare( strict_types=1 );
namespace STMC\Taxonomies;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Location {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 6 );
	}
	public function register(): void {
		register_taxonomy( 'location', [ 'doctor', 'clinic' ], [
			'labels' => [
				'name'          => _x( 'مناطق و شهرها', 'taxonomy general name', STMC_TEXT ),
				'singular_name' => _x( 'منطقه', 'taxonomy singular name', STMC_TEXT ),
				'menu_name'     => __( 'مناطق', STMC_TEXT ),
				'all_items'     => __( 'همه مناطق', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن منطقه', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش منطقه', STMC_TEXT ),
				'search_items'  => __( 'جستجو در مناطق', STMC_TEXT ),
				'not_found'     => __( 'منطقه‌ای یافت نشد', STMC_TEXT ),
			],
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'location', 'with_front' => false, 'hierarchical' => true ],
			'query_var'         => true,
		] );
	}
}
