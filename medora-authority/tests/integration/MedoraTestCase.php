<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\Core\Plugin;
use Medora\Authority\Core\Tables;
use WP_UnitTestCase;

/**
 * Shared setup for integration tests.
 *
 * Truncates Medora's tables between tests. WordPress's own rollback only
 * covers core tables, so without this a repository test would see rows written
 * by whichever test ran before it.
 */
abstract class MedoraTestCase extends WP_UnitTestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Plugin::instance()->container();

        $this->truncateMedoraTables();

        // Options are autoloaded and cached in-process, so a test that changes
        // a setting would otherwise leak into the next one.
        $this->container->get(Options::class)->replace(Options::defaults());
        $this->container->get(Options::class)->flush();
    }

    protected function tearDown(): void
    {
        $this->truncateMedoraTables();

        parent::tearDown();
    }

    protected function truncateMedoraTables(): void
    {
        global $wpdb;

        foreach (Tables::allNames() as $table) {
            $name = Tables::name($table);

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- names come from a constant list.
            $wpdb->query("TRUNCATE TABLE {$name}");
        }
    }

    /**
     * A published post with real prose, so extraction and scoring have
     * something to work on.
     */
    protected function makePost(array $args = []): \WP_Post
    {
        $defaults = [
            'post_title'   => 'Fatty liver disease: symptoms and treatment',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_excerpt' => 'Fatty liver disease is the accumulation of fat in liver cells.',
            'post_content' => '<p>Fatty liver disease is the accumulation of fat in liver cells. '
                . 'It affects roughly 25% of adults worldwide and is usually reversible.</p>'
                . '<h2>What are the symptoms?</h2>'
                . '<p>Many people have no symptoms at all. Fatigue and discomfort in the upper right '
                . 'abdomen are the most commonly reported complaints. Jaundice appears only in advanced disease.</p>'
                . '<h2>How is it diagnosed?</h2>'
                . '<p>Diagnosis usually begins with a blood test and an ultrasound scan. '
                . 'A FibroScan measures liver stiffness without a biopsy.</p>'
                . '<h2>How is it treated?</h2>'
                . '<p>Weight loss of 7% to 10% reverses inflammation in most patients. '
                . 'Exercise helps independently of weight change. No drug is currently licensed for it.</p>',
        ];

        $postId = self::factory()->post->create(array_merge($defaults, $args));

        return get_post($postId);
    }

    /** An administrator, set as the current user. */
    protected function actingAsAdmin(): int
    {
        $userId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($userId);

        return $userId;
    }

    protected function actingAsSubscriber(): int
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($userId);

        return $userId;
    }
}
