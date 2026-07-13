<?php
/**
 * SignTeb Setup Wizard — 8-Step Setup Runner (موتور راه‌اندازی خودکار)
 *
 * ارکستریشن ۸ مرحله‌ی راه‌اندازی به‌سبک Astra/Kadence:
 *   1. environment — بررسی محیط سرور
 *   2. plugins     — نصب/فعال‌سازی افزونه‌های لازم
 *   3. demo        — ایمپورت محتوای دمو
 *   4. options     — ایمپورت تنظیمات قالب
 *   5. menus       — ساخت منوها
 *   6. home        — تعیین صفحه‌ی خانه
 *   7. blog        — تعیین صفحه‌ی بلاگ
 *   8. finish      — پایان
 *
 * هر مرحله idempotent است (اجرای دوباره خرابی نمی‌سازد) و وضعیت در یک option
 * ذخیره می‌شود تا فرآیند resumable باشد (اگر وسط کار قطع شد، از همان‌جا ادامه).
 *
 * @package SignTeb_Wizard
 */

declare( strict_types=1 );

namespace SignTeb\Wizard\Setup;

use SignTeb\Wizard\DemoImporter;

defined( 'ABSPATH' ) || exit;

final class SetupRunner {

	public const STATE_OPTION = 'stwiz_setup_state';

	private const STEPS = [ 'environment', 'plugins', 'demo', 'options', 'menus', 'home', 'blog', 'finish' ];

	/** برچسب فارسی هر مرحله (برای UI). */
	public const LABELS = [
		'environment' => 'بررسی محیط سرور',
		'plugins'     => 'نصب و فعال‌سازی افزونه‌ها',
		'demo'        => 'ایمپورت محتوای دمو',
		'options'     => 'ایمپورت تنظیمات قالب',
		'menus'       => 'ساخت منوها',
		'home'        => 'تعیین صفحه‌ی خانه',
		'blog'        => 'تعیین صفحه‌ی بلاگ',
		'finish'      => 'اتمام راه‌اندازی',
	];

	/** افزونه‌های همراهِ لازم. */
	private const REQUIRED_PLUGINS = [
		'signteb-medical-core/signteb-medical-core.php',
		'signteb-blocks/signteb-blocks.php',
	];

	/** @return string[] */
	public function steps(): array {
		return self::STEPS;
	}

	/**
	 * اجرای یک مرحله بر اساس کلید. نتیجه در state ذخیره می‌شود.
	 */
	public function run_step( string $key, array $args = [] ): StepResult {
		$result = match ( $key ) {
			'environment' => $this->step_environment(),
			'plugins'     => $this->step_plugins(),
			'demo'        => $this->step_demo( $args ),
			'options'     => $this->step_options( $args ),
			'menus'       => $this->step_menus(),
			'home'        => $this->step_home(),
			'blog'        => $this->step_blog(),
			'finish'      => $this->step_finish( $args ),
			default       => StepResult::fail( sprintf( 'مرحله نامعتبر: %s', $key ) ),
		};

		$this->save_state( $key, $result );
		return $result;
	}

	// ─── Steps ─────────────────────────────────────────────────────────────────

	private function step_environment(): StepResult {
		$env    = ( new EnvironmentCheck() )->run();
		$failed = array_filter( $env['checks'], static fn( $c ) => $c['fatal'] && ! $c['pass'] );

		if ( ! $env['pass'] ) {
			$names = implode( '، ', array_map( static fn( $c ) => $c['label'], $failed ) );
			return StepResult::fail( 'برخی پیش‌نیازهای ضروری برقرار نیستند: ' . $names, $env );
		}
		return StepResult::ok( 'محیط سرور آماده است.', $env );
	}

