<?php

declare(strict_types=1);

namespace Medora\Authority\Schema\Nodes;

use Medora\Authority\Schema\NodeInterface;
use Medora\Authority\Schema\SchemaContext;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Publishes the page's own entities as first-class nodes.
 *
 * Without this, `mentions` and `about` on the page node would point at `@id`s
 * that resolve to nothing. Emitting the entity nodes alongside makes the graph
 * self-contained: a crawler that fetches one page gets both the claim and the
 * definition of what the claim is about.
 */
final class EntityNode implements NodeInterface
{
    private const MIN_SALIENCE = 0.15;
    private const MAX_NODES    = 10;

    public function id(): string
    {
        return 'entities';
    }

    public function appliesTo(SchemaContext $context): bool
    {
        return $context->entities !== [];
    }

    public function build(SchemaContext $context): array
    {
        $nodes = [];

        foreach ($context->entities as $row) {
            if ($row['salience'] < self::MIN_SALIENCE) {
                continue;
            }

            $node = $row['entity']->toSchemaNode();

            // A node with only a type and an id tells a model nothing; skip it
            // rather than pad the graph.
            if (! isset($node['name'])) {
                continue;
            }

            $nodes[] = $node;

            if (count($nodes) >= self::MAX_NODES) {
                break;
            }
        }

        return $nodes;
    }
}
