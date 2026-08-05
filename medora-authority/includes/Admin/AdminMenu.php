<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

use Medora\Authority\Core\Capabilities;
use Medora\Authority\Core\Container;
use Medora\Authority\Score\AnalysisRepository;
use Medora\Authority\Score\AuthorityScoreCalculator;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the admin menu and renders the React mount point.
 *
 * All sub-screens are routed client-side; the PHP side registers them only so
 * WordPress renders the submenu and so a deep link lands on the right view.
 */
final class AdminMenu
{
    private const SLUG = 'medora';

    public function __construct(
        private readonly Container $container,
        private readonly AssetManager $assets,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('plugin_action_links_' . MEDORA_PLUGIN_BASENAME, [$this, 'actionLinks']);
        add_action('admin_bar_menu', [$this, 'adminBarScore'], 80);
    }

    public function addPages(): void
    {
        /** @var string $menuTitle */
        $menuTitle = apply_filters('medora_admin_menu_title', __('Medora', 'medora-authority'));
        /** @var string $pageTitle */
        $pageTitle = apply_filters('medora_admin_page_title', __('Medora Authority', 'medora-authority'));

        add_menu_page(
            $pageTitle,
            $menuTitle,
            Capabilities::VIEW_DASHBOARD,
            self::SLUG,
            [$this, 'render'],
            'dashicons-superhero-alt',
            58
        );

        // Route => [label, experience feature]. The feature decides whether the
        // menu entry is drawn; it is not a permission check — the page itself
        // still renders for anyone with the capability, because hiding a menu
        // item is not security and must not be mistaken for it.
        $subpages = [
            ''          => [__('Overview', 'medora-authority'), 'overview'],
            'entities'  => [__('Entities', 'medora-authority'), 'entities'],
            'graph'     => [__('Knowledge Graph', 'medora-authority'), 'graph'],
            'content'   => [__('Content', 'medora-authority'), 'content'],
            'crawlers'  => [__('AI Crawlers', 'medora-authority'), 'crawlers'],
            'analytics' => [__('AI Analytics', 'medora-authority'), 'analytics'],
            'audit'     => [__('Audit log', 'medora-authority'), 'audit_log'],
            'settings'  => [__('Settings', 'medora-authority'), 'settings'],
        ];

        $experience = $this->container->get(ExperienceMode::class);

        foreach ($subpages as $route => [$label, $feature]) {
            if (! $experience->shows($feature)) {
                continue;
            }

            add_submenu_page(
                self::SLUG,
                $label,
                $label,
                match ($route) {
                    'settings' => Capabilities::MANAGE_SETTINGS,
                    // The log carries who did what; reading it is its own
                    // permission, not a side effect of seeing the dashboard.
                    'audit'    => Capabilities::VIEW_AUDIT_LOG,
                    default    => Capabilities::VIEW_DASHBOARD,
                },
                $route === '' ? self::SLUG : self::SLUG . '-' . $route,
                [$this, 'render']
            );
        }
    }

    public function enqueue(string $hookSuffix): void
    {
        // Conditional loading: a 300 KB dashboard bundle has no business
        // loading on the posts list or on someone else's settings page.
        if (! str_contains($hookSuffix, self::SLUG)) {
            return;
        }

        $this->assets->enqueueApp($this->routeFromHook($hookSuffix));
    }

    public function render(): void
    {
        printf(
            '<div class="wrap medora-wrap"><div id="medora-root" data-route="%s"><p class="medora-loading">%s</p></div></div>',
            esc_attr($this->currentRoute()),
            esc_html__('Loading Medora…', 'medora-authority')
        );
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function actionLinks(array $links): array
    {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=' . self::SLUG . '-settings')),
                esc_html__('Settings', 'medora-authority')
            )
        );

        return $links;
    }

    /**
     * Surface the current page's authority score in the admin bar while
     * viewing it on the front end — the fastest possible feedback loop.
     */
    public function adminBarScore(mixed $adminBar): void
    {
        if (! $adminBar instanceof \WP_Admin_Bar || is_admin() || ! is_singular()) {
            return;
        }

        if (! current_user_can(Capabilities::VIEW_DASHBOARD)) {
            return;
        }

        $post = get_post();

        if (! $post instanceof \WP_Post) {
            return;
        }

        $analysis = $this->container->get(AnalysisRepository::class)->find('post', $post->ID);

        if ($analysis === null) {
            return;
        }

        $score = (float) $analysis['result']['overall'];

        $adminBar->add_node([
            'id'    => 'medora-score',
            'title' => sprintf(
                /* translators: 1: score out of 100, 2: letter grade. */
                __('AI Authority: %1$s (%2$s)', 'medora-authority'),
                number_format_i18n($score, 1),
                AuthorityScoreCalculator::gradeFor($score)
            ),
            'href'  => admin_url('post.php?post=' . $post->ID . '&action=edit'),
        ]);
    }

    private function currentRoute(): string
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : self::SLUG;

        return $page === self::SLUG ? 'overview' : (string) str_replace(self::SLUG . '-', '', $page);
    }

    private function routeFromHook(string $hookSuffix): string
    {
        return $this->currentRoute() !== '' ? $this->currentRoute() : 'overview';
    }
}
