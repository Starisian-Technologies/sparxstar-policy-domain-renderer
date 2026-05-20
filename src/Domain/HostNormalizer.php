<?php
/**
 * HTTP host normalizer.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Domain
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and normalises the HTTP request host safely.
 *
 * Normalization steps applied in order:
 *  1. Read HTTP_HOST; fall back to SERVER_NAME.
 *  2. Strip port number.
 *  3. Lowercase entire string.
 *  4. Remove trailing dot (DNS absolute-name notation).
 *  5. Optionally remove leading www. when the plugin setting is enabled.
 */
final class HostNormalizer {

	/**
	 * Returns the normalized host for the current HTTP request.
	 */
	public function get_current_host(): string {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = '';

		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) );
		}

		if ( '' === $raw && isset( $_SERVER['SERVER_NAME'] ) ) {
			$raw = sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_NAME'] ) );
		}
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return $this->normalize( $raw );
	}

	/**
	 * Normalizes an arbitrary host string according to the plugin rules.
	 *
	 * @param string $host Raw host value.
	 *
	 * @return string Normalized host (lowercase, no port, no trailing dot).
	 */
	public function normalize( string $host ): string {
		// Strip port number.
		$host = (string) preg_replace( '/:\d+$/', '', $host );

		// Lowercase.
		$host = strtolower( $host );

		// Remove trailing dot (DNS absolute-name notation).
		$host = rtrim( $host, '.' );

		// Optionally strip www. prefix when the plugin setting is enabled.
		$settings = get_option( 'spx_policy_settings', [] );
		if ( is_array( $settings ) && ! empty( $settings['strip_www'] ) ) {
			$host = (string) preg_replace( '/^www\./', '', $host );
		}

		return $host;
	}
}
