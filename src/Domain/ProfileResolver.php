<?php
/**
 * Resolves the current HTTP host to a Policy Profile CPT post.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Domain
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Maps a normalized hostname to an active `spx_policy_profile` post.
 *
 * Resolution order:
 *  1. Match exact primary_domain.
 *  2. Match within the stored alias_domains array.
 *  3. Match within the stored allowed_hosts array.
 *  4. Fall back to the configured default fallback profile.
 *  5. Return null if nothing matches.
 */
final class ProfileResolver {

	/**
	 * Per-request static cache keyed by normalized host.
	 *
	 * @var array<string, WP_Post|null>
	 */
	private static array $cache = [];

	public function __construct(
		private readonly HostNormalizer $host_normalizer
	) {}

	/**
	 * Returns the matching Policy Profile post for the given normalized host,
	 * or null when no profile matches and no default fallback is configured.
	 *
	 * @param string $host Normalized host string.
	 *
	 * @return WP_Post|null
	 */
	public function resolve( string $host ): ?WP_Post {
		$profile = $this->find_by_host( $host );

		if ( null !== $profile ) {
			return $profile;
		}

		// Use the administrator-configured default fallback profile.
		$settings    = get_option( 'spx_policy_settings', [] );
		$fallback_id = (int) ( is_array( $settings ) ? ( $settings['default_fallback_profile'] ?? 0 ) : 0 );

		if ( $fallback_id > 0 ) {
			$fallback = get_post( $fallback_id );
			if (
				$fallback instanceof WP_Post
				&& 'spx_policy_profile' === $fallback->post_type
				&& 'publish' === $fallback->post_status
			) {
				return $fallback;
			}
		}

		return null;
	}

	/**
	 * Clears the per-request static cache.
	 * Useful for unit tests or preview mode where the host may change mid-request.
	 */
	public function flush_cache(): void {
		self::$cache = [];
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Queries for a published profile whose domains include the given host.
	 *
	 * @param string $host Normalized host.
	 *
	 * @return WP_Post|null
	 */
	private function find_by_host( string $host ): ?WP_Post {
		if ( array_key_exists( $host, self::$cache ) ) {
			return self::$cache[ $host ];
		}

		$serialized_host = '"' . $host . '"';

		// Broad database query; exact validation happens in profile_allows_host().
		$candidates = get_posts( [
			'post_type'      => 'spx_policy_profile',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [
				'relation' => 'OR',
				[
					'key'     => 'primary_domain',
					'value'   => $host,
					'compare' => '=',
				],
				[
					'key'     => 'alias_domains',
					'value'   => $serialized_host,
					'compare' => 'LIKE',
				],
				[
					'key'     => 'allowed_hosts',
					'value'   => $serialized_host,
					'compare' => 'LIKE',
				],
			],
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		] );

		foreach ( $candidates as $candidate ) {
			if ( ! ( $candidate instanceof WP_Post ) ) {
				continue;
			}

			if ( $this->profile_allows_host( $candidate, $host ) ) {
				self::$cache[ $host ] = $candidate;
				return $candidate;
			}
		}

		self::$cache[ $host ] = null;
		return null;
	}

	/**
	 * Strictly validates that a profile is active and explicitly allows the host.
	 *
	 * @param WP_Post $profile The candidate profile post.
	 * @param string  $host    Normalized host to check.
	 *
	 * @return bool
	 */
	private function profile_allows_host( WP_Post $profile, string $host ): bool {
		// Require the profile to be explicitly marked active.
		$active = get_post_meta( $profile->ID, 'active', true );
		if ( in_array( $active, [ '0', 'false', false, 0 ], true ) ) {
			return false;
		}

		// Check primary domain.
		$primary = (string) get_post_meta( $profile->ID, 'primary_domain', true );
		if ( '' !== $primary && $this->host_normalizer->normalize( $primary ) === $host ) {
			return true;
		}

		foreach ( [ 'alias_domains', 'allowed_hosts' ] as $meta_key ) {
			$hosts = get_post_meta( $profile->ID, $meta_key, true );
			if ( ! is_array( $hosts ) ) {
				continue;
			}

			foreach ( $hosts as $allowed ) {
				if ( $this->host_normalizer->normalize( (string) $allowed ) === $host ) {
					return true;
				}
			}
		}

		return false;
	}
}
