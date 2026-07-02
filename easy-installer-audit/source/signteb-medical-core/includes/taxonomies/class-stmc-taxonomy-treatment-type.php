<?php
/**
 * SignTeb Medical Core — TreatmentType Taxonomy
 * برای دسته‌بندی نوع درمان: جراحی، دارویی، فیزیوتراپی...
 */
declare( strict_types=1 );
namespace STMC\Taxonomies;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class TreatmentType {
	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 6 );
	}
	public function register(): void {
		register_taxonomy( 'treatment-type', [ 'treatment', 'medical-service', 'case-study' ], [
			'labels' => [
				'name'          => _x( 'نوع درمان', 'taxonomy general name', STMC_TEXT ),
				'singular_name' => _x( 'نوع درمان', 'taxonomy singular name', STMC_TEXT ),
				'menu_name'     => __( 'انواع درمان', STMC_TEXT ),
				'all_items'     => __( 'همه انواع', STMC_TEXT ),
				'add_new_item'  => __( 'افزودن نوع جدید', STMC_TEXT ),
				'edit_item'     => __( 'ویرایش نوع درمان', STMC_TEXT ),
				'not_found'     => __( 'نوع درمانی یافت نشد', STMC_TEXT ),
			],
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => [ 'slug' => 'treatment-type', 'with_front' => false ],
			'query_var'         => true,
		] );
	}
}
