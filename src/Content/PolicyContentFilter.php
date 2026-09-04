<?php
/**
 * Content filter — orchestrates the full policy rendering pipeline.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Starisian\Sparxstar\PolicyRenderer\Cache\RenderCache;
use Starisian\Sparxstar\PolicyRenderer\Domain\HostNormalizer;
use Starisian\Sparxstar\PolicyRenderer\Domain\ProfileResolver;
use WP_Post;

/**
 * Hooks into WordPress content filters and coordinates the rendering pipeline:
 *
 *  1. Normalize the current request host.
 *  2. Resolve the host to a Policy Profile.
 *  3. Determine the policy key from the current queried post/page.
 *  4. Run the policy override resolver to find the correct policy post.
 *  5. Replace placeholders (using the object cache).
 *  6. Return rendered content.
 *
 * Also handles:
 *  - 404 for unknown hosts.
 *  - Robots meta for preview URLs and inactive profiles.
 *  - Cache-Control headers for public policy pages.
 */
final class PolicyContentFilter {

	/**
	 * Guards against recursive filter invocation (e.g. get_the_excerpt inside the_content).
	 */
	private bool $rendering = false;

	public function __construct(
		private readonly HostNormalizer    $host_normalizer,
		private readonly ProfileResolver   $profile_resolver,
		private readonly PlaceholderRenderer $placeholder_renderer,
		private readonly PolicyResolver    $policy_resolver,
		private readonly RenderCache       $render_cache
	) {}

	// -------------------------------------------------------------------------
	// Public filter callbacks
	// -------------------------------------------------------------------------

	/**
	 * Filters `the_content` to replace placeholders on policy pages.
	 *
	 * @param string $content Original post content.
	 *
	 * @return string Rendered content.
	 */
	public function filter_content( string $content ): string {
		if ( $this->rendering || ! is_singular() ) {
			return $content;
		}

		$context = $this->build_render_context();
		if ( null === $context ) {
			return $content;
		}

		return $this->render_with_cache( $content, $context );
	}

	/**
	 * Filters `the_excerpt` to replace placeholders.
	 *
	 * @param string $excerpt Original excerpt.
	 *
	 * @return string Rendered excerpt.
	 */
	public function filter_excerpt( string $excerpt ): string {
		if ( $this->rendering || ! is_singular() ) {
			return $excerpt;
		}

		$context = $this->build_render_context();
		if ( null === $context ) {
			return $excerpt;
		}

		$this->rendering = true;
		$rendered        = $this->placeholder_renderer->render(
			$excerpt,
			$context['profile'],
			$context['policy_post']
		);
		$this->rendering = false;

		return $rendered;
	}

	/**
	 * Filters `wp_title` (legacy title filter).
	 *
	 * @param string $title Original title.
	 *
	 * @return string Rendered title.
	 */
	public function filter_title( string $title ): string {
		return $this->filter_title_string( $title );
	}

	/**
	 * Filters `pre_get_document_title`.
	 *
	 * @param string $title Original document title.
	 *
	 * @return string Rendered document title.
	 */
	public function filter_document_title( string $title ): string {
		return $this->filter_title_string( $title );
	}

