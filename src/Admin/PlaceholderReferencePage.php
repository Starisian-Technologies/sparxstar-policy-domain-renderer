<?php
/**
 * Placeholder reference admin screen.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Admin
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Starisian\Sparxstar\PolicyRenderer\Content\PlaceholderRegistry;

/**
 * Registers and renders the admin placeholder reference screen.
 *
 * Accessible at: Appearance > Policy Placeholders
 * (or via the top-level Policy Profiles menu).
 */
final class PlaceholderReferencePage {

	/**
	 * Registers the admin submenu page.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=spx_policy_profile',
			__( 'Placeholder Reference', 'sparxstar-policy-renderer' ),
			__( 'Placeholder Reference', 'sparxstar-policy-renderer' ),
			'manage_spx_policy_profiles',
			'spx-policy-placeholder-reference',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Renders the placeholder reference page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_spx_policy_profiles' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sparxstar-policy-renderer' ) );
		}

		$registry    = new PlaceholderRegistry();
		$definitions = $registry->get_definitions();

		?>
		<div class="wrap spx-placeholder-reference">
			<h1><?php esc_html_e( 'Policy Placeholder Reference', 'sparxstar-policy-renderer' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Use these placeholders in your policy pages. They are replaced at render time with values from the matched Policy Profile.', 'sparxstar-policy-renderer' ); ?>
			</p>

			<div class="spx-placeholder-copy-hint">
				<span class="dashicons dashicons-info"></span>
				<?php esc_html_e( 'Click a placeholder token to copy it to the clipboard.', 'sparxstar-policy-renderer' ); ?>
			</div>

			<table class="wp-list-table widefat fixed striped spx-placeholder-table">
				<thead>
					<tr>
						<th scope="col" style="width:28%"><?php esc_html_e( 'Placeholder', 'sparxstar-policy-renderer' ); ?></th>
						<th scope="col" style="width:20%"><?php esc_html_e( 'Label', 'sparxstar-policy-renderer' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'sparxstar-policy-renderer' ); ?></th>
						<th scope="col" style="width:10%"><?php esc_html_e( 'Source', 'sparxstar-policy-renderer' ); ?></th>
						<th scope="col" style="width:8%"><?php esc_html_e( 'Required', 'sparxstar-policy-renderer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $definitions as $token => $def ) : ?>
					<tr>
						<td>
							<code
								class="spx-placeholder-token"
								data-token="<?php echo esc_attr( $token ); ?>"
								title="<?php esc_attr_e( 'Click to copy', 'sparxstar-policy-renderer' ); ?>"
							><?php echo esc_html( $token ); ?></code>
						</td>
						<td><?php echo esc_html( $def['label'] ); ?></td>
						<td><?php echo esc_html( $def['description'] ); ?></td>
						<td>
							<span class="spx-source-badge spx-source-badge--<?php echo esc_attr( $def['source'] ); ?>">
								<?php echo esc_html( $def['source'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( $def['required'] ) : ?>
								<span class="spx-required-badge"><?php esc_html_e( 'Required', 'sparxstar-policy-renderer' ); ?></span>
							<?php else : ?>
								<span class="spx-optional-badge"><?php esc_html_e( 'Optional', 'sparxstar-policy-renderer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Preview a Policy with a Profile', 'sparxstar-policy-renderer' ); ?></h2>
			<p class="description">
				<?php
				echo wp_kses(
					__(
						'To preview a policy with a specific profile, append <code>?spx_policy_profile={profile_key}</code> to any policy page URL while logged in as an administrator.',
						'sparxstar-policy-renderer'
					),
					[ 'code' => [] ]
				);
				?>
			</p>
		</div>
		<?php
	}
}