	private function step_plugins(): StepResult {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$activated = [];
		$already   = [];
		$missing   = [];

		foreach ( self::REQUIRED_PLUGINS as $plugin ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin;
			if ( ! file_exists( $path ) ) {
				$missing[] = $plugin;
				continue;
			}
			if ( is_plugin_active( $plugin ) ) {
				$already[] = $plugin;
				continue;
			}
			$res = activate_plugin( $plugin );
			if ( is_wp_error( $res ) ) {
				return StepResult::fail( 'فعال‌سازی افزونه ناموفق بود: ' . $plugin . ' — ' . $res->get_error_message() );
			}
			$activated[] = $plugin;
		}

		$msg = sprintf( '%d افزونه فعال شد، %d از قبل فعال بود.', count( $activated ), count( $already ) );
		if ( $missing ) {
			// افزونه‌های همراه معمولاً توسط نصب‌کننده نصب شده‌اند؛ نبودشان مانع ادامه نیست.
			$msg .= sprintf( ' (%d افزونه یافت نشد و رد شد)', count( $missing ) );
		}
		return StepResult::ok( $msg, compact( 'activated', 'already', 'missing' ) );
	}

	private function step_demo( array $args ): StepResult {
		$type = $this->demo_type( $args );
		if ( ! DemoImporter::is_valid_type( $type ) ) {
			return StepResult::fail( 'نوع دمو نامعتبر است.' );
		}

		$importer = $this->importer();
		$importer->create_doctor( $type );
		$home_id = $importer->create_pages( $type );

		// شناسه‌ی صفحه‌ی خانه را برای مرحله‌ی home در state نگه می‌داریم.
		$this->set_state_value( 'home_id', $home_id );
		$this->set_state_value( 'demo_type', $type );

		return StepResult::ok( 'محتوای دمو ایمپورت شد.', [ 'home_id' => $home_id ] );
	}

	private function step_options( array $args ): StepResult {
		$type = $this->demo_type( $args );
		$this->importer()->apply_options( $type );
		return StepResult::ok( 'تنظیمات قالب اعمال شد.' );
	}

	private function step_menus(): StepResult {
		$this->importer()->create_menus();
		return StepResult::ok( 'منوها ساخته و به جایگاه اصلی اختصاص یافتند.' );
	}

	private function step_home(): StepResult {
		$home_id = (int) $this->get_state_value( 'home_id', 0 );
		$assigned = $this->importer()->assign_front_page( $home_id );
		if ( ! $assigned ) {
			return StepResult::fail( 'صفحه‌ی خانه یافت نشد؛ ابتدا مرحله‌ی دمو باید اجرا شود.' );
		}
		return StepResult::ok( 'صفحه‌ی خانه تعیین شد.', [ 'home_id' => $assigned ] );
	}

	private function step_blog(): StepResult {
		$blog_id = $this->importer()->assign_blog_page();
		if ( ! $blog_id ) {
			return StepResult::fail( 'ساخت صفحه‌ی بلاگ ناموفق بود.' );
		}
		return StepResult::ok( 'صفحه‌ی بلاگ تعیین شد.', [ 'blog_id' => $blog_id ] );
	}

	private function step_finish( array $args ): StepResult {
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
		$type = $this->demo_type( $args );
		update_option( 'stwiz_demo_installed', $type );
		update_option( 'stwiz_setup_complete', gmdate( 'Y-m-d H:i:s' ) );
		return StepResult::ok( 'راه‌اندازی با موفقیت کامل شد.' );
	}

	// ─── Helpers ───────────────────────────────────────────────────────────────

	private function demo_type( array $args ): string {
		$type = isset( $args['demo'] ) ? (string) $args['demo'] : '';
		if ( '' === $type ) {
			$type = (string) $this->get_state_value( 'demo_type', 'solo-doctor' );
		}
		return $type;
	}

	private function importer(): DemoImporter {
		require_once STWIZ_DIR . 'includes/class-wizard-demo-importer.php';
		return new DemoImporter();
	}

	// ─── State (resumable) ──────────────────────────────────────────────────────

	public function get_state(): array {
		$state = get_option( self::STATE_OPTION, [] );
		return is_array( $state ) ? $state : [];
	}

	private function save_state( string $key, StepResult $result ): void {
		$state = $this->get_state();
		$state['steps'][ $key ] = [
			'success' => $result->success,
			'message' => $result->message,
			'time'    => gmdate( 'Y-m-d H:i:s' ),
		];
		update_option( self::STATE_OPTION, $state );
	}

	private function set_state_value( string $key, $value ): void {
		$state = $this->get_state();
		$state['values'][ $key ] = $value;
		update_option( self::STATE_OPTION, $state );
	}

	private function get_state_value( string $key, $default = null ) {
		$state = $this->get_state();
		return $state['values'][ $key ] ?? $default;
	}
}
