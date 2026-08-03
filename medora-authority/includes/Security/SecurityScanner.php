<?php

declare(strict_types=1);

namespace Medora\Authority\Security;

use Medora\Authority\Core\Options;
use Medora\Authority\Crawler\RobotsManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Self-audit of the platform's own configuration.
 *
 * Scoped to things Medora is responsible for or that directly break its
 * function. It is not a general WordPress malware scanner — pretending
 * otherwise would give a false sense of coverage.
 */
final class SecurityScanner
{
    public function __construct(
        private readonly Options $options,
        private readonly RobotsManager $robots,
    ) {
    }

    /**
     * @return array{
     *     passed: int,
     *     failed: int,
     *     checks: list<array{id: string, label: string, status: string, detail: string, severity: string}>
     * }
     */
    public function run(): array
    {
        $checks = [];

        // --- Transport ------------------------------------------------------
        $checks[] = $this->check(
            'https',
            __('Site is served over HTTPS', 'medora-authority'),
            str_starts_with(home_url(), 'https://'),
            __('Several AI crawlers skip plain-HTTP origins entirely.', 'medora-authority'),
            'high'
        );

        // --- Secret handling --------------------------------------------------
        $checks[] = $this->check(
            'api_key_storage',
            __('Embedding API key is not stored in the database', 'medora-authority'),
            $this->options->getString('embedding_api_key') === '',
            __('Move the key to the MEDORA_EMBEDDING_API_KEY constant or environment variable so it stays out of database backups.', 'medora-authority'),
            'high'
        );

        $checks[] = $this->check(
            'llm_key_storage',
            __('AI Writer API key is not stored in the database', 'medora-authority'),
            $this->options->getString('llm_api_key') === '',
            __('Move the key to the MEDORA_LLM_API_KEY constant or environment variable so it stays out of database backups.', 'medora-authority'),
            'high'
        );

        // --- Discoverability ---------------------------------------------------
        $checks[] = $this->check(
            'robots_virtual',
            __('robots.txt is managed by WordPress', 'medora-authority'),
            ! $this->robots->hasPhysicalRobotsFile(),
            __('A physical robots.txt file overrides the virtual one, so the crawler policy configured here is never served.', 'medora-authority'),
            'critical'
        );

        $checks[] = $this->check(
            'search_visible',
            __('Search engine visibility is enabled', 'medora-authority'),
            (int) get_option('blog_public') === 1,
            __('Settings → Reading is currently discouraging search engines.', 'medora-authority'),
            'critical'
        );

        // --- Platform hygiene ---------------------------------------------------
        $checks[] = $this->check(
            'php_version',
            __('PHP is a supported version', 'medora-authority'),
            version_compare(PHP_VERSION, '8.2', '>='),
            sprintf(
                /* translators: %s: PHP version. */
                __('Running PHP %s. Medora requires 8.2 or newer.', 'medora-authority'),
                PHP_VERSION
            ),
            'critical'
        );

        $checks[] = $this->check(
            'debug_display',
            __('Debug output is not shown to visitors', 'medora-authority'),
            ! (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY && defined('WP_DEBUG') && WP_DEBUG),
            __('WP_DEBUG_DISPLAY is on. Errors leak paths and internals into page output — including into what crawlers index.', 'medora-authority'),
            'high'
        );

        $checks[] = $this->check(
            'file_edit',
            __('Dashboard file editing is disabled', 'medora-authority'),
            defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            __('Define DISALLOW_FILE_EDIT to stop plugin and theme files being edited from the admin.', 'medora-authority'),
            'medium'
        );

        // --- Data retention ------------------------------------------------------
        $retention = $this->options->getInt('retention_days', 180);

        $checks[] = $this->check(
            'retention',
            __('Log retention is bounded', 'medora-authority'),
            $retention > 0 && $retention <= 400,
            __('Set a retention period between 1 and 400 days so crawl and referral logs do not grow without limit.', 'medora-authority'),
            'low'
        );

        /**
         * Add checks to the security scan.
         *
         * @param list<array<string, mixed>> $checks
         */
        $checks = (array) apply_filters('medora_security_checks', $checks);

        $failed = count(array_filter($checks, static fn (array $c): bool => $c['status'] === 'fail'));

        return [
            'passed' => count($checks) - $failed,
            'failed' => $failed,
            'checks' => array_values($checks),
        ];
    }

    /** @return array{id: string, label: string, status: string, detail: string, severity: string} */
    private function check(string $id, string $label, bool $passed, string $detail, string $severity): array
    {
        return [
            'id'       => $id,
            'label'    => $label,
            'status'   => $passed ? 'pass' : 'fail',
            'detail'   => $passed ? '' : $detail,
            'severity' => $severity,
        ];
    }
}
