<?php
/**
 * Surfaces new (unseen) conversations across wp-admin.
 *
 * Three touchpoints, all driven by a per-user "last seen" marker:
 *   1. an admin-bar bubble (top of every admin screen), kept live via the
 *      WordPress Heartbeat API — no page reload needed;
 *   2. a notice on the main Dashboard screen;
 *   3. a count badge on the Pezhkam menu item (same style as Comments).
 *
 * Visiting the leads & conversations list marks everything as seen. The
 * marker is user meta, so each admin tracks their own unread state.
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class ChatNotifier
{
    private const META = 'pzk_chats_seen_at';

    /** Conversations list URL — the click target everywhere. */
    public static function list_url(): string
    {
        return admin_url('admin.php?page=pzk-chat&tab=conversations');
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
        return (new \Pezhkam\Database\ConversationRepository())->count_since($seen);
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
        $page = \Pezhkam\Core\Input::get_key('page');
        $tab  = \Pezhkam\Core\Input::get_key('tab');
        if ($page === 'pzk-chat' && $tab === 'conversations') {
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
            __('%s گفتگوی جدید', 'pezhkam'),
            '<span class="pzk-ab-count">' . esc_html(number_format_i18n($count)) . '</span>'
        );
        $bar->add_node([
            'id'    => 'pzk-chats',
            'title' => Icon::svg('chat', 'pzk-ab-ico') . ' ' . $label,
            'href'  => self::list_url(),
            'meta'  => [
                'class' => $count > 0 ? 'pzk-ab-has-new' : 'pzk-ab-zero',
                'title' => __('گفتگوهای جدید Pezhkam', 'pezhkam'),
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
            '<div class="notice notice-info pzk-new-chats-notice"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
            esc_html__('Pezhkam:', 'pezhkam'),
            esc_html(sprintf(
                /* translators: %s: number of new conversations */
                _n('%s گفتگوی جدید از آخرین بازدید شما ثبت شده است.', '%s گفتگوی جدید از آخرین بازدید شما ثبت شده است.', $count, 'pezhkam'),
                number_format_i18n($count)
            )),
            esc_url(self::list_url()),
            esc_html__('مشاهده گفتگوها', 'pezhkam')
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
            'pzk-notifier',
            PZK_URL . 'assets/js/notifier.js',
            ['jquery', 'heartbeat'],
            PZK_VERSION,
            true
        );
        $css = '
            #wp-admin-bar-pzk-chats.pzk-ab-zero { display: none; }
            #wp-admin-bar-pzk-chats .pzk-ab-count {
                display: inline-block; min-width: 18px; height: 18px; line-height: 18px;
                margin: 0 2px; padding: 0 5px; border-radius: 9px; text-align: center;
                background: #d63638; color: #fff; font-size: 11px; font-weight: 700;
            }
            #wp-admin-bar-pzk-chats .pzk-ab-ico {
                width: 16px; height: 16px; vertical-align: -3px;
                margin-inline-end: 3px;
            }
        ';
        wp_register_style('pzk-notifier', false, [], PZK_VERSION);
        wp_enqueue_style('pzk-notifier');
        wp_add_inline_style('pzk-notifier', $css);
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
        if (! empty($data['pzk_notify']) && current_user_can('manage_options')) {
            $response['pzk_new_chats'] = $this->unseen_count();
        }
        return $response;
    }
}
