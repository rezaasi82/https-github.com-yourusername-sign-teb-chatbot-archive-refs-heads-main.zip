<?php

namespace STMC_Chat\Admin;

use STMC_Chat\Core\Settings;
use STMC_Chat\Integration\MedicalCoreBridge;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings screen: API key (stored encrypted), personality/tone, colors,
 * welcome message, business hours, and the fallback business profile used when
 * Medical Core is inactive.
 */
class SettingsPage
{
    private const OPTION = 'stmc_chat_settings';

    public function handle_save(): void
    {
        if (! isset($_POST['stmc_chat_settings_submit'])) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('stmc_chat_settings');

        $in  = wp_unslash($_POST);
        $out = [
            'enabled'            => isset($in['enabled']) ? 1 : 0,
            'provider'           => in_array(($in['provider'] ?? 'anthropic'), ['anthropic', 'openai'], true) ? $in['provider'] : 'anthropic',
            'model'              => sanitize_text_field($in['model'] ?? 'claude-opus-4-8'),
            'tone'               => ($in['tone'] ?? 'friendly') === 'formal' ? 'formal' : 'friendly',
            'language'           => in_array(($in['language'] ?? 'auto'), ['auto', 'fa', 'ar', 'en'], true) ? $in['language'] : 'auto',
            'widget_color'       => sanitize_hex_color($in['widget_color'] ?? '#0f1f3d') ?: '#0f1f3d',
            'accent_color'       => sanitize_hex_color($in['accent_color'] ?? '#c8a04e') ?: '#c8a04e',
            'welcome_message'    => sanitize_textarea_field($in['welcome_message'] ?? ''),
            'quick_replies'      => sanitize_textarea_field($in['quick_replies'] ?? ''),
            'business_hours'     => sanitize_text_field($in['business_hours'] ?? ''),
            'offhours_message'   => sanitize_textarea_field($in['offhours_message'] ?? ''),
            'rate_limit_per_min' => max(1, (int) ($in['rate_limit_per_min'] ?? 8)),
            'clinic_name'        => sanitize_text_field($in['clinic_name'] ?? ''),
            'specialty'          => sanitize_text_field($in['specialty'] ?? ''),
            'phone'              => sanitize_text_field($in['phone'] ?? ''),
            'whatsapp'           => sanitize_text_field($in['whatsapp'] ?? ''),
            'address'            => sanitize_text_field($in['address'] ?? ''),
            'emergency_number'   => sanitize_text_field($in['emergency_number'] ?? '115'),
            'booking_url'        => esc_url_raw($in['booking_url'] ?? ''),
            'manual_services'    => sanitize_textarea_field($in['manual_services'] ?? ''),
        ];

        $existing = get_option(self::OPTION, []);
        update_option(self::OPTION, array_merge(is_array($existing) ? $existing : [], $out));

        // API key handled separately and only overwritten when a new value is typed.
        if (isset($in['api_key']) && trim((string) $in['api_key']) !== '') {
            Settings::save_api_key((string) $in['api_key']);
        }
        if (isset($in['fallback_key']) && trim((string) $in['fallback_key']) !== '') {
            update_option('stmc_chat_fallback_key_enc', \STMC_Chat\Core\Encryption::encrypt((string) $in['fallback_key']), false);
        }

        add_settings_error('stmc_chat', 'saved', __('تنظیمات ذخیره شد.', 'signteb-ai-chat'), 'updated');
        set_transient('stmc_chat_admin_notice', get_settings_errors('stmc_chat'), 30);

        wp_safe_redirect(admin_url('admin.php?page=stmc-chat-settings&updated=1'));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $s      = new Settings();
        $bridge = new MedicalCoreBridge($s);
        $core   = $bridge->is_active();

        include STMC_CHAT_DIR . 'includes/Admin/views/settings.php';
    }
}
