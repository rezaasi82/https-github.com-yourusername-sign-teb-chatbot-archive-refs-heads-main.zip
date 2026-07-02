<?php
/**
 * SignTeb Medical Core — Settings Page
 */
declare( strict_types=1 );
namespace STMC\Admin;
use STMC\Loader;
defined( 'ABSPATH' ) || exit;

final class Settings {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'admin_init', $this, 'register_settings' );
		$this->loader->add_action( 'wp_ajax_stmc_test_sms', $this, 'ajax_test_sms' );
	}

	public function register_settings(): void {
		$options = [
			'stmc_clinic_name',
			'stmc_clinic_phone',
			'stmc_clinic_whatsapp',
			'stmc_clinic_email',
			'stmc_clinic_address',
			'stmc_appointment_email',
			'stmc_schema_enabled',
			'stmc_internal_links',
			'stmc_market',
			'stmc_primary_language',
			'stmc_country_code',
			'stmc_geo_region',
			'stmc_geo_placename',
			'stmc_geo_position',
			'stmc_social_instagram',
			'stmc_social_linkedin',
			'stmc_social_youtube',
			// ── SMS / ملی‌پیامک ──
			'stmc_sms_enabled',
			'stmc_sms_username',
			'stmc_sms_password',
			'stmc_sms_sender_line',
			'stmc_sms_confirmation_enabled',
			'stmc_sms_reminder_24h_enabled',
			'stmc_sms_reminder_2h_enabled',
			// ── Booking ──
			'stmc_default_slot_minutes',
			'stmc_booking_lead_hours',
			'stmc_booking_max_days',
		];

		foreach ( $options as $option ) {
			register_setting( 'stmc_settings_group', $option, [ 'sanitize_callback' => 'sanitize_text_field' ] );
		}
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['stmc_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stmc_settings_nonce'] ) ), 'stmc_settings_save' ) ) {
			self::save_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( '✅ تنظیمات ذخیره شد.', STMC_TEXT ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1>⚙️ <?php esc_html_e( 'تنظیمات SignTeb MedCore', STMC_TEXT ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'stmc_settings_save', 'stmc_settings_nonce' ); ?>

				<h2><?php esc_html_e( 'اطلاعات کلینیک / مطب', STMC_TEXT ); ?></h2>
				<table class="form-table">
					<?php
					$fields = [
						[ 'stmc_clinic_name',      __( 'نام کلینیک / مطب', STMC_TEXT ),       'text' ],
						[ 'stmc_clinic_phone',     __( 'تلفن', STMC_TEXT ),                   'tel'  ],
						[ 'stmc_clinic_whatsapp',  __( 'WhatsApp', STMC_TEXT ),               'tel'  ],
						[ 'stmc_clinic_email',     __( 'ایمیل مدیر', STMC_TEXT ),             'email'],
						[ 'stmc_clinic_address',   __( 'آدرس', STMC_TEXT ),                   'text' ],
						[ 'stmc_appointment_email',__( 'ایمیل دریافت نوبت', STMC_TEXT ),     'email'],
					];
					foreach ( $fields as [$key, $label, $type] ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
								value="<?php echo esc_attr( get_option( $key, '' ) ); ?>" class="regular-text">
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'SEO و بازار', STMC_TEXT ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'بازار هدف', STMC_TEXT ); ?></th>
						<td>
							<select name="stmc_market">
								<option value="ir" <?php selected( get_option('stmc_market'), 'ir' ); ?>><?php esc_html_e( 'ایران (FA)', STMC_TEXT ); ?></option>
								<option value="ae" <?php selected( get_option('stmc_market'), 'ae' ); ?>><?php esc_html_e( 'امارات (EN/AR)', STMC_TEXT ); ?></option>
								<option value="multi" <?php selected( get_option('stmc_market'), 'multi' ); ?>><?php esc_html_e( 'چندزبانه', STMC_TEXT ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schema JSON-LD', STMC_TEXT ); ?></th>
						<td><label><input type="checkbox" name="stmc_schema_enabled" value="1" <?php checked( get_option( 'stmc_schema_enabled', '1' ), '1' ); ?>>
						<?php esc_html_e( 'فعال', STMC_TEXT ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'لینک‌دهی داخلی خودکار', STMC_TEXT ); ?></th>
						<td><label><input type="checkbox" name="stmc_internal_links" value="1" <?php checked( get_option( 'stmc_internal_links', '1' ), '1' ); ?>>
						<?php esc_html_e( 'فعال', STMC_TEXT ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_geo_region"><?php esc_html_e( 'کد منطقه Geo', STMC_TEXT ); ?></label></th>
						<td><input type="text" id="stmc_geo_region" name="stmc_geo_region"
							value="<?php echo esc_attr( get_option( 'stmc_geo_region', '' ) ); ?>"
							placeholder="IR-16" class="regular-text">
						<p class="description"><?php esc_html_e( 'مثال: IR-16 برای تهران، AE-DU برای دبی', STMC_TEXT ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_geo_placename"><?php esc_html_e( 'نام شهر', STMC_TEXT ); ?></label></th>
						<td><input type="text" id="stmc_geo_placename" name="stmc_geo_placename"
							value="<?php echo esc_attr( get_option( 'stmc_geo_placename', '' ) ); ?>"
							placeholder="تهران" class="regular-text"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'شبکه‌های اجتماعی', STMC_TEXT ); ?></h2>
				<table class="form-table">
					<?php
					$socials = [
						[ 'stmc_social_instagram', 'Instagram URL' ],
						[ 'stmc_social_linkedin',  'LinkedIn URL'  ],
						[ 'stmc_social_youtube',   'YouTube URL'   ],
					];
					foreach ( $socials as [$key, $label] ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input type="url" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
							value="<?php echo esc_attr( get_option( $key, '' ) ); ?>" class="regular-text" placeholder="https://..."></td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2>📱 <?php esc_html_e( 'پیامک — ملی‌پیامک', STMC_TEXT ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'فعال‌سازی SMS', STMC_TEXT ); ?></th>
						<td><label><input type="checkbox" name="stmc_sms_enabled" value="1" <?php checked( get_option( 'stmc_sms_enabled', '0' ), '1' ); ?>>
						<?php esc_html_e( 'فعال', STMC_TEXT ); ?></label>
						<p class="description"><?php esc_html_e( 'برای دریافت اطلاعات حساب از panel.payamak-panel.com اقدام کنید.', STMC_TEXT ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_sms_username"><?php esc_html_e( 'نام کاربری', STMC_TEXT ); ?></label></th>
						<td><input type="text" id="stmc_sms_username" name="stmc_sms_username"
							value="<?php echo esc_attr( get_option( 'stmc_sms_username', '' ) ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_sms_password"><?php esc_html_e( 'رمز عبور', STMC_TEXT ); ?></label></th>
						<td><input type="password" id="stmc_sms_password" name="stmc_sms_password"
							value="<?php echo esc_attr( get_option( 'stmc_sms_password', '' ) ); ?>" class="regular-text" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_sms_sender_line"><?php esc_html_e( 'شماره خط ارسال', STMC_TEXT ); ?></label></th>
						<td><input type="text" id="stmc_sms_sender_line" name="stmc_sms_sender_line"
							value="<?php echo esc_attr( get_option( 'stmc_sms_sender_line', '' ) ); ?>" class="regular-text" placeholder="مثال: 09982004676"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'پیامک‌های فعال', STMC_TEXT ); ?></th>
						<td>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="stmc_sms_confirmation_enabled" value="1" <?php checked( get_option( 'stmc_sms_confirmation_enabled', '1' ), '1' ); ?>> <?php esc_html_e( 'تأیید ثبت نوبت (فوری)', STMC_TEXT ); ?></label>
							<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="stmc_sms_reminder_24h_enabled" value="1" <?php checked( get_option( 'stmc_sms_reminder_24h_enabled', '1' ), '1' ); ?>> <?php esc_html_e( 'یادآوری ۲۴ ساعت قبل', STMC_TEXT ); ?></label>
							<label style="display:block;"><input type="checkbox" name="stmc_sms_reminder_2h_enabled" value="1" <?php checked( get_option( 'stmc_sms_reminder_2h_enabled', '1' ), '1' ); ?>> <?php esc_html_e( 'یادآوری ۲ ساعت قبل', STMC_TEXT ); ?></label>
						</td>
					</tr>
				</table>

				<h2>📅 <?php esc_html_e( 'تنظیمات رزرو نوبت', STMC_TEXT ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stmc_default_slot_minutes"><?php esc_html_e( 'مدت پیش‌فرض هر نوبت', STMC_TEXT ); ?></label></th>
						<td>
							<select id="stmc_default_slot_minutes" name="stmc_default_slot_minutes">
								<?php foreach ( [ 15, 20, 30, 45, 60 ] as $min ) : ?>
									<option value="<?php echo $min; ?>" <?php selected( (int) get_option( 'stmc_default_slot_minutes', 30 ), $min ); ?>><?php echo $min; ?> <?php esc_html_e( 'دقیقه', STMC_TEXT ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_booking_lead_hours"><?php esc_html_e( 'حداقل فاصله تا نوبت', STMC_TEXT ); ?></label></th>
						<td><input type="number" id="stmc_booking_lead_hours" name="stmc_booking_lead_hours" min="0" max="48"
							value="<?php echo esc_attr( get_option( 'stmc_booking_lead_hours', 2 ) ); ?>" class="small-text"> <?php esc_html_e( 'ساعت', STMC_TEXT ); ?>
							<p class="description"><?php esc_html_e( 'برای نوبت‌های امروز، حداقل چند ساعت قبل باید رزرو شود.', STMC_TEXT ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="stmc_booking_max_days"><?php esc_html_e( 'حداکثر بازه رزرو آینده', STMC_TEXT ); ?></label></th>
						<td><input type="number" id="stmc_booking_max_days" name="stmc_booking_max_days" min="1" max="180"
							value="<?php echo esc_attr( get_option( 'stmc_booking_max_days', 30 ) ); ?>" class="small-text"> <?php esc_html_e( 'روز', STMC_TEXT ); ?></td>
					</tr>
				</table>

				<?php submit_button( __( 'ذخیره تنظیمات', STMC_TEXT ) ); ?>
			</form>

			<!-- Test SMS panel (separate form, AJAX) -->
			<?php if ( '1' === get_option( 'stmc_sms_enabled', '0' ) ) : ?>
			<div class="postbox" style="padding:16px 20px;max-width:480px;margin-top:24px;">
				<h3>🧪 <?php esc_html_e( 'تست ارسال پیامک', STMC_TEXT ); ?></h3>
				<p>
					<input type="text" id="stmc-test-sms-phone" placeholder="09xxxxxxxxx" class="regular-text">
					<button type="button" id="stmc-test-sms-btn" class="button button-secondary"><?php esc_html_e( 'ارسال تست', STMC_TEXT ); ?></button>
				</p>
				<p id="stmc-test-sms-result" style="font-size:13px;"></p>
			</div>
			<script>
			document.getElementById('stmc-test-sms-btn')?.addEventListener('click', function() {
				var phone  = document.getElementById('stmc-test-sms-phone').value;
				var result = document.getElementById('stmc-test-sms-result');
				result.textContent = '<?php echo esc_js( __( 'در حال ارسال...', STMC_TEXT ) ); ?>';

				fetch(ajaxurl, {
					method: 'POST',
					headers: {'Content-Type':'application/x-www-form-urlencoded'},
					body: new URLSearchParams({
						action: 'stmc_test_sms',
						nonce: '<?php echo esc_js( wp_create_nonce( 'stmc_test_sms_nonce' ) ); ?>',
						phone: phone
					})
				}).then(r => r.json()).then(data => {
					result.textContent = data.success ? '✅ ' + data.data.message : '❌ ' + data.data.message;
					result.style.color = data.success ? '#16a34a' : '#dc2626';
				});
			});
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$keys = [
			'stmc_clinic_name', 'stmc_clinic_phone', 'stmc_clinic_whatsapp',
			'stmc_clinic_email', 'stmc_clinic_address', 'stmc_appointment_email',
			'stmc_market', 'stmc_geo_region', 'stmc_geo_placename',
			'stmc_social_instagram', 'stmc_social_linkedin', 'stmc_social_youtube',
			'stmc_sms_username', 'stmc_sms_sender_line',
			'stmc_default_slot_minutes', 'stmc_booking_lead_hours', 'stmc_booking_max_days',
		];

		foreach ( $keys as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		// رمز عبور SMS — فقط اگر مقدار جدید وارد شده باشد، تا با مقدار خالی پاک نشود
		if ( ! empty( $_POST['stmc_sms_password'] ) ) {
			update_option( 'stmc_sms_password', sanitize_text_field( wp_unslash( $_POST['stmc_sms_password'] ) ) );
		}

		// Checkboxes
		update_option( 'stmc_schema_enabled', ! empty( $_POST['stmc_schema_enabled'] ) ? '1' : '' );
		update_option( 'stmc_internal_links', ! empty( $_POST['stmc_internal_links'] ) ? '1' : '' );
		update_option( 'stmc_sms_enabled', ! empty( $_POST['stmc_sms_enabled'] ) ? '1' : '0' );
		update_option( 'stmc_sms_confirmation_enabled', ! empty( $_POST['stmc_sms_confirmation_enabled'] ) ? '1' : '0' );
		update_option( 'stmc_sms_reminder_24h_enabled', ! empty( $_POST['stmc_sms_reminder_24h_enabled'] ) ? '1' : '0' );
		update_option( 'stmc_sms_reminder_2h_enabled', ! empty( $_POST['stmc_sms_reminder_2h_enabled'] ) ? '1' : '0' );
	}

	// ─── AJAX: Test SMS ──────────────────────────────────────────────────────

	public function ajax_test_sms(): void {
		check_ajax_referer( 'stmc_test_sms_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'دسترسی غیر مجاز', STMC_TEXT ) ], 403 );
		}

		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
			wp_send_json_error( [ 'message' => __( 'شماره موبایل نامعتبر است (فرمت: 09xxxxxxxxx)', STMC_TEXT ) ] );
		}

		$client = new \STMC\Sms\MeliPayamakClient();
		if ( ! $client->is_configured() ) {
			wp_send_json_error( [ 'message' => __( 'ابتدا تنظیمات SMS را کامل و ذخیره کنید.', STMC_TEXT ) ] );
		}

		$clinic = get_option( 'stmc_clinic_name', get_bloginfo( 'name' ) );
		$result = $client->send( $phone, "پیامک تست از سیستم نوبت‌دهی {$clinic} — اتصال با موفقیت برقرار شد ✅" );

		if ( $result['success'] ) {
			wp_send_json_success( [ 'message' => __( 'پیامک تست با موفقیت ارسال شد.', STMC_TEXT ) ] );
		}

		wp_send_json_error( [ 'message' => __( 'ارسال ناموفق: ', STMC_TEXT ) . $result['message'] ] );
	}
}
