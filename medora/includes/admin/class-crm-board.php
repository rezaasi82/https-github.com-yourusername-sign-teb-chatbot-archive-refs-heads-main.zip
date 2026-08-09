<?php
/**
 * The Kanban pipeline board.
 *
 * Self-registering admin service. Renders one column per lead status and lets
 * the admin drag a lead card between columns; the drop persists via the shared
 * LeadCrm AJAX endpoint (nonce + capability enforced there).
 *
 * @package Medora
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CrmBoard
{
    private const PAGE = 'mdr-board';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'mdr-chat',
            __('بورد CRM', 'medora'),
            __('بورد CRM', 'medora'),
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
        wp_enqueue_style('mdr-board', MDR_URL . 'assets/css/board.css', [], MDR_VERSION);
        wp_enqueue_script('mdr-board', MDR_URL . 'assets/js/board.js', [], MDR_VERSION, true);
        wp_localize_script('mdr-board', 'MDR_BOARD', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => \Medora\Crm\LeadCrm::nonce(),
            'moved'   => __('منتقل شد', 'medora'),
            'failed'  => __('خطا در انتقال', 'medora'),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'medora'), '', ['response' => 403]);
        }

        $repo    = new \Medora\Database\ConversationRepository();
        $columns = [];
        foreach (\Medora\Crm\LeadCrm::STATUSES as $key => $def) {
            $columns[$key] = [
                'label' => $def[0],
                'color' => $def[1],
                'leads' => $repo->by_status($key, 40),
            ];
        }

        include MDR_DIR . 'includes/admin/views/crm-board.php';
    }
}
