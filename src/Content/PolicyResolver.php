<?php
/**
 * Policy resolver — selects the correct policy post for the current request.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Starisian\Sparxstar\PolicyRenderer\Taxonomies\PolicyKeyTaxonomy;
use WP_Post;

/**
 * Determines which policy post to render for the current request.
 *
 * Resolution order (first valid match wins):
 *  1. host_override  — policy explicitly linked to the exact current host.
 *  2. profile_override — policy linked to the resolved profile ID.
 *  3. policy_set     — policy linked to a named policy set.
 *  4. default        — the default policy for the requested key.
 *
 * The resolver never modifies post content. It only locates the correct post.
 */
final class PolicyResolver {

	// Policy scope values stored in post meta.
	public const SCOPE_DEFAULT          = 'default';
	public const SCOPE_POLICY_SET       = 'policy_set';
	public const SCOPE_PROFILE_OVERRIDE = 'profile_override';
	public const SCOPE_HOST_OVERRIDE    = 'host_override';

	/**
	 * Determines the policy key for the current queried page/post.
	 *
	 * Returns null when the current request is not for a policy document.
	 *
	 * @return string|null Policy key slug (e.g. 'privacy') or null.
	 */
	public function get_current_policy_key(): ?string {
		$post = get_queried_object();

		if ( ! ( $post instanceof WP_Post ) ) {
			return null;
		}

		$terms = get_the_terms( $post->ID, PolicyKeyTaxonomy::TAXONOMY );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return null;
		}

		return (string) $terms[0]->slug;
	}

	/**
	 * Resolves the policy post to render for the given key and context.
	 *
	 * @param string   $policy_key  The policy key slug.
	 * @param int|null $profile_id  Resolved profile post ID (or null).
	 * @param string   $host        Normalized current host.
	 *
	 * @return WP_Post|null The resolved policy post, or null if none found.
	 */
	public function resolve(
		string $policy_key,
		?int $profile_id,
		string $host
	): ?WP_Post {
		// 1. Host override — highest priority.
		$post = $this->find_by_scope( $policy_key, self::SCOPE_HOST_OVERRIDE, null, $host );
		if ( null !== $post ) {
			return $post;
		}

		// 2. Profile override.
		if ( null !== $profile_id ) {
			$post = $this->find_by_scope( $policy_key, self::SCOPE_PROFILE_OVERRIDE, $profile_id, $host );
			if ( null !== $post ) {
				return $post;
			}
		}

		// 3. Policy set (linked to profile's default_policy_set).
		if ( null !== $profile_id ) {
			$policy_set = (string) get_post_meta( $profile_id, 'default_policy_set', true );
			if ( '' !== $policy_set ) {
				$post = $this->find_by_policy_set( $policy_key, $policy_set );
				if ( null !== $post ) {
					return $post;
				}
			}
		}

		// 4. Default policy.
		return $this->find_by_scope( $policy_key, self::SCOPE_DEFAULT, null, '' );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Queries for a policy post by scope, optional profile, and optional host.
	 *
	 * @param string   $policy_key Policy key slug.
	 * @param string   $scope      One of the SCOPE_* constants.
	 * @param int|null $profile_id Profile ID for profile-scoped lookups.
	 * @param string   $host       Exact host for host-scoped lookups.
	 *
	 * @return WP_Post|null
	 */
	private function find_by_scope(
		string $policy_key,
		string $scope,
		?int $profile_id,
		string $host
	): ?WP_Post {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$query_args = [
			'post_type'      => [ 'page', 'post' ],
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'meta_value_num',
			'meta_key'       => 'spx_policy_priority', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'          => 'DESC',
			'tax_query'      => [
				[
					'taxonomy' => PolicyKeyTaxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => [ $policy_key ],
				],
			],
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => 'spx_policy_scope',
					'value'   => $scope,
					'compare' => '=',
				],
			],
		];
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		if ( self::SCOPE_PROFILE_OVERRIDE === $scope && null !== $profile_id ) {
			$query_args['meta_query'][] = [
				'key'     => 'spx_policy_profile_id',
				'value'   => (string) $profile_id,
				'compare' => '=',
			];
		}

		if ( self::SCOPE_HOST_OVERRIDE === $scope && '' !== $host ) {
			$query_args['meta_query'][] = [
				'key'     => 'spx_policy_host',
				'value'   => $host,
				'compare' => '=',
			];
		}

		$posts = get_posts( $query_args );

		return ! empty( $posts ) && $posts[0] instanceof WP_Post ? $posts[0] : null;
	}

	/**
	 * Queries for a policy post by policy set slug.
	 *
	 * @param string $policy_key  Policy key slug.
	 * @param string $policy_set  Policy set identifier.
	 *
	 * @return WP_Post|null
	 */
	private function find_by_policy_set( string $policy_key, string $policy_set ): ?WP_Post {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$posts = get_posts( [
			'post_type'      => [ 'page', 'post' ],
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'tax_query'      => [
				[
					'taxonomy' => PolicyKeyTaxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => [ $policy_key ],
				],
			],
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => 'spx_policy_scope',
					'value'   => self::SCOPE_POLICY_SET,
					'compare' => '=',
				],
				[
					'key'     => 'spx_policy_set',
					'value'   => $policy_set,
					'compare' => '=',
				],
			],
		] );
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		return ! empty( $posts ) && $posts[0] instanceof WP_Post ? $posts[0] : null;
	}
}
