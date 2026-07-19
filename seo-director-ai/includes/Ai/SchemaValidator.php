<?php
/**
 * Strict-enough JSON-schema validation for AI output.
 * Supports: type, properties, required, items, enum, maxLength — the subset
 * our prompt schemas use. Anything failing validation is rejected, never shown.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class SchemaValidator {

	/**
	 * Parse raw model text into a schema-valid array, or null.
	 *
	 * @param array<string, mixed> $schema JSON schema (subset).
	 * @return array<string, mixed>|null
	 */
	public function parse_and_validate( string $raw, array $schema ): ?array {
		$raw = trim( $raw );
		// Models occasionally wrap JSON in a fence despite instructions.
		if ( str_starts_with( $raw, '```' ) ) {
			$raw = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $raw ) ?? $raw;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $this->validate( $decoded, $schema ) ? $decoded : null;
	}

	/**
	 * @param mixed                $value  Decoded value.
	 * @param array<string, mixed> $schema Schema node.
	 */
	public function validate( mixed $value, array $schema ): bool {
		$type = $schema['type'] ?? null;

		if ( isset( $schema['enum'] ) && ! in_array( $value, (array) $schema['enum'], true ) ) {
			return false;
		}

		switch ( $type ) {
			case 'object':
				if ( ! is_array( $value ) ) {
					return false;
				}
				foreach ( (array) ( $schema['required'] ?? array() ) as $key ) {
					if ( ! array_key_exists( $key, $value ) ) {
						return false;
					}
				}
				foreach ( (array) ( $schema['properties'] ?? array() ) as $key => $prop_schema ) {
					if ( array_key_exists( $key, $value ) && ! $this->validate( $value[ $key ], (array) $prop_schema ) ) {
						return false;
					}
				}
				return true;

			case 'array':
				if ( ! is_array( $value ) || ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) && array() !== $value ) ) {
					return false;
				}
				if ( isset( $schema['items'] ) ) {
					foreach ( $value as $item ) {
						if ( ! $this->validate( $item, (array) $schema['items'] ) ) {
							return false;
						}
					}
				}
				return true;

			case 'string':
				if ( ! is_string( $value ) ) {
					return false;
				}
				return ! ( isset( $schema['maxLength'] ) && mb_strlen( $value ) > (int) $schema['maxLength'] );

			case 'number':
			case 'integer':
				return is_int( $value ) || ( 'number' === $type && is_float( $value ) );

			case 'boolean':
				return is_bool( $value );

			case null:
				return true; // No type constraint.

			default:
				return false;
		}
	}
}
