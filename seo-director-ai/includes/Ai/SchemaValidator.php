<?php
/**
 * Extracts and validates JSON from an AI text response against a minimal
 * JSON-schema subset (type, required, properties, enum, items). Providers
 * sometimes wrap JSON in prose or code fences; this pulls the object out.
 * Pure, no I/O — the one-retry "repair" loop lives in ProviderRouter.
 *
 * @package SEODirector
 */

namespace SEODirector\Ai;

defined( 'ABSPATH' ) || exit;

final class SchemaValidator {

	/**
	 * Extract the first balanced JSON object from arbitrary text.
	 *
	 * @return array<string, mixed>|null
	 */
	public function extract( string $text ): ?array {
		$text = trim( $text );

		// Strip ```json fences if present.
		if ( preg_match( '/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m ) ) {
			$text = $m[1];
		}

		$start = strpos( $text, '{' );
		if ( false === $start ) {
			return null;
		}

		// Walk braces to find the matching close (string-aware).
		$depth     = 0;
		$in_string = false;
		$escaped   = false;
		$length    = strlen( $text );

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $text[ $i ];

			if ( $in_string ) {
				if ( $escaped ) {
					$escaped = false;
				} elseif ( '\\' === $char ) {
					$escaped = true;
				} elseif ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
			} elseif ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					$decoded = json_decode( substr( $text, $start, $i - $start + 1 ), true );
					return is_array( $decoded ) ? $decoded : null;
				}
			}
		}

		return null;
	}

	/**
	 * Validate a decoded value against a schema subset.
	 *
	 * @param mixed                $value
	 * @param array<string, mixed> $schema
	 * @return true|string True, or an error path/message for the repair prompt.
	 */
	public function validate( mixed $value, array $schema, string $path = '$' ): bool|string {
		$type = $schema['type'] ?? null;

		switch ( $type ) {
			case 'object':
				if ( ! is_array( $value ) ) {
					return "$path must be an object";
				}
				foreach ( (array) ( $schema['required'] ?? [] ) as $key ) {
					if ( ! array_key_exists( $key, $value ) ) {
						return "$path missing required key '$key'";
					}
				}
				foreach ( (array) ( $schema['properties'] ?? [] ) as $key => $sub_schema ) {
					if ( array_key_exists( $key, $value ) ) {
						$check = $this->validate( $value[ $key ], $sub_schema, "$path.$key" );
						if ( true !== $check ) {
							return $check;
						}
					}
				}
				return true;

			case 'array':
				if ( ! is_array( $value ) ) {
					return "$path must be an array";
				}
				if ( isset( $schema['items'] ) ) {
					foreach ( $value as $index => $item ) {
						$check = $this->validate( $item, $schema['items'], "{$path}[{$index}]" );
						if ( true !== $check ) {
							return $check;
						}
					}
				}
				return true;

			case 'string':
				if ( ! is_string( $value ) ) {
					return "$path must be a string";
				}
				if ( isset( $schema['enum'] ) && ! in_array( $value, (array) $schema['enum'], true ) ) {
					return "$path must be one of: " . implode( ', ', (array) $schema['enum'] );
				}
				return true;

			case 'number':
			case 'integer':
				return is_numeric( $value ) ? true : "$path must be a number";

			case 'boolean':
				return is_bool( $value ) ? true : "$path must be a boolean";

			default:
				return true;
		}
	}
}
