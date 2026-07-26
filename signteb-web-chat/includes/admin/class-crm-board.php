<?php
/**
 * \Medora\Admin\CrmBoard — the Kanban pipeline board.
 *
 * Self-registering admin service. Renders one column per lead status and lets
 * the admin drag a lead card between columns; the drop persists via the shared
 * \Medora\Crm\LeadCrm AJAX endpoint (nonce + capability enforced there).
 *
 * @package SignTeb_Web_Chat
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class CrmBoard
{
    private const PAGE = 'swc-board';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20); // after the parent menu (priority 10).
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'swc-chat',
            __('بورد CRM', 'signteb-web-chat'),
            __('بورد CRM', 'signteb-web-chat'),
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
        wp_enqueue_style('swc-board', SWC_URL . 'assets/css/board.css', [], SWC_VERSION);
        wp_enqueue_script('swc-board', SWC_URL . 'assets/js/board.js', [], SWC_VERSION, true);
        wp_localize_script('swc-board', 'SWC_BOARD', [
            'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
            'nonce'   => \Medora\Crm\LeadCrm::nonce(),
            'moved'   => __('منتقل شد', 'signteb-web-chat'),
            'failed'  => __('خطا در انتقال', 'signteb-web-chat'),
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'signteb-web-chat'), '', ['response' => 403]);
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

        include SWC_DIR . 'includes/admin/views/crm-board.php';
    }
}
