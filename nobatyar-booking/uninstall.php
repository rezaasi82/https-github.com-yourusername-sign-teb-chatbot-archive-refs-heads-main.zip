<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('nobatyar_license_check');
wp_clear_scheduled_hook('nobatyar_send_reminders');

delete_option('nobatyar_db_version');
delete_option('nobatyar_terminology_overrides');
delete_option('nobatyar_sms_settings');
delete_option('nobatyar_payment_settings');
delete_option('nobatyar_notification_settings');

// نوبتیار هیچ‌گاه داده‌های رزرو/تراکنش/لایسنس را به‌صورت خودکار حذف نمی‌کند؛
// جدول‌های nby_* عمداً دست‌نخورده باقی می‌مانند تا کاربر بتواند قبل از حذف
// قطعی، از داده‌ها پشتیبان بگیرد یا پلاگین را دوباره نصب کند.
