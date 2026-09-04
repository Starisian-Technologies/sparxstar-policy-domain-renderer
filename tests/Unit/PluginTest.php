<?php
/**
 * Tests for primary policy host behavior in Plugin.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../../' );
	}

	$GLOBALS['spx_policy_settings_option'] = [];
	$GLOBALS['spx_policy_home_url']        = 'https://example.com/';

	function get_option( string $option, mixed $default = false ): mixed {
		if ( 'spx_policy_settings' !== $option ) {
			return $default;
		}

		return $GLOBALS['spx_policy_settings_option'];
	}

	function home_url( string $path = '' ): string {
		return (string) $GLOBALS['spx_policy_home_url'];
	}

	function wp_parse_url( string $url, int $component = -1 ): string|int|null|false|array {
		return parse_url( $url, $component );
	}
}

namespace Starisian\Sparxstar\PolicyRenderer\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use ReflectionMethod;
	use Starisian\Sparxstar\PolicyRenderer\Plugin;

	final class PluginTest extends TestCase {

		protected function tearDown(): void {
			$GLOBALS['spx_policy_settings_option'] = [];
			$GLOBALS['spx_policy_home_url']        = 'https://example.com/';

			parent::tearDown();
		}

		/**
		 * @test
		 */
		public function it_treats_the_site_as_primary_when_no_host_is_configured(): void {
			self::assertTrue( $this->invoke_is_primary_policy_site() );
		}

		/**
		 * @test
		 */
		public function it_treats_a_matching_configured_host_as_the_primary_policy_site(): void {
			$GLOBALS['spx_policy_settings_option'] = [
				'primary_policy_host' => 'Policy.Example.com.',
			];
			$GLOBALS['spx_policy_home_url'] = 'https://policy.example.com:8443/';

			self::assertTrue( $this->invoke_is_primary_policy_site() );
		}

		/**
		 * @test
		 */
		public function it_rejects_non_primary_sites_when_a_primary_policy_host_is_configured(): void {
			$GLOBALS['spx_policy_settings_option'] = [
				'primary_policy_host' => 'policy.example.com',
			];
			$GLOBALS['spx_policy_home_url'] = 'https://tenant.example.com/';

			self::assertFalse( $this->invoke_is_primary_policy_site() );
		}

		private function invoke_is_primary_policy_site(): bool {
			$method = new ReflectionMethod( Plugin::class, 'is_primary_policy_site' );
			$method->setAccessible( true );

			return $method->invoke( Plugin::get_instance() );
		}
	}
}
