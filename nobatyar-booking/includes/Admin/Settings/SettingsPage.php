<?php

namespace Nobatyar\Admin\Settings;

use Nobatyar\Labels\TerminologyMap;
use Nobatyar\License\LicenseManager;
use Nobatyar\Notifications\SmsProviderFactory;
use Nobatyar\Payment\PaymentGatewayFactory;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A Persian step-by-step setup wizard (terminology -> SMS -> payment ->
 * license -> summary), not a single flat settings dump - addresses the
 * Booknetic weakness (steep learning curve for non-technical users) called
 * out in CLAUDE.md. Every step stays independently revisitable after first
 * completion; this is not a one-time-only onboarding flow.
 */
class SettingsPage
{
    public const STEPS = ['terminology', 'sms', 'payment', 'license', 'summary'];

    private LicenseManager $license_manager;
    private ?string $license_message = null;
    private bool $license_error = false;

    public function __construct(LicenseManager $license_manager)
    {
        $this->license_manager = $license_manager;
    }

    /**
     * Processes a step submission. Hooked to admin_init so it runs before
     * the settings page itself is rendered in the same request.
     */
    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_settings_step'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_settings_save', 'nobatyar_settings_nonce')) {
            return;
        }

        $step = sanitize_key($_POST['nobatyar_settings_step']);

        switch ($step) {
            case 'terminology':
                $this->save_terminology();
                break;
            case 'sms':
                $this->save_sms();
                break;
            case 'payment':
                $this->save_payment();
                break;
            case 'license':
                $this->save_license();
                break;
        }
    }

    private function save_terminology(): void
    {
        $overrides = [];
        $input     = (array) ($_POST['terminology'] ?? []);

        foreach (array_keys(TerminologyMap::defaults()) as $key) {
            if (isset($input[$key]) && $input[$key] !== '') {
                $overrides[$key] = sanitize_text_field(wp_unslash($input[$key]));
            }
        }

        update_option('nobatyar_terminology_overrides', $overrides);
    }

    private function save_sms(): void
    {
        $input = (array) ($_POST['sms'] ?? []);

        update_option('nobatyar_sms_settings', [
            'provider'    => sanitize_key($input['provider'] ?? ''),
            'kavehnegar'  => [
                'api_key' => sanitize_text_field($input['kavehnegar']['api_key'] ?? ''),
                'sender'  => sanitize_text_field($input['kavehnegar']['sender'] ?? ''),
            ],
            'melipayamak' => [
                'username' => sanitize_text_field($input['melipayamak']['username'] ?? ''),
                'password' => sanitize_text_field($input['melipayamak']['password'] ?? ''),
                'sender'   => sanitize_text_field($input['melipayamak']['sender'] ?? ''),
            ],
            'whatsapp' => [
                'enabled'         => ! empty($input['whatsapp']['enabled']),
                'phone_number_id' => sanitize_text_field($input['whatsapp']['phone_number_id'] ?? ''),
                'access_token'    => sanitize_text_field($input['whatsapp']['access_token'] ?? ''),
            ],
        ]);
    }

    private function save_payment(): void
    {
        $input = (array) ($_POST['payment'] ?? []);

        update_option('nobatyar_payment_settings', [
            'provider' => sanitize_key($input['provider'] ?? ''),
            'zarinpal' => [
                'merchant_id' => sanitize_text_field($input['zarinpal']['merchant_id'] ?? ''),
                'sandbox'     => ! empty($input['zarinpal']['sandbox']),
            ],
            'idpay' => [
                'api_key' => sanitize_text_field($input['idpay']['api_key'] ?? ''),
                'sandbox' => ! empty($input['idpay']['sandbox']),
            ],
            'nextpay' => [
                'api_key' => sanitize_text_field($input['nextpay']['api_key'] ?? ''),
            ],
        ]);
    }

    private function save_license(): void
    {
        $license_key = sanitize_text_field(wp_unslash($_POST['license_key'] ?? ''));

        $result = $this->license_manager->activate($license_key);

        if (is_wp_error($result)) {
            $this->license_error   = true;
            $this->license_message = $result->get_error_message();

            return;
        }

        $this->license_error   = false;
        $this->license_message = __('لایسنس با موفقیت فعال شد.', 'nobatyar-booking');
    }

    public function render(string $step = 'terminology'): string
    {
        if (! in_array($step, self::STEPS, true)) {
            $step = 'terminology';
        }

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-settings">
            <h1><?php echo esc_html__('تنظیمات نوبتیار', 'nobatyar-booking'); ?></h1>

            <ul class="nobatyar-settings-steps">
                <?php foreach (self::STEPS as $s) : ?>
                    <li class="<?php echo $s === $step ? 'active' : ''; ?>"><?php echo esc_html($this->step_label($s)); ?></li>
                <?php endforeach; ?>
            </ul>

            <?php echo $this->render_step($step); ?>
        </div>
        <?php

        return ob_get_clean();
    }

    private function step_label(string $step): string
    {
        $labels = [
            'terminology' => __('اصطلاحات', 'nobatyar-booking'),
            'sms'         => __('پیامک', 'nobatyar-booking'),
            'payment'     => __('پرداخت', 'nobatyar-booking'),
            'license'     => __('لایسنس', 'nobatyar-booking'),
            'summary'     => __('خلاصه', 'nobatyar-booking'),
        ];

        return $labels[$step] ?? $step;
    }

    private function render_step(string $step): string
    {
        switch ($step) {
            case 'terminology':
                return $this->render_terminology_step();
            case 'sms':
                return $this->render_sms_step();
            case 'payment':
                return $this->render_payment_step();
            case 'license':
                return $this->render_license_step();
            default:
                return $this->render_summary_step();
        }
    }

    private function render_terminology_step(): string
    {
        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('nobatyar_settings_save', 'nobatyar_settings_nonce'); ?>
            <input type="hidden" name="nobatyar_settings_step" value="terminology">

            <?php foreach (TerminologyMap::defaults() as $key => $default_label) : ?>
                <p>
                    <label><?php echo esc_html($key); ?></label>
                    <input type="text" name="terminology[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(TerminologyMap::get($key)); ?>" placeholder="<?php echo esc_attr($default_label); ?>">
                </p>
            <?php endforeach; ?>

            <button type="submit" class="button button-primary"><?php echo esc_html__('ذخیره', 'nobatyar-booking'); ?></button>
        </form>
        <?php

        return ob_get_clean();
    }

    private function render_sms_step(): string
    {
        $settings = SmsProviderFactory::settings();

        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('nobatyar_settings_save', 'nobatyar_settings_nonce'); ?>
            <input type="hidden" name="nobatyar_settings_step" value="sms">

            <p>
                <label><?php echo esc_html__('سرویس پیامک', 'nobatyar-booking'); ?></label>
                <select name="sms[provider]">
                    <option value="" <?php selected($settings['provider'], ''); ?>><?php echo esc_html__('غیرفعال', 'nobatyar-booking'); ?></option>
                    <option value="kavehnegar" <?php selected($settings['provider'], 'kavehnegar'); ?>>Kavehnegar</option>
                    <option value="melipayamak" <?php selected($settings['provider'], 'melipayamak'); ?>>Melipayamak</option>
                </select>
            </p>

            <p><label>Kavehnegar API Key</label>
            <input type="text" name="sms[kavehnegar][api_key]" value="<?php echo esc_attr($settings['kavehnegar']['api_key']); ?>"></p>
            <p><label>Kavehnegar Sender</label>
            <input type="text" name="sms[kavehnegar][sender]" value="<?php echo esc_attr($settings['kavehnegar']['sender']); ?>"></p>

            <p><label>Melipayamak Username</label>
            <input type="text" name="sms[melipayamak][username]" value="<?php echo esc_attr($settings['melipayamak']['username']); ?>"></p>
            <p><label>Melipayamak Password</label>
            <input type="password" name="sms[melipayamak][password]" value="<?php echo esc_attr($settings['melipayamak']['password']); ?>"></p>
            <p><label>Melipayamak Sender</label>
            <input type="text" name="sms[melipayamak][sender]" value="<?php echo esc_attr($settings['melipayamak']['sender']); ?>"></p>

            <p>
                <label>
                    <input type="checkbox" name="sms[whatsapp][enabled]" value="1" <?php checked($settings['whatsapp']['enabled']); ?>>
                    <?php echo esc_html__('فعال‌سازی واتس‌اپ', 'nobatyar-booking'); ?>
                </label>
            </p>
            <p><label>WhatsApp Phone Number ID</label>
            <input type="text" name="sms[whatsapp][phone_number_id]" value="<?php echo esc_attr($settings['whatsapp']['phone_number_id']); ?>"></p>
            <p><label>WhatsApp Access Token</label>
            <input type="text" name="sms[whatsapp][access_token]" value="<?php echo esc_attr($settings['whatsapp']['access_token']); ?>"></p>

            <button type="submit" class="button button-primary"><?php echo esc_html__('ذخیره', 'nobatyar-booking'); ?></button>
        </form>
        <?php

        return ob_get_clean();
    }

    private function render_payment_step(): string
    {
        $settings = PaymentGatewayFactory::settings();

        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('nobatyar_settings_save', 'nobatyar_settings_nonce'); ?>
            <input type="hidden" name="nobatyar_settings_step" value="payment">

            <p>
                <label><?php echo esc_html__('درگاه پرداخت', 'nobatyar-booking'); ?></label>
                <select name="payment[provider]">
                    <option value="" <?php selected($settings['provider'], ''); ?>><?php echo esc_html__('غیرفعال', 'nobatyar-booking'); ?></option>
                    <option value="zarinpal" <?php selected($settings['provider'], 'zarinpal'); ?>>Zarinpal</option>
                    <option value="idpay" <?php selected($settings['provider'], 'idpay'); ?>>IDPay</option>
                    <option value="nextpay" <?php selected($settings['provider'], 'nextpay'); ?>>NextPay</option>
                </select>
            </p>

            <p><label>Zarinpal Merchant ID</label>
            <input type="text" name="payment[zarinpal][merchant_id]" value="<?php echo esc_attr($settings['zarinpal']['merchant_id']); ?>"></p>
            <p><label><input type="checkbox" name="payment[zarinpal][sandbox]" value="1" <?php checked($settings['zarinpal']['sandbox']); ?>> Sandbox</label></p>

            <p><label>IDPay API Key</label>
            <input type="text" name="payment[idpay][api_key]" value="<?php echo esc_attr($settings['idpay']['api_key']); ?>"></p>
            <p><label><input type="checkbox" name="payment[idpay][sandbox]" value="1" <?php checked($settings['idpay']['sandbox']); ?>> Sandbox</label></p>

            <p><label>NextPay API Key</label>
            <input type="text" name="payment[nextpay][api_key]" value="<?php echo esc_attr($settings['nextpay']['api_key']); ?>"></p>

            <button type="submit" class="button button-primary"><?php echo esc_html__('ذخیره', 'nobatyar-booking'); ?></button>
        </form>
        <?php

        return ob_get_clean();
    }

    private function render_license_step(): string
    {
        $row = $this->license_manager->current_row();

        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('nobatyar_settings_save', 'nobatyar_settings_nonce'); ?>
            <input type="hidden" name="nobatyar_settings_step" value="license">

            <?php if ($this->license_message) : ?>
                <p class="<?php echo $this->license_error ? 'nobatyar-error' : 'nobatyar-success'; ?>"><?php echo esc_html($this->license_message); ?></p>
            <?php endif; ?>

            <p>
                <?php echo esc_html(sprintf(__('وضعیت فعلی: %s', 'nobatyar-booking'), $this->license_manager->current_status())); ?>
                <?php if ($row) : ?>
                    (<?php echo esc_html($row['tier']); ?>)
                <?php endif; ?>
            </p>

            <p>
                <label><?php echo esc_html__('کد لایسنس', 'nobatyar-booking'); ?></label>
                <input type="text" name="license_key" value="<?php echo esc_attr($row['license_key'] ?? ''); ?>">
            </p>

            <button type="submit" class="button button-primary"><?php echo esc_html__('فعال‌سازی / انتقال به این دامنه', 'nobatyar-booking'); ?></button>
        </form>
        <?php

        return ob_get_clean();
    }

    private function render_summary_step(): string
    {
        $sms     = SmsProviderFactory::settings();
        $payment = PaymentGatewayFactory::settings();
        $license = $this->license_manager->current_status();

        ob_start();
        ?>
        <div class="nobatyar-settings-summary">
            <p><?php echo esc_html(sprintf(__('سرویس پیامک: %s', 'nobatyar-booking'), $sms['provider'] ?: __('غیرفعال', 'nobatyar-booking'))); ?></p>
            <p><?php echo esc_html(sprintf(__('درگاه پرداخت: %s', 'nobatyar-booking'), $payment['provider'] ?: __('غیرفعال', 'nobatyar-booking'))); ?></p>
            <p><?php echo esc_html(sprintf(__('وضعیت لایسنس: %s', 'nobatyar-booking'), $license)); ?></p>
        </div>
        <?php

        return ob_get_clean();
    }
}
