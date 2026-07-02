<?php
/**
 * SignTeb Medical Blocks — Orchestrator
 *
 * تمام ۱۰ block را ثبت می‌کند.
 * هر block یک پوشه مستقل با block.json دارد.
 *
 * @package SignTeb_Blocks
 */

declare( strict_types=1 );

namespace STMB;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	/** لیست همه block‌ها */
	private const BLOCKS = [
		'doctor-hero',
		'appointment-cta',
		'service-grid',
		'before-after-slider',
		'faq-accordion',
		'stats-counter',
		'testimonials-slider',
		'doctor-card-grid',
		'contact-cta',
		'medical-video',
	];

	public function run(): void {
		add_action( 'init',                    [ $this, 'register_blocks' ] );
		add_action( 'enqueue_block_assets',    [ $this, 'enqueue_shared_styles' ] );
		add_filter( 'block_categories_all',    [ $this, 'register_category' ], 10, 2 );
	}

	// ─── Block Registration ───────────────────────────────────────────────────

	public function register_blocks(): void {
		foreach ( self::BLOCKS as $block_name ) {
			$path = STMB_DIR . 'blocks/' . $block_name;
			if ( is_dir( $path ) && file_exists( $path . '/block.json' ) ) {
				register_block_type( $path );
			}
		}
	}

	// ─── Shared CSS (Glass tokens, animations) ────────────────────────────────

	public function enqueue_shared_styles(): void {
		// از لود بدون‌دلیل (404) وقتی build/shared.css هنوز تولید نشده جلوگیری می‌شود.
		if ( ! file_exists( STMB_DIR . 'build/shared.css' ) ) {
			return;
		}

		// در فرانت فقط زمانی لود شود که صفحه واقعاً یکی از block‌های این پلاگین را
		// دارد (نه در تمام صفحات سایت) — طبق اصل «Conditional asset loading».
		// در ادمین/ویرایشگر همیشه لود می‌شود چون پیش‌نمایش block در آنجا لازم است.
		if ( ! is_admin() && ! $this->current_page_has_block() ) {
			return;
		}

		wp_enqueue_style(
			'stmb-shared',
			STMB_URI . 'build/shared.css',
			[],
			STMB_VERSION
		);
	}

	/** آیا محتوای درخواست جاری حداقل یکی از block‌های این پلاگین را دارد؟ */
	private function current_page_has_block(): bool {
		foreach ( self::BLOCKS as $block_name ) {
			if ( has_block( 'signteb/' . $block_name ) ) {
				return true;
			}
		}
		return false;
	}

	// ─── Custom Block Category ────────────────────────────────────────────────

	public function register_category( array $categories, \WP_Block_Editor_Context $context ): array {
		return array_merge(
			[
				[
					'slug'  => 'signteb-medical',
					'title' => __( '⚕️ SignTeb Medical', STMB_TEXT ),
					'icon'  => 'heart',
				],
			],
			$categories
		);
	}
}
