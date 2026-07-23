<?php
/**
 * SignTeb Setup Wizard — Data-driven Demo Content Importer
 *
 * محتوای دمو را از یک آرایه‌ی تعریف (تولیدشده توسط demos/<slug>/demo.php) می‌سازد.
 * همه‌ی عملیات idempotent است: اجرای دوباره محتوای تکراری نمی‌سازد (هر آیتم با
 * متای _stwiz_key یکتا و صفحات با slug شناسایی می‌شوند).
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

defined( 'ABSPATH' ) || exit;

final class DemoContentImporter {

	private string $demo_id;

	public function __construct( string $demo_id ) {
		$this->demo_id = $demo_id;
	}

	// ─── Options (مرحله‌ی options) ───────────────────────────────────────────

	public function apply_options( array $def ): void {
		foreach ( (array) ( $def['options'] ?? [] ) as $key => $val ) {
			update_option( $key, $val );
		}

		$this->apply_palette( $def );

		// دموی فعال + سبک هیرو را ذخیره کن تا قالب کلاس body و CSS مختص هر دمو
		// را اعمال کند (هویت بصری مستقلِ هر دمو).
		update_option( 'stwiz_active_demo',  (string) ( $def['id'] ?? '' ) );
		update_option( 'stwiz_active_hero',  sanitize_key( (string) ( $def['hero'] ?? 'aurora' ) ) );
	}

	/**
	 * اعمالِ پالتِ رنگیِ دمو به theme_modهایی که Design Tokens قالب می‌خواند
	 * (stmc_color_primary/accent/dark) — به‌این‌ترتیب کلِ سایت (هدر، دکمه‌ها،
	 * هیرو، کارت‌ها، فوتر) رنگِ همان دمو را می‌گیرد، نه فقط عنوان. این همان چیزی
	 * است که هر دمو را واقعاً «متفاوت» می‌کند.
	 */
	private function apply_palette( array $def ): void {
		if ( ! function_exists( 'set_theme_mod' ) ) {
			return;
		}
		$palette = (array) ( $def['palette'] ?? [] );

		// اگر پالت کامل تعریف نشده، از فیلد قدیمی color به‌عنوان primary استفاده کن.
		$primary = $this->valid_hex( (string) ( $palette['primary'] ?? ( $def['color'] ?? '' ) ) );
		$accent  = $this->valid_hex( (string) ( $palette['accent'] ?? '' ) );
		$dark    = $this->valid_hex( (string) ( $palette['dark'] ?? '' ) );

		if ( $primary ) {
			set_theme_mod( 'stmc_color_primary', $primary );
		}
		if ( $accent ) {
			set_theme_mod( 'stmc_color_accent', $accent );
		}
		if ( $dark ) {
			set_theme_mod( 'stmc_color_dark', $dark );
		}
	}

	/** اعتبارسنجی رنگ hex؛ رشته‌ی خالی اگر نامعتبر بود. */
	private function valid_hex( string $hex ): string {
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex ) ? $hex : '';
	}

	// ─── Content: doctors + services + faqs + reviews + posts + pages ─────────

	/**
	 * ساخت همه‌ی محتوای دمو. شناسه‌ی صفحه‌ی خانه را برمی‌گرداند.
	 */
	public function create_content( array $def ): int {
		// سوییچ تمیز دمو: محتوای دموهای «دیگر» (تگ‌شده با _stwiz_demo ولی متعلق به
		// دموی دیگری) حذف می‌شود تا گرید پزشکان/خدمات محتوای دموی قبلی را کنار
		// دموی جدید نشان ندهد — دقیقاً همان «موارد همان قبلی است». محتوای واقعیِ
		// کاربر (بدون تگ _stwiz_demo) هرگز حذف نمی‌شود.
		$this->cleanup_other_demos();

		$doctor_ids = $this->create_doctors( (array) ( $def['doctors'] ?? [] ) );
		$this->create_services( (array) ( $def['services'] ?? [] ) );
		$this->create_faqs( (array) ( $def['faqs'] ?? [] ) );
		$this->create_reviews( (array) ( $def['reviews'] ?? [] ), $doctor_ids );

		// نوشته‌های خودِ دمو + چند مقاله‌ی عمومیِ مشترک تا بلاگ/آرشیو پر دیده شوند.
		$posts = (array) ( $def['posts'] ?? [] );
		$posts = array_merge( $posts, $this->shared_posts() );
		$this->create_posts( $posts );

		return $this->create_pages( (array) ( $def['pages'] ?? [] ) );
	}

	/** @return int[] نگاشت index → doctor post ID */
	private function create_doctors( array $doctors ): array {
		$ids = [];
		foreach ( $doctors as $i => $doc ) {
			$key = 'doctor-' . $this->demo_id . '-' . $i;
			$existing = $this->find_by_key( 'doctor', $key );
			if ( $existing ) {
				$ids[ $i ] = $existing;
				continue;
			}
			$pid = wp_insert_post( wp_slash( [
				'post_type'    => 'doctor',
				'post_title'   => $doc['name'] ?? 'پزشک',
				'post_status'  => 'publish',
				'post_content' => $doc['content'] ?? '',
				'post_excerpt' => $doc['bio'] ?? '',
			] ) );
			if ( is_wp_error( $pid ) ) {
				continue;
			}
			update_post_meta( $pid, 'stmc_doctor_specialty', $doc['specialty'] ?? '' );
			update_post_meta( $pid, 'stmc_doctor_experience_yrs', (int) ( $doc['exp'] ?? 0 ) );
			update_post_meta( $pid, 'stmc_doctor_patients_count', (int) ( $doc['patients'] ?? 0 ) );
			$this->tag_demo( $pid, $key );

			if ( ! empty( $doc['specialty'] ) ) {
				$this->assign_term( $pid, 'specialty', (string) $doc['specialty'] );
			}

			// تصویر شاخصِ placeholder (سه واریانت به‌صورت چرخشی).
			$this->set_featured( $pid, 'doctor-' . ( ( $i % 3 ) + 1 ) . '.png' );

			$ids[ $i ] = $pid;
		}
		return $ids;
	}

	private function create_services( array $services ): void {
		foreach ( $services as $i => $svc ) {
			$key = 'service-' . $this->demo_id . '-' . $i;
			if ( $this->find_by_key( 'medical-service', $key ) ) {
				continue;
			}
			$pid = wp_insert_post( wp_slash( [
				'post_type'    => 'medical-service',
				'post_title'   => $svc['title'] ?? 'خدمت',
				'post_status'  => 'publish',
				'post_content' => $svc['content'] ?? '',
				'post_excerpt' => $svc['excerpt'] ?? '',
				'menu_order'   => $i,
			] ) );
			if ( is_wp_error( $pid ) ) {
				continue;
			}
			update_post_meta( $pid, 'stmc_service_duration', $svc['duration'] ?? '' );
			update_post_meta( $pid, 'stmc_service_price_from', $svc['price'] ?? '' );
			update_post_meta( $pid, 'stmc_service_icon', $svc['icon'] ?? '⚕️' );
			$this->tag_demo( $pid, $key );
			if ( ! empty( $svc['specialty'] ) ) {
				$this->assign_term( $pid, 'specialty', (string) $svc['specialty'] );
			}
			$this->set_featured( $pid, 'service.png' );
		}
	}

	private function create_faqs( array $faqs ): void {
		foreach ( $faqs as $i => $faq ) {
			$key = 'faq-' . $this->demo_id . '-' . $i;
			if ( $this->find_by_key( 'medical-faq', $key ) ) {
				continue;
			}
			$pid = wp_insert_post( wp_slash( [
				'post_type'    => 'medical-faq',
				'post_title'   => $faq['q'] ?? '',
				'post_content' => $faq['a'] ?? '',
				'post_status'  => 'publish',
				'menu_order'   => $i,
			] ) );
			if ( ! is_wp_error( $pid ) ) {
				$this->tag_demo( $pid, $key );
			}
		}
	}

	private function create_reviews( array $reviews, array $doctor_ids ): void {
		if ( empty( $reviews ) || ! class_exists( '\STMC\Reviews\Repository' ) ) {
			return;
		}
		try {
			$repo = new \STMC\Reviews\Repository();
			// اگر از قبل نظر تأییدشده‌ای هست، دوباره نساز (idempotent).
			if ( ! empty( $repo->get_approved( 0, 1 ) ) ) {
				return;
			}
			foreach ( $reviews as $rev ) {
				$doc_index = (int) ( $rev['doctor'] ?? 0 );
				$repo->create_manual( [
					'doctor_id'     => $doctor_ids[ $doc_index ] ?? 0,
					'reviewer_name' => $rev['name'] ?? 'بیمار',
					'reviewer_city' => $rev['city'] ?? '',
					'rating'        => (int) ( $rev['rating'] ?? 5 ),
					'content'       => $rev['text'] ?? '',
					'treatment'     => $rev['treatment'] ?? '',
				], true );
			}
		} catch ( \Throwable $e ) {
			// نبود/تغییر Repository نباید ایمپورت را بشکند.
			return;
		}
	}

	private function create_posts( array $posts ): void {
		foreach ( $posts as $i => $post ) {
			$key      = 'post-' . $this->demo_id . '-' . $i;
			$existing = $this->find_by_key( 'post', $key );
			$cat      = (string) ( $post['category'] ?? '' );
			if ( '' === $cat ) {
				$defaults = [ 'آموزش سلامت', 'راهنمای بیماران', 'اخبار مرکز' ];
				$cat      = $defaults[ $i % count( $defaults ) ];
			}
			if ( $existing ) {
				// نوشته از قبل هست (اجرای مجدد) — تصویرِ شاخص و دسته را ترمیم کن
				// تا نصب‌های قدیمیِ بدونِ تصویر هم کامل شوند.
				$this->set_featured( $existing, 'article-' . ( ( $i % 3 ) + 1 ) . '.png' );
				$this->assign_category( $existing, $cat );
				continue;
			}
			$pid = wp_insert_post( wp_slash( [
				'post_type'    => 'post',
				'post_title'   => $post['title'] ?? '',
				'post_content' => $post['content'] ?? '',
				'post_excerpt' => $post['excerpt'] ?? '',
				'post_status'  => 'publish',
			] ) );
			if ( is_wp_error( $pid ) ) {
				continue;
			}
			$this->tag_demo( $pid, $key );
			// تصویرِ شاخصِ مقاله (سه واریانتِ چرخشی) + دسته — تا بلاگ/آرشیو پر باشد.
			$this->set_featured( $pid, 'article-' . ( ( $i % 3 ) + 1 ) . '.png' );
			$this->assign_category( $pid, $cat );
		}
	}

	/** مقاله‌های عمومیِ سلامت — به بلاگِ همه‌ی دموها اضافه می‌شوند تا کامل دیده شود. */
	private function shared_posts(): array {
		return [
			[
				'title'    => 'راهنمای کامل آماده‌شدن برای اولین ویزیت',
				'excerpt'  => 'قبل از مراجعه چه مدارکی همراه داشته باشید و چه سؤالاتی بپرسید.',
				'category' => 'راهنمای بیماران',
				'content'  => '<!-- wp:paragraph --><p>اولین ویزیت پزشکی می‌تواند کمی استرس‌زا باشد. با کمی آمادگی، بیشترین بهره را از وقت خود می‌برید. سوابق پزشکی، فهرست داروهای مصرفی و نتایج آزمایش‌های قبلی را همراه داشته باشید.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">چه بپرسیم؟</h2><!-- /wp:heading --><!-- wp:list --><ul><li>گزینه‌های درمانی و مدت زمان هرکدام</li><li>عوارض احتمالی و مراقبت‌های پس از درمان</li><li>هزینه‌ها و پوشش بیمه</li></ul><!-- /wp:list -->',
			],
			[
				'title'    => '۵ عادت ساده برای حفظ سلامت در طول سال',
				'excerpt'  => 'تغذیه، خواب، تحرک و چکاپ منظم؛ کوچک اما تأثیرگذار.',
				'category' => 'آموزش سلامت',
				'content'  => '<!-- wp:paragraph --><p>سلامت پایدار حاصلِ عادت‌های کوچکِ روزانه است. خواب کافی، نوشیدن آب کافی، تحرک منظم و کاهش استرس، پایه‌های یک زندگی سالم‌اند.</p><!-- /wp:paragraph --><!-- wp:quote --><blockquote class="wp-block-quote"><p>پیشگیری همیشه ساده‌تر و کم‌هزینه‌تر از درمان است.</p></blockquote><!-- /wp:quote --><!-- wp:paragraph --><p>یک چکاپ سالانه به تشخیص زودهنگام کمک می‌کند و خیال شما را آسوده نگه می‌دارد.</p><!-- /wp:paragraph -->',
			],
			[
				'title'    => 'نوبت‌دهی آنلاین چگونه کار می‌کند؟',
				'excerpt'  => 'در چند گام ساده و بدون تماس تلفنی، نوبت خود را رزرو کنید.',
				'category' => 'اخبار مرکز',
				'content'  => '<!-- wp:paragraph --><p>با سامانه‌ی نوبت‌دهی آنلاین، در هر ساعت از شبانه‌روز می‌توانید زمان مناسب خود را انتخاب و رزرو کنید. پس از ثبت، یک پیامک تأیید دریافت می‌کنید.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/appointment">رزرو نوبت آنلاین</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
			],
		];
	}

	/** ساخت/اختصاصِ یک دسته‌ی استانداردِ وردپرس به نوشته (برای آرشیوِ دسته). */
	private function assign_category( int $post_id, string $name ): void {
		$term = term_exists( $name, 'category' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'category' );
		}
		if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
			wp_set_post_terms( $post_id, [ (int) $term['term_id'] ], 'category', false );
		}
	}

	/** @return int شناسه‌ی صفحه‌ی front (اگر تعریف شده باشد). */
	private function create_pages( array $pages ): int {
		$front_id = 0;
		foreach ( $pages as $page ) {
			$slug = $page['slug'] ?? sanitize_title( $page['title'] ?? '' );

			// جایگزینی توکن‌های تصویر (قبل/بعد + پوسترِ ویدیو) با URL واقعیِ آپلودشده.
			$content = strtr( (string) ( $page['content'] ?? '' ), [
				'%%IMG_BEFORE%%' => $this->image_url( 'before.png' ),
				'%%IMG_AFTER%%'  => $this->image_url( 'after.png' ),
				'%%IMG_VIDEO%%'  => $this->image_url( 'video-poster.png' ),
			] );

			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				// اگر صفحه‌ی موجود متعلق به خودِ دموست (تگ _stwiz_demo دارد)،
				// محتوایش را با markup تازه به‌روزرسانی کن. این کار اجرای دوباره‌ی
				// ویزارد را به یک «ابزار تعمیر» تبدیل می‌کند: صفحات دمویی که با
				// نسخه‌های قبلی (مثلاً با باگ حذف بک‌اسلش در JSON آمار) ساخته
				// شده‌اند، با یک بار اجرای مجدد مرحله‌ی دمو سالم می‌شوند.
				// صفحات غیر-دمو (ساخته‌ی کاربر) دست‌نخورده می‌مانند.
				if ( get_post_meta( $existing->ID, '_stwiz_demo', true ) ) {
					wp_update_post( wp_slash( [
						'ID'           => $existing->ID,
						'post_content' => $content,
					] ) );
				}
				if ( ! empty( $page['front'] ) ) {
					$front_id = (int) $existing->ID;
				}
				continue;
			}

			$pid = wp_insert_post( wp_slash( [
				'post_type'    => 'page',
				'post_title'   => $page['title'] ?? '',
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'page_template'=> $page['template'] ?? '',
			] ) );
			if ( is_wp_error( $pid ) ) {
				continue;
			}
			$this->tag_demo( $pid, 'page-' . $this->demo_id . '-' . $slug );
			if ( ! empty( $page['front'] ) ) {
				$front_id = (int) $pid;
			}
		}
		return $front_id;
	}

	// ─── Menu (مرحله‌ی menus) ─────────────────────────────────────────────────

	public function create_menu( array $def ): void {
		$menu_name = $def['menu_name'] ?? __( 'منوی اصلی', STWIZ_TEXT );
		$menu_id   = wp_create_nav_menu( $menu_name );
		if ( is_wp_error( $menu_id ) ) {
			$menu = wp_get_nav_menu_object( $menu_name );
			if ( ! $menu ) {
				return;
			}
			$menu_id = $menu->term_id;
		}

		foreach ( (array) ( $def['menu'] ?? [] ) as $slug => $label ) {
			$page = get_page_by_path( $slug );
			if ( ! $page ) {
				continue;
			}
			// جلوگیری از آیتم تکراری هنگام اجرای دوباره.
			if ( $this->menu_has_object( $menu_id, (int) $page->ID ) ) {
				continue;
			}
			wp_update_nav_menu_item( $menu_id, 0, [
				'menu-item-title'     => $label,
				'menu-item-object-id' => $page->ID,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			] );
		}

		$locations            = get_theme_mod( 'nav_menu_locations', [] );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	// ─── Front / Blog (مراحل home / blog) ────────────────────────────────────

	public function assign_front( array $def, int $home_id = 0 ): int {
		if ( ! $home_id ) {
			$slug    = $def['front_page'] ?? 'home';
			$page    = get_page_by_path( $slug );
			$home_id = $page ? (int) $page->ID : 0;
		}
		if ( $home_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
		}
		return $home_id;
	}

	public function assign_blog( array $def ): int {
		$slug     = $def['blog_page'] ?? 'blog';
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$blog_id = (int) $existing->ID;
		} else {
			$blog_id = wp_insert_post( wp_slash( [
				'post_type'   => 'page',
				'post_title'  => $def['blog_title'] ?? __( 'بلاگ', STWIZ_TEXT ),
				'post_name'   => $slug,
				'post_status' => 'publish',
			] ) );
			if ( is_wp_error( $blog_id ) ) {
				return 0;
			}
			$this->tag_demo( $blog_id, 'page-' . $this->demo_id . '-' . $slug );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $blog_id );
		return (int) $blog_id;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function tag_demo( int $post_id, string $key ): void {
		update_post_meta( $post_id, '_stwiz_demo', $this->demo_id );
		update_post_meta( $post_id, '_stwiz_key', $key );
	}

	/**
	 * حذف محتوای دموهای «دیگر» (نه دموی جاری). فقط پست‌هایی که با _stwiz_demo
	 * تگ‌ شده‌اند و مقدارِ تگ‌شان با دموی جاری فرق دارد حذف می‌شوند — پس محتوای
	 * واقعیِ کاربر و نیز خودِ دموی جاری (برای idempotency) دست‌نخورده می‌ماند.
	 */
	private function cleanup_other_demos(): void {
		$types = [ 'doctor', 'medical-service', 'medical-faq', 'post', 'page' ];

		$orphans = get_posts( [
			'post_type'      => $types,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_stwiz_demo',
					'value'   => $this->demo_id,
					'compare' => '!=',
				],
			],
		] );

		foreach ( $orphans as $id ) {
			// دفاع مضاعف: فقط اگر واقعاً تگ دمو دارد (get_posts با meta_query
			// != پست‌های بدون متا را هم می‌تواند برگرداند) و تگش با جاری فرق دارد.
			$tag = get_post_meta( (int) $id, '_stwiz_demo', true );
			if ( $tag && $tag !== $this->demo_id ) {
				wp_delete_post( (int) $id, true );
			}
		}
	}

	// ─── Media / تصاویر placeholder ──────────────────────────────────────────

	/**
	 * ست کردن تصویر شاخص از یک placeholderِ همراهِ افزونه. کاملاً دفاعی: هر خطای
	 * رسانه‌ای نباید ایمپورت را بشکند.
	 */
	private function set_featured( int $post_id, string $filename ): void {
		if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post_id ) ) {
			return; // idempotent
		}
		$att = $this->ensure_attachment( $filename );
		if ( $att && function_exists( 'set_post_thumbnail' ) ) {
			set_post_thumbnail( $post_id, $att );
		}
	}

	/** URL تصویرِ placeholder (پس از اطمینان از وجود attachment). */
	private function image_url( string $filename ): string {
		$att = $this->ensure_attachment( $filename );
		return $att ? (string) wp_get_attachment_url( $att ) : '';
	}

	/**
	 * کپی یک تصویرِ همراهِ افزونه به کتابخانه‌ی رسانه (یک‌بار) و ساخت attachment.
	 * idempotent با متای _stwiz_media_key. شناسه‌ی attachment را برمی‌گرداند (یا ۰).
	 */
	private function ensure_attachment( string $filename ): int {
		try {
			$existing = get_posts( [
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_stwiz_media_key',
				'meta_value'     => $filename,
			] );
			if ( $existing ) {
				return (int) $existing[0];
			}

			$src = STWIZ_DIR . 'demos/_shared/img/' . $filename;
			if ( ! is_file( $src ) ) {
				return 0;
			}

			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) || empty( $uploads['path'] ) ) {
				return 0;
			}
			$dest = trailingslashit( $uploads['path'] ) . $filename;
			if ( ! file_exists( $dest ) ) {
				copy( $src, $dest );
			}

			$filetype  = wp_check_filetype( $filename );
			$attach_id = wp_insert_attachment( [
				'post_mime_type' => $filetype['type'] ?: 'image/png',
				'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
				'post_status'    => 'inherit',
			], $dest );

			if ( is_wp_error( $attach_id ) || ! $attach_id ) {
				return 0;
			}

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$meta = wp_generate_attachment_metadata( $attach_id, $dest );
			if ( $meta ) {
				wp_update_attachment_metadata( $attach_id, $meta );
			}
			update_post_meta( $attach_id, '_stwiz_media_key', $filename );

			return (int) $attach_id;
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	private function find_by_key( string $post_type, string $key ): int {
		$found = get_posts( [
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_stwiz_key',
			'meta_value'     => $key,
		] );
		return $found ? (int) $found[0] : 0;
	}

	private function assign_term( int $post_id, string $taxonomy, string $term_name ): void {
		$term = term_exists( $term_name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $term_name, $taxonomy );
		}
		if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
			wp_set_object_terms( $post_id, (int) $term['term_id'], $taxonomy, true );
		}
	}

	private function menu_has_object( int $menu_id, int $object_id ): bool {
		$items = wp_get_nav_menu_items( $menu_id );
		if ( ! $items ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( (int) $item->object_id === $object_id ) {
				return true;
			}
		}
		return false;
	}
}
