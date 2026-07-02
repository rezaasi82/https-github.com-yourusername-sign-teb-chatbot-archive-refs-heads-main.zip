<?php
/**
 * SignTeb Medical Core — Case Study CPT
 * Schema: MedicalStudy | URL: /case/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class CaseStudy {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'case-study', [
			'labels' => [
				'name'          => _x( 'پرونده‌های موفق', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'پرونده موفق', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'پرونده‌ها', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن پرونده جدید', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش پرونده', STMC_TEXT ),
				'all_items'     => __( 'همه پرونده‌ها', STMC_TEXT ),
				'not_found'     => __( 'پرونده‌ای یافت نشد.', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'case', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => 'cases',
			'menu_icon'       => 'dashicons-media-document',
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
			'taxonomies'      => [ 'specialty', 'treatment-type' ],
			'show_in_rest'    => true,
		] );
	}
}
