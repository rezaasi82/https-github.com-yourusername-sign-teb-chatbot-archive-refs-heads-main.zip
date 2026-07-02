<?php
/**
 * SignTeb Medical Core — Disease CPT
 * Schema: MedicalCondition | URL: /disease/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Disease {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'disease', [
			'labels' => [
				'name'          => _x( 'بیماری‌ها', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'بیماری', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'بیماری‌ها', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن بیماری', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش بیماری', STMC_TEXT ),
				'all_items'     => __( 'همه بیماری‌ها', STMC_TEXT ),
				'not_found'     => __( 'بیماری‌ای یافت نشد.', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'disease', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => 'diseases',
			'menu_icon'       => 'dashicons-warning',
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
			'taxonomies'      => [ 'specialty', 'condition' ],
			'show_in_rest'    => true,
		] );
	}
}
