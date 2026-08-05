<?php

declare(strict_types=1);

namespace Medora\Authority\Admin;

use Medora\Authority\Core\Capabilities;
use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Performance\Jobs\IndexPostJob;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One-click setup.
 *
 * The wizard asks five questions, then does the work: it writes the settings,
 * applies a crawler policy, and queues an initial analysis of the site's
 * existing content — so the dashboard has real numbers the first time it is
 * opened rather than an empty state.
 *
 * The UI is React (served on the dashboard route); this class owns the steps
 * definition and the completion endpoint, so the two cannot drift apart.
 */
final class SetupWizard
{
    private const OPTION_DONE = 'onboarded';

    public function __construct(private readonly Container $container)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_notices', [$this, 'renderNotice']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('medora/v1', '/onboarding', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'steps'],
                'permission_callback' => static fn (): bool => current_user_can(Capabilities::MANAGE_SETTINGS),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'complete'],
                'permission_callback' => static fn (): bool => current_user_can(Capabilities::MANAGE_SETTINGS),
                'args'                => [
                    'site_mode'         => ['type' => 'string', 'enum' => ['general', 'medical']],
                    'experience_mode'   => ['type' => 'string', 'enum' => array_column(ExperienceMode::choices(), 'id')],
                    'organization_name' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'organization_type' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'crawler_policy'    => ['type' => 'string', 'enum' => ['allow', 'selective', 'block']],
                    'analyze_existing'  => ['type' => 'boolean', 'default' => true],
                ],
            ],
        ]);
    }

    public function steps(): WP_REST_Response
    {
        $options = $this->container->get(Options::class);

        return new WP_REST_Response([
            'completed' => $options->getBool(self::OPTION_DONE),
            'defaults'  => [
                'organization_name' => $options->getString('organization_name') ?: (string) get_bloginfo('name'),
                'site_mode'         => $options->getString('site_mode', 'general'),
                'experience_mode'   => $options->getString('experience_mode', 'beginner'),
                'crawler_policy'    => $options->getString('crawler_policy', 'allow'),
            ],
            'steps' => [
                [
                    'id'          => 'welcome',
                    'title'       => __('Welcome to Medora', 'medora-authority'),
                    'description' => __('Five questions. Then Medora analyses your site and shows you exactly what stands between it and being cited by AI assistants.', 'medora-authority'),
                ],
                [
                    'id'          => 'site_mode',
                    'title'       => __('What kind of site is this?', 'medora-authority'),
                    'description' => __('Medical mode adds clinical vocabulary, reviewer metadata and YMYL trust scoring. Pick General for everything else.', 'medora-authority'),
                    'field'       => 'site_mode',
                    'options'     => [
                        ['value' => 'general', 'label' => __('General', 'medora-authority')],
                        ['value' => 'medical', 'label' => __('Health / medical (YMYL)', 'medora-authority')],
                    ],
                ],
                [
                    'id'          => 'organization',
                    'title'       => __('Who publishes this site?', 'medora-authority'),
                    'description' => __('This becomes the publisher node every page in your knowledge graph points back to.', 'medora-authority'),
                    'field'       => 'organization_name',
                ],
                [
                    'id'          => 'crawlers',
                    'title'       => __('AI crawler policy', 'medora-authority'),
                    'description' => __('Selective is the usual choice: stay citable in AI answers, opt out of model training. You can change any individual crawler later.', 'medora-authority'),
                    'field'       => 'crawler_policy',
                    'options'     => [
                        ['value' => 'allow', 'label' => __('Allow all — maximum AI visibility', 'medora-authority')],
                        ['value' => 'selective', 'label' => __('Selective — allow search, block training', 'medora-authority')],
                        ['value' => 'block', 'label' => __('Block AI crawlers (classic search still allowed)', 'medora-authority')],
                    ],
                ],
                [
                    'id'          => 'experience',
                    'title'       => __('How much do you want to see?', 'medora-authority'),
                    'description' => __('This only changes how much of the dashboard is on screen. It grants nobody any extra access, and you can switch at any time.', 'medora-authority'),
                    'field'       => 'experience_mode',
                    // Drawn from ExperienceMode so the wizard cannot describe a
                    // mode differently from the class that implements it, and a
                    // fifth mode needs adding in exactly one place.
                    'options'     => array_map(
                        static fn (array $choice): array => [
                            'value'       => $choice['id'],
                            'label'       => $choice['label'],
                            'description' => $choice['description'],
                        ],
                        ExperienceMode::choices()
                    ),
                ],
            ],
        ]);
    }

    public function complete(WP_REST_Request $request): WP_REST_Response
    {
        $options = $this->container->get(Options::class);

        $settings = [self::OPTION_DONE => true];

        foreach (['site_mode', 'experience_mode', 'organization_name', 'organization_type', 'crawler_policy'] as $key) {
            $value = $request->get_param($key);

            if ($value !== null && $value !== '') {
                $settings[$key] = sanitize_text_field((string) $value);
            }
        }

        $settings['organization_url'] ??= home_url('/');

        $options->merge($settings);

        delete_option('medora_needs_onboarding');
        set_transient('medora_flush_rewrites', 1, HOUR_IN_SECONDS);

        $queued = 0;

        if ((bool) $request->get_param('analyze_existing')) {
            $queued = $this->queueInitialAnalysis();
        }

        do_action('medora_onboarding_completed', $settings);

        return new WP_REST_Response([
            'completed'     => true,
            'queued_posts'  => $queued,
            'settings'      => $settings,
        ]);
    }

    /**
     * Queue the site's most recent content for analysis.
     *
     * Capped rather than unbounded: on a 50,000-post site, queueing everything
     * during onboarding would fill the jobs table and give a first impression
     * of a stalled plugin. The nightly cron picks up the rest.
     */
    private function queueInitialAnalysis(int $limit = 200): int
    {
        $postIds = get_posts([
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        $queue = $this->container->get(JobQueue::class);

        foreach ($postIds as $postId) {
            $queue->push(IndexPostJob::class, ['post_id' => (int) $postId]);
        }

        return count($postIds);
    }

    public function renderNotice(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SETTINGS)) {
            return;
        }

        if ($this->container->get(Options::class)->getBool(self::OPTION_DONE)) {
            return;
        }

        $screen = get_current_screen();

        // Do not stack a notice on top of the wizard the notice points at.
        if ($screen !== null && str_contains((string) $screen->id, 'medora')) {
            return;
        }

        printf(
            '<div class="notice notice-info"><p><strong>%s</strong> %s <a class="button button-primary" href="%s">%s</a></p></div>',
            esc_html__('Medora Authority', 'medora-authority'),
            esc_html__('Run the two-minute setup to start measuring your AI authority.', 'medora-authority'),
            esc_url(admin_url('admin.php?page=medora')),
            esc_html__('Start setup', 'medora-authority')
        );
    }
}
