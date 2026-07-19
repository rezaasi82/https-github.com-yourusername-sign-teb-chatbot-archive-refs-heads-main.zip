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

	public function test_decline_envelope_declares_required_schema_keys(): void {
		$env = $this->library->build( 'explain_decline', array( 'clicks_wow_pct' => -20 ), 'en' );
		$this->assertContains( 'causes', $env->output_schema['required'] );
		$this->assertContains( 'actions', $env->output_schema['required'] );
		$this->assertSame( PromptLibrary::VERSION, $env->prompt_version );
	}

	public function test_language_is_threaded_into_system_prompt(): void {
		$fa = $this->library->build( 'explain_decline', array(), 'fa' );
		$this->assertStringContainsString( 'Persian', $fa->system_prompt );
		$en = $this->library->build( 'explain_decline', array(), 'en' );
		$this->assertStringContainsString( 'English', $en->system_prompt );
	}

	public function test_same_evidence_same_version_same_lang_is_cacheable(): void {
		$a = $this->library->build( 'explain_growth', array( 'x' => 1 ), 'en' );
		$b = $this->library->build( 'explain_growth', array( 'x' => 1 ), 'en' );
		$this->assertSame( $a->evidence_hash(), $b->evidence_hash() );
	}

	public function test_language_changes_cache_key(): void {
		$en = $this->library->build( 'explain_growth', array( 'x' => 1 ), 'en' );
		$fa = $this->library->build( 'explain_growth', array( 'x' => 1 ), 'fa' );
		$this->assertNotSame( $en->evidence_hash(), $fa->evidence_hash() );
	}

	public function test_weekly_summary_has_focus_field(): void {
		$env = $this->library->build( 'summary_weekly', array(), 'en' );
		$this->assertArrayHasKey( 'focus', $env->output_schema['properties'] );
	}
}
