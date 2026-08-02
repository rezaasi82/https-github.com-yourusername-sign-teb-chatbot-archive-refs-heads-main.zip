<?php

declare(strict_types=1);

namespace Medora\Authority\Entity\Extractors;

use Medora\Authority\Entity\Candidate;
use Medora\Authority\Entity\Entity;
use Medora\Authority\Entity\EntityType;
use Medora\Authority\Entity\ExtractorInterface;
use WP_Post;
use WP_Term;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Promotes assigned taxonomy terms to entities.
 *
 * This is the highest-confidence extractor available: a term is a deliberate
 * editorial statement about what a page is about, and it already carries a
 * canonical URL, a description and a site-wide identity. Everything else in
 * the pipeline is inference by comparison.
 */
final class TaxonomyExtractor implements ExtractorInterface
{
    public function id(): string
    {
        return 'taxonomy';
    }

    public function priority(): int
    {
        return 100;
    }

    public function extract(WP_Post $post, string $plainText): array
    {
        $candidates = [];

        foreach (get_object_taxonomies($post->post_type, 'objects') as $taxonomy) {
            if (! $taxonomy->public && ! $taxonomy->show_ui) {
                continue;
            }

            $terms = get_the_terms($post, $taxonomy->name);

            if (! is_array($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (! $term instanceof WP_Term) {
                    continue;
                }

                $type = $this->typeForTaxonomy($taxonomy->name, $term);

                $entity = new Entity(
                    name: $term->name,
                    type: $type,
                    description: (string) $term->description,
                    objectType: 'term',
                    objectId: $term->term_id,
                    permalink: (string) get_term_link($term),
                    sameAs: $this->sameAsFor($term),
                    meta: ['taxonomy' => $taxonomy->name, 'slug' => $term->slug],
                );

                $candidates[] = new Candidate(
                    entity: $entity,
                    // Count real mentions in the body so salience reflects how
                    // central the term is, not just that it was assigned.
                    occurrences: max(1, $this->countMentions($plainText, $term->name)),
                    confidence: 0.95,
                    source: $this->id(),
                );
            }
        }

        return $candidates;
    }

    /**
     * Map a taxonomy to an entity type.
     *
     * Sites vary wildly here, so a filter is the escape hatch: a medical site
     * with a `condition` taxonomy maps it to `MedicalCondition` in one line.
     */
    private function typeForTaxonomy(string $taxonomy, WP_Term $term): string
    {
        $map = [
            'category'          => EntityType::TOPIC,
            'post_tag'          => EntityType::TOPIC,
            'specialty'         => EntityType::MEDICAL_SPECIALTY,
            'medical_specialty' => EntityType::MEDICAL_SPECIALTY,
            'condition'         => EntityType::MEDICAL_CONDITION,
            'disease'           => EntityType::MEDICAL_CONDITION,
            'procedure'         => EntityType::MEDICAL_PROCEDURE,
            'treatment'         => EntityType::MEDICAL_PROCEDURE,
            'symptom'           => EntityType::SYMPTOM,
            'drug'              => EntityType::DRUG,
            'location'          => EntityType::PLACE,
            'city'              => EntityType::PLACE,
            'product_cat'       => EntityType::PRODUCT,
            'service'           => EntityType::SERVICE,
        ];

        $type = $map[$taxonomy] ?? EntityType::TOPIC;

        /**
         * Filter the entity type inferred from a taxonomy term.
         *
         * @param string  $type
         * @param string  $taxonomy
         * @param WP_Term $term
         */
        return (string) apply_filters('medora_taxonomy_entity_type', $type, $taxonomy, $term);
    }

    /**
     * External identifiers stored against the term, e.g. a Wikidata Q-id or an
     * ICD-10 code. These are what let an AI system reconcile the site's entity
     * with the one in its own knowledge base.
     *
     * @return list<string>
     */
    private function sameAsFor(WP_Term $term): array
    {
        $links = [];

        foreach (['wikidata', 'icd10', 'snomed', 'mesh', 'same_as'] as $key) {
            $value = get_term_meta($term->term_id, '_medora_' . $key, true);

            if (! is_string($value) || $value === '') {
                continue;
            }

            $links[] = match ($key) {
                'wikidata' => 'https://www.wikidata.org/wiki/' . $value,
                'mesh'     => 'https://meshb.nlm.nih.gov/record/ui?ui=' . $value,
                default    => $value,
            };
        }

        return array_values(array_filter($links, static fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false));
    }

    private function countMentions(string $text, string $needle): int
    {
        $needle = trim($needle);

        if ($needle === '') {
            return 0;
        }

        return substr_count(
            \Medora\Authority\Support\Text::normalize($text),
            \Medora\Authority\Support\Text::normalize($needle)
        );
    }
}
