<?php
/**
 * Plugin Name:       SPARXSTAR Policy Domain Renderer
 * Plugin URI:        https://sparxstar.com
 * Description:       Turns one WordPress multisite subsite into a centralized policy site. Resolves the requesting domain to a policy profile and renders placeholder-based policy pages.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Starisian Technologies
 * Author URI:        https://starisian.com
 * License:           Proprietary
 * Text Domain:       sparxstar-policy-renderer
 * Domain Path:       /languages
 * Network:           false
 *
 * SPARXSTAR is a trademark of Starisian Technologies.
 * © Starisian Technologies. All rights reserved.
 *
 * @package Starisian\Sparxstar\PolicyRenderer
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPX_POLICY_VERSION', '1.0.0' );
define( 'SPX_POLICY_FILE', __FILE__ );
define( 'SPX_POLICY_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPX_POLICY_URL', plugin_dir_url( __FILE__ ) );

// Check minimum requirements before loading any further code.
require_once SPX_POLICY_DIR . 'src/Support/Requirements.php';

if ( ! \Starisian\Sparxstar\PolicyRenderer\Support\Requirements::met() ) {
	add_action( 'admin_notices', [ \Starisian\Sparxstar\PolicyRenderer\Support\Requirements::class, 'admin_notice' ] );
	return;
}

// PSR-4 autoloader (Composer or manual fallback).
if ( file_exists( SPX_POLICY_DIR . 'vendor/autoload.php' ) ) {
	require_once SPX_POLICY_DIR . 'vendor/autoload.php';
} else {
	// Manual autoloader fallback for environments without Composer.
	spl_autoload_register( static function ( string $class ): void {
		$prefix = 'Starisian\\Sparxstar\\PolicyRenderer\\';
		$base   = SPX_POLICY_DIR . 'src/';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( $prefix, '', $class );
		$file     = $base . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Boot on plugins_loaded so all WordPress APIs are available.
add_action( 'plugins_loaded', static function (): void {
	\Starisian\Sparxstar\PolicyRenderer\Plugin::get_instance()->boot();
} );

// Activation hook — flush rewrite rules after registering CPT/taxonomy.
register_activation_hook( __FILE__, static function (): void {
	\Starisian\Sparxstar\PolicyRenderer\Plugin::get_instance()->activate();
} );

// Deactivation hook.
register_deactivation_hook( __FILE__, static function (): void {
	\Starisian\Sparxstar\PolicyRenderer\Plugin::get_instance()->deactivate();
} );
