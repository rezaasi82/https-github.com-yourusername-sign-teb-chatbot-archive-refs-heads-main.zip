<?php
/**
 * SignTeb Medical Core — Treatment CPT
 * Schema: MedicalTherapy | URL: /treatment/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Treatment {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'treatment', [
			'labels' => [
				'name'          => _x( 'درمان‌ها', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'درمان', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'درمان‌ها', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن درمان جدید', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش درمان', STMC_TEXT ),
				'all_items'     => __( 'همه درمان‌ها', STMC_TEXT ),
				'not_found'     => __( 'درمانی یافت نشد.', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'treatment', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => 'treatments',
			'menu_icon'       => 'dashicons-plus-alt',
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
			'taxonomies'      => [ 'specialty', 'condition', 'treatment-type' ],
			'show_in_rest'    => true,
		] );
	}
}
