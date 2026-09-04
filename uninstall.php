<?php
/**
 * Uninstall handler.
 *
 * Executed when the plugin is deleted from the WordPress admin.
 * Only removes data when the "delete on uninstall" option is explicitly
 * set to true by an administrator.
 *
 * @package Starisian\Sparxstar\PolicyRenderer
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'spx_policy_settings', [] );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	// Default: do not delete any data on uninstall.
	return;
}

// --- Remove plugin options ---
delete_option( 'spx_policy_settings' );

// --- Flush rendered object cache ---
if ( function_exists( 'wp_cache_flush_group' ) ) {
	wp_cache_flush_group( 'spx_policy_renderer' );
} else {
	wp_cache_flush();
}

// --- Remove all Policy Profile CPT posts and their meta ---
$profiles = get_posts( [
	'post_type'      => 'spx_policy_profile',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
] );

foreach ( $profiles as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

// --- Remove policy-specific post meta from all post types ---
$policy_meta_keys = [
	'spx_policy_scope',
	'spx_policy_profile_id',
	'spx_policy_host',
	'spx_policy_set',
	'spx_policy_priority',
	'spx_policy_effective_date',
	'spx_policy_last_reviewed_date',
	'spx_policy_version',
];

foreach ( $policy_meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// --- Remove taxonomy terms for spx_policy_key ---
$terms = get_terms( [
	'taxonomy'   => 'spx_policy_key',
	'hide_empty' => false,
	'fields'     => 'ids',
] );

if ( is_array( $terms ) ) {
	foreach ( $terms as $term_id ) {
		wp_delete_term( (int) $term_id, 'spx_policy_key' );
	}
}
