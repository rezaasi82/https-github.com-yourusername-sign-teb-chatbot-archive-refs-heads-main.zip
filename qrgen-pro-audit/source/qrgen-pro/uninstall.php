<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('qrgen_default_size');
delete_option('qrgen_default_color_dark');
delete_option('qrgen_default_color_light');
delete_option('qrgen_version');
