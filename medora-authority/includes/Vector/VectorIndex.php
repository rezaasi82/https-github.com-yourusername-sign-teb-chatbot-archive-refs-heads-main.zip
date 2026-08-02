<?php

declare(strict_types=1);

namespace Medora\Authority\Vector;

use Medora\Authority\Support\Chunker;
use Medora\Authority\Support\Text;
use Throwable;
use WP_Post;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Keeps the vector store in sync with published content and answers semantic
 * queries against it.
 */
final class VectorIndex
{
    public function __construct(
        private readonly VectorRepository $repository,
        private readonly EmbeddingProviderInterface $provider,
        private readonly Chunker $chunker,
    ) {
    }

    public function provider(): EmbeddingProviderInterface
    {
        return $this->provider;
    }

    /**
     * Chunk, embed and store a post.
     *
     * @return int Number of chunks indexed.
     */
    public function indexPost(WP_Post $post, bool $force = false): int
    {
        $hash = Text::hash($post->post_title . "\n" . $post->post_content);

        // Skip unchanged content: on a remote provider every re-index costs
        // money, and on the local one it still costs CPU on a cron tick.
        if (! $force && $this->repository->contentHashFor('post', $post->ID) === $hash) {
            return 0;
        }

        $chunks = $this->chunker->chunk($post->post_title . ".\n" . $post->post_content);

        if ($chunks === []) {
            $this->repository->deleteObject('post', $post->ID);

            return 0;
        }

        $texts = array_map(static fn (array $chunk): string => $chunk['text'], $chunks);

        try {
            $vectors = $this->provider->embedBatch($texts);
        } catch (Throwable $exception) {
            // Let the queue retry with back-off rather than leaving a partially
            // written index behind.
            throw $exception;
        }

        // Replace wholesale: a post that lost a section must lose its chunks.
        $this->repository->deleteObject('post', $post->ID);

        foreach ($chunks as $position => $chunk) {
            if (! isset($vectors[$position])) {
                continue;
            }

            $this->repository->store(
                'post',
                $post->ID,
                (int) $chunk['index'],
                $this->provider->id(),
                $vectors[$position],
                Text::truncate($chunk['text'], 400),
                $hash
            );
        }

        do_action('medora_post_embedded', $post, count($chunks));

        return count($chunks);
    }

    /**
     * Semantic search over the site.
     *
     * @return list<array{object_id: int, score: float, excerpt: string, title: string, url: string}>
     */
    public function search(string $query, int $limit = 10, array $args = []): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $vector  = $this->provider->embed($query);
        $matches = $this->repository->searchObjects($vector, $limit, $args + ['provider' => $this->provider->id()]);

        return $this->hydrate($matches);
    }

    /**
     * Pages semantically closest to a given post.
     *
     * @return list<array{object_id: int, score: float, excerpt: string, title: string, url: string}>
     */
    public function related(int $postId, int $limit = 5, float $threshold = 0.25): array
    {
        $centroid = $this->repository->centroidFor('post', $postId);

        if ($centroid === []) {
            return [];
        }

        $matches = $this->repository->searchObjects($centroid, $limit, [
            'object_type'       => 'post',
            'exclude_object_id' => $postId,
            'provider'          => $this->provider->id(),
            'threshold'         => $threshold,
        ]);

        return $this->hydrate($matches);
    }

    /**
     * @param list<array{object_id: int, score: float, excerpt: string}> $matches
     * @return list<array{object_id: int, score: float, excerpt: string, title: string, url: string}>
     */
    private function hydrate(array $matches): array
    {
        $results = [];

        foreach ($matches as $match) {
            $post = get_post($match['object_id']);

            if (! $post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }

            $results[] = $match + [
                'title' => get_the_title($post),
                'url'   => (string) get_permalink($post),
            ];
        }

        return $results;
    }
}
