<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Support\Arr;
use Medora\Authority\Support\Text;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * A node in the site's knowledge graph.
 *
 * An entity is a *thing* — a person, a condition, a procedure, a clinic, a
 * product — not a keyword. It may be backed by a WordPress object (a post, a
 * term, a user) or be purely conceptual, extracted from prose.
 */
final class Entity
{
    /**
     * @param list<string>        $sameAs External identifiers (Wikidata, ORCID, official site).
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type = EntityType::THING,
        public readonly int $id = 0,
        public readonly string $uid = '',
        public readonly string $description = '',
        public readonly string $objectType = 'virtual',
        public readonly int $objectId = 0,
        public readonly string $permalink = '',
        public readonly array $sameAs = [],
        public readonly array $meta = [],
        public readonly float $authorityScore = 0.0,
        public readonly float $confidence = 0.0,
        public readonly int $occurrences = 0,
        public readonly bool $isPrimary = false,
    ) {
    }

    /**
     * Deterministic identity.
     *
     * Two extractors that find "Crohn's disease" and "crohn's disease" on
     * different pages must produce the same uid, so identity is derived from
     * the *normalised* name plus the type — never from the database id, which
     * is not stable across a re-import.
     */
    public static function uidFor(string $type, string $name): string
    {
        return substr(hash('sha256', $type . '|' . Text::normalize($name)), 0, 40);
    }

    public function canonicalName(): string
    {
        return Text::normalize($this->name);
    }

    public function withId(int $id, string $uid): self
    {
        return new self(
            $this->name,
            $this->type,
            $id,
            $uid,
            $this->description,
            $this->objectType,
            $this->objectId,
            $this->permalink,
            $this->sameAs,
            $this->meta,
            $this->authorityScore,
            $this->confidence,
            $this->occurrences,
            $this->isPrimary,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            name: (string) ($row['name'] ?? ''),
            type: (string) ($row['entity_type'] ?? EntityType::THING),
            id: (int) ($row['id'] ?? 0),
            uid: (string) ($row['entity_uid'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            objectType: (string) ($row['object_type'] ?? 'virtual'),
            objectId: (int) ($row['object_id'] ?? 0),
            permalink: (string) ($row['permalink'] ?? ''),
            sameAs: array_values(array_map('strval', Arr::fromJson($row['same_as'] ?? null))),
            meta: Arr::fromJson($row['meta'] ?? null),
            authorityScore: (float) ($row['authority_score'] ?? 0),
            confidence: (float) ($row['confidence'] ?? 0),
            occurrences: (int) ($row['occurrences'] ?? 0),
            isPrimary: (bool) ($row['is_primary'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'entity_uid'      => $this->uid !== '' ? $this->uid : self::uidFor($this->type, $this->name),
            'entity_type'     => $this->type,
            'name'            => mb_substr($this->name, 0, 191),
            'canonical_name'  => mb_substr($this->canonicalName(), 0, 191),
            'description'     => $this->description,
            'object_type'     => $this->objectType,
            'object_id'       => $this->objectId,
            'permalink'       => mb_substr($this->permalink, 0, 255),
            'same_as'         => Arr::toJson($this->sameAs),
            'meta'            => Arr::toJson($this->meta),
            'authority_score' => round($this->authorityScore, 2),
            'confidence'      => round($this->confidence, 3),
            'occurrences'     => $this->occurrences,
            'is_primary'      => $this->isPrimary ? 1 : 0,
        ];
    }

    /** REST/JSON representation. */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'uid'             => $this->uid,
            'name'            => $this->name,
            'type'            => $this->type,
            'type_label'      => EntityType::label($this->type),
            'description'     => $this->description,
            'permalink'       => $this->permalink,
            'same_as'         => $this->sameAs,
            'authority_score' => $this->authorityScore,
            'confidence'      => $this->confidence,
            'occurrences'     => $this->occurrences,
            'object'          => ['type' => $this->objectType, 'id' => $this->objectId],
        ];
    }

    /** Schema.org node for this entity, ready to drop into the `@graph`. */
    public function toSchemaNode(): array
    {
        return Arr::compact([
            '@type'       => $this->type,
            '@id'         => $this->schemaId(),
            'name'        => $this->name,
            'description' => $this->description,
            'url'         => $this->permalink,
            'sameAs'      => $this->sameAs,
        ]);
    }

    public function schemaId(): string
    {
        return home_url('/#/entity/' . ($this->uid !== '' ? $this->uid : self::uidFor($this->type, $this->name)));
    }
}
