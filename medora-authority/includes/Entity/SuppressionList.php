<?php

declare(strict_types=1);

namespace Medora\Authority\Entity;

use Medora\Authority\Core\Options;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Entities the publisher has told us not to extract.
 *
 * Deleting an entity without this would be a trap. Extraction is deterministic:
 * whatever the heuristic extractor found in the text once, it finds again on
 * the next analysis. So a delete button on its own removes the row, the entity
 * reappears within the hour, and the editor concludes the feature is broken —
 * which is a fair conclusion.
 *
 * Keyed on the entity uid, `sha256(type|normalised name)`, which is stable
 * across re-extraction and across locales. Storing the *name* instead would
 * miss the re-extraction it exists to prevent, because the extractor works from
 * the normalised form.
 *
 * A suppression is not a deletion: nothing is destroyed here, and lifting one
 * puts the entity back on the next analysis. That is why the list is stored,
 * inspectable and reversible rather than being a one-way door.
 */
final class SuppressionList
{
    private const OPTION = 'suppressed_entities';

    /**
     * A hard cap on the stored list.
     *
     * The option is autoloaded on every request, so this cannot be allowed to
     * grow without bound. A site suppressing hundreds of entities has an
     * extraction problem that a blocklist is the wrong fix for, and the cap
     * makes that visible rather than quietly degrading every page load.
     */
    public const LIMIT = 500;

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @return list<array{uid: string, name: string, type: string, at: string}>
     */
    public function all(): array
    {
        $stored = $this->options->getArray(self::OPTION);

        return array_values(array_filter(
            $stored,
            static fn ($row): bool => is_array($row) && isset($row['uid'])
        ));
    }

    public function isSuppressed(string $uid): bool
    {
        foreach ($this->all() as $row) {
            if ($row['uid'] === $uid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record an entity as suppressed.
     *
     * The name and type are stored alongside the uid purely so the list can be
     * read by a human — a page of bare hashes is not something anyone can
     * review, and reviewing it is the point.
     */
    public function suppress(Entity $entity): bool
    {
        if ($this->isSuppressed($entity->uid)) {
            return true;
        }

        $rows = $this->all();

        if (count($rows) >= self::LIMIT) {
            return false;
        }

        $rows[] = [
            'uid'  => $entity->uid,
            'name' => $entity->name,
            'type' => $entity->type,
            'at'   => current_time('mysql', true),
        ];

        $this->options->set(self::OPTION, $rows);

        return true;
    }

    public function restore(string $uid): void
    {
        $this->options->set(self::OPTION, array_values(array_filter(
            $this->all(),
            static fn (array $row): bool => $row['uid'] !== $uid
        )));
    }

    public function clear(): void
    {
        $this->options->set(self::OPTION, []);
    }

    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Drop suppressed candidates from an extraction result.
     *
     * @param array<string, Candidate> $candidates Keyed by uid.
     * @return array<string, Candidate>
     */
    public function filter(array $candidates): array
    {
        $rows = $this->all();

        if ($rows === []) {
            return $candidates;
        }

        $suppressed = array_flip(array_column($rows, 'uid'));

        return array_diff_key($candidates, $suppressed);
    }
}
