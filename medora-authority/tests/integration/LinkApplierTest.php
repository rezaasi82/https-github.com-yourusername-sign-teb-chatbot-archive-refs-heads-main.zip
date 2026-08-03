<?php

declare(strict_types=1);

namespace Medora\Authority\Tests\Integration;

use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Linking\LinkApplier;
use Medora\Authority\Security\AuditLogRepository;
use Medora\Authority\Vector\VectorIndex;
use WP_Error;

/**
 * This is the only code path that writes to a customer's published content, so
 * the guard rails get tested rather than trusted: it refuses targets it did not
 * suggest, it refuses users who cannot edit, it leaves a revision, and it can
 * be undone precisely.
 */
final class LinkApplierTest extends MedoraTestCase
{
    private LinkApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applier = $this->container->get(LinkApplier::class);
    }

    /**
     * Build a source and a target that the suggestion engine will actually
     * connect: shared entity, shared vocabulary, both embedded.
     *
     * @return array{0: \WP_Post, 1: \WP_Post}
     */
    private function linkablePair(): array
    {
        $entities = $this->container->get(EntityRepository::class);
        $entity   = $entities->upsert(new Entity(
            name: 'liver biopsy',
            type: EntityType::MEDICAL_PROCEDURE,
        ));

        $source = $this->makePost([
            'post_title'   => 'Fatty liver disease explained',
            'post_content' => '<p>Fatty liver disease is common. A liver biopsy is sometimes needed '
                . 'to stage the disease, though a liver biopsy is invasive and now used less often.</p>'
                . '<h2>How is a liver biopsy performed?</h2>'
                . '<p>The procedure takes about twenty minutes under local anaesthetic.</p>',
        ]);

        $target = $this->makePost([
            'post_title'   => 'Liver biopsy: what to expect',
            'post_content' => '<p>A liver biopsy takes a small sample of liver tissue. '
                . 'It is the reference standard for staging fatty liver disease and fibrosis.</p>',
        ]);

        foreach ([$source, $target] as $post) {
            $entities->link($entity->id, 'post', $post->ID, 3, 0.8);
            $this->container->get(VectorIndex::class)->indexPost($post, true);
        }

        return [$source, $target];
    }

    public function test_applies_a_suggested_link_and_records_it(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        $result = $this->applier->apply($source, $target->ID, 'liver biopsy');

        $this->assertNotInstanceOf(WP_Error::class, $result, is_wp_error($result) ? $result->get_error_message() : '');
        $this->assertTrue($result['applied']);

        $updated = get_post($source->ID);

        $this->assertStringContainsString('data-medora-link="1"', $updated->post_content);
        $this->assertStringContainsString(get_permalink($target), $updated->post_content);
        $this->assertSame(1, $this->applier->countApplied($updated));

        // The heading occurrence must be untouched.
        $this->assertStringContainsString(
            '<h2>How is a liver biopsy performed?</h2>',
            $updated->post_content
        );
    }

    public function test_apply_leaves_a_revision_so_the_edit_is_recoverable(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        $before = count(wp_get_post_revisions($source->ID));

        $this->applier->apply($source, $target->ID, 'liver biopsy');

        $this->assertGreaterThan(
            $before,
            count(wp_get_post_revisions($source->ID)),
            'wp_update_post must create a revision — that is the real undo.'
        );
    }

    public function test_apply_writes_an_audit_entry(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        $this->applier->apply($source, $target->ID, 'liver biopsy');

        $actions = array_column($this->container->get(AuditLogRepository::class)->recent(), 'action');

        $this->assertContains('link.applied', $actions);
    }

    public function test_refuses_a_target_that_was_not_suggested(): void
    {
        $this->actingAsAdmin();
        [$source] = $this->linkablePair();

        // A post with nothing in common — the engine would never suggest it.
        $unrelated = $this->makePost([
            'post_title'   => 'Office opening hours',
            'post_content' => '<p>We are open Monday to Friday.</p>',
        ]);

        $result = $this->applier->apply($source, $unrelated->ID, 'liver biopsy');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('medora_bad_request', $result->get_error_code());
        $this->assertStringNotContainsString('data-medora-link', get_post($source->ID)->post_content);
    }

    public function test_refuses_an_anchor_that_was_not_suggested(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        // The phrase exists in the prose but is not a shared entity, so it was
        // never offered as an anchor. Accepting it would make this endpoint an
        // arbitrary-markup injection primitive.
        $result = $this->applier->apply($source, $target->ID, 'local anaesthetic');

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_refuses_a_user_who_cannot_edit_the_post(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        $this->actingAsSubscriber();

        $result = $this->applier->apply($source, $target->ID, 'liver biopsy');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('medora_forbidden', $result->get_error_code());
    }

    public function test_refuses_a_self_link(): void
    {
        $this->actingAsAdmin();
        [$source] = $this->linkablePair();

        $result = $this->applier->apply($source, $source->ID, 'liver biopsy');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('medora_bad_request', $result->get_error_code());
    }

    public function test_refuses_an_unpublished_target(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        wp_update_post(['ID' => $target->ID, 'post_status' => 'draft']);

        $result = $this->applier->apply($source, $target->ID, 'liver biopsy');

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_can_target_a_later_occurrence(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        // The first mention sits inside the opening answer, where a link pulls
        // the reader away from the payload; the second is the better choice.
        $this->assertGreaterThanOrEqual(2, $this->applier->countEligible($source, 'liver biopsy'));

        $result = $this->applier->apply($source, $target->ID, 'liver biopsy', 2);

        $this->assertNotInstanceOf(WP_Error::class, $result);

        $content = get_post($source->ID)->post_content;

        $this->assertSame(1, substr_count($content, 'data-medora-link'));
        $this->assertStringContainsString(
            'though a <a href="' . get_permalink($target) . '" data-medora-link="1">liver biopsy</a> is invasive',
            $content
        );
    }

    public function test_reverting_restores_the_prose_exactly(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        $original = $source->post_content;

        $this->applier->apply($source, $target->ID, 'liver biopsy');

        $result = $this->applier->revert(get_post($source->ID));

        $this->assertSame(1, $result['reverted']);
        $this->assertSame($original, get_post($source->ID)->post_content);
    }

    public function test_reverting_leaves_an_editors_own_links_alone(): void
    {
        $this->actingAsAdmin();
        [$source, $target] = $this->linkablePair();

        wp_update_post([
            'ID'           => $source->ID,
            'post_content' => $source->post_content . '<p>See also <a href="/handbook/">the handbook</a>.</p>',
        ]);

        $this->applier->apply(get_post($source->ID), $target->ID, 'liver biopsy');
        $this->applier->revert(get_post($source->ID));

        $content = get_post($source->ID)->post_content;

        $this->assertStringContainsString('<a href="/handbook/">the handbook</a>', $content);
        $this->assertStringNotContainsString('data-medora-link', $content);
    }

    public function test_reverting_with_nothing_applied_is_a_no_op(): void
    {
        $this->actingAsAdmin();
        [$source] = $this->linkablePair();

        $this->assertSame(0, $this->applier->revert($source)['reverted']);
    }
}
