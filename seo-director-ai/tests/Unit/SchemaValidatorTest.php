<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Ai\SchemaValidator;

final class SchemaValidatorTest extends TestCase {

	private SchemaValidator $validator;

	protected function setUp(): void {
		$this->validator = new SchemaValidator();
	}

	public function test_extracts_plain_json(): void {
		$this->assertSame( [ 'a' => 1 ], $this->validator->extract( '{"a": 1}' ) );
	}

	public function test_extracts_json_from_prose(): void {
		$text = "Here is the result:\n{\"summary\": \"ok\"}\nHope that helps!";
		$this->assertSame( [ 'summary' => 'ok' ], $this->validator->extract( $text ) );
	}

	public function test_extracts_json_from_code_fence(): void {
		$text = "```json\n{\"x\": true}\n```";
		$this->assertSame( [ 'x' => true ], $this->validator->extract( $text ) );
	}

	public function test_handles_nested_and_braces_in_strings(): void {
		$text = '{"note": "a } brace", "inner": {"k": 1}}';
		$this->assertSame( [ 'note' => 'a } brace', 'inner' => [ 'k' => 1 ] ], $this->validator->extract( $text ) );
	}

	public function test_returns_null_without_json(): void {
		$this->assertNull( $this->validator->extract( 'no json here' ) );
	}

	public function test_validates_required_and_types(): void {
		$schema = [
			'type'       => 'object',
			'required'   => [ 'summary', 'causes' ],
			'properties' => [
				'summary' => [ 'type' => 'string' ],
				'causes'  => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'required'   => [ 'cause', 'confidence' ],
						'properties' => [
							'cause'      => [ 'type' => 'string' ],
							'confidence' => [ 'type' => 'integer' ],
						],
					],
				],
			],
		];

		$valid = [ 'summary' => 'ok', 'causes' => [ [ 'cause' => 'x', 'confidence' => 80 ] ] ];
		$this->assertTrue( $this->validator->validate( $valid, $schema ) );

		$missing = [ 'summary' => 'ok' ];
		$this->assertIsString( $this->validator->validate( $missing, $schema ) );

		$bad_type = [ 'summary' => 5, 'causes' => [] ];
		$this->assertIsString( $this->validator->validate( $bad_type, $schema ) );

		$bad_item = [ 'summary' => 'ok', 'causes' => [ [ 'cause' => 'x' ] ] ];
		$this->assertIsString( $this->validator->validate( $bad_item, $schema ) );
	}

	public function test_enum_validation(): void {
		$schema = [ 'type' => 'string', 'enum' => [ 'a', 'b' ] ];
		$this->assertTrue( $this->validator->validate( 'a', $schema ) );
		$this->assertIsString( $this->validator->validate( 'c', $schema ) );
	}
}
