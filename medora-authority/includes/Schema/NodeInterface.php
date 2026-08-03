<?php

declare(strict_types=1);

namespace Medora\Authority\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A contributor to the JSON-LD `@graph`.
 *
 * Nodes return *lists* of node arrays rather than a single node, because one
 * logical concern often produces several: a breadcrumb builder emits one
 * `BreadcrumbList` plus its `ListItem`s, and an FAQ builder emits one
 * `FAQPage` containing many `Question`s.
 */
interface NodeInterface
{
    public function id(): string;

    public function appliesTo(SchemaContext $context): bool;

    /**
     * @return list<array<string, mixed>>
     */
    public function build(SchemaContext $context): array;
}
