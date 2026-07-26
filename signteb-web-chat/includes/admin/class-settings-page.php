<?php
/**
 * The single tabbed admin screen and its save handler.
 *
 * Each tab posts only its own fields; the handler updates just those keys so
 * one tab never clobbers another's settings. API keys are stored encrypted and
 * only overwritten when a new value is actually typed.
 *
 * @package Medora
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class SettingsPage
{
    private const TABS = ['provider', 'clinic', 'appearance', 'integrations', 'conversations', 'stats'];

    public function current_tab(): string
    {
        $tab = \Medora\Core\Input::get_key('tab', 'provider');
        return in_array($tab, self::TABS, true) ? $tab : 'provider';
    }

    public function handle_save(): void
    {
        if (! \Medora\Core\Input::has_post('swc_settings_submit')) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('swc_settings');

        $in  = \Medora\Core\Input::post_fields();
        $tab = isset($in['tab']) && in_array($in['tab'], self::TABS, true) ? $in['tab'] : 'provider';

        if ($tab === 'integrations') {
            $this->save_integrations($in);
            $this->finish($tab);
        }

        $existing = get_option(\Medora\Core\Settings::OPTION, []);
        $existing = is_array($existing) ? $existing : [];
        $update   = [];

        if ($tab === 'provider') {
            $update['enabled']            = isset($in['enabled']) ? 1 : 0;
            $update['provider']           = in_array(($in['provider'] ?? 'anthropic'), ['anthropic', 'openai', 'gapgpt'], true) ? $in['provider'] : 'anthropic';
            $update['model_anthropic']    = sanitize_text_field($in['model_anthropic'] ?? 'claude-haiku-4-5-20251001');
            $update['model_openai']       = sanitize_text_field($in['model_openai'] ?? 'gpt-4o-mini');
            $update['model_gapgpt']       = sanitize_text_field($in['model_gapgpt'] ?? 'gpt-4o-mini');
            $update['tone']               = ($in['tone'] ?? 'friendly') === 'formal' ? 'formal' : 'friendly';
            $update['language']           = in_array(($in['language'] ?? 'auto'), ['auto', 'fa', 'ar', 'en'], true) ? $in['language'] : 'auto';
            $update['rate_limit_per_min'] = max(1, (int) ($in['rate_limit_per_min'] ?? 8));

            if (isset($in['api_key_anthropic']) && trim((string) $in['api_key_anthropic']) !== '') {
                \Medora\Core\Settings::save_api_key('anthropic', sanitize_text_field((string) $in['api_key_anthropic']));
            }
            if (isset($in['api_key_openai']) && trim((string) $in['api_key_openai']) !== '') {
                \Medora\Core\Settings::save_api_key('openai', sanitize_text_field((string) $in['api_key_openai']));
            }
            if (isset($in['api_key_gapgpt']) && trim((string) $in['api_key_gapgpt']) !== '') {
                \Medora\Core\Settings::save_api_key('gapgpt', sanitize_text_field((string) $in['api_key_gapgpt']));
            }
        } elseif ($tab === 'clinic') {
            $update['clinic_name']      = sanitize_text_field($in['clinic_name'] ?? '');
            $update['specialty']        = sanitize_text_field($in['specialty'] ?? '');
            $update['phone']            = sanitize_text_field($in['phone'] ?? '');
            $update['whatsapp']         = sanitize_text_field($in['whatsapp'] ?? '');
            $update['address']          = sanitize_text_field($in['address'] ?? '');
            $update['emergency_number'] = sanitize_text_field($in['emergency_number'] ?? '115');
            $update['booking_url']      = esc_url_raw($in['booking_url'] ?? '');
            $update['bale_url']         = esc_url_raw($in['bale_url'] ?? '');
            $update['business_hours']   = sanitize_text_field($in['business_hours'] ?? '');
            $update['manual_services']  = sanitize_textarea_field($in['manual_services'] ?? '');
            $update['avg_service_price'] = max(0, (int) ($in['avg_service_price'] ?? 0));
            $update['lead_capture']     = isset($in['lead_capture']) ? 1 : 0;
            $update['ch_booking']       = isset($in['ch_booking']) ? 1 : 0;
            $update['ch_whatsapp']      = isset($in['ch_whatsapp']) ? 1 : 0;
            $update['ch_call']          = isset($in['ch_call']) ? 1 : 0;
            $update['ch_bale']          = isset($in['ch_bale']) ? 1 : 0;
        } elseif ($tab === 'appearance') {
            $update['bot_name']         = sanitize_text_field($in['bot_name'] ?? '');
            $update['avatar_url']       = esc_url_raw($in['avatar_url'] ?? '');
            $update['widget_color']     = sanitize_hex_color($in['widget_color'] ?? '#0f1f3d') ?: '#0f1f3d';
            $update['accent_color']     = sanitize_hex_color($in['accent_color'] ?? '#c8a04e') ?: '#c8a04e';
            $update['direction']        = ($in['direction'] ?? 'rtl') === 'ltr' ? 'ltr' : 'rtl';
            $update['brand_footer']     = sanitize_text_field($in['brand_footer'] ?? '');
            $update['use_bundled_font'] = isset($in['use_bundled_font']) ? 1 : 0;
            $update['welcome_message']  = sanitize_textarea_field($in['welcome_message'] ?? '');
            $update['quick_replies']    = sanitize_textarea_field($in['quick_replies'] ?? '');
            $update['offhours_message'] = sanitize_textarea_field($in['offhours_message'] ?? '');
            $update['teaser_message']   = sanitize_textarea_field($in['teaser_message'] ?? '');
            $update['teaser_delay']     = max(0, min(120, (int) ($in['teaser_delay'] ?? 3)));
            $update['teaser_sound']     = isset($in['teaser_sound']) ? 1 : 0;
        }

        update_option(\Medora\Core\Settings::OPTION, array_merge($existing, $update));
        $this->finish($tab);
    }

    /**
     * Persist the Integrations tab (webhook + Google Sheets). Secrets are
     * stored encrypted and only overwritten when a new value is typed.
     */
    private function save_integrations(array $in): void
    {
        $existing = get_option(\Medora\Core\Settings::OPTION, []);
        $existing = is_array($existing) ? $existing : [];

        $update = [
            'webhook_enabled'  => isset($in['webhook_enabled']) ? 1 : 0,
            'webhook_url'      => esc_url_raw($in['webhook_url'] ?? ''),
            'webhook_events'   => sanitize_text_field($in['webhook_events'] ?? ''),
            'webhook_retry'    => isset($in['webhook_retry']) ? 1 : 0,
            'gsheet_enabled'   => isset($in['gsheet_enabled']) ? 1 : 0,
            'gsheet_auto'      => isset($in['gsheet_auto']) ? 1 : 0,
            'gsheet_webapp_url' => esc_url_raw($in['gsheet_webapp_url'] ?? ''),
            'gsheet_name'      => sanitize_text_field($in['gsheet_name'] ?? 'Leads'),
            'cloud_enabled'    => isset($in['cloud_enabled']) ? 1 : 0,
            'cloud_endpoint'   => esc_url_raw($in['cloud_endpoint'] ?? ''),
            // SMS / messaging.
            'sms_enabled'       => isset($in['sms_enabled']) ? 1 : 0,
            'sms_provider'      => sanitize_key($in['sms_provider'] ?? 'kavenegar'),
            'sms_sender'        => sanitize_text_field($in['sms_sender'] ?? ''),
            'sms_optout'        => sanitize_text_field($in['sms_optout'] ?? ''),
            'sms_staff_numbers' => sanitize_textarea_field($in['sms_staff_numbers'] ?? ''),
            'sms_custom_url'    => esc_url_raw($in['sms_custom_url'] ?? ''),
            'sms_custom_method' => in_array(strtoupper((string) ($in['sms_custom_method'] ?? 'POST')), ['GET', 'POST', 'PUT'], true) ? strtoupper((string) $in['sms_custom_method']) : 'POST',
            'sms_custom_headers' => sanitize_textarea_field($in['sms_custom_headers'] ?? ''),
            'sms_custom_body'   => sanitize_textarea_field($in['sms_custom_body'] ?? ''),
            // Messenger lead alerts (Bale / Telegram).
            'msgr_bale_enabled'     => isset($in['msgr_bale_enabled']) ? 1 : 0,
            'msgr_bale_chat'        => sanitize_text_field($in['msgr_bale_chat'] ?? ''),
            'msgr_telegram_enabled' => isset($in['msgr_telegram_enabled']) ? 1 : 0,
            'msgr_telegram_chat'    => sanitize_text_field($in['msgr_telegram_chat'] ?? ''),
        ];

        // Editable message templates (defaults fill any left blank).
        $tpls = [];
        if (isset($in['sms_templates']) && is_array($in['sms_templates'])) {
            foreach ($in['sms_templates'] as $key => $text) {
                $tpls[sanitize_key($key)] = sanitize_textarea_field((string) $text);
            }
        }
        $update['sms_templates'] = $tpls;

        // Optional pattern / service-line code per template.
        $codes = [];
        if (isset($in['sms_template_codes']) && is_array($in['sms_template_codes'])) {
            foreach ($in['sms_template_codes'] as $key => $code) {
                $codes[sanitize_key($key)] = sanitize_text_field((string) $code);
            }
        }
        $update['sms_template_codes'] = $codes;

        update_option(\Medora\Core\Settings::OPTION, array_merge($existing, $update));

        // Activation code (API key) + optional second credential — encrypted,
        // only overwritten when a new value is typed.
        if (isset($in['sms_key']) && trim((string) $in['sms_key']) !== '') {
            \Medora\Notifications\SmsManager::save_key((string) $in['sms_key']);
        }
        if (isset($in['sms_secret']) && trim((string) $in['sms_secret']) !== '') {
            \Medora\Notifications\SmsManager::save_secret((string) $in['sms_secret']);
        }

        // Messenger bot tokens — encrypted, only overwritten when re-typed.
        foreach (['bale', 'telegram'] as $ch) {
            if (isset($in['msgr_' . $ch . '_token']) && trim((string) $in['msgr_' . $ch . '_token']) !== '') {
                \Medora\Notifications\MessengerNotifier::save_token($ch, (string) $in['msgr_' . $ch . '_token']);
            }
        }

        if (isset($in['webhook_secret']) && trim((string) $in['webhook_secret']) !== '') {
            \Medora\Export\WebhookManager::save_secret((string) $in['webhook_secret']);
        }
        if (isset($in['gsheet_secret']) && trim((string) $in['gsheet_secret']) !== '') {
            \Medora\Export\GoogleSheets::save_secret((string) $in['gsheet_secret']);
        }
        if (isset($in['cloud_secret']) && trim((string) $in['cloud_secret']) !== '') {
            \Medora\Cloud\CloudClient::save_secret((string) $in['cloud_secret']);
        }
    }

    private function finish(string $tab): void
    {
        \Medora\Security\AuditLog::record('settings_saved', ['object' => $tab, 'severity' => 'info']);
        add_settings_error('swc', 'saved', __('تنظیمات ذخیره شد.', 'signteb-web-chat'), 'updated');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=swc-chat&tab=' . $tab . '&updated=1'));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $tab     = $this->current_tab();
        $s       = new \Medora\Core\Settings();

        echo '<div class="wrap swc-admin" dir="rtl">';
        echo '<h1>' . esc_html__('Medora AI — دستیار هوشمند جذب بیمار', 'signteb-web-chat') . '</h1>';

        // Pistachio-green success toast after a save (auto-dismisses via CSS).
        if (\Medora\Core\Input::get_key('updated') === '1') {
            echo '<div class="swc-saved-toast" role="status">'
                . '<span class="swc-saved-ico" aria-hidden="true">✓</span>'
                . '<span>' . esc_html__('تغییرات با موفقیت ذخیره شد.', 'signteb-web-chat') . '</span>'
                . '</div>';
        } else {
            settings_errors('swc');
        }
        $this->render_tab_nav($tab);

        if (in_array($tab, ['provider', 'clinic', 'appearance', 'integrations'], true)) {
            include SWC_DIR . 'includes/admin/views/settings.php';
        } elseif ($tab === 'conversations') {
            (new \Medora\Admin\ConversationsPage())->render_inner();
        } elseif ($tab === 'stats') {
            (new \Medora\Admin\StatsPage())->render_inner();
        }

        echo '</div>';
    }

    private function render_tab_nav(string $current): void
    {
        $labels = [
            'provider'      => __('هوش مصنوعی', 'signteb-web-chat'),
            'clinic'        => __('اطلاعات کلینیک', 'signteb-web-chat'),
            'appearance'    => __('ظاهر ویجت', 'signteb-web-chat'),
            'integrations'  => __('اتصال‌ها و خروجی', 'signteb-web-chat'),
            'conversations' => __('لیدها و مکالمات', 'signteb-web-chat'),
            'stats'         => __('آمار', 'signteb-web-chat'),
        ];
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($labels as $slug => $label) {
            $url    = admin_url('admin.php?page=swc-chat&tab=' . $slug);
            $active = $slug === $current ? ' nav-tab-active' : '';
            printf('<a href="%s" class="nav-tab%s">%s</a>', esc_url($url), esc_attr($active), esc_html($label));
        }
        echo '</h2>';
    }
}
