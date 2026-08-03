<?php

declare(strict_types=1);

namespace Medora\Authority\Linking;

use Medora\Authority\Security\AuditLogRepository;
use Medora\Authority\Support\Hash;
use Medora\Authority\Support\Text;
use WP_Error;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Applies one suggested internal link, on an editor's explicit instruction.
 *
 * This is the narrowest possible crossing of the "never rewrite published
 * content" line, and every part of it is deliberate:
 *
 * - **One link per call, chosen by a human.** There is no "apply all". The
 *   editor picks the target and the anchor.
 * - **The anchor must already exist in the prose.** Text is never inserted, only
 *   wrapped. That is what stops the feature degenerating into the keyword
 *   stuffing it is meant to replace.
 * - **The target must be one the engine actually suggested.** Otherwise the
 *   endpoint would be an arbitrary-HTML injection primitive wearing a
 *   capability check.
 * - **Inserted anchors are marked** with `data-medora-link`, so they are
 *   visible in the editor, revertible precisely, and auditable.
 * - **WordPress revisions do the undo.** The update goes through
 *   `wp_update_post()`, so the previous content is always recoverable even if
 *   `revert()` is never called.
 */
final class LinkApplier
{
    public function __construct(
        private readonly LinkSuggestionEngine $engine,
        private readonly AuditLogRepository $audit,
        private readonly Hash $hash,
        private readonly AnchorWrapper $wrapper,
    ) {
    }

    /**
     * Wrap an existing phrase in a link to another post.
     *
     * @param int $occurrence Which occurrence to link, 1-based. Later
     *                        occurrences are usually better: the first mention
     *                        is often inside the opening answer, where a link
     *                        pulls the reader away from the payload.
     * @return array{applied: bool, anchor: string, target_id: int, occurrence: int}|WP_Error
     */
    public function apply(WP_Post $post, int $targetId, string $anchor, int $occurrence = 1): array|WP_Error
    {
        if (! current_user_can('edit_post', $post->ID)) {
            return new WP_Error(
                'medora_forbidden',
                __('You cannot edit this post.', 'medora-authority'),
                ['status' => 403]
            );
        }

        $target = get_post($targetId);

        if (! $target instanceof WP_Post || $target->post_status !== 'publish') {
            return new WP_Error(
                'medora_not_found',
                __('Link target not found.', 'medora-authority'),
                ['status' => 404]
            );
        }

        if ($targetId === $post->ID) {
            return new WP_Error(
                'medora_bad_request',
                __('A page cannot link to itself.', 'medora-authority'),
                ['status' => 400]
            );
        }

        $suggestion = $this->findSuggestion($post, $targetId, $anchor);

        if ($suggestion === null) {
            return new WP_Error(
                'medora_bad_request',
                __('That link was not suggested for this page. Only suggested targets and anchors can be applied.', 'medora-authority'),
                ['status' => 400]
            );
        }

        $wrapped = $this->wrapper->wrap(
            $post->post_content,
            $anchor,
            (string) get_permalink($target),
            max(1, $occurrence)
        );

        if (! $wrapped['applied']) {
            return new WP_Error(
                'medora_bad_request',
                sprintf(
                    /* translators: 1: the anchor phrase, 2: number of eligible places found. */
                    __('Could not link "%1$s" at that position — %2$d linkable occurrence(s) found in the body text.', 'medora-authority'),
                    $anchor,
                    $wrapped['eligible']
                ),
                ['status' => 400, 'eligible' => $wrapped['eligible']]
            );
        }

        // wp_update_post creates a revision, which is the real undo.
        $result = wp_update_post(
            ['ID' => $post->ID, 'post_content' => $wrapped['content']],
            true
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit->record(
            'link.applied',
            'post',
            $post->ID,
            ['target_id' => $targetId, 'anchor' => $anchor, 'occurrence' => $occurrence],
            $this->hash->visitorIp()
        );

        /**
         * Fires after an internal link has been applied.
         *
         * @param WP_Post $post
         * @param int     $targetId
         * @param string  $anchor
         */
        do_action('medora_link_applied', $post, $targetId, $anchor);

        return [
            'applied'    => true,
            'anchor'     => $anchor,
            'target_id'  => $targetId,
            'occurrence' => max(1, $occurrence),
        ];
    }

    /**
     * Remove links this class inserted.
     *
     * Only anchors carrying `data-medora-link` are touched, so an editor's own
     * links to the same target survive.
     *
     * @return array{reverted: int}|WP_Error
     */
    public function revert(WP_Post $post, ?int $targetId = null): array|WP_Error
    {
        if (! current_user_can('edit_post', $post->ID)) {
            return new WP_Error(
                'medora_forbidden',
                __('You cannot edit this post.', 'medora-authority'),
                ['status' => 403]
            );
        }

        $targetUrl = null;

        if ($targetId !== null) {
            $target = get_post($targetId);

            if (! $target instanceof WP_Post) {
                return new WP_Error('medora_not_found', __('Link target not found.', 'medora-authority'), ['status' => 404]);
            }

            $targetUrl = (string) get_permalink($target);
        }

        $unwrapped = $this->wrapper->unwrap($post->post_content, 'data-medora-link', $targetUrl);
        $reverted  = $unwrapped['reverted'];

        if ($reverted === 0) {
            return ['reverted' => 0];
        }

        $result = wp_update_post(['ID' => $post->ID, 'post_content' => $unwrapped['content']], true);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->audit->record(
            'link.reverted',
            'post',
            $post->ID,
            ['target_id' => $targetId, 'count' => $reverted],
            $this->hash->visitorIp()
        );

        return ['reverted' => $reverted];
    }

    /**
     * Confirm the target and anchor came from the engine's own suggestions.
     *
     * @return array<string, mixed>|null
     */
    private function findSuggestion(WP_Post $post, int $targetId, string $anchor): ?array
    {
        $normalisedAnchor = Text::normalize($anchor);

        foreach ($this->engine->suggestFor($post, 25) as $suggestion) {
            if ($suggestion['target_id'] !== $targetId) {
                continue;
            }

            foreach ($suggestion['anchors'] as $candidate) {
                if (Text::normalize($candidate) === $normalisedAnchor) {
                    return $suggestion;
                }
            }
        }

        return null;
    }

    /**
     * How many links this class has inserted into a post.
     */
    public function countApplied(WP_Post $post): int
    {
        return (int) preg_match_all('#<a\b[^>]*\bdata-medora-link\b#i', $post->post_content);
    }

    /**
     * How many places a phrase could legitimately be linked.
     *
     * Lets the UI say "3 places you could put this" before an editor commits
     * to one.
     */
    public function countEligible(WP_Post $post, string $anchor): int
    {
        return $this->wrapper->countEligible($post->post_content, $anchor);
    }
}
