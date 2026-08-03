<?php

declare(strict_types=1);

namespace Medora\Authority\Performance\Jobs;

use Medora\Authority\Core\Container;
use Medora\Authority\Graph\KnowledgeGraphBuilder;
use Medora\Authority\Performance\JobInterface;

if (! defined('ABSPATH')) {
    exit;
}

final class RebuildGraphJob implements JobInterface
{
    public function handle(array $payload, Container $container): void
    {
        $container->get(KnowledgeGraphBuilder::class)->rebuild();
    }
}
