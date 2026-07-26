<?php
/**
 * Surfaces new (unseen) conversations across wp-admin.
 *
 * Three touchpoints, all driven by a per-user "last seen" marker:
 *   1. an admin-bar bubble (top of every admin screen), kept live via the
 *      WordPress Heartbeat API — no page reload needed;
 *   2. a notice on the main Dashboard screen;
 *   3. a count badge on the Pazira menu item (same style as Comments).
 *
 * Visiting the leads & conversations list marks everything as seen. The
 * marker is user meta, so each admin tracks their own unread state.
 *
 * @package Pazira
 */

namespace Pazira\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class ChatNotifier
{
    private const META = 'pzr_chats_seen_at';

    /** Conversations list URL — the click target everywhere. */
    public static function list_url(): string
    {
        return admin_url('admin.php?page=pzr-chat&tab=conversations');
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybe_mark_seen']);
        add_action('admin_bar_menu', [$this, 'admin_bar'], 70);
        add_action('admin_notices', [$this, 'dashboard_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('heartbeat_received', [$this, 'heartbeat'], 10, 2);
    }

    /**
     * Unseen conversation count for the current admin.
     */
    public function unseen_count(): int
    {
        if (! current_user_can('manage_options')) {
            return 0;
        }
        $seen = (string) get_user_meta(get_current_user_id(), self::META, true);
        if ($seen === '') {
            // First run for this user: start counting from now instead of
            // flagging the entire history as unread.
            $this->mark_seen();
            return 0;
        }
        return (new \Pazira\Database\ConversationRepository())->count_since($seen);
    }

    public function mark_seen(): void
    {
        update_user_meta(get_current_user_id(), self::META, current_time('mysql'));
    }

    /**
     * Opening the conversations list (or a single lead) clears the counter.
     */
    public function maybe_mark_seen(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $page = \Pazira\Core\Input::get_key('page');
        $tab  = \Pazira\Core\Input::get_key('tab');
        if ($page === 'pzr-chat' && $tab === 'conversations') {
            $this->mark_seen();
        }
    }

    /**
     * Admin-bar bubble. Always rendered (hidden at zero) so the Heartbeat
     * updater has a node to reveal when a chat arrives mid-session.
     */
    public function admin_bar(\WP_Admin_Bar $bar): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $count = $this->unseen_count();
        $label = sprintf(
            /* translators: %s: number of new conversations */
            __('%s گفتگوی جدید', 'pazira'),
            '<span class="pzr-ab-count">' . esc_html(number_format_i18n($count)) . '</span>'
        );
        $bar->add_node([
            'id'    => 'pzr-chats',
            'title' => '<span class="pzr-ab-ico" aria-hidden="true">💬</span> ' . $label,
            'href'  => self::list_url(),
            'meta'  => [
                'class' => $count > 0 ? 'pzr-ab-has-new' : 'pzr-ab-zero',
                'title' => __('گفتگوهای جدید Pazira', 'pazira'),
            ],
        ]);
    }

    /**
     * Info notice at the top of the main Dashboard screen.
     */
    public function dashboard_notice(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->id !== 'dashboard' || ! current_user_can('manage_options')) {
            return;
        }
        $count = $this->unseen_count();
        if ($count < 1) {
            return;
        }
        printf(
            '<div class="notice notice-info pzr-new-chats-notice"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
            esc_html__('Pazira:', 'pazira'),
            esc_html(sprintf(
                /* translators: %s: number of new conversations */
                _n('%s گفتگوی جدید از آخرین بازدید شما ثبت شده است.', '%s گفتگوی جدید از آخرین بازدید شما ثبت شده است.', $count, 'pazira'),
                number_format_i18n($count)
            )),
            esc_url(self::list_url()),
            esc_html__('مشاهده گفتگوها', 'pazira')
        );
    }

    /**
     * Tiny admin-wide script (Heartbeat listener) + bubble styling.
     */
    public function enqueue(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_script(
            'pzr-notifier',
            PZR_URL . 'assets/js/notifier.js',
            ['jquery', 'heartbeat'],
            PZR_VERSION,
            true
        );
        $css = '
            #wp-admin-bar-pzr-chats.pzr-ab-zero { display: none; }
            #wp-admin-bar-pzr-chats .pzr-ab-count {
                display: inline-block; min-width: 18px; height: 18px; line-height: 18px;
                margin: 0 2px; padding: 0 5px; border-radius: 9px; text-align: center;
                background: #d63638; color: #fff; font-size: 11px; font-weight: 700;
            }
            #wp-admin-bar-pzr-chats .pzr-ab-ico { margin-inline-end: 2px; }
        ';
        wp_register_style('pzr-notifier', false, [], PZR_VERSION);
        wp_enqueue_style('pzr-notifier');
        wp_add_inline_style('pzr-notifier', $css);
    }

    /**
     * Heartbeat responder: ships the fresh unseen count to the browser.
     *
     * @param array $response Heartbeat response payload.
     * @param array $data     Data sent by the client.
     * @return array
     */
    public function heartbeat(array $response, array $data): array
    {
        if (! empty($data['pzr_notify']) && current_user_can('manage_options')) {
            $response['pzr_new_chats'] = $this->unseen_count();
        }
        return $response;
    }
}
