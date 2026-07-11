<?php
/**
 * SWC_Settings_Page — the single tabbed admin screen and its save handler.
 *
 * Each tab posts only its own fields; the handler updates just those keys so
 * one tab never clobbers another's settings. API keys are stored encrypted and
 * only overwritten when a new value is actually typed.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Settings_Page
{
    private const TABS = ['provider', 'clinic', 'appearance', 'integrations', 'conversations', 'stats', 'license'];

    public function current_tab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'provider';
        return in_array($tab, self::TABS, true) ? $tab : 'provider';
    }

    public function handle_save(): void
    {
        if (! isset($_POST['swc_settings_submit'])) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('swc_settings');

        $in  = wp_unslash($_POST);
        $tab = isset($in['tab']) && in_array($in['tab'], self::TABS, true) ? $in['tab'] : 'provider';

        if ($tab === 'license') {
            (new SWC_License_Manager())->activate((string) ($in['license_key'] ?? ''));
            $this->finish($tab);
        }

        if ($tab === 'integrations') {
            $this->save_integrations($in);
            $this->finish($tab);
        }

        $existing = get_option(SWC_Settings::OPTION, []);
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
                SWC_Settings::save_api_key('anthropic', (string) $in['api_key_anthropic']);
            }
            if (isset($in['api_key_openai']) && trim((string) $in['api_key_openai']) !== '') {
                SWC_Settings::save_api_key('openai', (string) $in['api_key_openai']);
            }
            if (isset($in['api_key_gapgpt']) && trim((string) $in['api_key_gapgpt']) !== '') {
                SWC_Settings::save_api_key('gapgpt', (string) $in['api_key_gapgpt']);
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
        }

        update_option(SWC_Settings::OPTION, array_merge($existing, $update));
        $this->finish($tab);
    }

    /**
     * Persist the Integrations tab (webhook + Google Sheets). Secrets are
     * stored encrypted and only overwritten when a new value is typed.
     */
    private function save_integrations(array $in): void
    {
        $existing = get_option(SWC_Settings::OPTION, []);
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
        ];
        update_option(SWC_Settings::OPTION, array_merge($existing, $update));

        if (isset($in['webhook_secret']) && trim((string) $in['webhook_secret']) !== '') {
            SWC_Webhook_Manager::save_secret((string) $in['webhook_secret']);
        }
        if (isset($in['gsheet_secret']) && trim((string) $in['gsheet_secret']) !== '') {
            SWC_Google_Sheets::save_secret((string) $in['gsheet_secret']);
        }
        if (isset($in['cloud_secret']) && trim((string) $in['cloud_secret']) !== '') {
            SWC_Cloud_Client::save_secret((string) $in['cloud_secret']);
        }
    }

    private function finish(string $tab): void
    {
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
        $s       = new SWC_Settings();
        $license = new SWC_License_Manager();

        echo '<div class="wrap swc-admin" dir="rtl">';
        echo '<h1>' . esc_html__('Medora AI — دستیار هوشمند جذب بیمار', 'signteb-web-chat') . '</h1>';
        settings_errors('swc');
        $this->render_tab_nav($tab);

        if (in_array($tab, ['provider', 'clinic', 'appearance', 'integrations', 'license'], true)) {
            include SWC_DIR . 'includes/admin/views/settings.php';
        } elseif ($tab === 'conversations') {
            (new SWC_Conversations_Page())->render_inner();
        } elseif ($tab === 'stats') {
            (new SWC_Stats_Page())->render_inner();
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
            'license'       => __('لایسنس', 'signteb-web-chat'),
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
