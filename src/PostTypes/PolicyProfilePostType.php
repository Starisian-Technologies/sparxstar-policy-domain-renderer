<?php
/**
 * Policy Profile custom post type.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\PostTypes
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `spx_policy_profile` custom post type.
 *
 * Profiles store the per-company/domain identity, contact, legal,
 * and branding data used to resolve placeholder values at render time.
 */
final class PolicyProfilePostType {

	public const POST_TYPE = 'spx_policy_profile';

	/**
	 * Registers the post type with WordPress.
	 */
	public function register(): void {
		$labels = [
			'name'               => _x( 'Policy Profiles', 'post type general name', 'sparxstar-policy-renderer' ),
			'singular_name'      => _x( 'Policy Profile', 'post type singular name', 'sparxstar-policy-renderer' ),
			'menu_name'          => _x( 'Policy Profiles', 'admin menu', 'sparxstar-policy-renderer' ),
			'name_admin_bar'     => _x( 'Policy Profile', 'add new on dashboard', 'sparxstar-policy-renderer' ),
			'add_new'            => __( 'Add New', 'sparxstar-policy-renderer' ),
			'add_new_item'       => __( 'Add New Policy Profile', 'sparxstar-policy-renderer' ),
			'new_item'           => __( 'New Policy Profile', 'sparxstar-policy-renderer' ),
			'edit_item'          => __( 'Edit Policy Profile', 'sparxstar-policy-renderer' ),
			'view_item'          => __( 'View Policy Profile', 'sparxstar-policy-renderer' ),
			'all_items'          => __( 'All Policy Profiles', 'sparxstar-policy-renderer' ),
			'search_items'       => __( 'Search Policy Profiles', 'sparxstar-policy-renderer' ),
			'not_found'          => __( 'No policy profiles found.', 'sparxstar-policy-renderer' ),
			'not_found_in_trash' => __( 'No policy profiles found in Trash.', 'sparxstar-policy-renderer' ),
		];

		$args = [
			'labels'              => $labels,
			'description'         => __( 'Company/domain profiles used to resolve policy placeholder values.', 'sparxstar-policy-renderer' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => 'spx-policy-profiles',
			'menu_icon'           => 'dashicons-id-alt',
			'menu_position'       => 75,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'capabilities'        => [
				'edit_post'          => 'manage_spx_policy_profiles',
				'edit_posts'         => 'manage_spx_policy_profiles',
				'edit_others_posts'  => 'manage_spx_policy_profiles',
				'publish_posts'      => 'manage_spx_policy_profiles',
				'read_post'          => 'manage_spx_policy_profiles',
				'read_private_posts' => 'manage_spx_policy_profiles',
				'delete_post'        => 'manage_spx_policy_profiles',
			],
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'revisions' ],
			'has_archive'         => false,
			'exclude_from_search' => true,
		];

		register_post_type( self::POST_TYPE, $args );

		// Map manage_spx_policy_profiles to manage_options for all roles that have it.
		add_filter( 'user_has_cap', [ $this, 'map_capabilities' ], 10, 3 );
	}

	/**
	 * Maps manage_spx_policy_profiles to manage_options / manage_network_options.
	 *
	 * @param bool[]  $allcaps All capabilities for the user.
	 * @param string[] $caps    Required capabilities.
	 * @param mixed[]  $args    Additional arguments.
	 *
	 * @return bool[]
	 */
	public function map_capabilities( array $allcaps, array $caps, array $args ): array {
		if ( in_array( 'manage_spx_policy_profiles', $caps, true ) ) {
			if ( ! empty( $allcaps['manage_options'] ) ) {
				$allcaps['manage_spx_policy_profiles'] = true;
			}
		}

		return $allcaps;
	}
}
