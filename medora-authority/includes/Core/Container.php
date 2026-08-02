<?php

declare(strict_types=1);

namespace Medora\Authority\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Minimal PSR-11 compatible service container with constructor autowiring.
 *
 * Deliberately dependency-free: shipping a full DI package inside a WordPress
 * plugin invites version conflicts with other plugins that bundle the same
 * library, so the container implements only what the platform needs —
 * singletons, factory bindings, interface aliases and reflection autowiring.
 */
final class Container
{
    /** @var array<string, callable(Container): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, true> Guards against circular resolution. */
    private array $resolving = [];

    /**
     * Bind a factory that is executed on every `get()` call.
     *
     * @param callable(Container): mixed $factory
     */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Bind a factory whose result is memoised for the request lifetime.
     *
     * @param callable(Container): mixed $factory
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bind($id, static function (Container $container) use ($id, $factory) {
            return $container->instances[$id] ??= $factory($container);
        });
    }

    /**
     * Register a concrete implementation for an interface or abstract id.
     */
    public function alias(string $abstract, string $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
        $this->factories[$id] = static fn (): mixed => $value;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id])
            || isset($this->instances[$id])
            || isset($this->aliases[$id])
            || class_exists($id);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return ($this->factories[$id])($this);
        }

        if (isset($this->aliases[$id])) {
            return $this->get($this->aliases[$id]);
        }

        return $this->build($id);
    }

    /**
     * Autowire a concrete class by resolving its constructor signature.
     */
    private function build(string $class): object
    {
        if (! class_exists($class)) {
            throw new RuntimeException(sprintf('Medora: service "%s" is not registered and is not an autoloadable class.', $class));
        }

        if (isset($this->resolving[$class])) {
            throw new RuntimeException(sprintf('Medora: circular dependency while resolving "%s".', $class));
        }

        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);

            if (! $reflection->isInstantiable()) {
                throw new RuntimeException(sprintf('Medora: "%s" cannot be instantiated.', $class));
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $this->instances[$class] = new $class();
            }

            $arguments = [];

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $arguments[] = $this->get($type->getName());
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Medora: cannot autowire parameter $%s of "%s".',
                    $parameter->getName(),
                    $class
                ));
            }

            return $this->instances[$class] = $reflection->newInstanceArgs($arguments);
        } finally {
            unset($this->resolving[$class]);
        }
    }
}
