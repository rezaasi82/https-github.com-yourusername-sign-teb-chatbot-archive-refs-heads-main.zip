<?php
/**
 * @package SEODirector
 */

namespace SEODirector\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SEODirector\Agency\SiteConnector;

final class SiteConnectorTest extends TestCase {

	private SiteConnector $connector;

	protected function setUp(): void {
		$this->connector = new SiteConnector();
	}

	public function test_sign_then_verify_roundtrip(): void {
		$body      = '{"health":{"score":82}}';
		$key       = 'sharedsecret';
		$timestamp = 1_700_000_000;

		$signature = $this->connector->sign( $body, $key, $timestamp );

		$this->assertTrue( $this->connector->verify( $body, $key, $timestamp, $signature, $timestamp + 5 ) );
	}

	public function test_rejects_wrong_key(): void {
		$body      = '{"a":1}';
		$timestamp = 1_700_000_000;
		$signature = $this->connector->sign( $body, 'correct-key', $timestamp );

		$this->assertFalse( $this->connector->verify( $body, 'wrong-key', $timestamp, $signature, $timestamp ) );
	}

	public function test_rejects_tampered_body(): void {
		$timestamp = 1_700_000_000;
		$key       = 'k';
		$signature = $this->connector->sign( '{"clicks":100}', $key, $timestamp );

		// Attacker inflates the number but reuses the signature.
		$this->assertFalse( $this->connector->verify( '{"clicks":999}', $key, $timestamp, $signature, $timestamp ) );
	}

	public function test_rejects_stale_timestamp(): void {
		$key       = 'k';
		$timestamp = 1_700_000_000;
		$signature = $this->connector->sign( '{}', $key, $timestamp );

		// 10 minutes later is outside the 5-minute freshness window.
		$this->assertFalse( $this->connector->verify( '{}', $key, $timestamp, $signature, $timestamp + 600 ) );
	}

	public function test_rejects_future_timestamp(): void {
		$key       = 'k';
		$timestamp = 1_700_000_600; // 10 min ahead of "now".
		$signature = $this->connector->sign( '{}', $key, $timestamp );

		$this->assertFalse( $this->connector->verify( '{}', $key, $timestamp, $signature, 1_700_000_000 ) );
	}

	public function test_rejects_empty_signature_or_key(): void {
		$this->assertFalse( $this->connector->verify( '{}', '', 1, 'sig', 1 ) );
		$this->assertFalse( $this->connector->verify( '{}', 'key', 1, '', 1 ) );
	}

	public function test_generated_keys_are_unique_and_hex(): void {
		$a = SiteConnector::generate_pair_key();
		$b = SiteConnector::generate_pair_key();

		$this->assertNotSame( $a, $b );
		$this->assertSame( 64, strlen( $a ) );
		$this->assertTrue( (bool) preg_match( '/^[a-f0-9]{64}$/', $a ) );
	}
}
