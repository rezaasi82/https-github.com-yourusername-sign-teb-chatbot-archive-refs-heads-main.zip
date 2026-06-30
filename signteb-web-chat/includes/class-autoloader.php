<?php
/**
 * SWC_Autoloader — explicit class-map autoloader.
 *
 * The plugin follows the WordPress class-file convention (one class per file,
 * `SWC_Foo_Bar` => `class-foo-bar.php`). An explicit map is used instead of a
 * path-guessing PSR-4 loader so the relationship between class names and the
 * files that must exist on disk is auditable at a glance — this is the exact
 * class of bug (a `require` pointing at a file that was never created) that
 * earlier SignTeb plugins hit, so it is made impossible here by construction.
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

        // AI provider layer.
        'SWC_AI_Provider_Interface'   => 'ai/interface-ai-provider.php',
        'SWC_Provider_Anthropic'      => 'ai/class-provider-anthropic.php',
        'SWC_Provider_OpenAI'         => 'ai/class-provider-openai.php',
        'SWC_AI_Manager'              => 'ai/class-ai-manager.php',
        'SWC_System_Prompt_Builder'   => 'ai/class-system-prompt-builder.php',
        'SWC_Language_Detector'       => 'ai/class-language-detector.php',
        'SWC_Cta_Detector'            => 'ai/class-cta-detector.php',

        // Safety.
        'SWC_Medical_Safety_Filter'   => 'safety/class-medical-safety-filter.php',

        // Database.
        'SWC_Schema'                  => 'database/class-schema.php',
        'SWC_Conversation_Repository' => 'database/class-conversation-repository.php',
        'SWC_Message_Repository'      => 'database/class-message-repository.php',

        // Rate limiting.
        'SWC_Rate_Limiter'            => 'ratelimit/class-rate-limiter.php',

        // Transports.
        'SWC_Chat_Controller'         => 'rest/class-chat-controller.php',
        'SWC_Sanitizer'               => 'rest/class-sanitizer.php',
        'SWC_Chat_Ajax_Handler'       => 'ajax/class-chat-ajax-handler.php',

        // License / trial.
        'SWC_License_Manager'         => 'license/class-license-manager.php',

        // Frontend.
        'SWC_Widget'                  => 'frontend/class-widget.php',

        // Admin.
        'SWC_Admin_Menu'              => 'admin/class-admin-menu.php',
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

    /**
     * Exposed for the integrity self-check (see SWC_Plugin::verify_integrity).
     *
     * @return array<string,string>
     */
    public static function class_map(): array
    {
        return self::$map;
    }
}
