<?php
/**
 * Manage clinics / doctors / branches and show each
 * branch's own statistics (multi-clinic support).
 *
 * @package Pazira
 */

namespace Pazira\Admin;

if (! defined('ABSPATH')) {
    exit;
}

class BranchesPage
{
    private const NONCE = 'pzr_branches';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_post_pzr_branch_save', [$this, 'handle_save']);
        add_action('admin_post_pzr_branch_delete', [$this, 'handle_delete']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'pzr-chat',
            __('کلینیک‌ها و شعب', 'pazira'),
            __('کلینیک‌ها', 'pazira'),
            'manage_options',
            'pzr-branches',
            [$this, 'render']
        );
    }

    public function handle_save(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'pazira'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE);

        $in   = \Pazira\Core\Input::post_fields();
        $data = [
            'name'    => sanitize_text_field($in['name'] ?? ''),
            'doctor'  => sanitize_text_field($in['doctor'] ?? ''),
            'phone'   => sanitize_text_field($in['phone'] ?? ''),
            'address' => sanitize_text_field($in['address'] ?? ''),
        ];

        if ($data['name'] !== '') {
            $repo = new \Pazira\Database\BranchRepository();
            $id   = absint($in['branch_id'] ?? 0);
            if ($id > 0 && $repo->exists($id)) {
                $repo->update($id, $data);
                \Pazira\Security\AuditLog::record('branch_updated', ['object' => 'branch#' . $id]);
            } else {
                $new = $repo->create($data);
                \Pazira\Security\AuditLog::record('branch_created', ['object' => 'branch#' . $new]);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=pzr-branches&saved=1'));
        exit;
    }

    public function handle_delete(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'pazira'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE);

        $id = \Pazira\Core\Input::post_int('branch_id');
        if ($id > 0) {
            (new \Pazira\Database\BranchRepository())->delete($id);
            \Pazira\Security\AuditLog::record('branch_deleted', ['object' => 'branch#' . $id, 'severity' => 'warning']);
        }
        wp_safe_redirect(admin_url('admin.php?page=pzr-branches&deleted=1'));
        exit;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $repo     = new \Pazira\Database\BranchRepository();
        $branches = $repo->all();
        $stats    = $repo->lead_stats();
        $edit_id  = \Pazira\Core\Input::get_int('edit');
        $editing  = $edit_id > 0 ? $repo->get($edit_id) : null;
        $nonce    = self::NONCE;

        include PZR_DIR . 'includes/admin/views/branches.php';
    }
}
