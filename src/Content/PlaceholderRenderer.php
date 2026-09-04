<?php
/**
 * Placeholder renderer — context-safe substitution engine.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Replaces double-curly placeholders in policy content with escaped values
 * drawn from the resolved Policy Profile post and the current policy post.
 *
 * Security rules:
 *  - Only placeholders registered in PlaceholderRegistry are substituted.
 *  - All output is escaped according to its declared context.
 *  - Missing optional fields render as empty string.
 *  - Missing required fields log a notice and render a visible admin warning
 *    for logged-in users with manage_options capability.
 *  - No dynamic request-based substitution is permitted.
 */
final class PlaceholderRenderer {

	public function __construct(
		private readonly PlaceholderRegistry $registry
	) {}

	/**
	 * Replaces all registered placeholders in the given content string.
	 *
	 * @param string       $content    Raw policy content.
	 * @param WP_Post      $profile    Resolved policy profile post.
	 * @param WP_Post|null $policy_post Current policy post (for policy-source placeholders).
	 *
	 * @return string Rendered content with placeholders replaced.
	 */
	public function render( string $content, WP_Post $profile, ?WP_Post $policy_post = null ): string {
		$definitions = $this->registry->get_definitions();

		// Build address composite before iterating (so {{SITE_OWNER_ADDRESS}} works).
		$computed_address = $this->build_address( $profile );

		foreach ( $definitions as $placeholder => $def ) {
			if ( false === strpos( $content, $placeholder ) ) {
				// Skip definitions not present in this content — fast-path.
				continue;
			}

			$raw_value = $this->get_raw_value( $def, $profile, $policy_post, $computed_address );
			$value     = $this->escape( $raw_value, $def['escape'] );

			if ( '' === $value && $def['required'] ) {
				$this->log_missing_required( $placeholder, $profile );
				$value = $this->admin_warning_html( $placeholder );
			}

			$content = str_replace( $placeholder, $value, $content );
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Retrieves the raw (un-escaped) value for a single placeholder definition.
	 *
	 * @param array<string, mixed> $def             Placeholder definition from registry.
	 * @param WP_Post              $profile         Resolved profile post.
	 * @param WP_Post|null         $policy_post     Current policy post (may be null).
	 * @param string               $computed_address Pre-built full address string.
	 *
	 * @return string
	 */
	private function get_raw_value(
		array $def,
		WP_Post $profile,
		?WP_Post $policy_post,
		string $computed_address
	): string {
		// Special case: composite address field.
		if ( '_spx_computed_address' === $def['meta_key'] ) {
			return $computed_address;
		}

		$source = $def['source'] ?? PlaceholderRegistry::SOURCE_PROFILE;

		if ( PlaceholderRegistry::SOURCE_POLICY === $source ) {
			if ( null === $policy_post ) {
				return '';
			}
			return (string) get_post_meta( $policy_post->ID, $def['meta_key'], true );
		}

		return (string) get_post_meta( $profile->ID, $def['meta_key'], true );
	}

	/**
	 * Escapes a raw value according to the declared context.
	 *
	 * @param string $value  Raw value.
	 * @param string $escape Escaping context constant.
	 *
	 * @return string Escaped value.
	 */
	private function escape( string $value, string $escape ): string {
		return match ( $escape ) {
			PlaceholderRegistry::ESCAPE_URL   => '' === $value ? '' : esc_url( $value ),
			PlaceholderRegistry::ESCAPE_EMAIL => '' === $value ? '' : esc_html( sanitize_email( $value ) ),
			default                           => esc_html( $value ),
		};
	}

	/**
	 * Builds a multi-line address block from individual address fields.
	 *
	 * Address fields are separated by a line break so that the rendered output
	 * respects typical postal address formatting.  The caller is responsible for
	 * appropriate escaping; this value is HTML-escaped before insertion so the
	 * newlines will not produce visible breaks in prose.  Authors should wrap
	 * {{SITE_OWNER_ADDRESS}} in a <pre> or use individual field placeholders
	 * ({{SITE_OWNER_ADDRESS_LINE_1}}, {{SITE_OWNER_CITY}}, etc.) when precise
	 * per-line control is needed.
	 *
	 * @param WP_Post $profile Resolved profile post.
	 *
	 * @return string
	 */
	private function build_address( WP_Post $profile ): string {
		$parts = array_filter( [
			(string) get_post_meta( $profile->ID, 'address_line_1', true ),
			(string) get_post_meta( $profile->ID, 'address_line_2', true ),
			(string) get_post_meta( $profile->ID, 'city', true ),
			(string) get_post_meta( $profile->ID, 'state_region', true ),
			(string) get_post_meta( $profile->ID, 'postal_code', true ),
			(string) get_post_meta( $profile->ID, 'country', true ),
		] );

		return implode( "\n", $parts );
	}

	/**
	 * Logs a notice when a required placeholder has no value.
	 *
	 * @param string  $placeholder The unresolved placeholder token.
	 * @param WP_Post $profile     The profile being rendered.
	 */
	private function log_missing_required( string $placeholder, WP_Post $profile ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[spx_policy_renderer] Required placeholder %s has no value in profile ID %d ("%s").',
				$placeholder,
				$profile->ID,
				$profile->post_title
			)
		);
	}

	/**
	 * Returns a visible admin-only warning for a missing required placeholder.
	 * The warning is invisible to logged-out visitors.
	 *
	 * @param string $placeholder The unresolved placeholder token.
	 *
	 * @return string HTML string (safe for insertion into content).
	 */
	private function admin_warning_html( string $placeholder ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return sprintf(
			'<span class="spx-policy-missing-placeholder" style="background:#ff0;color:#000;padding:0 4px;font-weight:bold;" title="%s">[%s]</span>',
			esc_attr(
				sprintf(
					/* translators: placeholder token name */
					__( 'Required placeholder %s has no value in the active policy profile.', 'sparxstar-policy-renderer' ),
					$placeholder
				)
			),
			esc_html( $placeholder )
		);
	}
}
