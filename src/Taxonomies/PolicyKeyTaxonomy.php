<?php
/**
 * Policy Key taxonomy.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Taxonomies
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `spx_policy_key` taxonomy used to identify policy document types
 * (privacy, terms, refund, etc.) independently of post slugs.
 */
final class PolicyKeyTaxonomy {

	public const TAXONOMY = 'spx_policy_key';

	/**
	 * All built-in policy key terms and their labels.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_TERMS = [
		'privacy'         => 'Privacy Policy',
		'terms'           => 'Terms of Service',
		'refund'          => 'Refund Policy',
		'cookies'         => 'Cookie Policy',
		'acceptable-use'  => 'Acceptable Use Policy',
		'ai-chat'         => 'AI Chat Policy',
		'rewards'         => 'Rewards Policy',
		'dmca'            => 'DMCA Policy',
		'copyright'       => 'Copyright Policy',
		'data-security'   => 'Data Security Policy',
	];

	/**
	 * Registers the taxonomy with WordPress and seeds default terms.
	 */
	public function register(): void {
		$labels = [
			'name'              => _x( 'Policy Keys', 'taxonomy general name', 'sparxstar-policy-renderer' ),
			'singular_name'     => _x( 'Policy Key', 'taxonomy singular name', 'sparxstar-policy-renderer' ),
			'search_items'      => __( 'Search Policy Keys', 'sparxstar-policy-renderer' ),
			'all_items'         => __( 'All Policy Keys', 'sparxstar-policy-renderer' ),
			'edit_item'         => __( 'Edit Policy Key', 'sparxstar-policy-renderer' ),
			'update_item'       => __( 'Update Policy Key', 'sparxstar-policy-renderer' ),
			'add_new_item'      => __( 'Add New Policy Key', 'sparxstar-policy-renderer' ),
			'new_item_name'     => __( 'New Policy Key Name', 'sparxstar-policy-renderer' ),
			'menu_name'         => __( 'Policy Keys', 'sparxstar-policy-renderer' ),
			'not_found'         => __( 'No policy keys found.', 'sparxstar-policy-renderer' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'rest_base'         => 'spx-policy-keys',
			'show_admin_column' => true,
			'rewrite'           => false,
			'query_var'         => false,
		];

		register_taxonomy( self::TAXONOMY, [ 'page', 'post' ], $args );

		// Seed default terms on first activation (idempotent).
		add_action( 'init', [ $this, 'seed_default_terms' ], 20 );
	}

	/**
	 * Creates default policy key terms if they do not already exist.
	 */
	public function seed_default_terms(): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		foreach ( self::DEFAULT_TERMS as $slug => $name ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term(
					$name,
					self::TAXONOMY,
					[ 'slug' => $slug ]
				);
			}
		}
	}
}
