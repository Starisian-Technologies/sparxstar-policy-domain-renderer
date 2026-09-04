<?php
/**
 * Minimum requirements check.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Support
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks PHP and WordPress version requirements before the plugin boots.
 */
final class Requirements {

	private const MIN_PHP_VERSION = '8.2';
	private const MIN_WP_VERSION  = '6.8';

	/**
	 * Returns true when both PHP and WordPress meet the minimum versions.
	 */
	public static function met(): bool {
		return version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' )
			&& version_compare( (string) get_bloginfo( 'version' ), self::MIN_WP_VERSION, '>=' );
	}

	/**
	 * Outputs an admin notice when requirements are not met.
	 */
	public static function admin_notice(): void {
			/* translators: 1: required PHP version, 2: required WordPress version */
			esc_html__(
				'SPARXSTAR Policy Domain Renderer requires PHP %1$s or higher and WordPress %2$s or higher. The plugin was not loaded.',
				'sparxstar-policy-renderer'
			),
			esc_html( self::MIN_PHP_VERSION ),
			esc_html( self::MIN_WP_VERSION )

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			$message // Already escaped above.
		);
	}
}
