<?php
/**
 * Object cache wrapper for rendered policy HTML.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Cache
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Stores rendered policy HTML fragments in the WordPress object cache.
 *
 * Cache key structure:
 *   spx_policy_rendered:{host}:{policy_key}:{post_id}:{profile_id}:{policy_modified_gmt}:{profile_modified_gmt}
 *
 * When either the policy post or the profile post is updated the key
 * changes naturally, so there is no need to explicitly delete old keys.
 *
 * The wp_cache_flush_group() function is used where the cache backend
 * supports groups (Redis, Memcached).  A full wp_cache_flush() is the
 * fallback for backends that do not support groups.
 */
final class RenderCache {

	public const CACHE_GROUP = 'spx_policy_renderer';

	/**
	 * Retrieves a cached rendered HTML fragment.
	 *
	 * @param string   $host        Normalized request host.
	 * @param string   $policy_key  Policy key slug.
	 * @param WP_Post  $policy_post The resolved policy post.
	 * @param WP_Post  $profile     The resolved profile post.
	 *
	 * @return string|null Cached HTML or null on a cache miss.
	 */
	public function get(
		string $host,
		string $policy_key,
		WP_Post $policy_post,
		WP_Post $profile
	): ?string {
		$key    = $this->build_key( $host, $policy_key, $policy_post, $profile );
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		return is_string( $cached ) ? $cached : null;
	}

	/**
	 * Stores a rendered HTML fragment in the object cache.
	 *
	 * @param string   $host        Normalized request host.
	 * @param string   $policy_key  Policy key slug.
	 * @param WP_Post  $policy_post The resolved policy post.
	 * @param WP_Post  $profile     The resolved profile post.
	 * @param string   $html        Rendered HTML content.
	 * @param int      $ttl         Time-to-live in seconds (default 1 hour).
	 */
	public function set(
		string $host,
		string $policy_key,
		WP_Post $policy_post,
		WP_Post $profile,
		string $html,
		int $ttl = 3600
	): void {
		$key = $this->build_key( $host, $policy_key, $policy_post, $profile );
		wp_cache_set( $key, $html, self::CACHE_GROUP, $ttl );
	}

	/**
	 * Invalidates all cached entries in the plugin group when a profile is saved.
	 *
	 * @param int     $post_id The saved post ID.
	 * @param WP_Post $post    The saved post object.
	 */
	public function invalidate_on_profile_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'spx_policy_profile' !== $post->post_type ) {
			return;
		}

		$this->invalidate_all();
	}

	/**
	 * Invalidates all cached entries when a policy page/post is saved.
	 *
	 * @param int     $post_id The saved post ID.
	 * @param WP_Post $post    The saved post object.
	 */
	public function invalidate_on_policy_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$this->invalidate_all();
	}

	/**
	 * Flushes the entire plugin object cache group.
	 */
	public function invalidate_all(): void {
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			// Fallback for backends that do not support group flushing.
			wp_cache_flush();
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds the deterministic cache key for a rendered policy HTML fragment.
	 *
	 * @param string  $host        Normalized host.
	 * @param string  $policy_key  Policy key slug.
	 * @param WP_Post $policy_post Resolved policy post.
	 * @param WP_Post $profile     Resolved profile post.
	 *
	 * @return string Cache key.
	 */
	private function build_key(
		string $host,
		string $policy_key,
		WP_Post $policy_post,
		WP_Post $profile
	): string {
		$policy_ts  = gmdate( 'YmdHis', strtotime( $policy_post->post_modified_gmt ) ?: 0 );
		$profile_ts = gmdate( 'YmdHis', strtotime( $profile->post_modified_gmt ) ?: 0 );

		return implode( ':', [
			'spx_policy_rendered',
			$host,
			$policy_key,
			(string) $policy_post->ID,
			(string) $profile->ID,
			$policy_ts,
			$profile_ts,
		] );
	}
}
