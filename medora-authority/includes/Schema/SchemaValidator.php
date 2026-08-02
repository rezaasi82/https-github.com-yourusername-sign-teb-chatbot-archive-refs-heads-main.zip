<?php

declare(strict_types=1);

namespace Medora\Authority\Schema;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Structural validation of a generated graph.
 *
 * This is not a full Schema.org validator — that would need the whole
 * vocabulary. It catches the failure modes that actually occur in generated
 * markup and that silently disqualify a page from rich results: dangling
 * `@id` references, missing required properties, and empty values that look
 * present but carry nothing.
 */
final class SchemaValidator
{
    /**
     * Properties without which a node is useless to a consumer.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED = [
        'Organization'           => ['name', 'url'],
        'MedicalOrganization'    => ['name', 'url'],
        'WebSite'                => ['name', 'url'],
        'WebPage'                => ['name', 'url'],
        'MedicalWebPage'         => ['name', 'url'],
        'Article'                => ['headline', 'author', 'datePublished'],
        'MedicalScholarlyArticle' => ['headline', 'author', 'datePublished'],
        'FAQPage'                => ['mainEntity'],
        'BreadcrumbList'         => ['itemListElement'],
        'Person'                 => ['name'],
        'Physician'              => ['name'],
        'ImageObject'            => ['url'],
    ];

    public const SEVERITY_ERROR   = 'error';
    public const SEVERITY_WARNING = 'warning';

    /**
     * @param array{'@context'?: string, '@graph'?: list<array<string, mixed>>} $document
     * @return array{
     *     valid: bool,
     *     errors: list<array{severity: string, node: string, message: string}>,
     *     node_count: int
     * }
     */
    public function validate(array $document): array
    {
        $graph  = $document['@graph'] ?? [];
        $issues = [];

        if (! isset($document['@context'])) {
            $issues[] = [
                'severity' => self::SEVERITY_ERROR,
                'node'     => '@document',
                'message'  => __('Missing @context. Consumers cannot resolve the vocabulary.', 'medora-authority'),
            ];
        }

        $declaredIds  = [];
        $referencedIds = [];

        foreach ($graph as $node) {
            $type = $node['@type'] ?? '';
            $type = is_array($type) ? (string) ($type[0] ?? '') : (string) $type;
            $id   = (string) ($node['@id'] ?? '');
            $name = $id !== '' ? $id : $type;

            if ($type === '') {
                $issues[] = [
                    'severity' => self::SEVERITY_ERROR,
                    'node'     => $name ?: '(anonymous)',
                    'message'  => __('Node has no @type.', 'medora-authority'),
                ];

                continue;
            }

            if ($id !== '') {
                $declaredIds[$id] = true;
            }

            foreach (self::REQUIRED[$type] ?? [] as $property) {
                if ($this->isEmpty($node[$property] ?? null)) {
                    $issues[] = [
                        'severity' => self::SEVERITY_ERROR,
                        'node'     => $name,
                        'message'  => sprintf(
                            /* translators: 1: property name, 2: schema type. */
                            __('Missing required property "%1$s" on %2$s.', 'medora-authority'),
                            $property,
                            $type
                        ),
                    ];
                }
            }

            $this->collectReferences($node, $referencedIds);
        }

        // A reference to an id no node declares is a dangling pointer: the
        // consumer sees a relationship to nothing.
        foreach (array_keys($referencedIds) as $reference) {
            if (isset($declaredIds[$reference])) {
                continue;
            }

            // References to another page's node are expected and legitimate.
            if (str_contains($reference, '/#/entity/')) {
                continue;
            }

            $issues[] = [
                'severity' => self::SEVERITY_WARNING,
                'node'     => $reference,
                'message'  => __('Referenced @id is not defined in this graph.', 'medora-authority'),
            ];
        }

        $errors = array_values(array_filter(
            $issues,
            static fn (array $issue): bool => $issue['severity'] === self::SEVERITY_ERROR
        ));

        return [
            'valid'      => $errors === [],
            'errors'     => $issues,
            'node_count' => count($graph),
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, bool>  $references
     */
    private function collectReferences(array $node, array &$references): void
    {
        foreach ($node as $key => $value) {
            if ($key === '@id') {
                continue;
            }

            if (is_array($value)) {
                if (isset($value['@id']) && is_string($value['@id'])) {
                    $references[$value['@id']] = true;
                }

                $this->collectReferences($value, $references);
            }
        }
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        // `['@id' => '']` looks like a reference but points nowhere.
        return is_array($value) && array_keys($value) === ['@id'] && $value['@id'] === '';
    }
}
