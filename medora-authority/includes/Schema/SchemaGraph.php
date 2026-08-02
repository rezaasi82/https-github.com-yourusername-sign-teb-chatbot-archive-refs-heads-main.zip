<?php

declare(strict_types=1);

namespace Medora\Authority\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Assembles one connected `@graph` for the current page.
 *
 * A single graph beats several standalone JSON-LD blocks: nodes reference each
 * other by `@id`, so an article, its author, its publisher and its entities are
 * delivered as one connected statement rather than four disconnected ones that
 * a consumer has to reconcile.
 */
final class SchemaGraph
{
    /** @var list<NodeInterface> */
    private array $nodes = [];

    public function addNode(NodeInterface $node): void
    {
        $this->nodes[] = $node;
    }

    /** @return list<NodeInterface> */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return array{'@context': string, '@graph': list<array<string, mixed>>}
     */
    public function build(SchemaContext $context): array
    {
        $graph = [];

        foreach ($this->nodes as $node) {
            if (! $node->appliesTo($context)) {
                continue;
            }

            foreach ($node->build($context) as $entry) {
                if ($entry !== []) {
                    $graph[] = $entry;
                }
            }
        }

        $graph = $this->deduplicate($graph);

        /**
         * Filter the assembled schema graph before output.
         *
         * @param list<array<string, mixed>> $graph
         * @param SchemaContext              $context
         */
        $graph = (array) apply_filters('medora_schema_graph', $graph, $context);

        return [
            '@context' => 'https://schema.org',
            '@graph'   => array_values($graph),
        ];
    }

    public function toJson(SchemaContext $context): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) wp_json_encode($this->build($context), $flags);
    }

    /**
     * Merge nodes that share an `@id`.
     *
     * Two nodes claiming the same identity with different properties is the
     * most common structured-data validation failure, and it happens easily
     * once several builders contribute to one graph.
     *
     * @param list<array<string, mixed>> $graph
     * @return list<array<string, mixed>>
     */
    private function deduplicate(array $graph): array
    {
        $byId     = [];
        $anonymous = [];

        foreach ($graph as $node) {
            $id = $node['@id'] ?? null;

            if (! is_string($id) || $id === '') {
                $anonymous[] = $node;
                continue;
            }

            $byId[$id] = isset($byId[$id]) ? array_merge($byId[$id], $node) : $node;
        }

        return [...array_values($byId), ...$anonymous];
    }
}
