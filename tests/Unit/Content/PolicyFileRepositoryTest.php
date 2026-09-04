<?php
/**
 * Tests for PolicyFileRepository — path traversal trap fixtures.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Tests\Unit\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Tests\Unit\Content;

// PolicyFileRepository has a single `if (!defined('ABSPATH')) exit;` guard.
// Define the constant before loading any plugin code so the guard is satisfied
// without needing a full WordPress bootstrap.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../../' );
}

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\PolicyRenderer\Content\PolicyFileRepository;

/**
 * Verifies that PolicyFileRepository::load_for() validates the supplied ID
 * before constructing any file-system path.
 *
 * Strategy — trap fixtures
 * ------------------------
 * A "trap fixture" is a real file placed at the path that a buggy
 * implementation would accidentally read if it built the path from untrusted
 * input without validation.  If the implementation is correct, the file is
 * never reached and the method returns [].  If the implementation is wrong
 * (e.g. it calls `realpath($base . '/' . $id . '/policy.json')` blindly), the
 * fixture file would be returned, causing the assertion to fail.
 *
 * Three canonical attack patterns are covered:
 *   1. `../escape`   — classic directory traversal using ../
 *   2. `unsafe/id`   — directory separator embedded in the ID
 *   3. `contains.dot` — dot character used as a component separator
 *
 * A valid fixture is also created to confirm the happy-path still works.
 */
final class PolicyFileRepositoryTest extends TestCase {

	private string $policies_dir;

	/**
	 * Set up a temporary policies directory with both valid and trap fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$base_parent = sys_get_temp_dir() . '/spx_policy_test_' . uniqid( '', true );
		mkdir( $base_parent, 0777, true );

		$base = $base_parent . '/policies';
		mkdir( $base, 0777, true );
		$this->policies_dir = $base;
		// ── Happy-path fixture ──────────────────────────────────────────────
		// A legitimately named profile that should be loadable.
		mkdir( $base . '/valid-profile', 0777, true );
		file_put_contents(
			$base . '/valid-profile/policy.json',
			json_encode( [ 'policy_id' => 'valid-profile', 'legal_name' => 'Test Corp' ], JSON_THROW_ON_ERROR )
		);

		// ── Trap fixture 1: directory traversal via ../ ─────────────────────
		// A bad implementation that does `$base . '/' . $id . '/policy.json'`
		// without validation would traverse out of $base when $id is '../escape'.
		$escape_dir = dirname( $base ) . '/escape';
		mkdir( $escape_dir, 0777, true );
		file_put_contents(
			$escape_dir . '/policy.json',
			json_encode( [ 'policy_id' => 'escaped_policy' ], JSON_THROW_ON_ERROR )
		);

		// ── Trap fixture 2: slash-separated component in ID ─────────────────
		// An implementation that blindly appends the ID to the path would treat
		// 'unsafe/id' as a two-level path, resolving to $base/unsafe/id/policy.json.
		mkdir( $base . '/unsafe/id', 0777, true );
		file_put_contents(
			$base . '/unsafe/id/policy.json',
			json_encode( [ 'policy_id' => 'should-not-be-read' ], JSON_THROW_ON_ERROR )
		);

		// ── Trap fixture 3: dot in ID ────────────────────────────────────────
		// Some implementations may strip or mishandle dots, potentially resolving
		// 'contains.dot' to the directory 'contains.dot' which could exist on
		// disk due to other tooling.
		mkdir( $base . '/contains.dot', 0777, true );
		file_put_contents(
			$base . '/contains.dot/policy.json',
			json_encode( [ 'policy_id' => 'dotted-id' ], JSON_THROW_ON_ERROR )
		);
	}

	/**
	 * Removes the temporary directory tree after each test.
	 */
	protected function tearDown(): void {
		$this->remove_dir( $this->policies_dir );

		// Also clean up the escape trap directory created outside policies_dir.
		$escape_dir = dirname( $this->policies_dir ) . '/escape';
		if ( is_dir( $escape_dir ) ) {
			$this->remove_dir( $escape_dir );
		}

		parent::tearDown();
	}

	// =========================================================================
	// Trap fixture tests — must return [] for every attack pattern
	// =========================================================================

	/**
	 * @test
	 * Classic directory traversal: `../escape`
	 *
	 * The trap file lives at `dirname($policiesDir)/escape/policy.json`.
	 * A vulnerable implementation would read it; the correct one must not.
	 */
	public function it_rejects_directory_traversal_via_dot_dot_slash(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( '../escape' ) );
	}

	/**
	 * @test
	 * Embedded slash: `unsafe/id`
	 *
	 * The trap file lives at `$policiesDir/unsafe/id/policy.json`.
	 * A vulnerable implementation would navigate into a sub-subdirectory.
	 */
	public function it_rejects_id_with_embedded_slash(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( 'unsafe/id' ) );
	}

	/**
	 * @test
	 * Dot in ID: `contains.dot`
	 *
	 * The trap file lives at `$policiesDir/contains.dot/policy.json`.
	 * Dots in IDs are disallowed to prevent extension confusion and
	 * relative-path tricks like `..` being formed by concatenation.
	 */
	public function it_rejects_id_containing_a_dot(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( 'contains.dot' ) );
	}

	// =========================================================================
	// Additional edge-case rejections
	// =========================================================================

	/**
	 * @test
	 * An empty string is not a valid ID.
	 */
	public function it_rejects_empty_id(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( '' ) );
	}

	/**
	 * @test
	 * A null byte in the ID must be rejected unconditionally.
	 */
	public function it_rejects_null_byte_in_id(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( "valid\0id" ) );
	}

	/**
	 * @test
	 * An ID that is only hyphens (no leading alphanumeric) is invalid.
	 */
	public function it_rejects_id_starting_with_hyphen(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( '-bad-id' ) );
	}

	/**
	 * @test
	 * An ID with uppercase letters is disallowed (IDs must be lowercase).
	 */
	public function it_rejects_uppercase_id(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( 'AIWestAfrica' ) );
	}

	/**
	 * @test
	 * An ID referencing a non-existent profile returns [].
	 */
	public function it_returns_empty_array_for_unknown_id(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( 'nonexistent-profile' ) );
	}

	// =========================================================================
	// Happy-path test
	// =========================================================================

	/**
	 * @test
	 * A valid, registered profile ID loads its policy data correctly.
	 */
	public function it_loads_data_for_a_valid_id(): void {
		$repository = new PolicyFileRepository( $this->policies_dir );

		$data = $repository->load_for( 'valid-profile' );

		self::assertSame( 'valid-profile', $data['policy_id'] );
		self::assertSame( 'Test Corp', $data['legal_name'] );
	}

	/**
	 * @test
	 * A profile directory exists but its policy.json is malformed — returns [].
	 */
	public function it_returns_empty_array_for_corrupt_json(): void {
		mkdir( $this->policies_dir . '/corrupt-profile', 0777, true );
		file_put_contents( $this->policies_dir . '/corrupt-profile/policy.json', '{ invalid json' );

		$repository = new PolicyFileRepository( $this->policies_dir );

		self::assertSame( [], $repository->load_for( 'corrupt-profile' ) );
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Recursively removes a directory and all its contents.
	 */
	private function remove_dir( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}

		rmdir( $path );
	}
}
