<?php

namespace QRCODR\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Menu
{
    const CAPABILITY = 'manage_options';
    const SLUG = 'qrcodr';

    public static function init()
    {
        add_action('admin_menu', array(self::class, 'register'));
        add_action('admin_enqueue_scripts', array(self::class, 'enqueue_assets'));
        add_action('admin_post_qrcodr_save_code', array(self::class, 'handle_save'));
        add_action('admin_post_qrcodr_delete_code', array(self::class, 'handle_delete'));
    }

    public static function register()
    {
        add_menu_page(
            __('QRCODR', 'qrcodr'),
            __('QRCODR', 'qrcodr'),
            self::CAPABILITY,
            self::SLUG,
            array(self::class, 'render'),
            'dashicons-qrcode',
            30
        );
    }

    public static function enqueue_assets($hook)
    {
        if (strpos($hook, self::SLUG) === false) {
            return;
        }

        wp_enqueue_style('qrcodr-admin', QRCODR_PLUGIN_URL . 'assets/css/admin.css', array(), QRCODR_VERSION);

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if (in_array($action, array('new', 'edit'), true)) {
            wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
            wp_enqueue_script('qrcodr-admin', QRCODR_PLUGIN_URL . 'assets/js/admin.js', array('qrcode-js'), QRCODR_VERSION, true);
        }
    }

    public static function render()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'qrcodr'));
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';

        switch ($action) {
            case 'new':
            case 'edit':
                EditPage::render();
                break;
            case 'analytics':
                AnalyticsPage::render();
                break;
            default:
                ListTable::render();
                break;
        }
    }

    public static function handle_save()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('شما دسترسی لازم برای این عملیات را ندارید.', 'qrcodr'));
        }

        check_admin_referer('qrcodr_save_code');

        EditPage::save();
    }

    public static function handle_delete()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('شما دسترسی لازم برای این عملیات را ندارید.', 'qrcodr'));
        }

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        check_admin_referer('qrcodr_delete_code_' . $id);

        ListTable::delete($id);
    }
}
