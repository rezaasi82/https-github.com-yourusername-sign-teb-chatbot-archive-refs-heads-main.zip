<?php
/**
 * Class-map autoloader.
 *
 * One class per file, following the WordPress `class-*.php` convention. An
 * explicit map keeps the class-to-file relationship in a single place.
 *
 * @package SignTeb_Web_Chat
 */

if (! defined('ABSPATH')) {
    exit;
}

class SWC_Autoloader
{
    /**
     * Fully-qualified class name => path relative to includes/.
     *
     * @var array<string,string>
     */
    private static array $map = [
        // Core.
        'SWC_Plugin'                  => 'core/class-plugin.php',
        'SWC_Activator'               => 'core/class-activator.php',
        'SWC_Deactivator'             => 'core/class-deactivator.php',
        'SWC_Settings'                => 'core/class-settings.php',
        'SWC_Encryption'              => 'core/class-encryption.php',
        'SWC_Json_Guard'              => 'core/class-json-guard.php',
        'SWC_Cache'                   => 'core/class-cache.php',

        // Security.
        'SWC_Security'                => 'security/class-security.php',
        'SWC_Audit_Log'               => 'security/class-audit-log.php',

        // SMS / messaging gateways.
        'SWC_Sms_Provider_Interface'  => 'notifications/class-sms-provider-interface.php',
        'SWC_Sms_Provider_Base'       => 'notifications/class-sms-provider-base.php',
        'SWC_Sms_Kavenegar'           => 'notifications/providers/class-sms-kavenegar.php',
        'SWC_Sms_Melipayamak'         => 'notifications/providers/class-sms-melipayamak.php',
        'SWC_Sms_Smsir'               => 'notifications/providers/class-sms-smsir.php',
        'SWC_Sms_Ghasedak'            => 'notifications/providers/class-sms-ghasedak.php',
        'SWC_Sms_Custom'              => 'notifications/providers/class-sms-custom.php',
        'SWC_Sms_Manager'             => 'notifications/class-sms-manager.php',
        'SWC_Messenger_Notifier'      => 'notifications/class-messenger-notifier.php',

        // AI provider layer.
        'SWC_AI_Provider_Interface'        => 'ai/interface-ai-provider.php',
        'SWC_Provider_Anthropic'           => 'ai/class-provider-anthropic.php',
        'SWC_OpenAI_Compatible_Provider'   => 'ai/class-provider-openai-compatible.php',
        'SWC_Provider_OpenAI'              => 'ai/class-provider-openai.php',
        'SWC_Provider_GapGPT'              => 'ai/class-provider-gapgpt.php',
        'SWC_Provider_Factory'             => 'ai/class-provider-factory.php',
        'SWC_AI_Manager'                   => 'ai/class-ai-manager.php',
        'SWC_System_Prompt_Builder'   => 'ai/class-system-prompt-builder.php',
        'SWC_Language_Detector'       => 'ai/class-language-detector.php',
        'SWC_Cta_Detector'            => 'ai/class-cta-detector.php',
        'SWC_Lead_Scorer'             => 'ai/class-lead-scorer.php',
        'SWC_Summary_Builder'         => 'ai/class-summary-builder.php',

        // Safety.
        'SWC_Medical_Safety_Filter'   => 'safety/class-medical-safety-filter.php',

        // Database.
        'SWC_Schema'                  => 'database/class-schema.php',
        'SWC_Conversation_Repository' => 'database/class-conversation-repository.php',
        'SWC_Message_Repository'      => 'database/class-message-repository.php',
        'SWC_Event_Repository'        => 'database/class-event-repository.php',
        'SWC_Sync_Log_Repository'     => 'database/class-sync-log-repository.php',
        'SWC_Analytics_Repository'    => 'database/class-analytics-repository.php',
        'SWC_Branch_Repository'       => 'database/class-branch-repository.php',

        // Background jobs / analytics rollup.
        'SWC_Rollup'                  => 'jobs/class-rollup.php',
        'SWC_Job_Queue'               => 'jobs/class-job-queue.php',

        // SEO intelligence.
        'SWC_Seo_Analyzer'            => 'seo/class-seo-analyzer.php',
        'SWC_Seo_Page'                => 'seo/class-seo-page.php',

        // Rate limiting.
        'SWC_Rate_Limiter'            => 'ratelimit/class-rate-limiter.php',

        // CRM.
        'SWC_Lead_CRM'                => 'crm/class-lead-crm.php',

        // Cloud (Level 2 telemetry spine).
        'SWC_Cloud_Client'            => 'cloud/class-cloud-client.php',

        // Export / integrations.
        'SWC_Lead_Payload'            => 'export/class-lead-payload.php',
        'SWC_Export_Logger'           => 'export/class-export-logger.php',
        'SWC_PDF_Generator'           => 'export/class-pdf-generator.php',
        'SWC_Webhook_Manager'         => 'export/class-webhook-manager.php',
        'SWC_Google_Sheets'           => 'export/class-google-sheets.php',
        'SWC_Export_Manager'          => 'export/class-export-manager.php',
        'SWC_Sync_Status'             => 'export/class-sync-status.php',

        // Transports.
        'SWC_Chat_Controller'         => 'rest/class-chat-controller.php',
        'SWC_Export_Controller'       => 'rest/class-export-controller.php',
        'SWC_Sanitizer'               => 'rest/class-sanitizer.php',
        'SWC_Chat_Ajax_Handler'       => 'ajax/class-chat-ajax-handler.php',
        'SWC_Export_Ajax_Handler'     => 'ajax/class-export-ajax-handler.php',

        // Frontend.
        'SWC_Widget'                  => 'frontend/class-widget.php',
        'SWC_Chat_Shortcode'          => 'frontend/class-chat-shortcode.php',

        // Admin.
        'SWC_Premium_Dashboard'       => 'admin/class-premium-dashboard.php',
        'SWC_Crm_Board'               => 'admin/class-crm-board.php',
        'SWC_Branches_Page'           => 'admin/class-branches-page.php',
        'SWC_Admin_Menu'              => 'admin/class-admin-menu.php',
        'SWC_Chat_Notifier'           => 'admin/class-chat-notifier.php',
        'SWC_Settings_Page'           => 'admin/class-settings-page.php',
        'SWC_Conversations_Page'      => 'admin/class-conversations-page.php',
        'SWC_Stats_Page'              => 'admin/class-stats-page.php',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (! isset(self::$map[$class])) {
            return;
        }
        $file = SWC_DIR . 'includes/' . self::$map[$class];
        if (is_readable($file)) {
            require_once $file;
        }
    }
}
