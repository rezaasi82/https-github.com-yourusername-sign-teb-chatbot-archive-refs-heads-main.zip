<?php
/**
 * Minimal PSR-11-style dependency injection container.
 *
 * Services are registered as factories and resolved lazily as shared
 * singletons. Domain code receives dependencies via constructor injection;
 * only composition roots (Plugin, service providers) touch the container.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

final class Container {

	/** @var array<string, callable(Container): mixed> */
	private array $factories = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	/**
	 * Register a service factory.
	 *
	 * @param string                    $id      Service id (usually a FQCN).
	 * @param callable(Container):mixed $factory Factory receiving the container.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a shared instance.
	 *
	 * @template T
	 * @param class-string<T>|string $id Service id.
	 * @return mixed
	 * @throws \RuntimeException If the service is unknown.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \RuntimeException( sprintf( 'SEO Director AI container: unknown service "%s".', $id ) );
		}

		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );

		return $this->instances[ $id ];
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}
}
