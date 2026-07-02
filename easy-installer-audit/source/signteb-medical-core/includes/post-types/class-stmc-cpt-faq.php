<?php
/**
 * SignTeb Medical Core — FAQ CPT
 * Schema: FAQPage | URL: /faq/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Faq {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'medical-faq', [
			'labels' => [
				'name'          => _x( 'سؤالات متداول', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'سؤال متداول', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'سؤالات متداول', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن سؤال جدید', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش سؤال', STMC_TEXT ),
				'all_items'     => __( 'همه سؤالات', STMC_TEXT ),
				'not_found'     => __( 'سؤالی یافت نشد.', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'faq', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => false,
			'menu_icon'       => 'dashicons-editor-help',
			'supports'        => [ 'title', 'editor', 'revisions' ],
			'taxonomies'      => [ 'specialty' ],
			'show_in_rest'    => true,
		] );
	}
}
