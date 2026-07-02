<?php
/**
 * SignTeb Medical Core — Medical Service CPT
 *
 * Custom Post Type: medical-service
 * URL: /service/{slug}/
 * Schema: MedicalProcedure
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\PostTypes;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}

	public function register(): void {
		register_post_type( 'medical-service', [
			'labels' => [
				'name'               => _x( 'خدمات پزشکی', 'post type general name', STMC_TEXT ),
				'singular_name'      => _x( 'خدمت پزشکی', 'post type singular name', STMC_TEXT ),
				'menu_name'          => _x( 'خدمات', 'admin menu', STMC_TEXT ),
				'add_new'            => __( 'افزودن خدمت', STMC_TEXT ),
				'add_new_item'       => __( 'افزودن خدمت جدید', STMC_TEXT ),
				'edit_item'          => __( 'ویرایش خدمت پزشکی', STMC_TEXT ),
				'view_item'          => __( 'مشاهده خدمت', STMC_TEXT ),
				'all_items'          => __( 'همه خدمات', STMC_TEXT ),
				'search_items'       => __( 'جستجو در خدمات', STMC_TEXT ),
				'not_found'          => __( 'خدمتی یافت نشد.', STMC_TEXT ),
				'not_found_in_trash' => __( 'خدمتی در سطل آشغال یافت نشد.', STMC_TEXT ),
			],
			'description'        => __( 'خدمات و اقدامات پزشکی ارائه شده', STMC_TEXT ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'signteb-medical-core',
			'query_var'          => true,
			'rewrite'            => [ 'slug' => 'service', 'with_front' => false ],
			'capability_type'    => 'post',
			'has_archive'        => 'services',
			'hierarchical'       => false,
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-heart',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
			'taxonomies'         => [ 'specialty', 'treatment-type' ],
			'show_in_rest'       => true,
			'rest_base'          => 'medical-services',
		] );
	}
}
