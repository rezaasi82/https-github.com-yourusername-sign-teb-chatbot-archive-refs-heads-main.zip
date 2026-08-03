<?php

declare(strict_types=1);

namespace Medora\Authority\Rest\Controllers;

use Medora\Authority\Analytics\TrendCalculator;
use Medora\Authority\Citation\CitationRepository;
use Medora\Authority\Core\Container;
use Medora\Authority\Crawler\CrawlAnalytics;
use Medora\Authority\Entity\EntityRepository;
use Medora\Authority\Graph\RelationRepository;
use Medora\Authority\License\LicenseManager;
use Medora\Authority\Module\ModuleRegistry;
use Medora\Authority\Performance\JobQueue;
use Medora\Authority\Rest\AbstractController;
use Medora\Authority\Score\AuthorityScoreCalculator;
use Medora\Authority\Security\SecurityScanner;
use Medora\Authority\Vector\VectorRepository;
use WP_REST_Server;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The dashboard's single bootstrap request.
 *
 * The overview screen needs a dozen different numbers. Fetching them in one
 * round trip rather than a dozen is the difference between a dashboard that
 * feels instant and one that spends two seconds populating.
 *
 * Every section is resolved defensively: a disabled module simply omits its
 * block rather than failing the whole response.
 */
final class OverviewController extends AbstractController
{
    public function __construct(private readonly Container $container)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/overview', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'overview'],
            'permission_callback' => [$this, 'canView'],
            'args'                => $this->daysArg(),
        ]);

        register_rest_route(self::NAMESPACE, '/security/scan', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'securityScan'],
            'permission_callback' => [$this, 'canManage'],
        ]);
    }

    public function overview(\WP_REST_Request $request): \WP_REST_Response
    {
        $days     = (int) $request->get_param('days');
        $registry = $this->container->get(ModuleRegistry::class);

        $payload = [
            'version' => MEDORA_VERSION,
            'site'    => [
                'name' => get_bloginfo('name'),
                'url'  => home_url('/'),
            ],
            'license' => $this->container->get(LicenseManager::class)->publicState(),
            'modules' => $this->modules($registry),
            'queue'   => $this->container->get(JobQueue::class)->stats(),
        ];

        if ($registry->isBooted('score')) {
            $payload['authority'] = $this->container->get(AuthorityScoreCalculator::class)->siteReport();
        }

        if ($registry->isBooted('entity')) {
            $entities = $this->container->get(EntityRepository::class);

            $payload['entities'] = [
                'total'    => $entities->count(),
                'by_type'  => $entities->countsByType(),
                'top'      => array_map(
                    static fn ($entity): array => $entity->toArray(),
                    $entities->query(['per_page' => 10, 'orderby' => 'authority'])['items']
                ),
            ];
        }

        if ($registry->isBooted('graph')) {
            $payload['graph'] = ['relations' => $this->container->get(RelationRepository::class)->count()];
        }

        if ($registry->isBooted('crawler')) {
            $payload['crawlers'] = $this->container->get(CrawlAnalytics::class)->report($days);
        }

        if ($registry->isBooted('analytics')) {
            $payload['referrals'] = $this->container->get(TrendCalculator::class)->report($days);
        }

        if ($registry->isBooted('vector')) {
            $payload['vectors'] = $this->container->get(VectorRepository::class)->stats();
        }

        if ($registry->isBooted('citation')) {
            $payload['citations'] = $this->container->get(CitationRepository::class)->stats();
        }

        return $this->ok($payload);
    }

    public function securityScan(): \WP_REST_Response
    {
        return $this->ok($this->container->get(SecurityScanner::class)->run());
    }

    /** @return list<array<string, mixed>> */
    private function modules(ModuleRegistry $registry): array
    {
        $modules = [];
        $skipped = $registry->skipped();

        foreach ($registry->all() as $id => $module) {
            $modules[] = [
                'id'            => $id,
                'title'         => $module->title(),
                'description'   => $module->description(),
                'enabled'       => $registry->isEnabled($id),
                'booted'        => $registry->isBooted($id),
                'required_tier' => $module->requiredTier(),
                'dependencies'  => $module->dependencies(),
                'skip_reason'   => $skipped[$id] ?? '',
            ];
        }

        return $modules;
    }
}
