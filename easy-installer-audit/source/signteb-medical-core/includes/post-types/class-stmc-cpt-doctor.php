<?php
/**
 * SignTeb Medical Core — Doctor CPT
 *
 * Custom Post Type: doctor
 * URL: /doctor/{slug}/
 * Schema: Physician (schema.org)
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\PostTypes;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Doctor {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'init', $this, 'register', 5 );
		$this->loader->add_filter( 'post_updated_messages', $this, 'messages' );
		$this->loader->add_filter( 'bulk_post_updated_messages', $this, 'bulk_messages', 10, 2 );
	}

	// ─── Registration ─────────────────────────────────────────────────────────

	public function register(): void {
		$labels = [
			'name'                  => _x( 'پزشکان', 'post type general name', STMC_TEXT ),
			'singular_name'         => _x( 'پزشک', 'post type singular name', STMC_TEXT ),
			'menu_name'             => _x( 'پزشکان', 'admin menu', STMC_TEXT ),
			'name_admin_bar'        => _x( 'پزشک', 'add new on admin bar', STMC_TEXT ),
			'add_new'               => _x( 'افزودن پزشک', 'doctor', STMC_TEXT ),
			'add_new_item'          => __( 'افزودن پزشک جدید', STMC_TEXT ),
			'new_item'              => __( 'پزشک جدید', STMC_TEXT ),
			'edit_item'             => __( 'ویرایش پروفایل پزشک', STMC_TEXT ),
			'view_item'             => __( 'مشاهده پروفایل', STMC_TEXT ),
			'all_items'             => __( 'همه پزشکان', STMC_TEXT ),
			'search_items'          => __( 'جستجو در پزشکان', STMC_TEXT ),
			'parent_item_colon'     => '',
			'not_found'             => __( 'پزشکی یافت نشد.', STMC_TEXT ),
			'not_found_in_trash'    => __( 'پزشکی در سطل آشغال یافت نشد.', STMC_TEXT ),
			'featured_image'        => __( 'تصویر پروفایل', STMC_TEXT ),
			'set_featured_image'    => __( 'تنظیم تصویر پروفایل', STMC_TEXT ),
			'remove_featured_image' => __( 'حذف تصویر پروفایل', STMC_TEXT ),
			'use_featured_image'    => __( 'استفاده به عنوان تصویر پروفایل', STMC_TEXT ),
			'archives'              => __( 'آرشیو پزشکان', STMC_TEXT ),
			'insert_into_item'      => __( 'درج در پروفایل', STMC_TEXT ),
			'uploaded_to_this_item' => __( 'آپلود شده در این پروفایل', STMC_TEXT ),
			'items_list'            => __( 'لیست پزشکان', STMC_TEXT ),
			'items_list_navigation' => __( 'ناوبری لیست پزشکان', STMC_TEXT ),
			'filter_items_list'     => __( 'فیلتر لیست پزشکان', STMC_TEXT ),
		];

		$args = [
			'labels'              => $labels,
			'description'         => __( 'پروفایل پزشکان و متخصصان', STMC_TEXT ),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'query_var'           => true,
			'rewrite'             => [
				'slug'       => 'doctor',
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			],
			'capability_type'     => 'post',
			'has_archive'         => 'doctors',
			'hierarchical'        => false,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-admin-users',
			'supports'            => [
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'revisions',
				'page-attributes',
				'custom-fields',
			],
			'taxonomies'          => [ 'specialty', 'location' ],
			'show_in_rest'        => true, // Gutenberg support
			'rest_base'           => 'doctors',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'can_export'          => true,
			'delete_with_user'    => false,
			'template'            => [], // Block template (can be set here)
			'template_lock'       => false,
		];

		register_post_type( 'doctor', $args );
	}

	// ─── Admin Messages ───────────────────────────────────────────────────────

	public function messages( array $messages ): array {
		global $post;

		$permalink = get_permalink( $post );

		$messages['doctor'] = [
			0  => '',
			1  => sprintf(
				/* translators: %s: post permalink */
				__( 'پروفایل پزشک ذخیره شد. <a href="%s">مشاهده پروفایل</a>', STMC_TEXT ),
				esc_url( $permalink )
			),
			2  => __( 'فیلد سفارشی بروزرسانی شد.', STMC_TEXT ),
			3  => __( 'فیلد سفارشی حذف شد.', STMC_TEXT ),
			4  => __( 'پروفایل پزشک بروزرسانی شد.', STMC_TEXT ),
			5  => isset( $_GET['revision'] ) // phpcs:ignore WordPress.Security.NonceVerification
				? sprintf( __( 'پروفایل به نسخه %s بازگردانده شد.', STMC_TEXT ), wp_post_revision_title( (int) $_GET['revision'], false ) ) // phpcs:ignore
				: false,
			6  => sprintf(
				__( 'پروفایل پزشک منتشر شد. <a href="%s">مشاهده پروفایل</a>', STMC_TEXT ),
				esc_url( $permalink )
			),
			7  => __( 'پروفایل پزشک ذخیره شد.', STMC_TEXT ),
			8  => sprintf(
				__( 'پروفایل ارسال شد. <a target="_blank" href="%s">پیش‌نمایش</a>', STMC_TEXT ),
				esc_url( add_query_arg( 'preview', 'true', $permalink ) )
			),
			9  => sprintf(
				__( 'پروفایل برای انتشار زمانبندی شد. <a target="_blank" href="%s">پیش‌نمایش</a>', STMC_TEXT ),
				esc_url( $permalink )
			),
			10 => sprintf(
				__( 'پیش‌نویس پروفایل بروزرسانی شد. <a target="_blank" href="%s">پیش‌نمایش</a>', STMC_TEXT ),
				esc_url( add_query_arg( 'preview', 'true', $permalink ) )
			),
		];

		return $messages;
	}

	public function bulk_messages( array $bulk_messages, array $bulk_counts ): array {
		$bulk_messages['doctor'] = [
			'updated'   => sprintf(
				/* translators: %s: number of doctors */
				_n( '%s پروفایل پزشک بروزرسانی شد.', '%s پروفایل پزشک بروزرسانی شدند.', $bulk_counts['updated'], STMC_TEXT ),
				number_format_i18n( $bulk_counts['updated'] )
			),
			'locked'    => sprintf(
				_n( '%s پروفایل قابل بروزرسانی نبود، در حال ویرایش توسط دیگری است.', '%s پروفایل قابل بروزرسانی نبودند.', $bulk_counts['locked'], STMC_TEXT ),
				number_format_i18n( $bulk_counts['locked'] )
			),
			'deleted'   => sprintf(
				_n( '%s پروفایل پزشک به طور دائم حذف شد.', '%s پروفایل پزشک به طور دائم حذف شدند.', $bulk_counts['deleted'], STMC_TEXT ),
				number_format_i18n( $bulk_counts['deleted'] )
			),
			'trashed'   => sprintf(
				_n( '%s پروفایل به سطل آشغال منتقل شد.', '%s پروفایل به سطل آشغال منتقل شدند.', $bulk_counts['trashed'], STMC_TEXT ),
				number_format_i18n( $bulk_counts['trashed'] )
			),
			'untrashed' => sprintf(
				_n( '%s پروفایل از سطل آشغال بازگردانده شد.', '%s پروفایل از سطل آشغال بازگردانده شدند.', $bulk_counts['untrashed'], STMC_TEXT ),
				number_format_i18n( $bulk_counts['untrashed'] )
			),
		];

		return $bulk_messages;
	}
}
