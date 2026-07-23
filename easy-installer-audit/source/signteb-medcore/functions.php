<?php
/**
 * SignTeb MedCore — Theme Bootstrap
 *
 * این فایل فقط و فقط یک کار انجام می‌دهد:
 * بارگذاری ماژول‌های inc/ به ترتیب صحیح
 *
 * هیچ منطق اضافه‌ای در این فایل نباید نوشته شود.
 *
 * @package    SignTeb_MedCore
 * @version    1.0.0
 * @author     SignTeb <hello@signteb.com>
 * @license    GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────

define( 'MEDCORE_VERSION',   '1.1.3' );
define( 'MEDCORE_DIR',       get_template_directory() );
define( 'MEDCORE_URI',       get_template_directory_uri() );
define( 'MEDCORE_INC',       MEDCORE_DIR . '/inc/' );
define( 'MEDCORE_ASSETS',    MEDCORE_URI . '/assets/' );
define( 'MEDCORE_TEXT',      'signteb-medcore' );
define( 'MEDCORE_MIN_PHP',   '8.1' );
define( 'MEDCORE_MIN_WP',    '6.4' );

// ── PHP version check ─────────────────────────────────────────────────────────

if ( version_compare( PHP_VERSION, MEDCORE_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'SignTeb MedCore نیاز به PHP %1$s یا بالاتر دارد. نسخه فعلی شما %2$s است.', 'signteb-medcore' ),
				esc_html( MEDCORE_MIN_PHP ),
				esc_html( PHP_VERSION )
			)
		);
	} );
	return;
}

// ── Logger (must load first, no dependencies) ────────────────────────────────

require_once MEDCORE_INC . 'class-medcore-logger.php';

// ── PSR-4 Autoloader + Service Container (معماری هدف — فاز ۱) ─────────────────
// کد جدید زیر فضای‌نام SignTeb\MedCore\ در inc/src/ نوشته می‌شود و خودکار
// بارگذاری می‌شود؛ ماژول‌های procedural موجود بدون تغییر و در کنار آن کار می‌کنند
// (الگوی strangler — مهاجرت افزایشی و کم‌ریسک به معماری جدید).
require_once MEDCORE_INC . 'src/Core/Autoloader.php';
( new \SignTeb\MedCore\Core\Autoloader( 'SignTeb\\MedCore\\', MEDCORE_INC . 'src/' ) )->register();

$GLOBALS['medcore_container'] = new \SignTeb\MedCore\Core\Container();

// ── Load modules in dependency order (defensively) ───────────────────────────
//
// هر ماژول در یک try/catch ایزوله بارگذاری می‌شود. در PHP 7+ حتی خطاهای
// «فاتال» مثل Class not found و ParseError از نوع \Throwable هستند و داخل
// بلوک try قابل گرفتن‌اند؛ پس اگر یک ماژول خراب باشد، به‌جای «صفحه سفید»
// روی کل سایت، فقط همان ماژول رد می‌شود، خطا لاگ می‌شود، و بقیه‌ی قالب به
// کار خود ادامه می‌دهد (graceful degradation). ماژول‌های حیاتی جداگانه
// علامت‌گذاری می‌شوند تا در صورت شکست، به مدیر هشدار داده شود.

$medcore_modules = [
	'helpers.php'                      => true,  // 1. Utility functions (critical)
	'class-medcore-setup.php'          => true,  // 2. Theme supports, menus (critical)
	'class-medcore-enqueue.php'        => true,  // 3. Scripts + Styles (critical)
	'class-medcore-template-tags.php'  => true,  // 4. Template helpers (critical)
	// 5. Customizer اکنون از طریق SignTeb\MedCore\Customize (پایین) بوت می‌شود.
	'class-medcore-block-patterns.php' => false, // 6. Block patterns (non-critical)
	'class-medcore-nav-walker.php'     => false, // 7. Nav walker (non-critical)
];

$medcore_failed = [];

foreach ( $medcore_modules as $module => $is_critical ) {
	$path = MEDCORE_INC . $module;

	if ( ! file_exists( $path ) ) {
		MedCore_Logger::log( 'Missing module: ' . $module, $is_critical ? 'error' : 'warning' );
		if ( $is_critical ) {
			$medcore_failed[] = $module;
		}
		continue;
	}

	try {
		require_once $path;
	} catch ( \Throwable $e ) {
		// خطای فاتال/parse در این ماژول گرفته شد — سایت سفید نمی‌شود.
		MedCore_Logger::log(
			'Module failed to load: ' . $module,
			$is_critical ? 'error' : 'warning',
			[
				'error' => $e->getMessage(),
				'file'  => basename( $e->getFile() ),
				'line'  => $e->getLine(),
			]
		);
		if ( $is_critical ) {
			$medcore_failed[] = $module;
		}
	}
}

// اگر ماژول حیاتی‌ای شکست خورد، به‌جای صفحه‌ی سفید، به مدیر در پیشخوان هشدار بده.
if ( $medcore_failed ) {
	add_action( 'admin_notices', function () use ( $medcore_failed ) {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong><br>%s<br><code>%s</code></p></div>',
			esc_html__( 'SignTeb MedCore: برخی بخش‌های قالب بارگذاری نشدند.', 'signteb-medcore' ),
			esc_html__( 'سایت در حالت ایمن اجرا می‌شود تا از «صفحه سفید» جلوگیری شود. برای جزئیات، حالت دیباگ را فعال کرده و لاگ‌ها را در wp-content/uploads/signteb-logs بررسی کنید.', 'signteb-medcore' ),
			esc_html( implode( ', ', $medcore_failed ) )
		);
	} );
}

unset( $medcore_modules, $module, $is_critical, $path, $medcore_failed );

// ── Integrations (namespaced services, booted via container) ─────────────────
// هر ادغام در try/catch ایزوله بوت می‌شود تا هرگز باعث صفحه‌ی سفید نشود.
try {
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Integration\Elementor::class,
		static fn() => new \SignTeb\MedCore\Integration\Elementor()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Integration\Elementor::class )->register();

	// پل Theme Builder المنتور Pro (هدر/فوتر قابل‌ویرایش روی قالب FSE).
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Integration\ElementorLocations::class,
		static fn() => new \SignTeb\MedCore\Integration\ElementorLocations()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Integration\ElementorLocations::class )->register();
} catch ( \Throwable $e ) {
	MedCore_Logger::log( 'Elementor integration failed to boot', 'warning', [ 'error' => $e->getMessage() ] );
}

// ── Frontend VIP (منوی پایدار هدر + هاب ارتباطی شناور) ───────────────────────
try {
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Frontend\NavMenu::class,
		static fn() => new \SignTeb\MedCore\Frontend\NavMenu()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Frontend\NavMenu::class )->register();

	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Frontend\FloatingHub::class,
		static fn() => new \SignTeb\MedCore\Frontend\FloatingHub()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Frontend\FloatingHub::class )->register();

	// کلاس body مختص دموی فعال (هویت بصری مستقل هر دمو).
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Frontend\DemoStyle::class,
		static fn() => new \SignTeb\MedCore\Frontend\DemoStyle()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Frontend\DemoStyle::class )->register();
} catch ( \Throwable $e ) {
	MedCore_Logger::log( 'Frontend VIP failed to boot', 'warning', [ 'error' => $e->getMessage() ] );
}

// ── Customizer + Design Tokens (فاز ۵) ───────────────────────────────────────
try {
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Customize\DesignTokens::class,
		static fn() => new \SignTeb\MedCore\Customize\DesignTokens()
	);
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Customize\Customizer::class,
		static fn( $c ) => new \SignTeb\MedCore\Customize\Customizer( $c->make( \SignTeb\MedCore\Customize\DesignTokens::class ) )
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Customize\Customizer::class )->register_hooks();
} catch ( \Throwable $e ) {
	MedCore_Logger::log( 'Customizer failed to boot', 'warning', [ 'error' => $e->getMessage() ] );
}

// ── Performance Optimizer (فاز ۷) ────────────────────────────────────────────
try {
	$GLOBALS['medcore_container']->singleton(
		\SignTeb\MedCore\Performance\Optimizer::class,
		static fn() => new \SignTeb\MedCore\Performance\Optimizer()
	);
	$GLOBALS['medcore_container']->make( \SignTeb\MedCore\Performance\Optimizer::class )->register();
} catch ( \Throwable $e ) {
	MedCore_Logger::log( 'Performance optimizer failed to boot', 'warning', [ 'error' => $e->getMessage() ] );
}
