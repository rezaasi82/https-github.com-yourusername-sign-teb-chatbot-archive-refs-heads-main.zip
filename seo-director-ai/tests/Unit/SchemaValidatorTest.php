<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Ai\SchemaValidator;

final class SchemaValidatorTest extends TestCase {

	private SchemaValidator $validator;

	/** @var array<string, mixed> */
	private array $schema = array(
		'type'       => 'object',
		'required'   => array( 'explanation', 'actions' ),
		'properties' => array(
			'explanation' => array(
				'type'      => 'string',
				'maxLength' => 500,
			),
			'confidence'  => array(
				'type' => 'number',
			),
			'actions'     => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
		),
	);

	protected function setUp(): void {
		$this->validator = new SchemaValidator();
	}

	public function test_valid_payload_passes(): void {
		$raw    = '{"explanation":"Traffic dropped after the core update.","confidence":0.8,"actions":["Refresh content","Add internal links"]}';
		$result = $this->validator->parse_and_validate( $raw, $this->schema );
		$this->assertIsArray( $result );
		$this->assertSame( 'Traffic dropped after the core update.', $result['explanation'] );
	}

	public function test_markdown_fenced_json_is_unwrapped(): void {
		$raw    = "```json\n{\"explanation\":\"ok\",\"actions\":[]}\n```";
		$result = $this->validator->parse_and_validate( $raw, $this->schema );
		$this->assertIsArray( $result );
	}

	public function test_missing_required_key_fails(): void {
		$this->assertNull( $this->validator->parse_and_validate( '{"explanation":"no actions"}', $this->schema ) );
	}

	public function test_wrong_item_type_fails(): void {
		$this->assertNull( $this->validator->parse_and_validate( '{"explanation":"x","actions":[1,2]}', $this->schema ) );
	}

	public function test_non_json_fails(): void {
		$this->assertNull( $this->validator->parse_and_validate( 'Sure! Here is my analysis…', $this->schema ) );
	}

	public function test_hallucinated_prose_around_json_fails(): void {
		$this->assertNull( $this->validator->parse_and_validate( 'Here you go: {"explanation":"x","actions":[]}', $this->schema ) );
	}
}
