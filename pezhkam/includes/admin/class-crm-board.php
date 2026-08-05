<?php
/**
 * The Kanban pipeline board.
 *
 * Self-registering admin service. Renders one column per lead status and lets
 * the admin drag a lead card between columns; the drop persists via the shared
 * LeadCrm AJAX endpoint (nonce + capability enforced there).
 *
 * @package Pezhkam
 */

namespace Pezhkam\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CrmBoard
{
    private const PAGE = 'pzk-board';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'pzk-chat',
            __('بورد CRM', 'pezhkam'),
            __('بورد CRM', 'pezhkam'),
            'manage_options',
            self::PAGE,
            [$this, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        if (substr($hook, -strlen(self::PAGE)) !== self::PAGE) {
            return;
        }
        wp_enqueue_style('pzk-board', PZK_URL . 'assets/css/board.css', [], PZK_VERSION);
        wp_enqueue_script('pzk-board', PZK_URL . 'assets/js/board.js', [], PZK_VERSION, true);
        wp_localize_script('pzk-board', 'PZK_BOARD', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => \Pezhkam\Crm\LeadCrm::nonce(),
            'moved'   => __('منتقل شد', 'pezhkam'),
            'failed'  => __('خطا در انتقال', 'pezhkam'),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'pezhkam'), '', ['response' => 403]);
        }

        $repo    = new \Pezhkam\Database\ConversationRepository();
        $columns = [];
        foreach (\Pezhkam\Crm\LeadCrm::STATUSES as $key => $def) {
            $columns[$key] = [
                'label' => $def[0],
                'color' => $def[1],
                'leads' => $repo->by_status($key, 40),
            ];
        }

        include PZK_DIR . 'includes/admin/views/crm-board.php';
    }
}
