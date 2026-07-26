<?php
/**
 * The Kanban pipeline board.
 *
 * Self-registering admin service. Renders one column per lead status and lets
 * the admin drag a lead card between columns; the drop persists via the shared
 * LeadCrm AJAX endpoint (nonce + capability enforced there).
 *
 * @package Pazira
 */

namespace Pazira\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CrmBoard
{
    private const PAGE = 'pzr-board';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'pzr-chat',
            __('بورد CRM', 'pazira'),
            __('بورد CRM', 'pazira'),
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
        wp_enqueue_style('pzr-board', PZR_URL . 'assets/css/board.css', [], PZR_VERSION);
        wp_enqueue_script('pzr-board', PZR_URL . 'assets/js/board.js', [], PZR_VERSION, true);
        wp_localize_script('pzr-board', 'PZR_BOARD', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => \Pazira\Crm\LeadCrm::nonce(),
            'moved'   => __('منتقل شد', 'pazira'),
            'failed'  => __('خطا در انتقال', 'pazira'),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'pazira'), '', ['response' => 403]);
        }

        $repo    = new \Pazira\Database\ConversationRepository();
        $columns = [];
        foreach (\Pazira\Crm\LeadCrm::STATUSES as $key => $def) {
            $columns[$key] = [
                'label' => $def[0],
                'color' => $def[1],
                'leads' => $repo->by_status($key, 40),
            ];
        }

        include PZR_DIR . 'includes/admin/views/crm-board.php';
    }
}
