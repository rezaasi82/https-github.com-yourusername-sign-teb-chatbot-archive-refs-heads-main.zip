<?php

namespace SignTeb\VideoHub\Rest;

use SignTeb\VideoHub\Core\Settings;
use SignTeb\VideoHub\Rest\Controllers\AdminController;
use SignTeb\VideoHub\Rest\Controllers\AnalyticsController;
use SignTeb\VideoHub\Rest\Controllers\VideoController;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers every REST controller under one namespace.
 */
class RestNamespace
{
    public const NAME = 'signteb-video/v1';

    private Settings $settings;

    public function __construct(?Settings $settings = null)
    {
        $this->settings = $settings ?? new Settings();
    }

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            (new VideoController($this->settings))->register_routes();
            (new AnalyticsController($this->settings))->register_routes();
            (new AdminController($this->settings))->register_routes();
        });
    }
}
