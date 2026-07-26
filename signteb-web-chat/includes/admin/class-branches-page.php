<?php
/**
 * Manage clinics / doctors / branches and show each
 * branch's own statistics (multi-clinic support).
 *
 * @package Medora
 */

namespace Medora\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class BranchesPage
{
    private const NONCE = 'swc_branches';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_post_swc_branch_save', [$this, 'handle_save']);
        add_action('admin_post_swc_branch_delete', [$this, 'handle_delete']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'swc-chat',
            __('کلینیک‌ها و شعب', 'signteb-web-chat'),
            __('کلینیک‌ها', 'signteb-web-chat'),
            'manage_options',
            'swc-branches',
            [$this, 'render']
        );
    }

    public function handle_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'signteb-web-chat'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE);

        $in   = \Medora\Core\Input::post_fields();
        $data = [
            'name'    => sanitize_text_field($in['name'] ?? ''),
            'doctor'  => sanitize_text_field($in['doctor'] ?? ''),
            'phone'   => sanitize_text_field($in['phone'] ?? ''),
            'address' => sanitize_text_field($in['address'] ?? ''),
        ];

        if ($data['name'] !== '') {
            $repo = new \Medora\Database\BranchRepository();
            $id   = absint($in['branch_id'] ?? 0);
            if ($id > 0 && $repo->exists($id)) {
                $repo->update($id, $data);
                \Medora\Security\AuditLog::record('branch_updated', ['object' => 'branch#' . $id]);
            } else {
                $new = $repo->create($data);
                \Medora\Security\AuditLog::record('branch_created', ['object' => 'branch#' . $new]);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=swc-branches&saved=1'));
        exit;
    }

    public function handle_delete(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'signteb-web-chat'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE);

        $id = \Medora\Core\Input::post_int('branch_id');
        if ($id > 0) {
            (new \Medora\Database\BranchRepository())->delete($id);
            \Medora\Security\AuditLog::record('branch_deleted', ['object' => 'branch#' . $id, 'severity' => 'warning']);
        }
        wp_safe_redirect(admin_url('admin.php?page=swc-branches&deleted=1'));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $repo     = new \Medora\Database\BranchRepository();
        $branches = $repo->all();
        $stats    = $repo->lead_stats();
        $edit_id  = \Medora\Core\Input::get_int('edit');
        $editing  = $edit_id > 0 ? $repo->get($edit_id) : null;
        $nonce    = self::NONCE;

        include SWC_DIR . 'includes/admin/views/branches.php';
    }
}
