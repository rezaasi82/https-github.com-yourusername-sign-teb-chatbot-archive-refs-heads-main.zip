<?php

declare(strict_types=1);

namespace Medora\Authority\Module;

use Medora\Authority\Core\Container;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contract every Medora module implements.
 *
 * Modules are independently installable units. `register()` may only bind
 * services — it must not touch the database, enqueue assets or add hooks that
 * assume other modules are present. All wiring happens in `boot()`, which is
 * called once every enabled module has been registered.
 */
interface ModuleInterface
{
    /** Stable machine id, used for options, REST payloads and dependencies. */
    public function id(): string;

    /** Human readable, translated title. */
    public function title(): string;

    /** Translated one-line description shown in the module manager. */
    public function description(): string;

    /**
     * Ids of modules that must boot before this one.
     *
     * @return list<string>
     */
    public function dependencies(): array;

    /** Minimum licence tier required, as a `LicenseTier` value. */
    public function requiredTier(): string;

    /** Whether the module is enabled by default on a fresh install. */
    public function enabledByDefault(): bool;

    /** Bind services into the container. No side effects. */
    public function register(Container $container): void;

    /** Attach hooks and start doing work. */
    public function boot(Container $container): void;
}
