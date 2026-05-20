<?php
/**
 * Filesystem-based policy data repository.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads per-profile policy override data from JSON files stored in a
 * controlled directory on disk.
 *
 * Each profile's data lives at:
 *   {policiesDir}/{id}/policy.json
 *
 * Security contract
 * -----------------
 *   - `$id` MUST match the strict allowlist pattern before any path is built.
 *   - Pattern: one or more lowercase ASCII letters, digits, or hyphens.
 *   - IDs containing slashes, dots, null bytes, or any other characters are
 *     rejected unconditionally, regardless of how they are encoded.
 *   - The resolved absolute path is verified with `realpath()` to confirm it
 *     still lives inside `$policiesDir` even after the OS resolves symlinks.
 *   - Any deviation from these rules returns an empty array — never an error
 *     that leaks filesystem structure.
 *
 * This class intentionally does NOT depend on WordPress APIs so that it can
 * be unit-tested in isolation without bootstrapping WordPress.
 */
final class PolicyFileRepository {

	/**
	 * Pattern that every valid policy ID must fully match.
	 * Only lowercase letters a–z, digits 0–9, and hyphens are allowed.
	 */
	private const SAFE_ID_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

	/**
	 * @param string $policies_dir Absolute path to the root policies directory.
	 *                              Must exist and be a real directory.
	 */
	public function __construct(
		private readonly string $policies_dir
	) {}

	/**
	 * Returns the decoded policy data array for the given ID, or an empty
	 * array when the ID is invalid, the file is missing, or the JSON is corrupt.
	 *
	 * @param string $id Policy profile identifier (e.g. 'ai-west-africa').
	 *
	 * @return array<string, mixed>
	 */
	public function load_for( string $id ): array {
		if ( ! $this->is_safe_id( $id ) ) {
			return [];
		}

		$file = $this->resolve_path( $id );
		if ( null === $file ) {
			return [];
		}

		$raw = file_get_contents( $file );
		if ( false === $raw ) {
			return [];
		}

		try {
			$decoded = json_decode( $raw, true, 10, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return [];
		}

		return is_array( $decoded ) ? $decoded : [];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns true only when the ID strictly conforms to the safe pattern.
	 *
	 * @param string $id Candidate ID.
	 */
	private function is_safe_id( string $id ): bool {
		if ( '' === $id ) {
			return false;
		}

		// Bail immediately on any null byte, slash, backslash, or dot.
		// These checks are intentionally redundant with the regex below to make
		// the safety intent explicit.
		if (
			str_contains( $id, "\0" ) ||
			str_contains( $id, '/' ) ||
			str_contains( $id, '\\' ) ||
			str_contains( $id, '.' )
		) {
			return false;
		}

		return (bool) preg_match( self::SAFE_ID_PATTERN, $id );
	}

	/**
	 * Builds and validates the absolute path to the policy JSON file.
	 *
	 * Returns null when:
	 *   - The candidate path does not resolve to a real file.
	 *   - The resolved path escapes the policies directory (path traversal).
	 *
	 * @param string $id Already-validated safe ID.
	 *
	 * @return string|null Absolute, realpath-resolved path, or null.
	 */
	private function resolve_path( string $id ): ?string {
		// Resolve the base directory once so comparison is against the real path.
		$base = realpath( $this->policies_dir );
		if ( false === $base ) {
			return null;
		}

		$candidate = $base . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'policy.json';

		// After building the candidate path, realpath() resolves any remaining
		// symbolic links or OS-level traversal.  If the resolved path does not
		// start with the base directory, an escape attempt occurred.
		$resolved = realpath( $candidate );
		if ( false === $resolved ) {
			return null;
		}

		$base_with_sep = rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( ! str_starts_with( $resolved, $base_with_sep ) ) {
			return null;
		}

		return $resolved;
	}
}
