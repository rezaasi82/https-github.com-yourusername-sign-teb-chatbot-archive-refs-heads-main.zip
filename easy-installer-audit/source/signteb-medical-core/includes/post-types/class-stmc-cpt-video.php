<?php
/**
 * SignTeb Medical Core — Medical Video CPT
 * Schema: VideoObject | URL: /video/{slug}/
 */
declare( strict_types=1 );
namespace STMC\PostTypes;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Video {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
	}
	public function register(): void {
		register_post_type( 'medical-video', [
			'labels' => [
				'name'          => _x( 'ویدیوهای پزشکی', 'post type general name', STMC_TEXT ),
				'singular_name' => _x( 'ویدیوی پزشکی', 'post type singular name', STMC_TEXT ),
				'menu_name'     => _x( 'ویدیوها', 'admin menu', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن ویدیو', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش ویدیو', STMC_TEXT ),
				'all_items'     => __( 'همه ویدیوها', STMC_TEXT ),
				'not_found'     => __( 'ویدیویی یافت نشد.', STMC_TEXT ),
			],
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => 'signteb-medical-core',
			'rewrite'         => [ 'slug' => 'video', 'with_front' => false ],
			'capability_type' => 'post',
			'has_archive'     => 'medical-videos',
			'menu_icon'       => 'dashicons-video-alt3',
			'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
			'taxonomies'      => [ 'specialty' ],
			'show_in_rest'    => true,
		] );
	}
}
