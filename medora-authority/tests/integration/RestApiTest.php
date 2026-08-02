<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Core\Options;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Routes exercised the way WordPress actually dispatches them, so capability
 * enforcement and argument sanitisation are covered rather than assumed.
 *
 * Authorisation is the highest-risk surface in the plugin: the knowledge API is
 * deliberately public, which makes every "is this route open?" assertion load
 * bearing.
 */
final class RestApiTest extends MedoraTestCase
{
    private WP_REST_Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        global $wp_rest_server;

        $wp_rest_server = new WP_REST_Server();
        $this->server   = $wp_rest_server;

        do_action('rest_api_init');
    }

    protected function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;

        parent::tearDown();
    }

    /** @return array{status: int, data: mixed} */
    private function dispatch(string $method, string $route, array $params = []): array
    {
        $request = new WP_REST_Request($method, $route);

        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        if ($method !== 'GET' && $params !== []) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body((string) wp_json_encode($params));
        }

        $response = $this->server->dispatch($request);

        return ['status' => $response->get_status(), 'data' => $response->get_data()];
    }

    // --- Route registration -------------------------------------------------

    public function test_all_expected_routes_are_registered(): void
    {
        $routes = array_keys($this->server->get_routes());

        foreach (
            [
                '/medora/v1/overview',
                '/medora/v1/entities',
                '/medora/v1/graph',
                '/medora/v1/search',
                '/medora/v1/settings',
                '/medora/v1/crawlers',
                '/medora/v1/license',
                '/medora/v1/analytics',
                '/medora/v1/audit-log',
                '/medora/v1/onboarding',
                '/medora/v1/security/scan',
            ] as $route
        ) {
            $this->assertContains($route, $routes, "Missing route {$route}");
        }
    }

    // --- Public read access -------------------------------------------------

    public function test_entities_endpoint_is_public(): void
    {
        wp_set_current_user(0);

        $this->container->get(EntityRepository::class)->upsert(
            new Entity(name: 'Hepatology', type: EntityType::MEDICAL_SPECIALTY)
        );

        $result = $this->dispatch('GET', '/medora/v1/entities');

        $this->assertSame(200, $result['status']);
        $this->assertSame(1, $result['data']['total']);
        $this->assertSame('Hepatology', $result['data']['items'][0]['name']);
    }

    public function test_public_api_can_be_closed_by_filter(): void
    {
        wp_set_current_user(0);

        add_filter('medora_public_api_enabled', '__return_false');

        $result = $this->dispatch('GET', '/medora/v1/entities');

        remove_filter('medora_public_api_enabled', '__return_false');

        $this->assertContains($result['status'], [401, 403]);
    }

    public function test_prompt_pack_does_not_leak_a_draft(): void
    {
        wp_set_current_user(0);

        $draft = $this->makePost(['post_status' => 'draft']);

        $result = $this->dispatch('GET', '/medora/v1/prompt/' . $draft->ID);

        $this->assertSame(404, $result['status'], 'Unpublished content must be invisible to the public API.');
    }

    // --- Capability enforcement ---------------------------------------------

    public function test_overview_requires_the_dashboard_capability(): void
    {
        wp_set_current_user(0);
        $this->assertContains($this->dispatch('GET', '/medora/v1/overview')['status'], [401, 403]);

        $this->actingAsSubscriber();
        $this->assertSame(403, $this->dispatch('GET', '/medora/v1/overview')['status']);

        $this->actingAsAdmin();
        $this->assertSame(200, $this->dispatch('GET', '/medora/v1/overview')['status']);
    }

    public function test_settings_write_requires_manage_capability(): void
    {
        $this->actingAsSubscriber();

        $this->assertSame(
            403,
            $this->dispatch('PATCH', '/medora/v1/settings', ['site_mode' => 'medical'])['status']
        );
    }

    public function test_audit_log_requires_its_own_capability(): void
    {
        $editor = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($editor);

        // Editors get the dashboard but not the audit trail.
        $this->assertSame(200, $this->dispatch('GET', '/medora/v1/overview')['status']);
        $this->assertSame(403, $this->dispatch('GET', '/medora/v1/audit-log')['status']);
    }

    // --- Settings -----------------------------------------------------------

    public function test_settings_round_trip(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch('PATCH', '/medora/v1/settings', [
            'site_mode'         => 'medical',
            'organization_name' => 'Acme Clinic',
            'retention_days'    => 90,
        ]);

        $this->assertSame(200, $result['status']);

        $options = $this->container->get(Options::class);
        $options->flush();

        $this->assertSame('medical', $options->getString('site_mode'));
        $this->assertSame('Acme Clinic', $options->getString('organization_name'));
        $this->assertSame(90, $options->getInt('retention_days'));
    }

    public function test_settings_rejects_undeclared_keys(): void
    {
        $this->actingAsAdmin();

        $this->dispatch('PATCH', '/medora/v1/settings', [
            'site_mode'          => 'medical',
            'evil_injected_key'  => 'payload',
        ]);

        $this->container->get(Options::class)->flush();

        $this->assertArrayNotHasKey('evil_injected_key', $this->container->get(Options::class)->all());
    }

    public function test_settings_response_never_returns_the_api_key(): void
    {
        $this->actingAsAdmin();
        $this->container->get(Options::class)->set('embedding_api_key', 'sk-secret');

        $result = $this->dispatch('GET', '/medora/v1/settings');

        $this->assertArrayNotHasKey('embedding_api_key', $result['data']['settings']);
        $this->assertArrayNotHasKey('install_hash', $result['data']['settings']);
        $this->assertTrue($result['data']['has_embedding_key']);
    }

    // --- Modules ------------------------------------------------------------

    public function test_module_cannot_be_disabled_while_another_depends_on_it(): void
    {
        $this->actingAsAdmin();

        // `graph`, `semantic`, `schema` and others depend on `entity`.
        $result = $this->dispatch('PATCH', '/medora/v1/modules/entity', ['enabled' => false]);

        $this->assertSame(400, $result['status']);
        $this->assertStringContainsString('depend', strtolower($result['data']['message']));
    }

    public function test_unknown_module_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(
            404,
            $this->dispatch('PATCH', '/medora/v1/modules/nope', ['enabled' => true])['status']
        );
    }

    public function test_enabling_a_module_above_the_licence_tier_returns_402(): void
    {
        $this->actingAsAdmin();

        // White Label requires Agency; a fresh install is unlicensed.
        $result = $this->dispatch('PATCH', '/medora/v1/modules/white_label', ['enabled' => true]);

        $this->assertSame(402, $result['status']);
        $this->assertSame('agency', $result['data']['data']['required_tier'] ?? null);
    }

    // --- Crawlers -----------------------------------------------------------

    public function test_crawler_policy_can_be_set_and_overridden(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch('PATCH', '/medora/v1/crawlers', ['preset' => 'selective']);
        $this->assertSame(200, $result['status']);
        $this->assertSame('selective', $result['data']['preset']);

        $byslug = [];
        foreach ($result['data']['crawlers'] as $crawler) {
            $byslug[$crawler['slug']] = $crawler;
        }

        // Selective blocks training crawlers but keeps search ones.
        $this->assertSame('block', $byslug['gptbot']['decision']);
        $this->assertSame('allow', $byslug['oai-searchbot']['decision']);

        // An explicit override beats the preset.
        $override = $this->dispatch('PATCH', '/medora/v1/crawlers', [
            'slug'     => 'gptbot',
            'decision' => 'allow',
        ]);

        $overridden = [];
        foreach ($override['data']['crawlers'] as $crawler) {
            $overridden[$crawler['slug']] = $crawler;
        }

        $this->assertSame('allow', $overridden['gptbot']['decision']);
        $this->assertTrue($overridden['gptbot']['is_override']);
    }

    public function test_block_preset_never_blocks_classic_googlebot(): void
    {
        $this->actingAsAdmin();

        $result = $this->dispatch('PATCH', '/medora/v1/crawlers', ['preset' => 'block']);

        $byslug = [];
        foreach ($result['data']['crawlers'] as $crawler) {
            $byslug[$crawler['slug']] = $crawler;
        }

        $this->assertSame('allow', $byslug['googlebot']['decision'], 'Blocking Googlebot is an SEO own-goal.');
        $this->assertSame('block', $byslug['gptbot']['decision']);
    }

    public function test_unknown_crawler_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(
            404,
            $this->dispatch('PATCH', '/medora/v1/crawlers', ['slug' => 'nope', 'decision' => 'block'])['status']
        );
    }

    // --- Entities -----------------------------------------------------------

    public function test_entity_update_rejects_a_malformed_same_as_url(): void
    {
        $this->actingAsAdmin();

        $entity = $this->container->get(EntityRepository::class)->upsert(
            new Entity(name: 'Hepatology', type: EntityType::MEDICAL_SPECIALTY)
        );

        $result = $this->dispatch('PATCH', '/medora/v1/entities/' . $entity->id, [
            'same_as' => ['https://good.test/1', 'not a url', ''],
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(['https://good.test/1'], $result['data']['same_as']);
    }

    public function test_entity_update_rejects_an_unknown_type(): void
    {
        $this->actingAsAdmin();

        $entity = $this->container->get(EntityRepository::class)->upsert(
            new Entity(name: 'Hepatology', type: EntityType::MEDICAL_SPECIALTY)
        );

        $this->assertSame(
            400,
            $this->dispatch('PATCH', '/medora/v1/entities/' . $entity->id, ['type' => 'NotAThing'])['status']
        );
    }

    public function test_entity_write_requires_the_entity_capability(): void
    {
        $entity = $this->container->get(EntityRepository::class)->upsert(
            new Entity(name: 'Hepatology', type: EntityType::MEDICAL_SPECIALTY)
        );

        $this->actingAsSubscriber();

        $this->assertSame(
            403,
            $this->dispatch('DELETE', '/medora/v1/entities/' . $entity->id)['status']
        );
    }

    // --- Scoring ------------------------------------------------------------

    public function test_analyze_queues_by_default_and_runs_inline_on_demand(): void
    {
        $this->actingAsAdmin();
        $post = $this->makePost();

        $queued = $this->dispatch('POST', '/medora/v1/score/' . $post->ID . '/analyze');
        $this->assertSame(202, $queued['status']);
        $this->assertTrue($queued['data']['queued']);

        $sync = $this->dispatch('POST', '/medora/v1/score/' . $post->ID . '/analyze', ['sync' => true]);
        $this->assertSame(200, $sync['status']);
        $this->assertIsFloat($sync['data']['overall'] + 0.0);
        $this->assertContains($sync['data']['grade'], ['A', 'B', 'C', 'D', 'F']);
    }

    public function test_score_response_explains_every_deduction(): void
    {
        $this->actingAsAdmin();
        $post = $this->makePost();

        $result = $this->dispatch('POST', '/medora/v1/score/' . $post->ID . '/analyze', ['sync' => true]);

        $this->assertNotEmpty($result['data']['components']);

        foreach ($result['data']['deductions'] as $deduction) {
            $this->assertNotSame('', $deduction['label']);
            $this->assertNotSame(
                '',
                $deduction['recommendation'],
                'Every lost point must carry an actionable fix.'
            );
            $this->assertContains($deduction['severity'], ['critical', 'high', 'medium', 'low']);
        }
    }

    public function test_score_for_a_missing_post_is_404(): void
    {
        $this->actingAsAdmin();

        $this->assertSame(404, $this->dispatch('GET', '/medora/v1/score/99999999')['status']);
    }

    // --- Onboarding ---------------------------------------------------------

    public function test_onboarding_completes_and_persists(): void
    {
        $this->actingAsAdmin();
        $this->makePost();

        $result = $this->dispatch('POST', '/medora/v1/onboarding', [
            'site_mode'         => 'medical',
            'organization_name' => 'Acme Clinic',
            'crawler_policy'    => 'selective',
            'analyze_existing'  => true,
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertTrue($result['data']['completed']);
        $this->assertGreaterThan(0, $result['data']['queued_posts']);

        $this->container->get(Options::class)->flush();
        $this->assertTrue($this->container->get(Options::class)->getBool('onboarded'));
    }
}
