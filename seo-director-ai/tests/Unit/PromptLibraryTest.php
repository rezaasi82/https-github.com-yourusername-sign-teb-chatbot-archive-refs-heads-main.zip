<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Ai\PromptLibrary;

final class PromptLibraryTest extends TestCase {

	private PromptLibrary $library;

	protected function setUp(): void {
		$this->library = new PromptLibrary();
	}

	public function test_root_cause_envelope_has_versioned_prompt_and_schema(): void {
		$envelope = $this->library->build( 'root_cause', [ 'entity' => 'x' ], [], 'en' );

		$this->assertSame( 'root_cause.v1', $envelope->prompt_version );
		$this->assertSame( 'object', $envelope->schema['type'] );
		$this->assertContains( 'causes', $envelope->schema['required'] );
		$this->assertStringContainsString( 'JSON', $envelope->user_message() );
	}

	public function test_language_directive_switches(): void {
		$fa = $this->library->build( 'summary_weekly', [], [], 'fa' );
		$ar = $this->library->build( 'summary_weekly', [], [], 'ar' );
		$en = $this->library->build( 'summary_weekly', [], [], 'en' );

		$this->assertStringContainsString( 'فارسی', $fa->system );
		$this->assertStringContainsString( 'العربية', $ar->system );
		$this->assertStringContainsString( 'English', $en->system );
		$this->assertSame( 'fa', $fa->lang );
	}

	public function test_unknown_language_falls_back_to_english(): void {
		$envelope = $this->library->build( 'growth', [], [], 'zz' );
		$this->assertSame( 'en', $envelope->lang );
	}

	public function test_site_context_included(): void {
		$envelope = $this->library->build( 'decline', [], [ 'niche' => 'law firm', 'goals' => 'leads' ], 'en' );
		$this->assertStringContainsString( 'law firm', $envelope->system );
		$this->assertStringContainsString( 'leads', $envelope->system );
	}

	public function test_cache_hash_is_stable_and_evidence_sensitive(): void {
		$a = $this->library->build( 'root_cause', [ 'x' => 1 ], [], 'en' );
		$b = $this->library->build( 'root_cause', [ 'x' => 1 ], [], 'en' );
		$c = $this->library->build( 'root_cause', [ 'x' => 2 ], [], 'en' );

		$this->assertSame( $a->cache_hash(), $b->cache_hash() );
		$this->assertNotSame( $a->cache_hash(), $c->cache_hash() );
	}
}
