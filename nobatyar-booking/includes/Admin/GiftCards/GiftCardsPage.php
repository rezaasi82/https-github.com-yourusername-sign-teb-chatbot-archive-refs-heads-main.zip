<?php

namespace Nobatyar\Admin\GiftCards;

use Nobatyar\GiftCards\GiftCardEngine;
use Nobatyar\GiftCards\GiftCardRepository;
use Nobatyar\License\LicenseManager;
use Nobatyar\License\LicenseTier;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Mirrors CouponsPage: gift cards are Business-only (no Pro fallback, per
 * LicenseTier's own docblock), so the whole create/edit/delete capability is
 * gated, and writes are delegated to GiftCardEngine rather than touching
 * GiftCardRepository directly. Existing gift cards (issued during a prior
 * Business period) still display read-only if the site later downgrades.
 */
class GiftCardsPage
{
    private GiftCardEngine $gift_card_engine;
    private GiftCardRepository $gift_card_repository;
    private LicenseManager $license_manager;

    public function __construct(
        GiftCardEngine $gift_card_engine,
        GiftCardRepository $gift_card_repository,
        LicenseManager $license_manager
    ) {
        $this->gift_card_engine     = $gift_card_engine;
        $this->gift_card_repository = $gift_card_repository;
        $this->license_manager      = $license_manager;
    }

    public function handle_submission(): void
    {
        if (! isset($_POST['nobatyar_gift_cards_action'])) {
            return;
        }

        if (! current_user_can('manage_options') || ! check_admin_referer('nobatyar_gift_cards_save', 'nobatyar_gift_cards_nonce')) {
            return;
        }

        $action = sanitize_key($_POST['nobatyar_gift_cards_action']);

        switch ($action) {
            case 'save':
                $this->save();
                break;
            case 'delete':
                $this->delete();
                break;
        }
    }

    private function save(): void
    {
        $id = isset($_POST['gift_card_id']) ? absint($_POST['gift_card_id']) : 0;

        $data = [
            'code'            => sanitize_text_field(wp_unslash($_POST['code'] ?? '')),
            'initial_balance' => (float) ($_POST['initial_balance'] ?? 0),
            'expires_at'      => '' === ($_POST['expires_at'] ?? '') ? null : sanitize_text_field($_POST['expires_at']),
            'recipient_name'  => '' === ($_POST['recipient_name'] ?? '') ? null : sanitize_text_field(wp_unslash($_POST['recipient_name'])),
            'recipient_email' => '' === ($_POST['recipient_email'] ?? '') ? null : sanitize_email(wp_unslash($_POST['recipient_email'])),
            'note'            => '' === ($_POST['note'] ?? '') ? null : sanitize_textarea_field(wp_unslash($_POST['note'])),
            'is_active'       => ! empty($_POST['is_active']),
        ];

        if ($id) {
            $this->gift_card_engine->update_gift_card($id, $data);
        } else {
            $this->gift_card_engine->create_gift_card($data);
        }
    }

    private function delete(): void
    {
        $id = isset($_POST['gift_card_id']) ? absint($_POST['gift_card_id']) : 0;

        if ($id) {
            $this->gift_card_engine->delete_gift_card($id);
        }
    }

    public function render(?int $editing_id = null): string
    {
        $gift_cards_available = $this->license_manager->is_tier_available(LicenseTier::BUSINESS);
        $gift_cards           = $this->gift_card_repository->all();
        $editing              = $editing_id ? $this->gift_card_repository->find($editing_id) : null;

        ob_start();
        ?>
        <div class="wrap nobatyar-admin-gift-cards">
            <h1><?php esc_html_e('مدیریت کارت‌های هدیه', 'nobatyar-booking'); ?></h1>

            <?php if (! $gift_cards_available) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('کارت‌های هدیه ویژگی پلن Business است. کارت‌های قبلی (در صورت وجود) فقط قابل مشاهده هستند و امکان ایجاد یا ویرایش کارت جدید وجود ندارد.', 'nobatyar-booking'); ?></p>
                </div>
            <?php else : ?>
                <?php echo $this->render_form($editing); ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('کد', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('موجودی', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('گیرنده', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('انقضا', 'nobatyar-booking'); ?></th>
                        <th><?php esc_html_e('وضعیت', 'nobatyar-booking'); ?></th>
                        <?php if ($gift_cards_available) : ?>
                            <th><?php esc_html_e('عملیات', 'nobatyar-booking'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gift_cards as $gift_card) : ?>
                        <tr>
                            <td><?php echo esc_html($gift_card['code']); ?></td>
                            <td><?php echo esc_html($gift_card['remaining_balance'] . ' / ' . $gift_card['initial_balance']); ?></td>
                            <td><?php echo esc_html($gift_card['recipient_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($gift_card['expires_at'] ?: __('بدون انقضا', 'nobatyar-booking')); ?></td>
                            <td><?php echo $gift_card['is_active'] ? esc_html__('فعال', 'nobatyar-booking') : esc_html__('غیرفعال', 'nobatyar-booking'); ?></td>
                            <?php if ($gift_cards_available) : ?>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['edit' => $gift_card['id']])); ?>"><?php esc_html_e('ویرایش', 'nobatyar-booking'); ?></a>
                                    |
                                    <form method="post" style="display:inline" onsubmit="return confirm('<?php echo esc_js(__('حذف شود؟', 'nobatyar-booking')); ?>');">
                                        <?php wp_nonce_field('nobatyar_gift_cards_save', 'nobatyar_gift_cards_nonce'); ?>
                                        <input type="hidden" name="nobatyar_gift_cards_action" value="delete">
                                        <input type="hidden" name="gift_card_id" value="<?php echo esc_attr($gift_card['id']); ?>">
                                        <button type="submit" class="button-link-delete"><?php esc_html_e('حذف', 'nobatyar-booking'); ?></button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (! $gift_cards) : ?>
                        <tr><td colspan="6"><?php esc_html_e('هنوز کارت هدیه‌ای ثبت نشده است.', 'nobatyar-booking'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php

        return ob_get_clean();
    }

    private function render_form(?array $editing): string
    {
        ob_start();
        ?>
        <form method="post" class="nobatyar-gift-cards-form">
            <?php wp_nonce_field('nobatyar_gift_cards_save', 'nobatyar_gift_cards_nonce'); ?>
            <input type="hidden" name="nobatyar_gift_cards_action" value="save">
            <input type="hidden" name="gift_card_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">

            <p>
                <label><?php esc_html_e('کد کارت هدیه (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="code" value="<?php echo esc_attr($editing['code'] ?? ''); ?>">
                <br><span class="description"><?php esc_html_e('خالی بگذارید برای تولید خودکار کد.', 'nobatyar-booking'); ?></span>
            </p>
            <?php if (! $editing) : ?>
                <p>
                    <label><?php esc_html_e('موجودی اولیه', 'nobatyar-booking'); ?></label>
                    <input type="number" step="0.01" name="initial_balance" value="" required>
                </p>
            <?php else : ?>
                <p>
                    <label><?php esc_html_e('موجودی فعلی', 'nobatyar-booking'); ?></label>
                    <input type="text" value="<?php echo esc_attr($editing['remaining_balance'] . ' / ' . $editing['initial_balance']); ?>" disabled>
                    <br><span class="description"><?php esc_html_e('موجودی فقط با استفاده از کارت در یک نوبت کاهش می‌یابد و از این فرم قابل تغییر نیست.', 'nobatyar-booking'); ?></span>
                </p>
            <?php endif; ?>
            <p>
                <label><?php esc_html_e('نام گیرنده (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="recipient_name" value="<?php echo esc_attr($editing['recipient_name'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('ایمیل گیرنده (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="email" name="recipient_email" value="<?php echo esc_attr($editing['recipient_email'] ?? ''); ?>">
            </p>
            <p>
                <label><?php esc_html_e('یادداشت (اختیاری)', 'nobatyar-booking'); ?></label>
                <textarea name="note"><?php echo esc_textarea($editing['note'] ?? ''); ?></textarea>
            </p>
            <p>
                <label><?php esc_html_e('تاریخ انقضا (اختیاری)', 'nobatyar-booking'); ?></label>
                <input type="text" name="expires_at" value="<?php echo esc_attr($editing['expires_at'] ?? ''); ?>" placeholder="YYYY-MM-DD HH:MM:SS">
            </p>
            <p>
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php checked(null === $editing || ! empty($editing['is_active'])); ?>>
                    <?php esc_html_e('فعال', 'nobatyar-booking'); ?>
                </label>
            </p>

            <button type="submit" class="button button-primary">
                <?php echo $editing ? esc_html__('به‌روزرسانی', 'nobatyar-booking') : esc_html__('افزودن', 'nobatyar-booking'); ?>
            </button>
        </form>
        <?php

        return ob_get_clean();
    }
}
