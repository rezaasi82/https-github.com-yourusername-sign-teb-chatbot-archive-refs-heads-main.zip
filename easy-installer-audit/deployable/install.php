<?php
/**
 * نصب‌کننده آسان — Easy Installer
 *
 * این فایل را در روت دامنه آپلود کنید (مثلاً public_html/install.php)
 * سپس در مرورگر به آدرس زیر بروید:
 *
 *     https://yourdomain.com/install.php
 *
 * این ابزار به صورت خودکار:
 *   ۱. پیش‌نیازهای سرور را بررسی می‌کند
 *   ۲. اطلاعات دیتابیس را از شما می‌گیرد
 *   ۳. وردپرس را دانلود و نصب می‌کند
 *   ۴. قالب و افزونه‌های همراه را نصب و فعال می‌کند
 *   ۵. سایت را آماده استفاده می‌کند
 *
 * پس از اتمام نصب، توصیه می‌شود این فایل و پوشه installer/
 * را از سرور حذف کنید.
 */

error_reporting( E_ALL & ~E_DEPRECATED & ~E_NOTICE );
ini_set( 'display_errors', '1' );

define( 'EZI_ROOT', __DIR__ );
define( 'EZI_DIR', __DIR__ . '/installer' );
define( 'EZI_VERSION', '1.0.1' );

if ( ! is_dir( EZI_DIR ) ) {
	die( 'خطا: پوشه installer/ یافت نشد. لطفاً تمام فایل‌های بسته نصبی را به‌درستی آپلود کنید.' );
}

require EZI_DIR . '/bootstrap.php';
