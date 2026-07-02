<?php
/**
 * SignTeb Medical Core — Doctor Meta Boxes
 *
 * ۱۸ فیلد Meta برای پروفایل پزشک:
 * تخصص، تحصیلات، فلوشیپ، گواهینامه‌ها، عضویت‌ها،
 * تجربه، بیماران، WhatsApp، رزرو، زبان‌ها، GPS
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Meta;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class Doctor {

	/** تمام Meta Fields پزشک */
	private const FIELDS = [
		// ── Basic Info ────────────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_title',
			'label'       => 'درجه علمی',
			'type'        => 'select',
			'options'     => [ '' => 'انتخاب کنید...', 'dr' => 'دکتر', 'prof' => 'پروفسور', 'assoc' => 'دانشیار', 'assist' => 'استادیار' ],
			'description' => 'مثال: دکتر، پروفسور',
		],
		[
			'key'         => 'stmc_doctor_specialty',
			'label'       => 'تخصص اصلی',
			'type'        => 'text',
			'placeholder' => 'مثال: جراح ارتوپدی',
			'description' => 'تخصص اصلی که در پروفایل نمایش داده می‌شود',
		],
		[
			'key'         => 'stmc_doctor_subspecialty',
			'label'       => 'فوق‌تخصص',
			'type'        => 'text',
			'placeholder' => 'مثال: جراحی ستون فقرات',
			'description' => 'در صورت داشتن فوق‌تخصص',
		],
		[
			'key'         => 'stmc_doctor_license_no',
			'label'       => 'شماره نظام پزشکی',
			'type'        => 'text',
			'placeholder' => 'مثال: ۱۲۳۴۵۶',
			'description' => 'شماره عضویت در نظام پزشکی',
		],
		// ── Experience & Education ────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_experience_yrs',
			'label'       => 'سال‌های تجربه',
			'type'        => 'number',
			'min'         => 0,
			'max'         => 70,
			'description' => 'تعداد سال‌های تجربه پزشکی',
		],
		[
			'key'         => 'stmc_doctor_patients_count',
			'label'       => 'تعداد بیماران',
			'type'        => 'number',
			'min'         => 0,
			'description' => 'تعداد تقریبی بیماران درمان شده',
		],
		[
			'key'         => 'stmc_doctor_education',
			'label'       => 'تحصیلات',
			'type'        => 'textarea',
			'rows'        => 4,
			'placeholder' => "دکتری پزشکی — دانشگاه تهران — ۱۳۸۵\nتخصص ارتوپدی — دانشگاه علوم پزشکی شهید بهشتی — ۱۳۹۰",
			'description' => 'هر مدرک در یک خط: مدرک — دانشگاه — سال',
		],
		[
			'key'         => 'stmc_doctor_fellowship',
			'label'       => 'فلوشیپ',
			'type'        => 'textarea',
			'rows'        => 3,
			'placeholder' => 'فلوشیپ جراحی دست — بیمارستان امام خمینی — ۱۳۹۲',
			'description' => 'فلوشیپ‌های تکمیلی',
		],
		// ── Credentials ──────────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_certifications',
			'label'       => 'گواهینامه‌ها',
			'type'        => 'textarea',
			'rows'        => 3,
			'placeholder' => 'بورد تخصصی ارتوپدی ایران',
			'description' => 'گواهینامه‌های تخصصی، هر مورد در یک خط',
		],
		[
			'key'         => 'stmc_doctor_memberships',
			'label'       => 'عضویت‌های انجمن پزشکی',
			'type'        => 'textarea',
			'rows'        => 3,
			'placeholder' => 'انجمن جراحان ارتوپدی ایران',
			'description' => 'عضویت در انجمن‌های علمی داخلی و بین‌المللی',
		],
		// ── Languages ────────────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_languages',
			'label'       => 'زبان‌های ارتباطی',
			'type'        => 'checkboxes',
			'options'     => [
				'fa' => 'فارسی',
				'ar' => 'عربی',
				'en' => 'انگلیسی',
				'fr' => 'فرانسوی',
				'tr' => 'ترکی',
				'ru' => 'روسی',
			],
		],
		// ── Contact ───────────────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_phone',
			'label'       => 'تلفن مستقیم',
			'type'        => 'tel',
			'placeholder' => '۰۲۱۱۲۳۴۵۶۷۸',
			'description' => 'تلفن مستقیم مطب یا کلینیک',
		],
		[
			'key'         => 'stmc_doctor_whatsapp',
			'label'       => 'شماره WhatsApp',
			'type'        => 'tel',
			'placeholder' => '989191182649',
			'description' => 'با کد کشور — بدون + — مثال: 989191182649',
		],
		[
			'key'         => 'stmc_doctor_booking_url',
			'label'       => 'لینک رزرو آنلاین',
			'type'        => 'url',
			'placeholder' => 'https://nobat.ir/doctor/...',
			'description' => 'لینک سیستم رزرو آنلاین (اختیاری)',
		],
		// ── Media ─────────────────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_video_url',
			'label'       => 'ویدیوی معرفی',
			'type'        => 'url',
			'placeholder' => 'https://www.youtube.com/watch?v=...',
			'description' => 'لینک YouTube یا Vimeo برای ویدیوی معرفی',
		],
		// ── Clinic & Location ─────────────────────────────────────────────────
		[
			'key'         => 'stmc_doctor_clinic_ids',
			'label'       => 'کلینیک(های) مرتبط',
			'type'        => 'post_select',
			'post_type'   => 'clinic',
			'description' => 'کلینیک‌هایی که این پزشک در آن‌ها فعالیت می‌کند',
		],
		[
			'key'         => 'stmc_doctor_lat',
			'label'       => 'عرض جغرافیایی (Latitude)',
			'type'        => 'text',
			'placeholder' => '35.6892',
			'description' => 'برای نقشه و Local SEO',
		],
		[
			'key'         => 'stmc_doctor_lng',
			'label'       => 'طول جغرافیایی (Longitude)',
			'type'        => 'text',
			'placeholder' => '51.3890',
			'description' => 'برای نقشه و Local SEO',
		],
	];

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'add_meta_boxes', $this, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post_doctor', $this, 'save', 10, 2 );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_scripts' );
	}

	// ─── Add Meta Boxes ───────────────────────────────────────────────────────

	public function add_meta_boxes(): void {
		add_meta_box(
			'stmc-doctor-profile',
			__( '⚕️ اطلاعات پروفایل پزشک', STMC_TEXT ),
			[ $this, 'render_meta_box' ],
			'doctor',
			'normal',
			'high'
		);

		add_meta_box(
			'stmc-doctor-seo-preview',
			__( '🔍 پیش‌نمایش Schema', STMC_TEXT ),
			[ $this, 'render_schema_preview' ],
			'doctor',
			'side',
			'default'
		);
	}

	// ─── Render ───────────────────────────────────────────────────────────────

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'stmc_doctor_meta_nonce', 'stmc_doctor_meta_nonce' );

		echo '<div class="stmc-meta-tabs">';
		echo '<nav class="stmc-meta-tabs__nav">';

		$tabs = [
			'basic'    => __( 'اطلاعات پایه', STMC_TEXT ),
			'edu'      => __( 'تحصیلات و مدارک', STMC_TEXT ),
			'contact'  => __( 'تماس و رزرو', STMC_TEXT ),
			'location' => __( 'موقعیت', STMC_TEXT ),
		];

		foreach ( $tabs as $tab_id => $tab_label ) {
			printf(
				'<button type="button" class="stmc-tab-btn%s" data-tab="%s">%s</button>',
				'basic' === $tab_id ? ' is-active' : '',
				esc_attr( $tab_id ),
				esc_html( $tab_label )
			);
		}

		echo '</nav>';
		echo '<div class="stmc-meta-tabs__content">';

		// Group fields by tab
		$groups = [
			'basic'    => [ 'stmc_doctor_title', 'stmc_doctor_specialty', 'stmc_doctor_subspecialty', 'stmc_doctor_license_no', 'stmc_doctor_experience_yrs', 'stmc_doctor_patients_count', 'stmc_doctor_languages' ],
			'edu'      => [ 'stmc_doctor_education', 'stmc_doctor_fellowship', 'stmc_doctor_certifications', 'stmc_doctor_memberships' ],
			'contact'  => [ 'stmc_doctor_phone', 'stmc_doctor_whatsapp', 'stmc_doctor_booking_url', 'stmc_doctor_video_url', 'stmc_doctor_clinic_ids' ],
			'location' => [ 'stmc_doctor_lat', 'stmc_doctor_lng' ],
		];

		foreach ( $groups as $tab_id => $field_keys ) {
			printf( '<div class="stmc-tab-panel%s" data-panel="%s">', 'basic' === $tab_id ? ' is-active' : '', esc_attr( $tab_id ) );
			echo '<div class="stmc-meta-grid">';

			foreach ( self::FIELDS as $field ) {
				if ( ! in_array( $field['key'], $field_keys, true ) ) {
					continue;
				}
				$value = get_post_meta( $post->ID, $field['key'], true );
				$this->render_field( $field, $value );
			}

			echo '</div></div>';
		}

		echo '</div></div>';
	}

	/**
	 * رندر یک فیلد meta
	 */
	private function render_field( array $field, mixed $value ): void {
		$key   = esc_attr( $field['key'] );
		$label = esc_html( $field['label'] );
		$desc  = isset( $field['description'] ) ? '<p class="description">' . esc_html( $field['description'] ) . '</p>' : '';

		echo '<div class="stmc-meta-field">';
		printf( '<label for="%s" class="stmc-meta-field__label">%s</label>', $key, $label );

		switch ( $field['type'] ) {
			case 'text':
			case 'tel':
			case 'url':
				printf(
					'<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" class="stmc-meta-input widefat">',
					esc_attr( $field['type'] ),
					$key, $key,
					esc_attr( (string) $value ),
					esc_attr( $field['placeholder'] ?? '' )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" class="stmc-meta-input stmc-meta-input--number">',
					$key, $key,
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? 0 ) ),
					esc_attr( (string) ( $field['max'] ?? 999 ) )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="%d" placeholder="%s" class="stmc-meta-input widefat">%s</textarea>',
					$key, $key,
					absint( $field['rows'] ?? 3 ),
					esc_attr( $field['placeholder'] ?? '' ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				echo '<select id="' . $key . '" name="' . $key . '" class="stmc-meta-input">';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $opt_val ),
						selected( $value, $opt_val, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'checkboxes':
				$saved = is_array( $value ) ? $value : ( $value ? explode( ',', (string) $value ) : [] );
				echo '<div class="stmc-meta-checkboxes">';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf(
						'<label class="stmc-meta-checkbox"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
						esc_attr( $field['key'] ),
						esc_attr( $opt_val ),
						checked( in_array( $opt_val, $saved, true ), true, false ),
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			case 'post_select':
				$posts = get_posts( [
					'post_type'      => $field['post_type'],
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				] );
				echo '<select id="' . $key . '" name="' . $key . '[]" class="stmc-meta-input" multiple size="4">';
				$saved_ids = is_array( $value ) ? array_map( 'absint', $value ) : [];
				foreach ( $posts as $p ) {
					printf(
						'<option value="%d"%s>%s</option>',
						$p->ID,
						in_array( $p->ID, $saved_ids, true ) ? ' selected' : '',
						esc_html( $p->post_title )
					);
				}
				echo '</select>';
				break;
		}

		echo $desc;
		echo '</div>';
	}

	// ─── Schema Preview ───────────────────────────────────────────────────────

	public function render_schema_preview( \WP_Post $post ): void {
		$name      = get_the_title( $post );
		$specialty = get_post_meta( $post->ID, 'stmc_doctor_specialty', true );
		$url       = get_permalink( $post );

		echo '<div class="stmc-schema-preview">';
		echo '<p style="font-size:11px;color:#666;">' . __( 'Schema JSON-LD که در head صفحه اضافه می‌شود:', STMC_TEXT ) . '</p>';
		echo '<pre style="font-size:10px;background:#f0f0f0;padding:8px;overflow:auto;max-height:200px;">';
		echo esc_html( wp_json_encode( [
			'@context'         => 'https://schema.org',
			'@type'            => 'Physician',
			'name'             => $name ?: 'نام پزشک',
			'medicalSpecialty' => $specialty ?: 'تخصص',
			'url'              => $url ?: home_url( '/doctor/' ),
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
		echo '</pre>';
		echo '</div>';
	}

	// ─── Save ─────────────────────────────────────────────────────────────────

	public function save( int $post_id, \WP_Post $post ): void {
		// Security checks
		if ( ! isset( $_POST['stmc_doctor_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stmc_doctor_meta_nonce'] ) ), 'stmc_doctor_meta_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save each field
		foreach ( self::FIELDS as $field ) {
			$key = $field['key'];

			if ( ! isset( $_POST[ $key ] ) ) {
				if ( 'checkboxes' === $field['type'] || 'post_select' === $field['type'] ) {
					delete_post_meta( $post_id, $key );
				}
				continue;
			}

			$raw = $_POST[ $key ]; // phpcs:ignore WordPress.Security.NonceVerification

			$value = match ( $field['type'] ) {
				'number'                     => absint( $raw ),
				'url'                        => esc_url_raw( wp_unslash( $raw ) ),
				'tel'                        => preg_replace( '/[^0-9+\-\s()]/', '', sanitize_text_field( wp_unslash( $raw ) ) ),
				'textarea'                   => sanitize_textarea_field( wp_unslash( $raw ) ),
				'checkboxes', 'post_select'  => is_array( $raw ) ? array_map( 'sanitize_text_field', wp_unslash( $raw ) ) : [],
				'select'                     => sanitize_key( $raw ),
				default                      => sanitize_text_field( wp_unslash( $raw ) ),
			};

			update_post_meta( $post_id, $key, $value );
		}
	}

	// ─── Scripts ──────────────────────────────────────────────────────────────

	public function enqueue_scripts( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'doctor' !== $screen->post_type ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->admin_css() );
		wp_add_inline_script( 'jquery', $this->admin_js() );
	}

	private function admin_css(): string {
		return '
		.stmc-meta-tabs__nav { display:flex; gap:4px; border-bottom:1px solid #ddd; margin-bottom:16px; }
		.stmc-tab-btn { padding:8px 16px; border:none; background:transparent; cursor:pointer; font-size:13px; border-bottom:2px solid transparent; color:#666; }
		.stmc-tab-btn.is-active { color:#2271b1; border-bottom-color:#2271b1; font-weight:600; }
		.stmc-tab-panel { display:none; }
		.stmc-tab-panel.is-active { display:block; }
		.stmc-meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
		.stmc-meta-field { display:flex; flex-direction:column; gap:4px; }
		.stmc-meta-field__label { font-weight:600; font-size:12px; color:#333; }
		.stmc-meta-input { border:1px solid #ddd; border-radius:4px; padding:6px 10px; font-size:13px; }
		.stmc-meta-input--number { width:120px; }
		.stmc-meta-checkboxes { display:flex; flex-wrap:wrap; gap:12px; }
		.stmc-meta-checkbox { display:flex; align-items:center; gap:6px; font-size:13px; cursor:pointer; }
		';
	}

	private function admin_js(): string {
		return '
		document.addEventListener("DOMContentLoaded",function(){
			var btns = document.querySelectorAll(".stmc-tab-btn");
			btns.forEach(function(btn){
				btn.addEventListener("click",function(){
					btns.forEach(function(b){ b.classList.remove("is-active"); });
					document.querySelectorAll(".stmc-tab-panel").forEach(function(p){ p.classList.remove("is-active"); });
					btn.classList.add("is-active");
					var panel = document.querySelector(".stmc-tab-panel[data-panel=\""+btn.dataset.tab+"\"]");
					if(panel) panel.classList.add("is-active");
				});
			});
		});
		';
	}
}
