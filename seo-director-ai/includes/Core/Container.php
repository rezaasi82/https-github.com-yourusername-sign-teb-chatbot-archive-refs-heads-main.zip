<?php
/**
 * Minimal PSR-11-style dependency injection container.
 *
 * @package SEODirector
 */

namespace SEODirector\Core;

defined( 'ABSPATH' ) || exit;

use Closure;
use InvalidArgumentException;

/**
 * Services are registered as factories and resolved lazily as shared singletons.
 * Constructor injection only — domain code never receives the container itself.
 */
final class Container {

	/** @var array<string, Closure(Container): object> */
	private array $factories = array();

	/** @var array<string, object> */
	private array $instances = array();

	/**
	 * Register a service factory.
	 *
	 * @param string                     $id      Service id (class/interface name).
	 * @param Closure(Container): object $factory Factory receiving the container.
	 */
	public function set( string $id, Closure $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a shared instance.
	 *
	 * @template T of object
	 * @param class-string<T>|string $id Service id.
	 * @return object
	 * @throws InvalidArgumentException When the id is unknown.
	 */
	public function get( string $id ): object {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new InvalidArgumentException( esc_html( "Unknown service: {$id}" ) );
		}
		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		return $this->instances[ $id ];
	}

	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}
}
