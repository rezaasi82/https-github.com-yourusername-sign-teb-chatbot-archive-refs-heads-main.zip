<?php

declare(strict_types=1);

namespace Medora\Authority\Module;

use Medora\Authority\Core\Container;
use Medora\Authority\Core\Options;
use Medora\Authority\License\LicenseManager;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Owns the module lifecycle: discovery, licence and dependency gating, then
 * ordered registration and boot.
 */
final class ModuleRegistry
{
    private const OPTION = 'modules';

    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    /** @var array<string, ModuleInterface> */
    private array $booted = [];

    /** @var array<string, string> id => reason the module was skipped */
    private array $skipped = [];

    public function __construct(
        private readonly Container $container,
        private readonly Options $options,
        private readonly LicenseManager $license,
    ) {
    }

    public function add(ModuleInterface $module): void
    {
        $this->modules[$module->id()] = $module;
    }

    /** @return array<string, ModuleInterface> */
    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $id): ?ModuleInterface
    {
        return $this->modules[$id] ?? null;
    }

    public function isBooted(string $id): bool
    {
        return isset($this->booted[$id]);
    }

    /** @return array<string, string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    public function isEnabled(string $id): bool
    {
        $module = $this->modules[$id] ?? null;

        if ($module === null) {
            return false;
        }

        $state = $this->options->get(self::OPTION, []);

        return (bool) ($state[$id] ?? $module->enabledByDefault());
    }

    public function setEnabled(string $id, bool $enabled): void
    {
        $state       = $this->options->get(self::OPTION, []);
        $state[$id]  = $enabled;

        $this->options->set(self::OPTION, $state);

        /**
         * Fires after a module is toggled. Rewrite-rule owning modules listen
         * for this to flush permalinks.
         *
         * @param string $id      Module id.
         * @param bool   $enabled New state.
         */
        do_action('medora_module_toggled', $id, $enabled);
    }

    /**
     * Register and boot every eligible module in dependency order.
     */
    public function bootAll(): void
    {
        /**
         * Last chance for third-party code to add modules.
         *
         * @param ModuleRegistry $registry
         */
        do_action('medora_register_modules', $this);

        $eligible = [];

        foreach ($this->modules as $id => $module) {
            if (! $this->isEnabled($id)) {
                $this->skipped[$id] = 'disabled';
                continue;
            }

            if (! $this->license->allowsTier($module->requiredTier())) {
                $this->skipped[$id] = 'licence';
                continue;
            }

            $eligible[$id] = $module;
        }

        foreach ($this->sort($eligible) as $module) {
            foreach ($module->dependencies() as $dependency) {
                if (! isset($eligible[$dependency])) {
                    $this->skipped[$module->id()] = sprintf('missing dependency: %s', $dependency);
                    continue 2;
                }
            }

            $module->register($this->container);
            $this->booted[$module->id()] = $module;
        }

        foreach ($this->booted as $module) {
            $module->boot($this->container);
        }

        /**
         * Fires once all modules have booted.
         *
         * @param ModuleRegistry $registry
         */
        do_action('medora_modules_booted', $this);
    }

    /**
     * Depth-first topological sort. Cycles are broken by emitting the node
     * anyway — a misconfigured third-party module must not fatal the site.
     *
     * @param array<string, ModuleInterface> $modules
     * @return list<ModuleInterface>
     */
    private function sort(array $modules): array
    {
        $sorted  = [];
        $visited = [];

        $visit = static function (string $id) use (&$visit, &$sorted, &$visited, $modules): void {
            if (isset($visited[$id])) {
                return;
            }

            $visited[$id] = true;
            $module       = $modules[$id] ?? null;

            if ($module === null) {
                return;
            }

            foreach ($module->dependencies() as $dependency) {
                $visit($dependency);
            }

            $sorted[] = $module;
        };

        foreach (array_keys($modules) as $id) {
            $visit($id);
        }

        return $sorted;
    }
}
