<?php
/**
 * The Kanban pipeline board.
 *
 * Self-registering admin service. Renders one column per lead status and lets
 * the admin drag a lead card between columns; the drop persists via the shared
 * LeadCrm AJAX endpoint (nonce + capability enforced there).
 *
 * @package Clinovix
 */

namespace Clinovix\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CrmBoard
{
    private const PAGE = 'clx-board';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'clx-chat',
            __('بورد CRM', 'clinovix'),
            __('بورد CRM', 'clinovix'),
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
        wp_enqueue_style('clx-board', CLX_URL . 'assets/css/board.css', [], CLX_VERSION);
        wp_enqueue_script('clx-board', CLX_URL . 'assets/js/board.js', [], CLX_VERSION, true);
        wp_localize_script('clx-board', 'CLX_BOARD', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => \Clinovix\Crm\LeadCrm::nonce(),
            'moved'   => __('منتقل شد', 'clinovix'),
            'failed'  => __('خطا در انتقال', 'clinovix'),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'clinovix'), '', ['response' => 403]);
        }

        $repo    = new \Clinovix\Database\ConversationRepository();
        $columns = [];
        foreach (\Clinovix\Crm\LeadCrm::STATUSES as $key => $def) {
            $columns[$key] = [
                'label' => $def[0],
                'color' => $def[1],
                'leads' => $repo->by_status($key, 40),
            ];
        }

        include CLX_DIR . 'includes/admin/views/crm-board.php';
    }
}
