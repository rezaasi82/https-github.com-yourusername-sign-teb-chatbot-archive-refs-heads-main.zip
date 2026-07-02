<?php
/**
 * SignTeb Medical Core — Condition Taxonomy
 * برای دسته‌بندی بیماری‌ها و درمان‌ها
 */
declare( strict_types=1 );
namespace STMC\Taxonomies;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Condition {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 6 );
	}
	public function register(): void {
		register_taxonomy( 'condition', [ 'disease', 'treatment', 'medical-faq' ], [
			'labels' => [
				'name'          => _x( 'بیماری‌ها و شرایط', 'taxonomy general name', STMC_TEXT ),
				'singular_name' => _x( 'بیماری / شرایط', 'taxonomy singular name', STMC_TEXT ),
				'menu_name'     => __( 'شرایط پزشکی', STMC_TEXT ),
				'all_items'     => __( 'همه شرایط', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن شرایط جدید', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش شرایط', STMC_TEXT ),
				'not_found'     => __( 'شرایطی یافت نشد', STMC_TEXT ),
			],
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'condition', 'with_front' => false ],
			'query_var'         => true,
		] );
	}
}