	/**
	 * Fires on `template_redirect` to issue a 404 when the host is unknown.
	 */
	public function handle_unknown_host(): void {
		if ( ! is_singular() ) {
			return;
		}

		$policy_key = $this->policy_resolver->get_current_policy_key();
		if ( null === $policy_key ) {
			// Not a policy page — skip.
			return;
		}

		$host    = $this->host_normalizer->get_current_host();
		$profile = $this->profile_resolver->resolve( $host );

		if ( null === $profile ) {
			// Unknown host with no fallback — 404.
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		// If profile is explicitly inactive, 404.
		$active = get_post_meta( $profile->ID, 'active', true );
		if ( in_array( $active, [ '0', 'false', false, 0 ], true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Fires on `wp_head` to output a noindex meta tag for preview URLs.
	 */
	public function output_robots_meta(): void {
		if ( ! is_singular() ) {
			return;
		}

		// Preview mode: always noindex.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['spx_policy_profile'] ) ) {
			echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
			return;
		}

		$policy_key = $this->policy_resolver->get_current_policy_key();
		if ( null === $policy_key ) {
			return;
		}

		$host    = $this->host_normalizer->get_current_host();
		$profile = $this->profile_resolver->resolve( $host );

		if ( null === $profile ) {
			return;
		}

		$robots = (string) get_post_meta( $profile->ID, 'robots_indexing', true );
		if ( 'noindex' === $robots ) {
			echo '<meta name="robots" content="noindex" />' . "\n";
		}
	}

	/**
	 * Fires on `template_redirect` to send cache headers for public policy pages.
	 *
	 * Sends: Cache-Control: public, max-age=300, s-maxage=86400
	 */
	public function send_cache_headers(): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$policy_key = $this->policy_resolver->get_current_policy_key();
		if ( null === $policy_key ) {
			return;
		}

		// Do not cache preview requests.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['spx_policy_profile'] ) ) {
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
			return;
		}

		$host    = $this->host_normalizer->get_current_host();
		$profile = $this->profile_resolver->resolve( $host );
		if ( null === $profile || ! $this->is_profile_cache_enabled( $profile ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			header( 'Cache-Control: public, max-age=300, s-maxage=86400' );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a preview-capable render context when a spx_policy_profile query
	 * var is present in the request (admin only).
	 *
	 * @return array{host: string, profile: WP_Post, policy_post: WP_Post|null, policy_key: string}|null
	 */
	private function build_preview_context(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['spx_policy_profile'] ) ) {
			return null;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$profile_key = sanitize_text_field( wp_unslash( (string) $_GET['spx_policy_profile'] ) );
		$profile     = $this->get_profile_by_key( $profile_key );
		if ( null === $profile ) {
			return null;
		}

		$host       = $this->host_normalizer->get_current_host();
		$policy_key = $this->policy_resolver->get_current_policy_key();
		if ( null === $policy_key ) {
			return null;
		}

		return [
			'host'        => $host,
			'profile'     => $profile,
			'policy_post' => get_queried_object() instanceof WP_Post ? get_queried_object() : null,
			'policy_key'  => $policy_key,
		];
	}

	/**
	 * Builds the normal (non-preview) render context for the current request.
	 *
	 * @return array{host: string, profile: WP_Post, policy_post: WP_Post|null, policy_key: string}|null
	 */
	private function build_render_context(): ?array {
		// Allow admin preview override.
		$preview = $this->build_preview_context();
		if ( null !== $preview ) {
			return $preview;
		}

		$policy_key = $this->policy_resolver->get_current_policy_key();
		if ( null === $policy_key ) {
			// Not a policy page.
			return null;
		}

		$host    = $this->host_normalizer->get_current_host();
		$profile = $this->profile_resolver->resolve( $host );

		if ( null === $profile ) {
			return null;
		}

		$resolved_post = $this->policy_resolver->resolve( $policy_key, $profile->ID, $host );
		$queried       = get_queried_object();
		$policy_post   = $resolved_post ?? ( $queried instanceof WP_Post ? $queried : null );

		return [
			'host'        => $host,
			'profile'     => $profile,
			'policy_post' => $policy_post,
			'policy_key'  => $policy_key,
		];
	}

	/**
	 * Renders content with object cache support.
	 *
	 * @param string                                                                                          $content  Raw content.
	 * @param array{host: string, profile: WP_Post, policy_post: WP_Post|null, policy_key: string} $context Render context.
	 *
	 * @return string Rendered content.
	 */
	private function render_with_cache( string $content, array $context ): string {
		$profile     = $context['profile'];
		$policy_post = $context['policy_post'];
		$host        = $context['host'];
		$policy_key  = $context['policy_key'];

		$cache_enabled = $this->is_profile_cache_enabled( $profile );

		// Only cache when a concrete policy post is known and the profile allows it.
		if ( $cache_enabled && null !== $policy_post ) {
			$cached = $this->render_cache->get( $host, $policy_key, $policy_post, $profile );
			if ( null !== $cached ) {
				return $cached;
			}
		}

		$this->rendering = true;
		$rendered        = $this->placeholder_renderer->render( $content, $profile, $policy_post );
		$this->rendering = false;

		if ( $cache_enabled && null !== $policy_post ) {
			$this->render_cache->set( $host, $policy_key, $policy_post, $profile, $rendered );
		}

		return $rendered;
	}

	/**
	 * Replaces placeholders in a title string (no caching needed for titles).
	 *
	 * @param string $title Title string.
	 *
	 * @return string Rendered title.
	 */
	private function filter_title_string( string $title ): string {
		if ( ! is_singular() ) {
			return $title;
		}

		$context = $this->build_render_context();
		if ( null === $context ) {
			return $title;
		}

		return $this->placeholder_renderer->render( $title, $context['profile'], $context['policy_post'] );
	}

	/**
	 * Retrieves a profile post by profile_key meta value.
	 *
	 * @param string $profile_key The profile key slug.
	 *
	 * @return WP_Post|null
	 */
	private function get_profile_by_key( string $profile_key ): ?WP_Post {
		$posts = get_posts( [
			'post_type'      => 'spx_policy_profile',
			'post_status'    => [ 'publish', 'draft' ],
			'posts_per_page' => 1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'       => 'profile_key',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value'     => sanitize_key( $profile_key ),
		] );

		return ! empty( $posts ) && $posts[0] instanceof WP_Post ? $posts[0] : null;
	}

	/**
	 * Returns true when frontend caching is enabled for the given profile.
	 *
	 * Empty meta defaults to enabled for backward compatibility.
	 *
	 * @param WP_Post $profile Profile post.
	 */
	private function is_profile_cache_enabled( WP_Post $profile ): bool {
		$cache_enabled = get_post_meta( $profile->ID, 'cache_enabled', true );

		return ! in_array( $cache_enabled, [ '0', 'false', false, 0 ], true );
	}
}
