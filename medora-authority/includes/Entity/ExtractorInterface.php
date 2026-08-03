<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contract for entity extraction strategies.
 *
 * Implementations must be pure and side-effect free: no database writes, no
 * HTTP calls on the hot path. An extractor that needs a remote NER service
 * should queue a background job and return what it can synchronously.
 */
interface ExtractorInterface
{
    public function id(): string;

    /**
     * Higher runs first; earlier extractors win ties on entity type.
     */
    public function priority(): int;

    /**
     * @param string  $plainText Content already stripped to plain prose.
     * @return list<Candidate>
     */
    public function extract(WP_Post $post, string $plainText): array;
}
