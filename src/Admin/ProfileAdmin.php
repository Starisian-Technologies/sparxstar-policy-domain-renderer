<?php
/**
 * Profile admin UI — meta boxes, list columns, policy page meta.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Admin
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

/**
 * Provides the admin meta boxes for the `spx_policy_profile` CPT and for
 * policy pages/posts, plus the customized list table columns.
 */
final class ProfileAdmin {

	// -------------------------------------------------------------------------
	// Profile CPT — meta boxes
	// -------------------------------------------------------------------------

	/**
	 * Registers meta boxes for the Policy Profile edit screen.
	 */
	public function add_meta_boxes(): void {
		$screen = 'spx_policy_profile';

		add_meta_box(
			'spx_policy_profile_identity',
			__( 'Identity', 'sparxstar-policy-renderer' ),
			[ $this, 'render_identity_box' ],
			$screen,
			'normal',
			'high'
		);

		add_meta_box(
			'spx_policy_profile_domains',
			__( 'Domain Routing', 'sparxstar-policy-renderer' ),
			[ $this, 'render_domains_box' ],
			$screen,
			'normal',
			'high'
		);

		add_meta_box(
			'spx_policy_profile_contact',
			__( 'Contact Information', 'sparxstar-policy-renderer' ),
			[ $this, 'render_contact_box' ],
			$screen,
			'normal',
			'default'
		);

		add_meta_box(
			'spx_policy_profile_legal',
			__( 'Legal / Compliance', 'sparxstar-policy-renderer' ),
			[ $this, 'render_legal_box' ],
			$screen,
			'normal',
			'default'
		);

		add_meta_box(
			'spx_policy_profile_branding',
			__( 'Branding & URLs', 'sparxstar-policy-renderer' ),
			[ $this, 'render_branding_box' ],
			$screen,
			'normal',
			'default'
		);

		add_meta_box(
			'spx_policy_profile_behavior',
			__( 'Policy Behavior', 'sparxstar-policy-renderer' ),
			[ $this, 'render_behavior_box' ],
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Renders the Identity meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_identity_box( WP_Post $post ): void {
		wp_nonce_field( 'spx_policy_save_profile_' . $post->ID, 'spx_policy_profile_nonce' );

		$fields = [
			'profile_key'             => [ __( 'Profile Key', 'sparxstar-policy-renderer' ), __( 'Unique slug identifier (e.g. ai-west-africa).', 'sparxstar-policy-renderer' ) ],
			'legal_name'              => [ __( 'Legal Name', 'sparxstar-policy-renderer' ), __( 'Registered legal entity name.', 'sparxstar-policy-renderer' ) ],
			'display_name'            => [ __( 'Display Name', 'sparxstar-policy-renderer' ), __( 'Public-facing name for use in policies.', 'sparxstar-policy-renderer' ) ],
			'brand_name'              => [ __( 'Brand Name', 'sparxstar-policy-renderer' ), '' ],
			'platform_operator_name'  => [ __( 'Platform Operator Name', 'sparxstar-policy-renderer' ), '' ],
			'site_owner_name'         => [ __( 'Site Owner Name', 'sparxstar-policy-renderer' ), '' ],
			'site_operator_name'      => [ __( 'Site Operator Name', 'sparxstar-policy-renderer' ), '' ],
			'seller_name'             => [ __( 'Seller Name', 'sparxstar-policy-renderer' ), '' ],
			'support_provider_name'   => [ __( 'Support Provider Name', 'sparxstar-policy-renderer' ), '' ],
		];

		$this->render_text_fields( $post, $fields );
	}

	/**
	 * Renders the Domain Routing meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_domains_box( WP_Post $post ): void {
		$primary       = (string) get_post_meta( $post->ID, 'primary_domain', true );
		$alias_domains = get_post_meta( $post->ID, 'alias_domains', true );
		$allowed_hosts = get_post_meta( $post->ID, 'allowed_hosts', true );

		$alias_text   = is_array( $alias_domains ) ? implode( "\n", $alias_domains ) : (string) $alias_domains;
		$allowed_text = is_array( $allowed_hosts ) ? implode( "\n", $allowed_hosts ) : (string) $allowed_hosts;

		echo '<table class="form-table spx-policy-form-table"><tbody>';

		$this->render_row(
			'primary_domain',
			__( 'Primary Domain', 'sparxstar-policy-renderer' ),
			sprintf(
				'<input type="text" name="spx_profile[primary_domain]" value="%s" class="regular-text" placeholder="policy.example.com" />',
				esc_attr( $primary )
			),
			__( 'The canonical policy domain for this profile (e.g. policy.aiwestafrica.com).', 'sparxstar-policy-renderer' )
		);

		$this->render_row(
			'alias_domains',
			__( 'Alias Domains', 'sparxstar-policy-renderer' ),
			sprintf(
				'<textarea name="spx_profile[alias_domains]" rows="4" class="large-text">%s</textarea>',
				esc_textarea( $alias_text )
			),
			__( 'One domain per line. These aliases also resolve to this profile.', 'sparxstar-policy-renderer' )
		);

		$this->render_row(
			'allowed_hosts',
			__( 'Allowed Hosts', 'sparxstar-policy-renderer' ),
			sprintf(
				'<textarea name="spx_profile[allowed_hosts]" rows="4" class="large-text">%s</textarea>',
				esc_textarea( $allowed_text )
			),
			__( 'One host per line (including www variants). All hosts listed here are validated against this profile.', 'sparxstar-policy-renderer' )
		);

		echo '</tbody></table>';
	}

	/**
	 * Renders the Contact Information meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_contact_box( WP_Post $post ): void {
		$fields = [
			'address_line_1' => [ __( 'Address Line 1', 'sparxstar-policy-renderer' ), '' ],
			'address_line_2' => [ __( 'Address Line 2', 'sparxstar-policy-renderer' ), '' ],
			'city'           => [ __( 'City', 'sparxstar-policy-renderer' ), '' ],
			'state_region'   => [ __( 'State / Region', 'sparxstar-policy-renderer' ), '' ],
			'postal_code'    => [ __( 'Postal Code', 'sparxstar-policy-renderer' ), '' ],
			'country'        => [ __( 'Country', 'sparxstar-policy-renderer' ), '' ],
			'phone'          => [ __( 'Phone', 'sparxstar-policy-renderer' ), '' ],
			'email'          => [ __( 'Email', 'sparxstar-policy-renderer' ), '' ],
			'support_email'  => [ __( 'Support Email', 'sparxstar-policy-renderer' ), '' ],
			'legal_email'    => [ __( 'Legal Email', 'sparxstar-policy-renderer' ), '' ],
			'privacy_email'  => [ __( 'Privacy Email', 'sparxstar-policy-renderer' ), '' ],
			'abuse_email'    => [ __( 'Abuse Email', 'sparxstar-policy-renderer' ), '' ],
		];

		$this->render_text_fields( $post, $fields );
	}

	/**
	 * Renders the Legal / Compliance meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_legal_box( WP_Post $post ): void {
		$fields = [
			'governing_law'                => [ __( 'Governing Law', 'sparxstar-policy-renderer' ), __( 'e.g. Laws of Ghana', 'sparxstar-policy-renderer' ) ],
			'jurisdiction'                 => [ __( 'Jurisdiction', 'sparxstar-policy-renderer' ), __( 'e.g. Accra, Ghana', 'sparxstar-policy-renderer' ) ],
			'business_registration_number' => [ __( 'Business Registration No.', 'sparxstar-policy-renderer' ), '' ],
			'tax_id'                       => [ __( 'Tax ID', 'sparxstar-policy-renderer' ), '' ],
			'dpo_name'                     => [ __( 'DPO Name', 'sparxstar-policy-renderer' ), '' ],
			'dpo_email'                    => [ __( 'DPO Email', 'sparxstar-policy-renderer' ), '' ],
			'privacy_contact_name'         => [ __( 'Privacy Contact Name', 'sparxstar-policy-renderer' ), '' ],
		];

		$this->render_text_fields( $post, $fields );
	}

	/**
	 * Renders the Branding & URLs meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_branding_box( WP_Post $post ): void {
		$fields = [
			'website_url' => [ __( 'Website URL', 'sparxstar-policy-renderer' ), '' ],
			'support_url' => [ __( 'Support URL', 'sparxstar-policy-renderer' ), '' ],
			'terms_url'   => [ __( 'Terms URL', 'sparxstar-policy-renderer' ), '' ],
			'privacy_url' => [ __( 'Privacy URL', 'sparxstar-policy-renderer' ), '' ],
			'refund_url'  => [ __( 'Refund URL', 'sparxstar-policy-renderer' ), '' ],
			'logo_url'    => [ __( 'Logo URL', 'sparxstar-policy-renderer' ), '' ],
		];

		$this->render_text_fields( $post, $fields );
	}

	/**
	 * Renders the Policy Behavior meta box (side column).
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_behavior_box( WP_Post $post ): void {
		$active            = get_post_meta( $post->ID, 'active', true );
		$cache_enabled     = get_post_meta( $post->ID, 'cache_enabled', true );
		$robots_indexing   = (string) get_post_meta( $post->ID, 'robots_indexing', true );
		$default_policy_set = (string) get_post_meta( $post->ID, 'default_policy_set', true );

		// Default active to true for new profiles.
		if ( '' === $active ) {
			$active = '1';
		}
		if ( '' === $cache_enabled ) {
			$cache_enabled = '1';
		}

		echo '<p>';
		printf(
			'<label><input type="checkbox" name="spx_profile[active]" value="1" %s /> %s</label>',
			checked( $active, '1', false ),
			esc_html__( 'Profile Active', 'sparxstar-policy-renderer' )
		);
		echo '</p>';

		echo '<p>';
		printf(
			'<label><input type="checkbox" name="spx_profile[cache_enabled]" value="1" %s /> %s</label>',
			checked( $cache_enabled, '1', false ),
			esc_html__( 'Enable Object Cache', 'sparxstar-policy-renderer' )
		);
		echo '</p>';

		echo '<p>';
		echo '<label>' . esc_html__( 'Robots Indexing', 'sparxstar-policy-renderer' ) . '</label><br />';
		echo '<select name="spx_profile[robots_indexing]">';
		printf(
			'<option value="index" %s>%s</option>',
			selected( $robots_indexing, 'index', false ),
			esc_html__( 'index', 'sparxstar-policy-renderer' )
		);
		printf(
			'<option value="noindex" %s>%s</option>',
			selected( $robots_indexing, 'noindex', false ),
			esc_html__( 'noindex', 'sparxstar-policy-renderer' )
		);
		echo '</select>';
		echo '</p>';

		echo '<p>';
		echo '<label>' . esc_html__( 'Default Policy Set', 'sparxstar-policy-renderer' ) . '</label><br />';
		printf(
			'<input type="text" name="spx_profile[default_policy_set]" value="%s" class="widefat" />',
			esc_attr( $default_policy_set )
		);
		echo '<span class="description">' . esc_html__( 'Optional policy set identifier.', 'sparxstar-policy-renderer' ) . '</span>';
		echo '</p>';
	}

	// -------------------------------------------------------------------------
	// Profile CPT — save_post handler
	// -------------------------------------------------------------------------

	/**
	 * Saves profile meta on post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['spx_policy_profile_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( (string) $_POST['spx_policy_profile_nonce'] ) ),
			'spx_policy_save_profile_' . $post_id
		) ) {
			return;
		}

		if ( ! current_user_can( 'manage_spx_policy_profiles' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input = isset( $_POST['spx_profile'] ) && is_array( $_POST['spx_profile'] )
			? wp_unslash( $_POST['spx_profile'] )
			: [];

		// Scalar text fields.
		$text_keys = [
			'profile_key', 'legal_name', 'display_name', 'brand_name',
			'platform_operator_name', 'site_owner_name', 'site_operator_name',
			'seller_name', 'support_provider_name', 'primary_domain',
			'address_line_1', 'address_line_2', 'city', 'state_region',
			'postal_code', 'country', 'phone', 'email', 'support_email',
			'legal_email', 'privacy_email', 'abuse_email', 'governing_law',
			'jurisdiction', 'business_registration_number', 'tax_id',
			'dpo_name', 'dpo_email', 'privacy_contact_name', 'website_url',
			'support_url', 'terms_url', 'privacy_url', 'refund_url', 'logo_url',
			'robots_indexing', 'default_policy_set',
		];

		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) $input[ $key ] ) );
			}
		}

		// Checkbox booleans (absence = false).
		update_post_meta( $post_id, 'active', ! empty( $input['active'] ) ? '1' : '0' );
		update_post_meta( $post_id, 'cache_enabled', ! empty( $input['cache_enabled'] ) ? '1' : '0' );

		// Multi-line textarea fields → normalized string arrays.
		foreach ( [ 'alias_domains', 'allowed_hosts' ] as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$normalized = array_values(
					array_map(
						'strtolower',
						array_filter(
							array_map( 'sanitize_text_field', explode( "\n", (string) $input[ $field ] ) )
						)
					)
				);
				update_post_meta( $post_id, $field, $normalized );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Policy page/post meta boxes
	// -------------------------------------------------------------------------

	/**
	 * Registers meta boxes for policy pages and posts.
	 */
	public function add_policy_meta_boxes(): void {
		foreach ( [ 'page', 'post' ] as $type ) {
			add_meta_box(
				'spx_policy_settings',
				__( 'Policy Settings', 'sparxstar-policy-renderer' ),
				[ $this, 'render_policy_settings_box' ],
				$type,
				'side',
				'default'
			);

			add_meta_box(
				'spx_policy_versioning',
				__( 'Policy Versioning', 'sparxstar-policy-renderer' ),
				[ $this, 'render_policy_versioning_box' ],
				$type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Renders the Policy Settings meta box (scope, profile, host, set, priority).
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_policy_settings_box( WP_Post $post ): void {
		wp_nonce_field( 'spx_policy_save_policy_meta_' . $post->ID, 'spx_policy_meta_nonce' );

		$scope      = (string) get_post_meta( $post->ID, 'spx_policy_scope', true );
		$profile_id = (int) get_post_meta( $post->ID, 'spx_policy_profile_id', true );
		$host       = (string) get_post_meta( $post->ID, 'spx_policy_host', true );
		$set        = (string) get_post_meta( $post->ID, 'spx_policy_set', true );
		$priority   = (string) get_post_meta( $post->ID, 'spx_policy_priority', true );

		$scope_options = [
			''                 => __( '— Select scope —', 'sparxstar-policy-renderer' ),
			'default'          => __( 'Default', 'sparxstar-policy-renderer' ),
			'policy_set'       => __( 'Policy Set', 'sparxstar-policy-renderer' ),
			'profile_override' => __( 'Profile Override', 'sparxstar-policy-renderer' ),
			'host_override'    => __( 'Host Override', 'sparxstar-policy-renderer' ),
		];

		echo '<p>';
		echo '<label for="spx_policy_scope">' . esc_html__( 'Policy Scope', 'sparxstar-policy-renderer' ) . '</label><br />';
		echo '<select name="spx_policy_meta[spx_policy_scope]" id="spx_policy_scope" class="widefat spx-scope-select">';
		foreach ( $scope_options as $val => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $scope, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
		echo '</p>';

		// Profile selector (shown for profile_override).
		$profiles = get_posts( [
			'post_type'      => 'spx_policy_profile',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		echo '<p class="spx-field-profile_override spx-field-host_override">';
		echo '<label for="spx_policy_profile_id">' . esc_html__( 'Profile', 'sparxstar-policy-renderer' ) . '</label><br />';
		echo '<select name="spx_policy_meta[spx_policy_profile_id]" id="spx_policy_profile_id" class="widefat">';
		echo '<option value="0">' . esc_html__( '— None —', 'sparxstar-policy-renderer' ) . '</option>';
		foreach ( $profiles as $profile ) {
			printf(
				'<option value="%d" %s>%s</option>',
				(int) $profile->ID,
				selected( $profile_id, (int) $profile->ID, false ),
				esc_html( $profile->post_title )
			);
		}
		echo '</select>';
		echo '</p>';

		echo '<p class="spx-field-host_override">';
		printf(
			'<label for="spx_policy_host">%s</label><br /><input type="text" name="spx_policy_meta[spx_policy_host]" id="spx_policy_host" value="%s" class="widefat" placeholder="policy.example.com" />',
			esc_html__( 'Host Override', 'sparxstar-policy-renderer' ),
			esc_attr( $host )
		);
		echo '</p>';

		echo '<p class="spx-field-policy_set">';
		printf(
			'<label for="spx_policy_set">%s</label><br /><input type="text" name="spx_policy_meta[spx_policy_set]" id="spx_policy_set" value="%s" class="widefat" />',
			esc_html__( 'Policy Set', 'sparxstar-policy-renderer' ),
			esc_attr( $set )
		);
		echo '</p>';

		echo '<p>';
		printf(
			'<label for="spx_policy_priority">%s</label><br /><input type="number" name="spx_policy_meta[spx_policy_priority]" id="spx_policy_priority" value="%s" class="small-text" min="0" step="1" />',
			esc_html__( 'Priority', 'sparxstar-policy-renderer' ),
			esc_attr( $priority ?: '10' )
		);
		echo '<span class="description"> ' . esc_html__( 'Higher value wins when multiple overrides match.', 'sparxstar-policy-renderer' ) . '</span>';
		echo '</p>';
	}

	/**
	 * Renders the Policy Versioning meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_policy_versioning_box( WP_Post $post ): void {
		$effective_date  = (string) get_post_meta( $post->ID, 'spx_policy_effective_date', true );
		$last_reviewed   = (string) get_post_meta( $post->ID, 'spx_policy_last_reviewed_date', true );
		$version         = (string) get_post_meta( $post->ID, 'spx_policy_version', true );

		printf(
			'<p><label>%s<br /><input type="date" name="spx_policy_versioning[spx_policy_effective_date]" value="%s" class="widefat" /></label></p>',
			esc_html__( 'Effective Date', 'sparxstar-policy-renderer' ),
			esc_attr( $effective_date )
		);

		printf(
			'<p><label>%s<br /><input type="date" name="spx_policy_versioning[spx_policy_last_reviewed_date]" value="%s" class="widefat" /></label></p>',
			esc_html__( 'Last Reviewed Date', 'sparxstar-policy-renderer' ),
			esc_attr( $last_reviewed )
		);

		printf(
			'<p><label>%s<br /><input type="text" name="spx_policy_versioning[spx_policy_version]" value="%s" class="widefat" placeholder="1.0" /></label></p>',
			esc_html__( 'Version', 'sparxstar-policy-renderer' ),
			esc_attr( $version )
		);
	}

	/**
	 * Saves policy meta for pages and posts.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_policy_meta( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['spx_policy_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( (string) $_POST['spx_policy_meta_nonce'] ) ),
			'spx_policy_save_policy_meta_' . $post_id
		) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Policy settings meta.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$input = isset( $_POST['spx_policy_meta'] ) && is_array( $_POST['spx_policy_meta'] )
			? wp_unslash( $_POST['spx_policy_meta'] )
			: [];

		$allowed_scopes = [ 'default', 'policy_set', 'profile_override', 'host_override', '' ];

		if ( isset( $input['spx_policy_scope'] ) && in_array( $input['spx_policy_scope'], $allowed_scopes, true ) ) {
			update_post_meta( $post_id, 'spx_policy_scope', sanitize_text_field( (string) $input['spx_policy_scope'] ) );
		}

		if ( isset( $input['spx_policy_profile_id'] ) ) {
			update_post_meta( $post_id, 'spx_policy_profile_id', (string) absint( $input['spx_policy_profile_id'] ) );
		}

		if ( isset( $input['spx_policy_host'] ) ) {
			update_post_meta( $post_id, 'spx_policy_host', sanitize_text_field( strtolower( (string) $input['spx_policy_host'] ) ) );
		}

		if ( isset( $input['spx_policy_set'] ) ) {
			update_post_meta( $post_id, 'spx_policy_set', sanitize_text_field( (string) $input['spx_policy_set'] ) );
		}

		if ( isset( $input['spx_policy_priority'] ) ) {
			update_post_meta( $post_id, 'spx_policy_priority', (string) absint( $input['spx_policy_priority'] ) );
		}

		// Versioning meta.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$versioning = isset( $_POST['spx_policy_versioning'] ) && is_array( $_POST['spx_policy_versioning'] )
			? wp_unslash( $_POST['spx_policy_versioning'] )
			: [];

		foreach ( [ 'spx_policy_effective_date', 'spx_policy_last_reviewed_date', 'spx_policy_version' ] as $key ) {
			if ( isset( $versioning[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) $versioning[ $key ] ) );
			}
		}
	}

	// -------------------------------------------------------------------------
	// List table columns
	// -------------------------------------------------------------------------

	/**
	 * Defines custom columns for the Policy Profiles list table.
	 *
	 * @param array<string, string> $columns Default columns.
	 *
	 * @return array<string, string>
	 */
	public function list_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['primary_domain'] = __( 'Primary Domain', 'sparxstar-policy-renderer' );
				$new['alias_domains']  = __( 'Alias Domains', 'sparxstar-policy-renderer' );
				$new['active']         = __( 'Active', 'sparxstar-policy-renderer' );
			}
		}

		return $new;
	}

	/**
	 * Renders values for custom list table columns.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'primary_domain':
				echo esc_html( (string) get_post_meta( $post_id, 'primary_domain', true ) );
				break;

			case 'alias_domains':
				$aliases = get_post_meta( $post_id, 'alias_domains', true );
				if ( is_array( $aliases ) && ! empty( $aliases ) ) {
					echo esc_html( implode( ', ', array_slice( $aliases, 0, 3 ) ) );
					if ( count( $aliases ) > 3 ) {
						printf(
							' <span class="description">+%d %s</span>',
							count( $aliases ) - 3,
							esc_html__( 'more', 'sparxstar-policy-renderer' )
						);
					}
				}
				break;

			case 'active':
				$active = get_post_meta( $post_id, 'active', true );
				$is_active = ! in_array( $active, [ '0', 'false', false, 0 ], true );
				printf(
					'<span class="spx-active-badge spx-active-badge--%s">%s</span>',
					$is_active ? 'yes' : 'no',
					$is_active
						? esc_html__( 'Active', 'sparxstar-policy-renderer' )
						: esc_html__( 'Inactive', 'sparxstar-policy-renderer' )
				);
				break;
		}
	}

	/**
	 * Declares sortable list table columns.
	 *
	 * @param array<string, mixed> $columns Current sortable columns.
	 *
	 * @return array<string, mixed>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['primary_domain'] = 'primary_domain';
		$columns['active']         = 'active';
		return $columns;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders a standard table of text input fields.
	 *
	 * @param WP_Post                          $post   Current post.
	 * @param array<string, array{0: string, 1: string}> $fields Field definitions: key → [label, description].
	 */
	private function render_text_fields( WP_Post $post, array $fields ): void {
		echo '<table class="form-table spx-policy-form-table"><tbody>';

		foreach ( $fields as $key => [ $label, $description ] ) {
			$value = (string) get_post_meta( $post->ID, $key, true );
			$field = sprintf(
				'<input type="text" name="spx_profile[%s]" value="%s" class="regular-text" />',
				esc_attr( $key ),
				esc_attr( $value )
			);

			$this->render_row( $key, $label, $field, $description );
		}

		echo '</tbody></table>';
	}

	/**
	 * Renders a single table row with label, field, and optional description.
	 *
	 * @param string $id          Field ID.
	 * @param string $label       Row label.
	 * @param string $field_html  Pre-escaped field HTML.
	 * @param string $description Optional description text.
	 */
	private function render_row( string $id, string $label, string $field_html, string $description = '' ): void {
		printf(
			'<tr><th scope="row"><label for="%s">%s</label></th><td>%s%s</td></tr>',
			esc_attr( $id ),
			esc_html( $label ),
			$field_html, // Already escaped by caller.
			'' !== $description ? '<p class="description">' . esc_html( $description ) . '</p>' : ''
		);
	}
}
